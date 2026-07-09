<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Product;

use Box\Mod\Product\Entity\Product;
use Box\Mod\Product\Entity\ProductCategory;
use Box\Mod\Product\Entity\ProductPayment;
use Box\Mod\Product\Entity\Promo;
use Box\Mod\Product\Entity\PromoRedemption;
use Box\Mod\Product\Repository\DomainPricingRepository;
use Box\Mod\Product\Repository\ProductCategoryRepository;
use Box\Mod\Product\Repository\ProductOrderRepository;
use Box\Mod\Product\Repository\ProductPaymentRepository;
use Box\Mod\Product\Repository\ProductRepository;
use Box\Mod\Product\Repository\PromoRedemptionRepository;
use Box\Mod\Product\Repository\PromoRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\QueryBuilder;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\PaginationOptions;

class Service implements InjectionAwareInterface
{
    final public const string CUSTOM = 'custom';
    final public const string LICENSE = 'license';
    final public const string ADDON = 'addon';
    final public const string DOMAIN = 'domain';
    final public const string DOWNLOADABLE = 'downloadable';
    final public const string HOSTING = 'hosting';
    final public const string VPS = 'vps';

    final public const string SETUP_AFTER_ORDER = 'after_order';
    final public const string SETUP_AFTER_PAYMENT = 'after_payment';
    final public const string SETUP_MANUAL = 'manual';

    protected ?\Pimple\Container $di = null;
    protected ?ProductRepository $productRepository = null;
    protected ?ProductCategoryRepository $productCategoryRepository = null;
    protected ?ProductPaymentRepository $productPaymentRepository = null;
    protected ?PromoRepository $promoRepository = null;
    protected ?PromoRedemptionRepository $promoRedemptionRepository = null;
    protected ?DomainPricingRepository $domainPricingRepository = null;
    protected ?ProductOrderRepository $productOrderRepository = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getPromoRedemptionRepository(): PromoRedemptionRepository
    {
        if ($this->promoRedemptionRepository === null) {
            if ($this->di === null) {
                throw new \FOSSBilling\Exception('The dependency injection container has not been set.');
            }

            $this->promoRedemptionRepository = $this->di['em']->getRepository(PromoRedemption::class);
        }

        return $this->promoRedemptionRepository;
    }

    public function getPromoRepository(): PromoRepository
    {
        if ($this->promoRepository === null) {
            if ($this->di === null) {
                throw new \FOSSBilling\Exception('The dependency injection container has not been set.');
            }

            $this->promoRepository = $this->di['em']->getRepository(Promo::class);
        }

        return $this->promoRepository;
    }

    public function getProductRepository(): ProductRepository
    {
        if ($this->productRepository === null) {
            if ($this->di === null) {
                throw new \FOSSBilling\Exception('The dependency injection container has not been set.');
            }

            $this->productRepository = $this->di['em']->getRepository(Product::class);
        }

        return $this->productRepository;
    }

    public function getProductCategoryRepository(): ProductCategoryRepository
    {
        if ($this->productCategoryRepository === null) {
            if ($this->di === null) {
                throw new \FOSSBilling\Exception('The dependency injection container has not been set.');
            }

            $this->productCategoryRepository = $this->di['em']->getRepository(ProductCategory::class);
        }

        return $this->productCategoryRepository;
    }

    public function getProductPaymentRepository(): ProductPaymentRepository
    {
        if ($this->productPaymentRepository === null) {
            if ($this->di === null) {
                throw new \FOSSBilling\Exception('The dependency injection container has not been set.');
            }

            $this->productPaymentRepository = $this->di['em']->getRepository(ProductPayment::class);
        }

        return $this->productPaymentRepository;
    }

    public function getDomainPricingRepository(): DomainPricingRepository
    {
        if ($this->domainPricingRepository === null) {
            $this->domainPricingRepository = new DomainPricingRepository($this->getDbalConnection());
        }

        return $this->domainPricingRepository;
    }

    public function getProductOrderRepository(): ProductOrderRepository
    {
        if ($this->productOrderRepository === null) {
            $this->productOrderRepository = new ProductOrderRepository($this->getDbalConnection());
        }

        return $this->productOrderRepository;
    }

    /**
     * @return mixed[]
     */
    public function getModulePermissions(): array
    {
        return [
            'view' => [
                'type' => 'bool',
                'display_name' => __trans('View products'),
                'description' => __trans('Allows the staff member to view products, categories, and promotions.'),
            ],
            'manage_products' => [
                'type' => 'bool',
                'display_name' => __trans('Manage products'),
                'description' => __trans('Allows the staff member to create, update, and delete products.'),
            ],
            'manage_categories' => [
                'type' => 'bool',
                'display_name' => __trans('Manage product categories'),
                'description' => __trans('Allows the staff member to create, update, and delete product categories.'),
            ],
            'manage_promos' => [
                'type' => 'bool',
                'display_name' => __trans('Manage promotions'),
                'description' => __trans('Allows the staff member to create, update, and delete promotional codes.'),
            ],
        ];
    }

    public function getPairs($data): array
    {
        return $this->getProductRepository()->getPairs($data);
    }

    public function toApiArray(Product $model, $deep = true, $identity = null): array
    {
        $config = json_decode($this->getProductConfigJson($model) ?? '', true) ?? [];
        $pricing = $this->getProductPricingArray($model);
        $starting_from = $this->getStartingFromPrice($model);
        $isAdmin = $identity instanceof \Model_Admin;
        $addons = $this->getAddonsApiArray($model, $isAdmin);

        $result = [
            'id' => $this->getProductId($model),
            'product_category_id' => $this->getProductCategoryId($model),
            'type' => $this->getProductType($model),
            'title' => $this->getProductTitle($model),
            'slug' => $this->getProductSlug($model),
            'description' => $this->getProductDescription($model),
            'unit' => $this->getProductUnit($model),
            'priority' => $this->getProductPriority($model),
            'pricing' => $isAdmin ? $pricing : $this->getPublicPricing($pricing),
            'config' => $isAdmin ? $config : $this->getPublicConfig($config),
            'addons' => $addons,

            'price_starting_from' => $starting_from,
            'icon_url' => $this->getProductIconUrl($model),

            // stock control
            'allow_quantity_select' => $this->isAllowQuantitySelect($model),

            // Exposed publicly so the order form can be fetched during guest checkout.
            'form_id' => $this->getProductFormId($model),
        ];

        if ($isAdmin) {
            $result['created_at'] = $this->formatDateTimeValue($this->getProductCreatedAt($model));
            $result['updated_at'] = $this->formatDateTimeValue($this->getProductUpdatedAt($model));
            $result['addons'] = $addons;
            $result['quantity_in_stock'] = $this->getProductQuantityInStock($model);
            $result['stock_control'] = $this->isStockControlled($model);
            $result['upgrades'] = $this->getUpgradablePairs($model);
            $result['status'] = $this->getProductStatus($model);
            $result['hidden'] = $this->isProductHidden($model);
            $result['setup'] = $this->getProductSetup($model);
            if ($this->getProductCategoryId($model)) {
                $productCategory = $this->findProductCategoryById((int) $this->getProductCategoryId($model));
                $result['category'] = [
                    'id' => $productCategory->getId(),
                    'title' => $productCategory->getTitle(),
                ];
            }
        }

        return $result;
    }

    private function getPublicConfig(array $config): array
    {
        $publicConfigKeys = [
            'allow_domain_register',
            'allow_domain_transfer',
            'allow_domain_own',
            'allow_subdomain',
            'subdomain_base_domain',
        ];

        return array_intersect_key($config, array_flip($publicConfigKeys));
    }

    private function getPublicPricing(array $pricing): array
    {
        foreach ($pricing as $key => $value) {
            if ($key === 'registrar') {
                unset($pricing[$key]);

                continue;
            }

            if (is_array($value)) {
                $pricing[$key] = $this->getPublicPricing($value);
            }
        }

        return $pricing;
    }

    public function getTypes(): array
    {
        $data = [
            self::CUSTOM => 'Custom',
            self::LICENSE => 'License',
            self::DOWNLOADABLE => 'Downloadable',
            self::HOSTING => 'Hosting',
            self::DOMAIN => 'Domain',
        ];

        // attach service modules
        $extensionService = $this->di['mod_service']('extension');
        $list = $extensionService->getInstalledMods();
        foreach ($list as $mod) {
            if (str_starts_with((string) $mod, 'service')) {
                $n = substr((string) $mod, strlen('service'));
                $data[$n] = ucfirst($n);
            }
        }

        return $data;
    }

    public function getMainDomainProduct(): ?Product
    {
        return $this->getProductRepository()->findMainDomainProduct();
    }

    public function getCartProductTitle(Product $product, ?array $config = null): string
    {
        $service = $this->getProductModuleService($product);
        if (method_exists($service, 'getCartProductTitle')) {
            return $service->getCartProductTitle($product, $config ?? []);
        }

        return (string) $this->getProductTitle($product);
    }

    public function validateSelectedAddonsForProduct(Product $product, array $addons): void
    {
        $validAddons = json_decode($this->getProductAddonsJson($product) ?? '', true);
        if (empty($validAddons)) {
            $validAddons = [];
        }

        foreach ($addons as $addonId => $properties) {
            if (!($properties['selected'] ?? false)) {
                continue;
            }

            $addon = $this->getAddonById((int) $addonId);
            if (!$addon instanceof Product || $this->getProductStatus($addon) !== 'enabled' || !in_array((int) $addonId, $validAddons)) {
                throw new \FOSSBilling\InformationException('One or more of your selected add-ons are invalid for the associated product.');
            }
        }
    }

