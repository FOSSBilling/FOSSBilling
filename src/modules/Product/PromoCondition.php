<?php

declare(strict_types=1);

namespace Box\Mod\Product;

/**
 * Cart condition a promo requires before it can apply (`promo.requires_products`).
 *
 * Two stored forms are accepted:
 *  - a plain list of product ids, which must all be present (the original bundle condition);
 *  - `{"all": [...]}`, where each requirement is either a product `{"product": 12, "min_qty": 2}`
 *    or a group `{"pick": 1, "from": [{"product": 14}, {"product": 15}]}` that is met when
 *    at least `pick` distinct products from the list are present.
 *
 * `min_qty` counts units of one product across the cart; `pick` counts distinct products.
 * Groups hold products only, so every condition can be edited in the admin form.
 */
final readonly class PromoCondition
{
    /**
     * @param list<array{product: int, min_qty: int}|array{pick: int, from: list<array{product: int, min_qty: int}>}> $requirements
     */
    private function __construct(private array $requirements)
    {
    }

    public static function none(): self
    {
        return new self([]);
    }

    /**
     * Parse the stored JSON. Empty or null means no condition.
     */
    public static function fromStorage(?string $storedJson): self
    {
        if ($storedJson === null || trim($storedJson) === '') {
            return self::none();
        }

        return self::fromArray(json_decode($storedJson, true));
    }

    /**
     * Parse API or form input (or decoded storage).
     *
     * @throws \FOSSBilling\InformationException when the structure is invalid
     */
    public static function fromArray(mixed $conditionData): self
    {
        if ($conditionData === null || $conditionData === '' || $conditionData === []) {
            return self::none();
        }

        if (!is_array($conditionData)) {
            throw new \FOSSBilling\InformationException('Required products must be a list of product IDs or a condition object.');
        }

        if (array_is_list($conditionData)) {
            return new self(self::parseLegacyList($conditionData));
        }

        $unknownKeys = array_diff(array_keys($conditionData), ['all']);
        if ($unknownKeys !== [] || !isset($conditionData['all']) || !is_array($conditionData['all']) || !array_is_list($conditionData['all'])) {
            throw new \FOSSBilling\InformationException('A required products condition must be an object with an "all" list.');
        }

        $requirements = [];
        foreach ($conditionData['all'] as $requirementData) {
            $requirements[] = is_array($requirementData) && array_key_exists('pick', $requirementData)
                ? self::parseGroup($requirementData)
                : self::parseProduct($requirementData);
        }

        return new self($requirements);
    }

    public function isEmpty(): bool
    {
        return $this->requirements === [];
    }

    /**
     * Canonical array form. Conditions that only list products (quantity 1)
     * stay a plain id list, so existing promos keep their stored format.
     *
     * @return list<int>|array{all: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        if ($this->isPlainProductList()) {
            return array_map(static fn (array $requirement): int => $requirement['product'], $this->requirements);
        }

        return ['all' => array_map(self::exportRequirement(...), $this->requirements)];
    }

    public function toStorage(): ?string
    {
        return $this->isEmpty() ? null : json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Every product id the condition refers to.
     *
     * @return list<int>
     */
    public function getProductIds(): array
    {
        $productIds = [];
        foreach ($this->requirements as $requirement) {
            foreach (isset($requirement['pick']) ? $requirement['from'] : [$requirement] as $requiredProduct) {
                $productIds[] = $requiredProduct['product'];
            }
        }

        return array_values(array_unique($productIds));
    }

    /**
     * Requirements of the condition the cart does not satisfy, in canonical form.
     *
     * @param array<int, int> $cartQuantities units in the cart, keyed by product id
     *
     * @return list<array<string, mixed>>
     */
    public function findUnmet(array $cartQuantities): array
    {
        $unmetRequirements = [];
        foreach ($this->requirements as $requirement) {
            if (!$this->isRequirementMet($requirement, $cartQuantities)) {
                $unmetRequirements[] = self::exportRequirement($requirement);
            }
        }

        return $unmetRequirements;
    }

    /**
     * @param array<int, int> $cartQuantities units in the cart, keyed by product id
     */
    public function isMet(array $cartQuantities): bool
    {
        return $this->findUnmet($cartQuantities) === [];
    }

    /**
     * @param array<string, mixed> $requirement
     * @param array<int, int>      $cartQuantities
     */
    private function isRequirementMet(array $requirement, array $cartQuantities): bool
    {
        if (!isset($requirement['pick'])) {
            return ($cartQuantities[$requirement['product']] ?? 0) >= $requirement['min_qty'];
        }

        $productsPresent = 0;
        foreach ($requirement['from'] as $groupProduct) {
            if (($cartQuantities[$groupProduct['product']] ?? 0) >= $groupProduct['min_qty']) {
                ++$productsPresent;
            }
        }

        return $productsPresent >= $requirement['pick'];
    }

    private function isPlainProductList(): bool
    {
        foreach ($this->requirements as $requirement) {
            if (isset($requirement['pick']) || $requirement['min_qty'] !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * The original format: ids, possibly as strings, with blanks and
     * non-positive values ignored and duplicates collapsed.
     *
     * @return list<array{product: int, min_qty: int}>
     */
    private static function parseLegacyList(array $productIds): array
    {
        if (array_filter($productIds, static fn (mixed $productId): bool => $productId !== null && !is_scalar($productId)) !== []) {
            throw new \FOSSBilling\InformationException('A list of required products may only contain product IDs; use {"all": [...]} for quantities or groups.');
        }

        $productIds = array_values(array_unique(array_filter(
            array_map(intval(...), $productIds),
            static fn (int $productId): bool => $productId > 0
        )));

        return array_map(static fn (int $productId): array => ['product' => $productId, 'min_qty' => 1], $productIds);
    }

    /**
     * @return array{pick: int, from: list<array{product: int, min_qty: int}>}
     */
    private static function parseGroup(array $groupData): array
    {
        $unknownKeys = array_diff(array_keys($groupData), ['pick', 'from']);
        if ($unknownKeys !== [] || !isset($groupData['from']) || !is_array($groupData['from']) || !array_is_list($groupData['from']) || $groupData['from'] === []) {
            throw new \FOSSBilling\InformationException('A product group needs "pick" and a non-empty "from" list.');
        }

        $parsedProducts = [];
        foreach ($groupData['from'] as $productData) {
            if (is_array($productData) && array_key_exists('pick', $productData)) {
                throw new \FOSSBilling\InformationException('Product groups cannot be nested.');
            }
            $parsedProduct = self::parseProduct($productData);
            if (isset($parsedProducts[$parsedProduct['product']])) {
                throw new \FOSSBilling\InformationException('A product group cannot list the same product twice.');
            }
            $parsedProducts[$parsedProduct['product']] = $parsedProduct;
        }

        $pickCount = self::parsePositiveInt($groupData['pick'] ?? null, '"pick" must be a whole number of at least 1.');
        if ($pickCount > count($parsedProducts)) {
            throw new \FOSSBilling\InformationException('"pick" cannot be larger than the number of products in its group.');
        }

        return ['pick' => $pickCount, 'from' => array_values($parsedProducts)];
    }

    /**
     * @return array{product: int, min_qty: int}
     */
    private static function parseProduct(mixed $productData): array
    {
        if (!is_array($productData) || array_diff(array_keys($productData), ['product', 'min_qty']) !== []) {
            throw new \FOSSBilling\InformationException('Each required product must look like {"product": id} with an optional "min_qty".');
        }

        return [
            'product' => self::parsePositiveInt($productData['product'] ?? null, 'Required product IDs must be positive whole numbers.'),
            'min_qty' => self::parsePositiveInt($productData['min_qty'] ?? 1, '"min_qty" must be a whole number of at least 1.'),
        ];
    }

    private static function parsePositiveInt(mixed $value, string $errorMessage): int
    {
        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        }

        if (!is_int($value) || $value < 1) {
            throw new \FOSSBilling\InformationException($errorMessage);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $requirement
     *
     * @return array<string, mixed>
     */
    private static function exportRequirement(array $requirement): array
    {
        if (isset($requirement['pick'])) {
            return ['pick' => $requirement['pick'], 'from' => array_map(self::exportRequirement(...), $requirement['from'])];
        }

        return $requirement['min_qty'] === 1 ? ['product' => $requirement['product']] : $requirement;
    }
}