    public function prepareCartProductConfig(Product $product, array $config): array
    {
        $service = $this->getProductModuleService($product);

        if (method_exists($service, 'attachOrderConfig')) {
            $config = $service->attachOrderConfig($product, $config);
        }

        if (method_exists($service, 'validateOrderData')) {
            $service->validateOrderData($config);
        }

        if (method_exists($service, 'validateCustomForm')) {
            $service->validateCustomForm($config, $this->getProductValidationData($product));
        }

        return $config;
    }

    /**
     * @return array<mixed, array<'active'|'allow_register'|'allow_transfer'|'min_years'|'price_registration'|'price_renew'|'price_transfer'|'registrar'|'tld', mixed>>
     */
    public function getDomainPricingArray(): array
    {
        return $this->getDomainPricingRepository()->getActivePricingByTld();
    }

    public function getProductPricingArray(Product $product): array
    {
        if ($this->getProductType($product) === self::DOMAIN) {
            return $this->getDomainPricingArray();
        }

        if ($this->getProductPaymentId($product)) {
            $productPayment = $this->getProductPaymentById((int) $this->getProductPaymentId($product));

            return $this->toProductPaymentApiArray($productPayment);
        }

        throw new \FOSSBilling\Exception('Product pricing could not be determined.');
    }

    public function getProductUnit(Product $product): string
    {
        if ($this->getProductType($product) === self::DOMAIN) {
            return 'year';
        }

        return $product->getUnit();
    }

    public function isRecurrentProductPricing(Product $product): bool
    {
        $pricing = $this->getProductPricingArray($product);

        return isset($pricing['type']) && $pricing['type'] == ProductPayment::RECURRENT;
    }

    public function isProductPeriodEnabled(Product $product, string $period): bool
    {
        $pricing = $this->getProductPricingArray($product);
        if (($pricing['type'] ?? null) == ProductPayment::RECURRENT) {
            return (bool) ($pricing['recurrent'][$period]['enabled'] ?? false);
        }

        return true;
    }

    public function getRelatedProductDiscount(Product $product, array $items, ?array $config = null): float
    {
        if ($this->getProductType($product) === self::DOMAIN) {
            return $this->getDomainRelatedDiscount($items, $config ?? []);
        }

        return 0.0;
    }

    public function getPaymentTypes(): array
    {
        return [
            ProductPayment::FREE => 'Free',
            ProductPayment::ONCE => 'One time',
            ProductPayment::RECURRENT => 'Recurrent',
        ];
    }

    public function createProduct($title, $type, $categoryId = null): int
    {
        $priority = $this->getProductRepository()->getMaxPriority();

        $productPayment = $this->createDefaultProductPayment();
        $paymentId = (int) $productPayment->getId();

        $slug = $this->generateUniqueProductSlug($title);

        $model = new Product();
        $model
            ->setProductPaymentId($paymentId)
            ->setProductCategoryId($categoryId !== null ? (int) $categoryId : null)
            ->setStatus('disabled')
            ->setTitle($title)
            ->setSlug($slug)
            ->setType($type)
            ->setSetup(self::SETUP_AFTER_PAYMENT)
            ->setPriority($priority + 10);

        $this->di['em']->persist($model);
        $this->di['em']->flush();
        $productId = $model->getId();
        $this->di['logger']->info('Created new product #%s', $model->getId());

        return (int) $productId;
    }

    public function updateProduct(Product $model, $data): bool
    {
        // pricing
        if (isset($data['pricing'])) {
            $types = $this->getPaymentTypes();

            if (!isset($data['pricing']['type']) || !array_key_exists($data['pricing']['type'], $types)) {
                throw new \FOSSBilling\InformationException('Pricing type is required');
            }
            $productPayment = $this->getProductPaymentById((int) $this->getProductPaymentId($model));
            $this->applyPricingToProductPayment($productPayment, $data['pricing']);
            $this->di['em']->flush();
        }

        if (isset($data['config']) && is_array($data['config'])) {
            $current = json_decode($this->getProductConfigJson($model) ?? '', true) ?? [];
            $c = array_merge($current, $data['config']);
            $this->setProductConfigJson($model, json_encode($c));
        }

        $form_id = $data['form_id'] ?? $this->getProductFormId($model);
        $productCategoryId = $data['product_category_id'] ?? $this->getProductCategoryId($model);

        $this->setProductCategoryIdValue($model, empty($productCategoryId) ? null : (int) $productCategoryId);
        $this->setProductFormIdValue($model, empty($form_id) ? null : (int) $form_id);
        $this->setProductIconUrlValue($model, $data['icon_url'] ?? $this->getProductIconUrl($model));
        $this->setProductStatusValue($model, (string) ($data['status'] ?? $this->getProductStatus($model)));
        $this->setProductHiddenValue($model, (bool) ($data['hidden'] ?? $this->isProductHidden($model)));
        $this->setProductSlugValue($model, $data['slug'] ?? $this->getProductSlug($model));
        $this->setProductSetupValue($model, (string) ($data['setup'] ?? $this->getProductSetup($model)));
        // remove empty value in data['upgrades];
        if (is_array($data['upgrades'] ?? null)) {
            $upgrades = array_values(array_filter($data['upgrades']));
            if (empty($upgrades)) {
                $this->setProductUpgradesJson($model, null);
            } else {
                $this->setProductUpgradesJson($model, json_encode($upgrades));
            }
        }
        if (is_array($data['addons'] ?? null)) {
            $addons = array_values(array_filter($data['addons']));
            if (empty($addons)) {
                $this->setProductAddonsJsonValue($model, null);
            } else {
                $this->setProductAddonsJsonValue($model, json_encode($addons));
            }
        }

        $this->setProductTitleValue($model, $data['title'] ?? $this->getProductTitle($model));
        $this->setProductStockControlValue($model, (bool) ($data['stock_control'] ?? $this->isStockControlled($model)));
        $this->setProductAllowQuantitySelectValue($model, (bool) ($data['allow_quantity_select'] ?? $this->isAllowQuantitySelect($model)));
        $this->setProductQuantityInStockValue($model, (int) ($data['quantity_in_stock'] ?? $this->getProductQuantityInStock($model)));
        $this->setProductDescriptionValue($model, $data['description'] ?? $this->getProductDescription($model));
        $this->setProductPluginValue($model, $data['plugin'] ?? $this->getProductPlugin($model));
        $this->setProductUpdatedAtValue($model, new \DateTime());

        $this->di['em']->flush();

        $this->di['logger']->info('Updated product #%s configuration', $this->getProductId($model));

        return true;
    }

    public function updatePriority($data): bool
    {
        foreach ($data['priority'] as $id => $p) {
            $model = $this->getProductRepository()->find((int) $id);
            if ($model instanceof Product) {
                $model->setPriority((int) $p);
                $model->setUpdatedAt(new \DateTime());
            }
        }

        $this->di['em']->flush();

        $this->di['logger']->info('Changed product priorities');

        return true;
    }

    public function updateConfig(Product $model, $data): bool
    {
        /* add new config value */
        $config = json_decode($this->getProductConfigJson($model) ?? '', true) ?? [];

        if (isset($data['config']) && is_array($data['config'])) {
            $config = array_intersect_key((array) $config, $data['config']);
            foreach ($data['config'] as $key => $val) {
                $config[$key] = $val;
                if (isset($config[$key]) && empty($val) && !is_numeric($val)) {
                    unset($config[$key]);
                }
            }
        }

        if (
            isset($data['new_config_name'])
            && isset($data['new_config_value'])
            && !empty($data['new_config_name'])
            && !empty($data['new_config_value'])
        ) {
            $config[$data['new_config_name']] = $data['new_config_value'];
        }

        $this->setProductConfigJson($model, json_encode($config));
        $this->setProductUpdatedAtValue($model, new \DateTime());
        $this->di['em']->flush();

        $this->di['logger']->info('Updated product #%s configuration', $this->getProductId($model));

        return true;
    }

    /**
     * @return mixed[]
     */
    public function getAddons(): array
    {
        return $this->getProductRepository()->getAddonPairs();
    }

    public function createAddon($title, $description = null, $setup = null, $status = null, $iconUrl = null): ?int
    {
        $productPayment = $this->createDefaultProductPayment();
        $paymentId = (int) $productPayment->getId();

        $slug = $this->generateUniqueProductSlug($title);

        $model = new Product();
        $model
            ->setProductPaymentId($paymentId)
            ->setProductCategoryId(null)
            ->setStatus($status ?? 'disabled')
            ->setTitle($title)
            ->setSlug($slug)
            ->setType(self::CUSTOM)
            ->setSetup($setup ?? self::SETUP_AFTER_PAYMENT)
            ->setIsAddon(true)
            ->setIconUrl($iconUrl)
            ->setDescription($description);

        $this->di['em']->persist($model);
        $this->di['em']->flush();
        $productId = $model->getId();

        $this->di['logger']->info('Created new addon #%s', $productId);

        return $productId;
    }

    public function deleteProduct(Product $product): bool
    {
        $orderService = $this->di['mod_service']('order');
        if ($orderService->productHasOrders($product)) {
            throw new \FOSSBilling\InformationException('Cannot remove product which has active orders.');
        }
        $id = $this->getProductId($product);
        $this->di['em']->remove($product);
        $this->di['em']->flush();
        $this->di['logger']->info('Deleted product #%s', $id);

        return true;
    }

    public function getPaginatedProducts(array $data, $identity = null): array
    {
        return $this->paginateMappedQuery(
            $this->getProductSearchQueryBuilder($data),
            PaginationOptions::fromArray($data),
            fn (Product $product): array => $this->toApiArray($product, false, $identity),
        );
    }

    /**
     * @return mixed[]
     */
    public function getProductCategoryPairs(array $data = []): array
    {
        return $this->getProductCategoryRepository()->getPairs();
    }

    public function updateCategory(ProductCategory $productCategory, $title = null, $description = null, $icon_url = null): bool
    {
        $productCategory
            ->setTitle($title)
            ->setIconUrl($icon_url)
            ->setDescription($description);
        $this->di['em']->flush();

        $this->di['logger']->info('Updated product category #%s', $productCategory->getId());

        return true;
    }

    public function createCategory($title, $description = null, $icon_url = null): ?int
    {
        $model = (new ProductCategory())
            ->setTitle($title)
            ->setDescription($description)
            ->setIconUrl($icon_url);
        $this->di['em']->persist($model);
        $this->di['em']->flush();
        $id = $model->getId();

        $this->di['logger']->info('Created new product category #%s', $id);

        return $id;
    }

    public function removeProductCategory(ProductCategory $category): bool
    {
        if ($this->getProductRepository()->hasProductsInCategory((int) $category->getId())) {
            throw new \FOSSBilling\InformationException('Cannot remove product category with products');
        }
        $id = $category->getId();
        $this->di['em']->remove($category);
        $this->di['em']->flush();

        $this->di['logger']->info('Deleted product category #%s', $id);

        return true;
    }

    public function getProductSearchQueryBuilder(array $data): QueryBuilder
    {
        return $this->getProductRepository()->getSearchQueryBuilder($data);
    }

    public function toProductCategoryApiArray(ProductCategory $model, $deep = true, $identity = null): array
    {
        $min_price = 0;
        $products = [];
        $pr = $this->getCategoryProducts($model);

        $type = null; // identified by first product in category
        foreach ($pr as $p) {
            $pa = $this->toApiArray($p, false, $identity);
            if (reset($pr) == $p) {
                $type = $this->getProductType($p);
            }
            $products[] = $pa;
            $startingPrice = $pa['price_starting_from'] ?? 0;

            if ($min_price == 0) {
                $min_price = $startingPrice;
            } elseif ($startingPrice < $min_price) {
                $min_price = $startingPrice;
            }
        }

        return [
            'id' => $model->getId(),
            'title' => $model->getTitle(),
            'description' => $model->getDescription(),
            'icon_url' => $model->getIconUrl(),
            'created_at' => $this->formatDateTimeValue($model->getCreatedAt()),
            'updated_at' => $this->formatDateTimeValue($model->getUpdatedAt()),
            'price_starting_from' => $min_price,
            'type' => $type,
            'products' => $products,
        ];
    }

    /**
     * @param int $id
     */
    public function findOneActiveById($id): ?Product
    {
        return $this->getProductRepository()->findActiveById((int) $id);
    }

    /**
     * @param string $slug
     */
    public function findOneActiveBySlug($slug): ?Product
    {
        return $this->getProductRepository()->findActiveBySlug((string) $slug);
    }

    public function getPaginatedProductCategories(array $data, $identity = null): array
    {
        return $this->paginateMappedQuery(
            $this->getProductCategorySearchQueryBuilder($data),
            PaginationOptions::fromArray($data),
            fn (ProductCategory $category): array => $this->toProductCategoryApiArray($category, true, $identity),
        );
    }

    public function getStartingFromPrice(Product $model)
    {
        if ($this->getProductType($model) == self::DOMAIN) {
            return $this->getStartingDomainPrice();
        }

        if ($this->getProductPaymentId($model)) {
            $productPaymentModel = $this->getProductPaymentById((int) $this->getProductPaymentId($model));

            return $this->getStartingPrice($productPaymentModel);
        }

        return null;
    }

    /**
     * @return mixed[]
     */
    public function getUpgradablePairs(Product $model): array
    {
        $ids = json_decode($this->getProductUpgradesJson($model) ?? '', true);
        $pids = $this->getProductTitlesByIds($ids);
        unset($pids[$this->getProductId($model)]);

        return $pids;
    }

    public function getUpgradablePairsByProductId(int $productId): array
    {
        return $this->getUpgradablePairs($this->findProductById($productId));
    }

    public function canUpgradeTo(Product $model, Product $new): bool
    {
        if ($this->getProductId($model) === $this->getProductId($new)) {
            return false;
        }

        $pairs = $this->getUpgradablePairs($model);

        return array_key_exists($this->getProductId($new), $pairs);
    }

    public function assertUpgradeAllowedByIds(int $productId, int $upgradeProductId): void
    {
        $product = $this->findProductById($productId);
        $allowedUpgrades = $this->getUpgradablePairsByProductId($productId);

        if (array_key_exists($upgradeProductId, $allowedUpgrades)) {
            return;
        }

        $upgrade = $this->findProductById($upgradeProductId);

        throw new \FOSSBilling\InformationException('Sorry, but ":product" is not allowed to be upgraded to ":upgrade"', [':product' => $product->getTitle() ?? 'unknown', ':upgrade' => $upgrade->getTitle() ?? 'unknown']);
    }

    /**
     * @return mixed[]
     */
    public function getProductTitlesByIds($ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $result = [];
        foreach ($this->getProductRepository()->findByIds($this->normalizeProductIds((array) $ids)) as $product) {
            $productId = $product->getId();
            if ($productId === null) {
                continue;
            }

            $result[$productId] = $product->getTitle();
        }

        return $result;
    }

    public function getCategoryProducts(ProductCategory $model)
    {
        return $this->getProductRepository()->findEnabledVisibleByCategoryId((int) $model->getId());
    }

    public function getProductCategorySearchQueryBuilder(array $data): QueryBuilder
    {
        return $this->getProductCategoryRepository()->getEnabledVisibleSearchQueryBuilder();
    }

    public function findProductCategoryById(int $id): ProductCategory
    {
        $category = $this->getProductCategoryRepository()->findById($id);
        if (!$category instanceof ProductCategory) {
            throw new \FOSSBilling\InformationException('Category not found');
        }

        return $category;
    }

    public function toProductPaymentApiArray(ProductPayment $model): array
    {
        $periods = [];
        $periods['1W'] = ['price' => $model->getPeriodPrice('w'), 'setup' => $model->getPeriodSetupPrice('w'), 'enabled' => $model->isPeriodEnabled('w')];
        $periods['1M'] = ['price' => $model->getPeriodPrice('m'), 'setup' => $model->getPeriodSetupPrice('m'), 'enabled' => $model->isPeriodEnabled('m')];
        $periods['3M'] = ['price' => $model->getPeriodPrice('q'), 'setup' => $model->getPeriodSetupPrice('q'), 'enabled' => $model->isPeriodEnabled('q')];
        $periods['6M'] = ['price' => $model->getPeriodPrice('b'), 'setup' => $model->getPeriodSetupPrice('b'), 'enabled' => $model->isPeriodEnabled('b')];
        $periods['1Y'] = ['price' => $model->getPeriodPrice('a'), 'setup' => $model->getPeriodSetupPrice('a'), 'enabled' => $model->isPeriodEnabled('a')];
        $periods['2Y'] = ['price' => $model->getPeriodPrice('bia'), 'setup' => $model->getPeriodSetupPrice('bia'), 'enabled' => $model->isPeriodEnabled('bia')];
        $periods['3Y'] = ['price' => $model->getPeriodPrice('tria'), 'setup' => $model->getPeriodSetupPrice('tria'), 'enabled' => $model->isPeriodEnabled('tria')];

        return [
            'type' => $model->getType(),
            ProductPayment::FREE => ['price' => 0, 'setup' => 0],
            ProductPayment::ONCE => ['price' => $model->getOncePrice(), 'setup' => $model->getOnceSetupPrice()],
            ProductPayment::RECURRENT => $periods,
        ];
    }

    public function getStartingDomainPrice(): float
    {
        $pricing = $this->getDomainPricingArray();
        $registrationPrices = array_column($pricing, 'price_registration');

        return $registrationPrices === [] ? 0.0 : (float) min($registrationPrices);
    }

    public function getStartingPrice(ProductPayment $model)
    {
        if ($model->getType() == ProductPayment::FREE) {
            return 0;
        }

        if ($model->getType() == ProductPayment::ONCE) {
            return $model->getOncePrice();
        }

        if ($model->getType() == ProductPayment::RECURRENT) {
            $p = [];

            if ($model->isPeriodEnabled('w')) {
                $p[] = $model->getPeriodPrice('w');
            }

            if ($model->isPeriodEnabled('m')) {
                $p[] = $model->getPeriodPrice('m');
            }

            if ($model->isPeriodEnabled('q')) {
                $p[] = $model->getPeriodPrice('q');
            }

            if ($model->isPeriodEnabled('b')) {
                $p[] = $model->getPeriodPrice('b');
            }

            if ($model->isPeriodEnabled('a')) {
                $p[] = $model->getPeriodPrice('a');
            }

            if ($model->isPeriodEnabled('bia')) {
                $p[] = $model->getPeriodPrice('bia');
            }

            if ($model->isPeriodEnabled('tria')) {
                $p[] = $model->getPeriodPrice('tria');
            }

            if ($p) {
                return min($p);
            }

            return null;
        }

        return null;
    }

    public function getAddonById(int $id): ?Product
    {
        return $this->getProductRepository()->findEnabledAddonById($id);
    }

    /**
     * @return list<array{product: Product, config: array}>
     */
    public function getSelectedAddonsForCart(Product $parentProduct, array $addons): array
    {
        $selectedAddons = [];
        foreach ($addons as $id => $addonConfig) {
            if (!isset($addonConfig['selected']) || !(bool) $addonConfig['selected']) {
                continue;
            }

            $addon = $this->getAddonById($id);
            if (!$addon instanceof Product) {
                $this->di['logger']->warning('Addon not found by id %s', $id);

                continue;
            }

            if ($this->isRecurrentProductPricing($addon)) {
                $required = [
                    'period' => 'Addon period parameter not passed',
                ];
                $this->di['validator']->checkRequiredParamsForArray($required, $addonConfig);

                if (!$this->isProductPeriodEnabled($addon, (string) $addonConfig['period'])) {
                    throw new \FOSSBilling\InformationException('Selected billing period is invalid for the selected add-on');
                }
            }

            $addonConfig['parent_id'] = $this->getProductId($parentProduct);
            $selectedAddons[] = [
                'product' => $addon,
                'config' => $addonConfig,
            ];
        }

        return $selectedAddons;
    }

    public function isStockAvailable(Product|int $product, $qty): bool
    {
        $resolvedProduct = $this->resolveStockProduct($product);
        if ($this->isStockControlled($resolvedProduct)) {
            return $this->getProductQuantityInStock($resolvedProduct) >= (int) $qty;
        }

        return true;
    }

    public function reduceStock(Product|int $product, $qty): bool
    {
        $resolvedProduct = $this->resolveStockProduct($product);
        if (!$this->isStockControlled($resolvedProduct)) {
            return true;
        }

        $quantity = (int) $qty;
        $available = $this->getProductQuantityInStock($resolvedProduct);
        if ($available < $quantity) {
            throw new \FOSSBilling\InformationException('Product :id is out of stock.', [':id' => $this->getProductId($resolvedProduct)], 831);
        }

        $this->setProductQuantityInStockValue($resolvedProduct, $available - $quantity);
        $this->setProductUpdatedAtValue($resolvedProduct, new \DateTime());

        $this->di['em']->flush();

        return true;
    }

    private function getPeriods(Promo $model): array
    {
        return $this->decodePromoSelection($this->getPromoSourceArray($model)['periods'] ?? null);
    }

    private function getProducts(Promo $model): array
    {
        return $this->decodePromoSelection($this->getPromoSourceArray($model)['products'] ?? null);
    }

    /**
     * @return mixed[]
     */
    private function getAddonsApiArray(Product $model, bool $isAdmin = false): array
    {
        $addons = [];
        foreach ($this->getProductAddons($model, $isAdmin) as $addon) {
            $d = $this->toAddonArray($addon, true, $isAdmin);
            $addons[] = $d;
        }

        return $addons;
    }

    /**
     * @return mixed[]
     */
    public function getProductAddons(Product $model, bool $includeUnavailable = false): array
    {
        $ids = $this->normalizeProductIds(json_decode($this->getProductAddonsJson($model) ?? '', true) ?? []);

        if ($ids === []) {
            return [];
        }

        return $this->getProductRepository()->findAddonsByIds($ids, (int) $this->getProductId($model), $includeUnavailable);
    }

    /**
     * @param list<int|string> $ids
     *
     * @return array<int, array{id: int, title: ?string, type: ?string, plugin: ?string}>
     */
    public function getProductSnapshotMap(array $ids): array
    {
        $result = [];
        foreach ($this->getProductRepository()->findByIds($this->normalizeProductIds($ids)) as $product) {
            $productId = $product->getId();
            if ($productId === null) {
                continue;
            }

            $result[$productId] = [
                'id' => $productId,
                'title' => $product->getTitle(),
                'type' => $product->getType(),
                'plugin' => $product->getPlugin(),
            ];
        }

        return $result;
    }

    public function getRelatedProductDiscountByProductId(int $productId, array $items, ?array $config = null): float
    {
        return $this->getRelatedProductDiscount($this->findProductById($productId), $items, $config);
    }

    public function getCartProductTitleById(int $productId, ?array $config = null): string
    {
        return $this->getCartProductTitle($this->findProductById($productId), $config);
    }

    public function getProductDiscountById(int $productId, Promo $promo, ?array $config = null)
    {
        return $this->getProductDiscount($this->findProductById($productId), $promo, $config);
    }

    /**
     * @return array{
     *   product_id: int,
     *   form_id: ?int,
     *   type: ?string,
     *   quantity: int|float|string,
     *   unit: string,
     *   price: float|int|string,
     *   setup_price: float|int|string,
     *   title: string,
     *   config: array
     * }
     */
    public function getCartProductViewData(\Model_CartProduct $item): array
    {
        $product = $this->findProductById((int) $item->product_id);
        $config = json_decode($item->config ?? '', true) ?? [];
        $line = $this->getProductOrderLineConfig($product, $config);

        return [
            'product_id' => (int) $this->getProductId($product),
            'form_id' => $this->getProductFormId($product),
            'type' => $this->getProductType($product),
            'quantity' => $line['quantity'],
            'unit' => $this->getProductUnit($product),
            'price' => $line['price'],
            'setup_price' => $line['setup_price'],
            'title' => $this->getCartProductTitle($product, $config),
            'config' => $config,
        ];
    }

    /**
     * @param list<int|string> $ids
     *
     * @return array<int, ?string>
     */
    public function getProductPluginMap(array $ids): array
    {
        $result = [];
        foreach ($this->getProductSnapshotMap($ids) as $productId => $product) {
            $result[$productId] = $product['plugin'];
        }

        return $result;
    }

    public function getProductPluginById(int $id): ?string
    {
        return $this->getProductPluginMap([$id])[$id] ?? null;
    }

    public function toAddonArray(Product $model, $deep = true, bool $isAdmin = false): array
    {
        $productPayment = $this->getProductPaymentById((int) $this->getProductPaymentId($model));
        $pricing = $this->toProductPaymentApiArray($productPayment);
        $config = json_decode($this->getProductConfigJson($model) ?? '', true) ?? [];

        $result = [
            'id' => $this->getProductId($model),
            'type' => $this->getProductType($model),
            'title' => $this->getProductTitle($model),
            'slug' => $this->getProductSlug($model),
            'description' => $this->getProductDescription($model),
            'unit' => $this->getProductUnit($model),
            'plugin' => $this->getProductPlugin($model),
            'allow_quantity_select' => $this->isAllowQuantitySelect($model),
            'created_at' => $this->formatDateTimeValue($this->getProductCreatedAt($model)),
            'updated_at' => $this->formatDateTimeValue($this->getProductUpdatedAt($model)),
            'icon_url' => $this->getProductIconUrl($model),

            'pricing' => $isAdmin ? $pricing : $this->getPublicPricing($pricing),
            'config' => $isAdmin ? $config : $this->getPublicConfig($config),
        ];

        if (!$isAdmin) {
            unset($result['plugin'], $result['created_at'], $result['updated_at']);
        }

        return $result;
    }

    /*
     * Product Promotion Functions
     */
    public function getPromoSearchQueryBuilder(array $data): QueryBuilder
    {
        return $this->getPromoRepository()->getSearchQueryBuilder($data);
    }

    public function createPromo($code, $type, $value, $products, $periods, $clientGroups, $data): int
    {
        if ($this->getPromoRepository()->findOneBy(['code' => $code]) instanceof Promo) {
            throw new \FOSSBilling\InformationException('This promotion code already exists.');
        }

        $promo = new Promo();
        $promo->setCode($code);
        $this->applyPromoDataToEntity($promo, [
            ...$data,
            'type' => $type,
            'value' => $value,
            'products' => $products,
            'periods' => $periods,
            'client_groups' => $clientGroups,
        ]);

        $this->di['em']->persist($promo);
        $this->di['em']->flush();
        $promoId = (int) $promo->getId();

        $this->di['logger']->info('Created new promotion code %s', $promo->getCode());

        return $promoId;
    }

    public function findActivePromoByCode($code): ?Promo
    {
        return $this->getPromoRepository()->findActiveByCode((string) $code);
    }

    public function findPromoById(int $id): Promo
    {
        $promo = $this->getPromoRepository()->find($id);
        if (!$promo instanceof Promo) {
            throw new \FOSSBilling\InformationException('Promo not found');
        }

        return $promo;
    }

    public function promoCanBeApplied(Promo $promo): bool
    {
        $promoData = $this->getPromoSourceArray($promo);

        if (empty($promoData['active'])) {
            return false;
        }

        $maxUses = (int) ($promoData['maxuses'] ?? 0);
        $used = (int) ($promoData['used'] ?? 0);
        if ($maxUses > 0 && $maxUses <= $used) {
            return false;
        }

        if (!empty($promoData['start_at']) && (strtotime((string) $promoData['start_at']) - time() > 0)) {
            return false;
        }

        if (!empty($promoData['end_at']) && (strtotime((string) $promoData['end_at']) - time() < 0)) {
            return false;
        }

        return true;
    }

    public function isPromoAvailableForClientGroup(Promo $promo, ?\Model_Client $client = null): bool
    {
        $promoData = $this->getPromoSourceArray($promo);
        $clientGroups = $this->decodePromoSelection($promoData['client_groups'] ?? null);

        if (empty($clientGroups)) {
            return true;
        }

        if ($client === null) {
            try {
                $client = $this->di['loggedin_client'];
            } catch (\Exception) {
                $client = null;
            }
        }

        if (is_null($client)) {
            return false;
        }

        if (!$client->client_group_id) {
            return false;
        }

        return in_array($client->client_group_id, $clientGroups);
    }

    public function canClientUsePromo(\Model_Client $client, Promo $promo): bool
    {
        if (!$this->promoCanBeApplied($promo)) {
            return false;
        }

        $promoData = $this->getPromoSourceArray($promo);
        if (empty($promoData['once_per_client'])) {
            return true;
        }

        return !$this->clientHasActivePromoApplication($client, $promo);
    }

    public function usePromo(Promo $promo): void
    {
        $promoId = (int) ($this->getPromoSourceArray($promo)['id'] ?? 0);
        $affectedRows = $this->getPromoRepository()->incrementUsageIfAvailable($promoId, new \DateTimeImmutable());
        if ($affectedRows === 0) {
            throw new \FOSSBilling\InformationException('This promo code has reached its maximum number of uses.');
        }
    }

    public function reservePromoForOrder(Promo $promo, \Model_ClientOrder $order): void
    {
        $this->usePromo($promo);
        $promoData = $this->getPromoSourceArray($promo);

        $order->promo_recurring = (int) !empty($promoData['recurring']);
        $order->promo_used = 1;
        $this->di['db']->store($order);
    }

    public function getPromoDiscountTitle(Promo $promo, string $currency): string
    {
        $api_guest = $this->di['api_guest'];
        $promoData = $this->getPromoSourceArray($promo);

        return match ($promoData['type'] ?? null) {
            Promo::ABSOLUTE => __trans('Promotional Code: :code - :value Discount', [
                ':code' => $promoData['code'] ?? '',
                ':value' => $api_guest->currency_format(['code' => $currency, 'price' => $promoData['value'] ?? 0]),
            ]),
            Promo::PERCENTAGE => __trans('Promotional Code: :code - :value%', [
                ':code' => $promoData['code'] ?? '',
                ':value' => $promoData['value'] ?? 0,
            ]),
            default => __trans('Promotional Code: :code', [':code' => $promoData['code'] ?? '']),
        };
    }

    /**
     * @param list<\Model_ClientOrder> $orders
     */
    public function createCheckoutPromoRedemptions(
        Promo $promo,
        \Model_Client $client,
        array $orders,
        ?\Model_Invoice $invoice,
        string $status,
    ): void {
        if ($orders === []) {
            return;
        }

        foreach ($orders as $order) {
            $redemption = $this->newPromoRedemption(
                $promo,
                $client,
                $order,
                $invoice,
                PromoRedemption::PHASE_CHECKOUT,
                (float) $order->discount,
                $order->currency,
                $order->created_at,
                $status,
            );

            $this->di['em']->persist($redemption);
        }

        $this->di['em']->flush();
    }

    /**
     * Compensate for a failed checkout by removing orphaned promo redemption
     * rows and decrementing the promo usage counter.
     *
     * Needed because RedBean's transaction (orders/invoices) operates on a
     * separate database connection from Doctrine (promo redemptions, promo.used).
     * When the RedBean transaction rolls back, Doctrine-side changes persist
     * orphaned unless explicitly cleaned up.
     *
     * Idempotent: safe to call multiple times. Returns early if redemptions
     * were already cleaned up by a previous invocation.
     *
     * @param int[] $orderIds      Order IDs from the rolled-back RedBean transaction
     * @param int   $reservedCount Number of successful reservePromoForOrder() calls
     */
    public function compensateCheckoutPromoFailure(Promo $promo, array $orderIds, int $reservedCount): void
    {
        if ($reservedCount <= 0) {
            return;
        }

        $promoId = (int) ($this->getPromoSourceArray($promo)['id'] ?? 0);
        if ($promoId <= 0) {
            return;
        }

        if ($orderIds === []) {
            return;
        }

        $redemptions = $this->getPromoRedemptionRepository()->findBy([
            'promoId' => $promoId,
            'clientOrderId' => $orderIds,
        ]);
        if ($redemptions === []) {
            return;
        }

        foreach ($redemptions as $redemption) {
            $this->di['em']->remove($redemption);
        }
        $this->di['em']->flush();

        $this->getPromoRepository()->decrementUsage($promoId, count($redemptions), new \DateTimeImmutable());
    }

    /**
     * @return array{
     *     promo: Promo,
     *     discount_amount: float,
     *     title: string,
     *     currency: string
     * }|null
     */
    public function getRenewalPromoAdjustment(\Model_ClientOrder $order, float $price, float $quantity): ?array
    {
        if (!$order->promo_recurring || !$order->promo_id) {
            return null;
        }

        $promo = $this->findPromoById((int) $order->promo_id);
        $product = $this->findProductById((int) $order->product_id);
        $discountAmount = (float) $order->discount;

        if ($this->getProductType($product) === self::DOMAIN) {
            $config = json_decode($order->config ?? '', true) ?? [];
            $discountAmount = $this->getRenewalProductDiscount($product, $promo, $config);

            $currencyService = $this->di['mod_service']('Currency');
            $currencyRepository = $currencyService->getCurrencyRepository();
            $rate = $currencyRepository->getRateByCode($order->currency);
            if ($rate === null) {
                throw new \FOSSBilling\Exception("Currency conversion rate cannot be determined for code {$order->currency}");
            }

            $discountAmount *= $rate;
        }

        $orderTotal = $price * $quantity;
        $discountAmount = min($discountAmount, $orderTotal);
        if ($discountAmount <= 0) {
            return null;
        }

        return [
            'promo' => $promo,
            'discount_amount' => $discountAmount,
            'title' => $this->getPromoDiscountTitle($promo, $order->currency),
            'currency' => $order->currency,
        ];
    }

    public function toPromoApiArray(Promo $model, $deep = false, $identity = null)
    {
        return $this->enrichPromoApiArray($this->getPromoApiSourceArray($model), $deep, $identity);
    }

    public function enrichPromoApiArray(array $result, $deep = false, $identity = null): array
    {
        $products = !empty($result['products']) ? $this->getProductTitlesByIds($this->decodePromoSelection($result['products'])) : null;
        $clientGroups = !empty($result['client_groups']) ? $this->di['tools']->getPairsForTableByIds('client_group', $this->decodePromoSelection($result['client_groups'])) : null;
        $usageStats = $deep ? $this->getPromoUsageStatsByValues((int) $result['id'], (int) ($result['used'] ?? 0), isset($result['maxuses']) ? (int) $result['maxuses'] : null) : null;
        $redemptionCount = $usageStats['recorded_applications'] ?? $this->getPromoRedemptionCountById((int) $result['id']);

        $result['applies_to'] = $products;
        $result['cgroups'] = $clientGroups;
        $result['products'] = $this->decodePromoSelection($result['products'] ?? null);
        $result['periods'] = $this->decodePromoSelection($result['periods'] ?? null);
        $result['client_groups'] = $this->decodePromoSelection($result['client_groups'] ?? null);
        $result['redemption_count'] = $redemptionCount;
        $result['can_be_deleted'] = !$this->hasPromoRedemptionHistoryById((int) $result['id'], $redemptionCount);
        if ($usageStats !== null) {
            $result['usage_stats'] = $usageStats;
        }

        return $result;
    }

    public function enrichPromoRedemptionApiArray(array $row): array
    {
        $result = $row;
        $result['client'] = null;
        $result['order'] = null;
        $result['invoice'] = null;

        if (!empty($result['client_id'])) {
            $client = $this->getPromoRedemptionRepository()->findClientSummary((int) $result['client_id']);

            $result['client'] = [
                'id' => (int) $result['client_id'],
                'first_name' => $client['first_name'] ?? null,
                'last_name' => $client['last_name'] ?? null,
                'email' => $client['email'] ?? null,
            ];
        }

        if (!empty($result['client_order_id'])) {
            $order = $this->getPromoRedemptionRepository()->findOrderSummary((int) $result['client_order_id']);

            $result['order'] = [
                'id' => (int) $result['client_order_id'],
                'title' => $order['title'] ?? null,
                'created_at' => $order['created_at'] ?? null,
            ];
        }

        if (!empty($result['invoice_id'])) {
            $invoice = $this->getPromoRedemptionRepository()->findInvoiceSummary((int) $result['invoice_id']);

            $result['invoice'] = [
                'id' => (int) $result['invoice_id'],
                'serie_nr' => $invoice['serie_nr'] ?? null,
                'status' => $invoice['status'] ?? null,
                'created_at' => $invoice['created_at'] ?? null,
            ];
        }

        return $result;
    }

    public function createPromoRedemption(
        Promo $promo,
        \Model_Client $client,
        ?\Model_ClientOrder $order,
        ?\Model_Invoice $invoice,
        string $phase,
        ?float $discountAmount,
        ?string $currency,
        ?string $createdAt = null,
        string $status = PromoRedemption::STATUS_COMMITTED,
    ): int {
        $redemption = $this->newPromoRedemption($promo, $client, $order, $invoice, $phase, $discountAmount, $currency, $createdAt, $status);
        $this->di['em']->persist($redemption);
        $this->di['em']->flush();

        return (int) $redemption->getId();
    }

    public function clientHasActivePromoApplication(\Model_Client $client, Promo $promo): bool
    {
        $promoId = (int) ($this->getPromoSourceArray($promo)['id'] ?? 0);

        return $this->getPromoRedemptionRepository()->clientHasActiveCheckoutApplication($promoId, (int) $client->id);
    }

    public function commitReservedPromoRedemptionsForInvoice(\Model_Invoice $invoice): void
    {
        $redemptions = $this->getPromoRedemptionRepository()->findBy([
            'invoiceId' => (int) $invoice->id,
            'status' => PromoRedemption::STATUS_RESERVED,
        ]);

        if ($redemptions === []) {
            return;
        }

        $committedAt = $invoice->paid_at ? new \DateTime((string) $invoice->paid_at) : new \DateTime();
        foreach ($redemptions as $redemption) {
            if (!$redemption instanceof PromoRedemption) {
                continue;
            }

            $redemption
                ->setStatus(PromoRedemption::STATUS_COMMITTED)
                ->setCommittedAt(clone $committedAt)
                ->setReleasedAt(null)
                ->setReleaseReason(null);
        }

        $this->di['em']->flush();
    }

    public function releaseReservedPromoRedemptionsForInvoice(\Model_Invoice $invoice, string $reason): void
    {
        $redemptions = $this->getPromoRedemptionRepository()->findBy([
            'invoiceId' => (int) $invoice->id,
            'status' => PromoRedemption::STATUS_RESERVED,
        ]);

        $this->releasePromoRedemptions($redemptions, $reason);
    }

    public function releaseReservedPromoRedemptionsForOrder(\Model_ClientOrder $order, string $reason): void
    {
        $redemptions = $this->getPromoRedemptionRepository()->findBy([
            'clientOrderId' => (int) $order->id,
            'status' => PromoRedemption::STATUS_RESERVED,
        ]);

        $this->releasePromoRedemptions($redemptions, $reason);
    }

    public function updatePromo(Promo $model, array $data = []): bool
    {
        $promo = $model;
        if (($data['code'] ?? null) !== null && $data['code'] !== $promo->getCode()) {
            $existing = $this->getPromoRepository()->findOneBy(['code' => $data['code']]);
            if ($existing instanceof Promo && $existing->getId() !== $promo->getId()) {
                throw new \FOSSBilling\InformationException('This promotion code already exists.');
            }
        }

        $this->applyPromoDataToEntity($promo, $data);
        $this->di['em']->flush();

        $this->di['logger']->info('Update promo code %s', $promo->getCode());

        return true;
    }

    public function deletePromo(Promo $model): bool
    {
        $promo = $model;
        $promoId = (int) $promo->getId();
        if ($this->hasPromoRedemptionHistoryById($promoId)) {
            throw new \FOSSBilling\InformationException('Promotions with redemption history cannot be deleted. Disable the promotion instead.');
        }

        $this->di['em']->remove($promo);
        $this->di['em']->flush();

        $this->di['logger']->info('Removed promo code %s', $promo->getCode());

        return true;
    }

    public function isPromoLinkedToProduct(Promo $promo, Product $product)
    {
        if ($product->isAddon()) {
            return false;
        }

        $products = $this->getProducts($promo);
        if (empty($products)) {
            return true;
        }

        return in_array($this->getProductId($product), $products);
    }

    /**
     * Resolve initial order line pricing for a product from the active pricing backend.
     *
     * @return array{price: float|int|string, quantity: int|float|string, setup_price?: float|int|string}
     */
    public function getProductOrderLineConfig(Product $product, ?array $config = null): array
    {
        if ($this->getProductType($product) === self::DOMAIN) {
            return $this->getDomainOrderLineConfig($config ?? []);
        }

        $quantity = max(1, (int) ($config['quantity'] ?? 1));

        return [
            'price' => (float) $this->getProductPrice($product, $config),
            'quantity' => $quantity,
            'setup_price' => $this->getProductSetupPrice($product, $config),
        ];
    }

    /**
     * Resolve renewal line pricing for a product from the active pricing backend.
     *
     * @return array{price: float|int|string, quantity: int|float|string, setup_price?: float|int|string}
     */
    public function getProductRenewalLineConfig(Product $product, ?array $config = null): array
    {
        if ($this->getProductType($product) === self::DOMAIN) {
            return $this->getDomainRenewalLineConfig($config ?? []);
        }

        return $this->getProductOrderLineConfig($product, $config);
    }

    public function getProductSetupPrice(Product $product, ?array $config = null): float
    {
        if ($this->getProductType($product) === self::DOMAIN) {
            return 0.0;
        }

        $pp = $this->getProductPaymentById((int) $this->getProductPaymentId($product));

        if ($pp->getType() == ProductPayment::FREE) {
            return 0.0;
        }

        if ($pp->getType() == ProductPayment::ONCE) {
            return $pp->getOnceSetupPrice();
        }

        if ($pp->getType() == ProductPayment::RECURRENT) {
            $period = new \Box_Period((string) ($config['period'] ?? ''));
            $key = $this->getProductPaymentPeriodKey($period);

            return $pp->getPeriodSetupPrice($key);
        }

        throw new \FOSSBilling\Exception('Unknown period selected for setup price');
    }

    public function getProductPrice(Product $product, ?array $config = null): float|int|string
    {
        if ($this->getProductType($product) === self::DOMAIN) {
            return $this->getDomainProductPrice($config ?? []);
        }

        $pp = $this->getProductPaymentById((int) $this->getProductPaymentId($product));

        if ($pp->getType() == ProductPayment::FREE) {
            return 0.0;
        }

        if ($pp->getType() == ProductPayment::ONCE) {
            return $pp->getOncePrice();
        }

        if ($pp->getType() == ProductPayment::RECURRENT) {
            if (!isset($config['period'])) {
                throw new \FOSSBilling\Exception('Product :id payment type is recurrent, but period was not selected', [':id' => $this->getProductId($product)]);
            }

            $period = new \Box_Period((string) $config['period']);
            $key = $this->getProductPaymentPeriodKey($period);

            return $pp->getPeriodPrice($key);
        }

        throw new \FOSSBilling\Exception('Unknown Period selected for price');
    }

    private function getProductPaymentPeriodKey(\Box_Period $period): string
    {
        $code = $period->getCode();

        try {
            return match ($code) {
                '1W' => 'w',
                '1M' => 'm',
                '3M' => 'q',
                '6M' => 'b',
                '12M', '1Y' => 'a',
                '2Y' => 'bia',
                '3Y' => 'tria',
            };
        } catch (\UnhandledMatchError) {
            throw new \FOSSBilling\Exception('Unknown period selected ' . $code);
        }
    }

    private function getProductPaymentById(int $id): ProductPayment
    {
        $productPayment = $this->getProductPaymentRepository()->find($id);
        if (!$productPayment instanceof ProductPayment) {
            throw new \FOSSBilling\InformationException('Product payment not found');
        }

        return $productPayment;
    }

    private function getDbalConnection(): Connection
    {
        if ($this->di === null) {
            throw new \FOSSBilling\Exception('The dependency injection container has not been set.');
        }

        if (isset($this->di['dbal']) && $this->di['dbal'] instanceof Connection) {
            return $this->di['dbal'];
        }

        return $this->di['em']->getConnection();
    }

    private function createDefaultProductPayment(): ProductPayment
    {
        $productPayment = new ProductPayment();
        $productPayment->setType(ProductPayment::FREE);
        $this->di['em']->persist($productPayment);
        $this->di['em']->flush();

        return $productPayment;
    }

    private function generateUniqueProductSlug(string $title): string
    {
        $slug = $this->di['tools']->slug($title);

        while ($this->getProductRepository()->findOneBy(['slug' => $slug]) instanceof Product) {
            $slug = $this->di['tools']->slug($title) . '-' . random_int(1, 9999);
        }

        return $slug;
    }

    private function resolveStockProduct(Product|int $product): Product
    {
        if ($product instanceof Product) {
            return $product;
        }

        return $this->findProductById($product);
    }

    /**
     * @param mixed[] $ids
     *
     * @return list<int>
     */
    private function normalizeProductIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map(intval(...), $ids),
            static fn (int $id): bool => $id > 0
        )));
    }

    /**
     * @param array<string, mixed> $pricing
     */
    private function applyPricingToProductPayment(ProductPayment $productPayment, array $pricing): void
    {
        $productPayment->setType((string) $pricing['type']);

        if ($pricing['type'] == ProductPayment::ONCE) {
            $productPayment
                ->setOnceSetupPrice((float) ($pricing['once']['setup'] ?? 0))
                ->setOncePrice((float) ($pricing['once']['price'] ?? 0));
        }

        if ($pricing['type'] == ProductPayment::RECURRENT) {
            $periodMap = [
                '1W' => 'w',
                '1M' => 'm',
                '3M' => 'q',
                '6M' => 'b',
                '1Y' => 'a',
                '2Y' => 'bia',
                '3Y' => 'tria',
            ];

            foreach ($periodMap as $period => $prefix) {
                if (!isset($pricing['recurrent'][$period])) {
                    continue;
                }

                $periodPricing = $pricing['recurrent'][$period];
                $productPayment->setPeriodPricing(
                    $prefix,
                    (float) ($periodPricing['price'] ?? 0),
                    (float) ($periodPricing['setup'] ?? 0),
                    $periodPricing['enabled'] ?? false
                );
            }
        }
    }

    public function findProductById(int $id): Product
    {
        $product = $this->getProductRepository()->find($id);
        if (!$product instanceof Product) {
            throw new \FOSSBilling\InformationException('Product not found');
        }

        return $product;
    }

    public function getProductModuleService(Product $product): object
    {
        $type = $this->getProductType($product);
        if ($type === null || $type === '') {
            throw new \FOSSBilling\Exception('Product type could not be determined.');
        }

        return $this->di['mod_service']('service' . $type);
    }

    private function getProductId(Product $product): ?int
    {
        return $product->getId();
    }

    private function getProductCategoryId(Product $product): ?int
    {
        return $product->getProductCategoryId();
    }

    private function getProductPaymentId(Product $product): ?int
    {
        return $product->getProductPaymentId();
    }

    private function getProductFormId(Product $product): ?int
    {
        return $product->getFormId();
    }

    private function getProductType(Product $product): ?string
    {
        return $product->getType();
    }

    private function getProductTitle(Product $product): ?string
    {
        return $product->getTitle();
    }

    private function getProductSlug(Product $product): ?string
    {
        return $product->getSlug();
    }

    private function getProductDescription(Product $product): ?string
    {
        return $product->getDescription();
    }

    private function getProductPriority(Product $product): ?int
    {
        return $product->getPriority();
    }

    private function getProductIconUrl(Product $product): ?string
    {
        return $product->getIconUrl();
    }

    private function isAllowQuantitySelect(Product $product): bool
    {
        return $product->isAllowQuantitySelect();
    }

    private function getProductQuantityInStock(Product $product): int
    {
        return $product->getQuantityInStock();
    }

    private function isStockControlled(Product $product): bool
    {
        return $product->isStockControl();
    }

    private function getProductStatus(Product $product): string
    {
        return $product->getStatus();
    }

    private function isProductHidden(Product $product): bool
    {
        return $product->isHidden();
    }

    private function getProductSetup(Product $product): string
    {
        return $product->getSetup();
    }

    private function getProductAddonsJson(Product $product): ?string
    {
        return $product->getAddons();
    }

    private function getProductPlugin(Product $product): ?string
    {
        return $product->getPlugin();
    }

    private function getProductConfigJson(Product $product): ?string
    {
        return $product->getConfig();
    }

    /**
     * @return array<string, mixed>
     */
    private function getProductValidationData(Product $product): array
    {
        return [
            'id' => $this->getProductId($product),
            'product_category_id' => $this->getProductCategoryId($product),
            'product_payment_id' => $this->getProductPaymentId($product),
            'form_id' => $this->getProductFormId($product),
            'title' => $this->getProductTitle($product),
            'description' => $this->getProductDescription($product),
            'unit' => $this->getProductUnit($product),
            'status' => $this->getProductStatus($product),
            'config' => json_decode($this->getProductConfigJson($product) ?? '', true) ?? [],
            'plugin' => $this->getProductPlugin($product),
            'type' => $this->getProductType($product),
        ];
    }

    private function getProductUpgradesJson(Product $product): ?string
    {
        return $product->getUpgrades();
    }

    private function getProductCreatedAt(Product $product): mixed
    {
        return $product->getCreatedAt();
    }

    private function getProductUpdatedAt(Product $product): mixed
    {
        return $product->getUpdatedAt();
    }

    private function setProductCategoryIdValue(Product $product, ?int $value): void
    {
        $product->setProductCategoryId($value);
    }

    private function setProductFormIdValue(Product $product, ?int $value): void
    {
        $product->setFormId($value);
    }

    private function setProductIconUrlValue(Product $product, ?string $value): void
    {
        $product->setIconUrl($value);
    }

    private function setProductStatusValue(Product $product, string $value): void
    {
        $product->setStatus($value);
    }

    private function setProductHiddenValue(Product $product, bool $value): void
    {
        $product->setHidden($value);
    }

    private function setProductSlugValue(Product $product, ?string $value): void
    {
        $product->setSlug($value);
    }

    private function setProductSetupValue(Product $product, string $value): void
    {
        $product->setSetup($value);
    }

    private function setProductUpgradesJson(Product $product, ?string $value): void
    {
        $product->setUpgrades($value);
    }

    private function setProductAddonsJsonValue(Product $product, ?string $value): void
    {
        $product->setAddons($value);
    }

    private function setProductTitleValue(Product $product, ?string $value): void
    {
        $product->setTitle($value);
    }

    private function setProductStockControlValue(Product $product, bool $value): void
    {
        $product->setStockControl($value);
    }

    private function setProductAllowQuantitySelectValue(Product $product, bool $value): void
    {
        $product->setAllowQuantitySelect($value);
    }

    private function setProductQuantityInStockValue(Product $product, int $value): void
    {
        $product->setQuantityInStock($value);
    }

    private function setProductDescriptionValue(Product $product, ?string $value): void
    {
        $product->setDescription($value);
    }

    private function setProductPluginValue(Product $product, ?string $value): void
    {
        $product->setPlugin($value);
    }

    private function setProductConfigJson(Product $product, ?string $value): void
    {
        $product->setConfig($value);
    }

    private function setProductUpdatedAtValue(Product $product, \DateTime $value): void
    {
        $product->setUpdatedAt($value);
    }

    private function formatDateTimeValue(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return $value !== null ? (string) $value : null;
    }

    /**
     * @param callable(object): array $mapper
     *
     * @return array{pages:int,page:int,per_page:int,total:int,list:array<int, array>}
     */
    private function paginateMappedQuery(QueryBuilder $qb, PaginationOptions $pagination, callable $mapper): array
    {
        return $this->di['pager']->paginateMappedQuery($qb, $pagination, $mapper);
    }

    public function getProductDiscount(Product $product, Promo $promo, ?array $config = null)
    {
        if (!$this->isPromoLinkedToProduct($promo, $product)) {
            return 0;
        }

        if (isset($config['period'])) {
            $periods = $this->getPeriods($promo);
            if (!empty($periods) && !in_array($config['period'], $periods)) {
                return 0;
            }
        }

        $line = $this->getProductOrderLineConfig($product, $config);
        $price = $line['price'] * $line['quantity'];

        if ($price == 0) {
            return 0;
        }

        $discount = 0;

        $promoData = $this->getPromoSourceArray($promo);

        switch ($promoData['type'] ?? null) {
            case Promo::ABSOLUTE:
                $discount += (float) ($promoData['value'] ?? 0);

                break;

            case Promo::PERCENTAGE:
                $discount += round($price * (float) ($promoData['value'] ?? 0) / 100, 2);

                break;

            default:
                break;
        }

        return $discount;
    }

    public function getRenewalProductDiscount(Product $product, Promo $promo, ?array $config = null): float
    {
        if (!$this->isPromoLinkedToProduct($promo, $product)) {
            return 0;
        }

        if (isset($config['period'])) {
            $periods = $this->getPeriods($promo);
            if (!empty($periods) && !in_array($config['period'], $periods)) {
                return 0;
            }
        }

        $line = $this->getProductRenewalLineConfig($product, $config);
        $price = $line['price'] * $line['quantity'];

        if ($price == 0) {
            return 0;
        }

        $promoData = $this->getPromoSourceArray($promo);

        return match ($promoData['type'] ?? null) {
            Promo::ABSOLUTE => (float) ($promoData['value'] ?? 0),
            Promo::PERCENTAGE => round($price * (float) ($promoData['value'] ?? 0) / 100, 2),
            default => 0,
        };
    }

    public function isPromoLinkedToTld(Promo $promo, \Model_Tld $tld): bool
    {
        unset($tld);

        if ($this->getProducts($promo) === []) {
            return true;
        }

        $domainProduct = $this->getMainDomainProduct();
        if (!$domainProduct instanceof Product) {
            return false;
        }

        return $this->isPromoLinkedToProduct($promo, $domainProduct);
    }

    // Function to get all orders for a product
    public function getOrdersForProduct(Product $product)
    {
        return $this->getProductOrderRepository()->getRowsByProductId((int) $this->getProductId($product));
    }

    /**
     * @param list<PromoRedemption> $redemptions
     */
    private function releasePromoRedemptions(array $redemptions, string $reason): void
    {
        if ($redemptions === []) {
            return;
        }

        $releasedAt = new \DateTime();
        $checkoutReleaseCounts = [];
        foreach ($redemptions as $redemption) {
            if ($redemption->getStatus() !== PromoRedemption::STATUS_RESERVED) {
                continue;
            }

            $redemption
                ->setStatus(PromoRedemption::STATUS_RELEASED)
                ->setReleasedAt(clone $releasedAt)
                ->setReleaseReason($reason);

            if ($redemption->getPhase() === PromoRedemption::PHASE_CHECKOUT && $redemption->getPromoId() !== null) {
                $promoId = (int) $redemption->getPromoId();
                $checkoutReleaseCounts[$promoId] = ($checkoutReleaseCounts[$promoId] ?? 0) + 1;
            }
        }

        foreach ($checkoutReleaseCounts as $promoId => $releaseCount) {
            $this->getPromoRepository()->decrementUsage($promoId, $releaseCount, $releasedAt);
        }

        $this->di['em']->flush();
    }

    private function newPromoRedemption(
        Promo $promo,
        \Model_Client $client,
        ?\Model_ClientOrder $order,
        ?\Model_Invoice $invoice,
        string $phase,
        ?float $discountAmount,
        ?string $currency,
        ?string $createdAt,
        string $status,
    ): PromoRedemption {
        $promoId = (int) ($this->getPromoSourceArray($promo)['id'] ?? 0);
        $timestamp = $createdAt ?? date('Y-m-d H:i:s');
        $dateTime = new \DateTime($timestamp);
        $redemption = new PromoRedemption();
        $redemption
            ->setPromoId($promoId)
            ->setClientId((int) $client->id)
            ->setClientOrderId($order?->id !== null ? (int) $order->id : null)
            ->setInvoiceId($invoice?->id !== null ? (int) $invoice->id : null)
            ->setPhase($phase)
            ->setStatus($status)
            ->setDiscountAmount($discountAmount)
            ->setCurrency($currency);
        $redemption->setCreatedAt($dateTime);
        $redemption->setUpdatedAt(clone $dateTime);
        if ($status === PromoRedemption::STATUS_COMMITTED) {
            $redemption->setCommittedAt(clone $dateTime);
        }

        return $redemption;
    }

    private function getPromoApiSourceArray(Promo $model): array
    {
        return $model->toApiArray();
    }

    private function getPromoSourceArray(Promo $model): array
    {
        return $this->getPromoApiSourceArray($model);
    }

    private function getPromoRedemptionCountById(int $promoId): int
    {
        return $this->getPromoRedemptionRepository()->countByPromoId($promoId);
    }

    private function getDomainRegistrationYears(array $config): int
    {
        if (isset($config['period']) && is_string($config['period']) && $config['period'] !== '') {
            $period = $this->di['period']($config['period']);

            return max(1, $period->getQty());
        }

        return max(1, (int) ($config['register_years'] ?? $config['quantity'] ?? 1));
    }

    private function getDomainTldModel(array $config): \Model_Tld
    {
        $tldService = $this->di['mod_service']('servicedomain', 'Tld');
        $tld = '';

        if (!isset($config['action'])) {
            throw new \FOSSBilling\Exception('Could not determine domain price. Domain action is missing', null, 498);
        }

        if ($config['action'] === 'register') {
            $tld = $config['register_tld'] ?? '';
        }

        if ($config['action'] === 'transfer') {
            $tld = $config['transfer_tld'] ?? '';
        }

        $tld = $tldService->findOneByTld($tld);
        if (!$tld instanceof \Model_Tld) {
            throw new \FOSSBilling\Exception('Unknown TLD. Could not determine registration price');
        }

        return $tld;
    }

    private function getDomainRegistrationTotal(\Model_Tld $tld, int $years): float
    {
        if ($years <= 0) {
            return 0.0;
        }

        if ($years <= 1) {
            return (float) $tld->price_registration;
        }

        return (float) $tld->price_registration + (($years - 1) * (float) $tld->price_renew);
    }

    /**
     * @return array{price: float, quantity: int, setup_price: float}
     */
    private function getDomainOrderLineConfig(array $config): array
    {
        if (($config['action'] ?? null) === 'owndomain') {
            return [
                'price' => 0.0,
                'quantity' => 1,
                'setup_price' => 0.0,
            ];
        }

        $tld = $this->getDomainTldModel($config);
        if (($config['action'] ?? null) === 'register') {
            return [
                'price' => $this->getDomainRegistrationTotal($tld, $this->getDomainRegistrationYears($config)),
                'quantity' => 1,
                'setup_price' => 0.0,
            ];
        }

        return [
            'price' => (float) $tld->price_transfer,
            'quantity' => 1,
            'setup_price' => 0.0,
        ];
    }

    /**
     * @return array{price: float, quantity: int}
     */
    private function getDomainRenewalLineConfig(array $config): array
    {
        $tld = $this->getDomainTldModel($config);

        return [
            'price' => (float) $tld->price_renew,
            'quantity' => $this->getDomainRegistrationYears($config),
        ];
    }

    public function getDomainProductPrice(array $config): float
    {
        if (($config['action'] ?? null) === 'owndomain') {
            return 0.0;
        }

        $tld = $this->getDomainTldModel($config);

        return match ($config['action'] ?? null) {
            'register' => (float) $tld->price_registration,
            'transfer' => (float) $tld->price_transfer,
            default => 0.0,
        };
    }

    private function getDomainRelatedDiscount(array $items, array $config): float
    {
        foreach ($items as $addon) {
            if (
                $this->isDomainActionNameSet($addon, 'register')
                && $this->isFreeDomainEnabled($addon)
                && $this->registerDomainMatches($addon, $config)
            ) {
                if ($this->hasFreeDomainPeriod($addon)) {
                    $discountYears = $this->getFreeDomainDiscountYears($addon, (string) ($config['period'] ?? ''));
                    $tld = $this->getDomainTldModel($config);

                    return $this->getDomainRegistrationTotal($tld, $discountYears);
                }

                return 0.0;
            }

            if (
                $this->isDomainActionNameSet($addon, 'transfer')
                && $this->isFreeTransferEnabled($addon)
                && $this->transferDomainMatches($addon, $config)
            ) {
                return $this->getDomainProductPrice($config);
            }
        }

        return 0.0;
    }

    private function hasFreeDomainPeriod(array $addon): bool
    {
        $freeDomainPeriods = $addon['config']['free_domain_periods'] ?? [];
        $addonPeriod = $addon['config']['period'] ?? null;

        return $addonPeriod !== null && in_array($addonPeriod, $freeDomainPeriods, true);
    }

    private function getFreeDomainDiscountYears(array $addon, string $period): int
    {
        $referencePeriod = $this->di['period']($period);
        $referenceQty = $referencePeriod->getQty();

        $addonPeriod = $addon['config']['period'];
        $addonQty = $this->di['period']($addonPeriod)->getQty();

        $freeDomainPeriods = $addon['config']['free_domain_periods'] ?? [];
        if (\FOSSBilling\Tools::safeCount($freeDomainPeriods) > 0) {
            if ($addonPeriod === $period && in_array($addonPeriod, $freeDomainPeriods, true)) {
                return $referenceQty;
            }

            if (str_contains((string) $addonPeriod, 'Y')) {
                if (min($referenceQty, $addonQty) === 1) {
                    return 1;
                }

                $freeDomainQtys = [];
                foreach ($freeDomainPeriods as $freePeriod) {
                    $quantity = $this->di['period']($freePeriod)->getQty();
                    if ($referenceQty - $quantity > 0) {
                        $freeDomainQtys[] = $quantity;
                    }
                }

                if ($freeDomainQtys !== []) {
                    return min($referenceQty, min($freeDomainQtys));
                }
            }
        }

        return 0;
    }

    private function isDomainActionNameSet(array $item, string $actionName): bool
    {
        return isset($item['config']['domain']['action']) && $item['config']['domain']['action'] === $actionName;
    }

    private function isFreeDomainEnabled(array $item): bool
    {
        $freeDomain = $item['config']['free_domain'] ?? false;
        if (!$freeDomain) {
            return false;
        }

        $tld = $item['config']['tld'] ?? null;
        $freeTlds = $item['config']['free_tlds'] ?? [];
        if ($freeTlds === []) {
            return true;
        }

        return $tld !== null && in_array($tld, $freeTlds, true);
    }

    private function registerDomainMatches(array $item, array $config): bool
    {
        if (!isset($item['config']['domain']['register_sld'])) {
            return false;
        }

        return ($item['config']['domain']['register_sld'] ?? null) === ($config['register_sld'] ?? null)
            && ($item['config']['domain']['register_tld'] ?? null) === ($config['register_tld'] ?? null);
    }

    private function transferDomainMatches(array $item, array $config): bool
    {
        if (!isset($item['config']['domain']['transfer_sld'])) {
            return false;
        }

        return ($item['config']['domain']['transfer_sld'] ?? null) === ($config['transfer_sld'] ?? null)
            && ($item['config']['domain']['transfer_tld'] ?? null) === ($config['transfer_tld'] ?? null);
    }

    private function isFreeTransferEnabled(array $item): bool
    {
        return !empty($item['config']['free_transfer']);
    }

    /**
     * @return array{
     *     operational_use_count: int,
     *     max_uses: int|null,
     *     remaining_operational_uses: int|null,
     *     recorded_applications: int,
     *     checkout_applications: int,
     *     renewal_applications: int,
     *     active_checkout_applications: int,
     *     reserved_applications: int,
     *     committed_applications: int,
     *     released_applications: int,
     *     distinct_clients: int,
     *     orders_using_promo: int
     * }
     */
    private function getPromoUsageStatsByValues(int $promoId, int $operationalUseCount, ?int $maxUses): array
    {
        $repoStats = $this->getPromoRedemptionRepository()->getUsageStatsByPromoId($promoId);

        return [
            'operational_use_count' => $operationalUseCount,
            'max_uses' => $maxUses,
            'remaining_operational_uses' => $maxUses !== null ? max($maxUses - $operationalUseCount, 0) : null,
            'recorded_applications' => $repoStats['recorded_applications'],
            'checkout_applications' => $repoStats['checkout_applications'],
            'renewal_applications' => $repoStats['renewal_applications'],
            'active_checkout_applications' => $repoStats['active_checkout_applications'],
            'reserved_applications' => $repoStats['reserved_applications'],
            'committed_applications' => $repoStats['committed_applications'],
            'released_applications' => $repoStats['released_applications'],
            'distinct_clients' => $repoStats['distinct_clients'],
            'orders_using_promo' => $repoStats['orders_using_promo'],
        ];
    }

    private function hasPromoRedemptionHistoryById(int $promoId, ?int $redemptionCount = null): bool
    {
        $redemptionCount ??= $this->getPromoRedemptionCountById($promoId);
        if ($redemptionCount > 0) {
            return true;
        }

        $orderCount = $this->getPromoRepository()->countLinkedOrdersByPromoId($promoId);

        return $orderCount > 0;
    }

    private function applyPromoDataToEntity(Promo $promo, array $data): void
    {
        $promo
            ->setCode($data['code'] ?? $promo->getCode())
            ->setDescription($data['description'] ?? $promo->getDescription())
            ->setType((string) ($data['type'] ?? $promo->getType()))
            ->setValue($data['value'] ?? $promo->getValue())
            ->setActive((bool) ($data['active'] ?? $promo->isActive()))
            ->setFreeSetup((bool) ($data['freesetup'] ?? $promo->isFreeSetup()))
            ->setOncePerClient((bool) ($data['once_per_client'] ?? $promo->isOncePerClient()))
            ->setRecurring((bool) ($data['recurring'] ?? $promo->isRecurring()))
            ->setUsed(isset($data['used']) ? (int) $data['used'] : $promo->getUsed())
            ->setMaxUses(isset($data['maxuses']) ? (int) $data['maxuses'] : $promo->getMaxUses())
            ->setProducts($this->encodePromoSelection($data['products'] ?? $this->decodePromoSelection($promo->getProducts())))
            ->setPeriods($this->encodePromoSelection($data['periods'] ?? $this->decodePromoSelection($promo->getPeriods())))
            ->setClientGroups($this->encodePromoSelection($data['client_groups'] ?? $this->decodePromoSelection($promo->getClientGroups())))
            ->setStartAt($this->normalizePromoDateTimeObject($data['start_at'] ?? $promo->getStartAt()))
            ->setEndAt($this->normalizePromoDateTimeObject($data['end_at'] ?? $promo->getEndAt()));
    }

    /**
     * @param array<mixed>|null $selection
     */
    private function encodePromoSelection(mixed $selection): ?string
    {
        if (!is_array($selection)) {
            return null;
        }

        $selection = array_values(array_filter($selection));

        return $selection === [] ? null : json_encode($selection);
    }

    /**
     * @return array<mixed>
     */
    private function decodePromoSelection(?string $selection): array
    {
        return json_decode($selection ?? '', true) ?? [];
    }

    private function normalizePromoDateTime(mixed $value): ?string
    {
        return !empty($value) ? date('Y-m-d H:i:s', strtotime((string) $value)) : null;
    }

    private function normalizePromoDateTimeObject(mixed $value): ?\DateTime
    {
        if ($value instanceof \DateTime) {
            return clone $value;
        }

        $normalized = $this->normalizePromoDateTime($value);

        return $normalized !== null ? new \DateTime($normalized) : null;
    }
}
