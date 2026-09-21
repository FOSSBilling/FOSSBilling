<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Cart;

use Box\Mod\Cart\Entity\Cart;
use Box\Mod\Cart\Entity\CartProduct;
use Box\Mod\Cart\Repository\CartProductRepository;
use Box\Mod\Cart\Repository\CartRepository;
use Box\Mod\Client\Entity\Client;
use Box\Mod\Currency\Entity\Currency;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Product\Entity\Promo;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use FOSSBilling\Doctrine\EntityManagerFactory;
use FOSSBilling\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    /**
     * Internal cart-item config key recording which order family (one
     * add-to-cart action: the product plus its bundled domain and selected
     * addons) an item belongs to. Server-side bookkeeping only: stamped after
     * the client-input filter in addItem(), consumed and stripped at checkout
     * when per-family order group_ids are assigned.
     */
    public const string CART_FAMILY_KEY = '__cart_family';

    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    protected function resetEntityManager(): void
    {
        unset($this->di['em']);
        $this->di['em'] = EntityManagerFactory::create();
    }

    public function getCartRepository(): CartRepository
    {
        return $this->di['em']->getRepository(Cart::class);
    }

    public function getCartProductRepository(): CartProductRepository
    {
        return $this->di['em']->getRepository(CartProduct::class);
    }

    private function persistCart(Cart $cart): void
    {
        $this->di['em']->persist($cart);
        $this->di['em']->flush();
    }

    /**
     * @return list<CartProduct>
     */
    private function findCartProducts(Cart $cart): array
    {
        return $this->getCartProductRepository()->findByCartId((int) $cart->getId());
    }

    private function findCartProduct(Cart $cart, int $id): ?CartProduct
    {
        $product = $this->getCartProductRepository()->findOneByCartAndId((int) $cart->getId(), $id);

        return $product instanceof CartProduct ? $product : null;
    }

    public function getModulePermissions(): array
    {
        return [
            'hide_permissions' => true,
        ];
    }

    public function getSearchQuery($data): array
    {
        $sql = '
            SELECT cart.id FROM cart
            LEFT JOIN currency ON cart.currency_id = currency.id
            LEFT JOIN promo ON cart.promo_id = promo.id';

        return [$sql, []];
    }

    public function transferFromOtherSession(string $sessionID): bool
    {
        $cart = $this->getSessionCart($sessionID);
        $cart->setSessionId($this->di['session']->getId());
        $this->persistCart($cart);

        return true;
    }

    public function getSessionCart(?string $sessionID = null): Cart
    {
        $sessionID ??= $this->di['session']->getId();
        $cart = $this->getCartRepository()->findBySessionId($sessionID);

        if ($cart instanceof Cart) {
            return $cart;
        }

        $currencyService = $this->di['mod_service']('currency');
        /** @var \Box\Mod\Currency\Repository\CurrencyRepository $currencyRepository */
        $currencyRepository = $currencyService->getCurrencyRepository();

        // Try to get client's currency if client is logged in
        $currency = null;
        $clientId = $this->di['session']->get('client_id');
        if ($clientId) {
            $currency = $currencyService->getCurrencyByClientId((int) $clientId);
        }

        // Fallback to default currency
        if (!$currency instanceof Currency) {
            $currency = $currencyRepository->findDefault();
            if (!$currency instanceof Currency) {
                throw new \FOSSBilling\Exception('Default currency not found');
            }
        }

        $cart = new Cart();
        $cart->setSessionId($sessionID);
        $cart->setCurrencyId($currency->getId());

        try {
            $this->di['em']->persist($cart);
            $this->di['em']->flush();
        } catch (UniqueConstraintViolationException $exception) {
            $this->resetEntityManager();
            $cart = $this->getCartRepository()->findBySessionId($sessionID);
            if (!$cart instanceof Cart) {
                throw $exception;
            }
        }

        return $cart;
    }

    public function addItem(Cart $cart, Product $product, array $data): bool
    {
        $event_params = [...$data, 'cart_id' => $cart->getId(), 'product_id' => $this->getProductId($product)];
        $this->di['events_manager']->fire(['event' => 'onBeforeProductAddedToCart', 'params' => $event_params]);

        $productService = $this->getProductService()->getProductModuleService($product);

        if ($this->isRecurrentPricing($product)) {
            $required = [
                'period' => 'Period parameter not passed',
            ];
            $this->di['validator']->checkRequiredParamsForArray($required, $data);

            if (!$this->isPeriodEnabledForProduct($product, $data['period'])) {
                throw new \FOSSBilling\InformationException('Selected billing period is invalid');
            }
        }

        $addons = $data['addons'] ?? [];
        unset($data['id']);
        unset($data['addons']);

        $productConfig = json_decode($product->getConfig() ?? '', true) ?? [];

        // Collect all domains that will be added: top-level (direct domain product) and
        // nested under $data['domain'] (domain bundled with a hosting product).
        $domainsBeingAdded = [];
        $topDomain = $this->extractDomainFromConfig($data, $productConfig);
        if ($topDomain !== null) {
            $domainsBeingAdded[] = $topDomain;
        }
        if (isset($data['domain']) && is_array($data['domain'])) {
            $nestedDomain = $this->extractDomainFromConfig($data['domain'], $data + $productConfig);
            if ($nestedDomain !== null) {
                $domainsBeingAdded[] = $nestedDomain;
            }
        }

        if (!empty($domainsBeingAdded)) {
            $existingItems = $this->findCartProducts($cart);
            foreach ($existingItems as $item) {
                $itemConfig = json_decode((string) $item->getConfig(), true);
                if (!is_array($itemConfig)) {
                    continue;
                }
                // Check both top-level and nested domain shapes in the existing item's config.
                $candidates = [$this->extractDomainFromConfig($itemConfig)];
                if (isset($itemConfig['domain']) && is_array($itemConfig['domain'])) {
                    $candidates[] = $this->extractDomainFromConfig($itemConfig['domain'], $itemConfig);
                }
                foreach ($candidates as $existing) {
                    if ($existing === null) {
                        continue;
                    }
                    foreach ($domainsBeingAdded as $incoming) {
                        if (strcasecmp($existing, $incoming) === 0) {
                            throw new \FOSSBilling\InformationException('This domain is already in the cart.');
                        }
                    }
                }
            }
        }

        $list = [];
        $list[] = [
            'product' => $product,
            'config' => $data,
        ];

        // check for required domain product
        if (method_exists($productService, 'getDomainProductFromConfig')) {
            $dc = $productService->getDomainProductFromConfig($product, $data);
            if (isset($dc['config']) && ($dc['product'] ?? null) instanceof Product) {
                $list[] = $dc;
            }
        }

        if ($addons !== []) {
            $productService = $this->di['mod_service']('Product');
            foreach ($productService->getSelectedAddonsForCart($product, $addons) as $selectedAddon) {
                $list[] = $selectedAddon;
            }
        }

        $pendingQuantities = [];
        foreach ($list as $cartItem) {
            /** @var Product $cartProduct */
            $cartProduct = $cartItem['product'];
            $requestedQty = $this->getRequestedQuantity($cartItem['config']);
            $cartProductId = $this->getProductId($cartProduct);
            $pendingQuantities[$cartProductId] = ($pendingQuantities[$cartProductId] ?? 0) + $requestedQty;

            $reservedQty = $this->getReservedQuantityInCart($cart, $cartProductId);
            if (!$this->isStockAvailable($cartProduct, $reservedQty + $pendingQuantities[$cartProductId])) {
                throw new \FOSSBilling\InformationException('This item is currently out of stock');
            }
        }

        $familyToken = bin2hex(random_bytes(16));

        foreach ($list as $c) {
            $productFromList = $c['product'];
            $productFromListConfig = $this->getProductService()->prepareCartProductConfig($productFromList, $c['config']);
            // One add-to-cart action equals one order family. Stamped after
            // prepareCartProductConfig() so the client-input filter can
            // neither strip it nor be bypassed with a forged value.
            $productFromListConfig[self::CART_FAMILY_KEY] = $familyToken;
            $this->addProduct($cart, $productFromList, $productFromListConfig);
        }

        $this->di['logger']->info('Added "{product_title}" to shopping cart', ['product_title' => $this->getProductTitle($product)]);

        $this->di['events_manager']->fire(['event' => 'onAfterProductAddedToCart', 'params' => $event_params]);

        return true;
    }

    public function isStockAvailable(Product|int $product, $qty)
    {
        return $this->getProductService()->isStockAvailable($product, $qty);
    }

    public function isRecurrentPricing(Product $model): bool
    {
        return $this->getProductService()->isRecurrentProductPricing($model);
    }

    public function isPeriodEnabledForProduct(Product $model, $period)
    {
        return $this->getProductService()->isProductPeriodEnabled($model, (string) $period);
    }

    protected function addProduct(Cart $cart, Product $product, array $data): bool
    {
        $item = new CartProduct();
        $item->setCart($cart);
        $item->setProductId($this->getProductId($product));
        $item->setConfig(json_encode($data));
        $this->di['em']->persist($item);
        $this->di['em']->flush();

        return true;
    }

    /**
     * Resolve the order group_id for one cart item at checkout.
     *
     * Items stamped with the same family token share one group_id
     * ("<cart_id>_<n>"). Every non-addon order in the family is a master -
     * including a bundled-domain sibling, which is an independently managed
     * service - while only genuine addon products nest under one. Items
     * from carts built before family stamping carry no token: standalone
     * items each start their own family, while an addon row rejoins the most
     * recent family of its parent product when one exists. Anything unmatched
     * gets its own family rather than joining an unrelated one, so a paid
     * item can never be hidden as another family's addon.
     */
    private function resolveFamilyGroupId(
        Cart $cart,
        array $item,
        mixed $familyToken,
        array &$familyGroupIds,
        array &$lastGroupIdByProductId,
        int &$familyIndex,
    ): string {
        if (is_string($familyToken) && $familyToken !== '') {
            return $familyGroupIds[$familyToken] ??= $this->nextFamilyGroupId($cart, $familyIndex);
        }

        $parentProductId = isset($item['parent_id']) ? (int) $item['parent_id'] : null;
        if ($parentProductId !== null && isset($lastGroupIdByProductId[$parentProductId])) {
            return $lastGroupIdByProductId[$parentProductId];
        }

        return $this->nextFamilyGroupId($cart, $familyIndex);
    }

    private function nextFamilyGroupId(Cart $cart, int &$familyIndex): string
    {
        ++$familyIndex;

        return $cart->getId() . '_' . $familyIndex;
    }

    protected function getReservedQuantityInCart(Cart $cart, int $productId): int
    {
        $reservedQty = 0;
        foreach ($this->getCartProducts($cart) as $cartProduct) {
            if ((int) $cartProduct->getProductId() !== $productId) {
                continue;
            }

            $config = $this->getItemConfig($cartProduct);
            $reservedQty += $this->getRequestedQuantity($config);
        }

        return $reservedQty;
    }

    protected function getRequestedQuantity(array $config): int
    {
        return max(1, (int) ($config['quantity'] ?? 1));
    }

    /**
     * Extract a normalized "sld+tld" domain string from a config array.
     * Handles register_*, transfer_*, and free subdomain key pairs.
     * Returns null when the config does not describe a domain.
     */
    private function extractDomainFromConfig(array $config, array $parentConfig = []): ?string
    {
        $sld = $config['register_sld'] ?? $config['transfer_sld'] ?? null;
        $tld = $config['register_tld'] ?? $config['transfer_tld'] ?? null;
        if ($sld !== null && $tld !== null) {
            return strtolower($sld . $tld);
        }

        if (($config['action'] ?? null) === 'subdomain' && isset($config['subdomain_sld'])) {
            $baseDomain = $config['subdomain_base_domain'] ?? $parentConfig['subdomain_base_domain'] ?? null;
            if ($baseDomain !== null) {
                return strtolower($config['subdomain_sld'] . '.' . trim((string) $baseDomain, '.'));
            }
        }

        return null;
    }

    public function removeProduct(Cart $cart, $id, $removeAddons = true): bool
    {
        $cartProduct = $this->findCartProduct($cart, (int) $id);
        if (!$cartProduct instanceof CartProduct) {
            throw new \FOSSBilling\Exception('Product not found');
        }

        if ($removeAddons) {
            $config_main = json_decode($cartProduct->getConfig() ?? '', true);
            $domain_name = $config_main['domain_name'] ?? '';
            $allCartProducts = $this->findCartProducts($cart);
            foreach ($allCartProducts as $cProduct) {
                $config = json_decode($cProduct->getConfig() ?? '', true);
                if (isset($config['parent_id']) && $config['parent_id'] == $cartProduct->getProductId()) {
                    $domain_name_addon = $config['domain_name'] ?? '';
                    if ($domain_name && $domain_name != $domain_name_addon) {
                        continue;
                    }
                    $this->di['em']->remove($cProduct);
                    $this->di['logger']->info('Removed product addon from shopping cart');
                }
            }
        }

        $this->di['em']->remove($cartProduct);
        $this->di['em']->flush();

        $this->di['logger']->info('Removed product from shopping cart');

        return true;
    }

    public function changeCartCurrency(Cart $cart, Currency $currency): bool
    {
        $cart->setCurrencyId($currency->getId());
        $this->persistCart($cart);

        $this->di['logger']->info('Changed shopping cart #{cart_id} currency to {currency_code}', ['cart_id' => $cart->getId(), 'currency_code' => $currency->getCode()]);

        return true;
    }

    public function resetCart(Cart $cart): bool
    {
        $cartProducts = $this->findCartProducts($cart);
        foreach ($cartProducts as $cartProduct) {
            $this->di['em']->remove($cartProduct);
        }
        $cart->setPromoId(null);
        $cart->setUpdatedAt(new \DateTime());
        $this->persistCart($cart);

        return true;
    }

    public function removePromo(Cart $cart): bool
    {
        $cart->setPromoId(null);
        $cart->setUpdatedAt(new \DateTime());
        $this->persistCart($cart);

        $this->di['logger']->info('Removed promo code from shopping cart #{cart_id}', ['cart_id' => $cart->getId()]);

        return true;
    }

    public function applyPromo(Cart $cart, Promo $promo): bool
    {
        $promoId = $promo->getId();
        $promoCode = $promo->getCode();

        if ($cart->getPromoId() == $promoId) {
            return true;
        }

        if ($this->isEmptyCart($cart)) {
            throw new \FOSSBilling\InformationException('Add products to your cart before applying promo code');
        }

        $cart->setPromoId($promoId);
        $this->persistCart($cart);

        $this->di['logger']->info('Applied promo code {promo_code} to shopping cart', ['promo_code' => $promoCode]);

        return true;
    }

    protected function isEmptyCart(Cart $cart): bool
    {
        $cartProducts = $this->findCartProducts($cart);

        return \FOSSBilling\Tools::safeCount($cartProducts) == 0;
    }

    public function rm(Cart $cart): bool
    {
        $cartProducts = $this->findCartProducts($cart);

        foreach ($cartProducts as $cartProduct) {
            $this->di['em']->remove($cartProduct);
        }

        $this->di['em']->remove($cart);
        $this->di['em']->flush();

        return true;
    }

    public function toApiArray(Cart $model, $deep = false, $identity = null): array
    {
        $products = $this->getCartProducts($model);

        $currencyService = $this->di['mod_service']('currency');
        /** @var \Box\Mod\Currency\Repository\CurrencyRepository $currencyRepository */
        $currencyRepository = $currencyService->getCurrencyRepository();
        $currency = $currencyRepository->find($model->getCurrencyId());
        if (!$currency instanceof Currency) {
            $currency = $currencyRepository->findDefault();
        }

        if (!$currency instanceof Currency) {
            throw new \FOSSBilling\Exception('Currency not found and no default currency is configured');
        }

        // Manual promo codes always win; automatic promos only resolve when no
        // code was entered, so clients are never surprised by stacked savings.
        $effective = $this->getEffectiveCartPromos($model);
        $promos = $effective['promos'];

        $items = [];
        $total = 0;
        $cart_discount = 0;
        $items_discount = 0;
        foreach ($products as $product) {
            $p = $this->cartProductToApiArray($product, $model, $products, $promos);
            $total += $p['total'] + $p['setup_price'];
            $items_discount += $p['discount'];
            $items[] = $p;
        }

        $promoId = $model->getPromoId();
        if ($promoId) {
            $promo = $this->getProductService()->findPromoById($promoId);
            $promocode = $promo->getCode();
        } else {
            $promocode = null;
        }

        $autoPromos = [];
        if ($effective['source'] === 'auto') {
            $promoTotals = [];
            foreach ($products as $product) {
                foreach ($this->getItemPromoDiscountShares($product, $promos, $model, $products) as $promoId => $share) {
                    $promoTotals[$promoId] = ($promoTotals[$promoId] ?? 0.0) + $share;
                }
            }

            foreach ($promos as $promo) {
                $autoPromos[] = [
                    'code' => $promo->getCode(),
                    'title' => $this->getProductService()->getPromoDiscountTitle($promo, $currency->getCode()),
                    'discount' => $promoTotals[(int) $promo->getId()] ?? 0.0,
                ];
            }
        }

        return [
            'promocode' => $promocode,
            'promo_source' => $effective['source'],
            'auto_promos' => $autoPromos,
            'discount' => $items_discount,
            'subtotal' => $total,
            'total' => $total - $items_discount,
            'items' => $items,
            'currency' => $currency->toApiArray(),
            'subscribable' => $this->getSubscriptionPeriodFromItems($items) !== null,
        ];
    }

    /**
     * Promos in effect for a cart: the manual code when one is set, otherwise
     * the eligible automatic promos for the (logged-in) client.
     *
     * @return array{source: 'manual'|'auto'|null, promos: list<Promo>}
     */
    public function getEffectiveCartPromos(Cart $cart, ?Client $client = null, ?array $cartProducts = null): array
    {
        $promoId = $cart->getPromoId();
        if ($promoId) {
            return [
                'source' => 'manual',
                'promos' => [$this->getProductService()->findPromoById((int) $promoId)],
            ];
        }

        $client ??= $this->getLoggedInClientOrNull();
        if (!$client instanceof Client) {
            return ['source' => null, 'promos' => []];
        }

        $lines = $this->getCartPromoLines($cart, $cartProducts);
        if ($lines === []) {
            return ['source' => null, 'promos' => []];
        }

        try {
            $promos = $this->getProductService()->resolveAutoPromosForLines($client, $lines);
        } catch (\Throwable $e) {
            $this->di['logger']->warning('Automatic promo resolution failed: {exception}', ['exception' => $e]);

            return ['source' => null, 'promos' => []];
        }

        return [
            'source' => $promos === [] ? null : 'auto',
            'promos' => $promos,
        ];
    }

    /**
     * @return list<array{product: Product, config: array}>
     */
    private function getCartPromoLines(Cart $cart, ?array $cartProducts = null): array
    {
        $lines = [];
        foreach ($cartProducts ?? $this->getCartProducts($cart) as $cartProduct) {
            try {
                $product = $this->getProductService()->findProductById((int) $cartProduct->getProductId());
            } catch (\Throwable) {
                continue;
            }

            $lines[] = ['product' => $product, 'config' => $this->getItemConfig($cartProduct)];
        }

        return $lines;
    }

    private function getLoggedInClientOrNull(): ?Client
    {
        try {
            $client = $this->di['loggedin_client'];
        } catch (\Exception) {
            return null;
        }

        return $client instanceof Client ? $client : null;
    }

    private function getSubscriptionPeriodFromItems(array $items): ?string
    {
        $subscriptionPeriod = null;

        foreach ($items as $item) {
            $netSetupPrice = (float) ($item['setup_price'] ?? 0) - (float) ($item['discount_setup'] ?? 0);
            if ($netSetupPrice > 0) {
                return null;
            }

            if ((float) ($item['total'] ?? 0) <= 0) {
                continue;
            }

            $period = $item['period'] ?? null;
            if (empty($period)) {
                return null;
            }

            if ($subscriptionPeriod === null) {
                $subscriptionPeriod = $period;

                continue;
            }

            if ($subscriptionPeriod !== $period) {
                return null;
            }
        }

        return $subscriptionPeriod;
    }

    public function isClientAbleToUsePromo(Client $client, Promo $promo)
    {
        return $this->getProductService()->canClientUsePromo($client, $promo);
    }

    public function promoCanBeApplied(Promo $promo): bool
    {
        return $this->getProductService()->promoCanBeApplied($promo);
    }

    public function isPromoAvailableForClientGroup(Promo $promo)
    {
        return $this->getProductService()->isPromoAvailableForClientGroup($promo);
    }

    protected function clientHadUsedPromo(Client $client, Promo $promo): bool
    {
        return $this->getProductService()->clientHasActivePromoApplication($client, $promo);
    }

    /**
     * Automatic promos eligible right now, minus any that fail the fail-fast
     * checkout guards (changed limits, group moves, prior use).
     *
     * @return list<Promo>
     */
    private function filterUsableAutoPromos(Cart $cart, Client $client, ?array $cartProducts = null): array
    {
        $effective = $this->getEffectiveCartPromos($cart, $client, $cartProducts);
        if ($effective['source'] !== 'auto') {
            return [];
        }

        $usable = [];
        foreach ($effective['promos'] as $promo) {
            if (!$this->isClientAbleToUsePromo($client, $promo)) {
                continue;
            }

            if (!$this->getProductService()->isPromoAvailableForClientGroup($promo, $client)) {
                continue;
            }

            $usable[] = $promo;
        }

        return $usable;
    }

    /**
     * In-transaction re-check of the once-per-client limit. The fail-fast check in
     * checkoutCart() runs before the transaction opens, so concurrent checkouts can all pass
     * it; the client-row mutex serializes them here instead. Throws the same exception as a
     * normal reuse.
     */
    private function assertClientAbleToUsePromoForUpdate(Client $client, Promo $promo): void
    {
        if ($this->getProductService()->clientHasActivePromoApplicationForUpdate($client, $promo)) {
            throw new \FOSSBilling\InformationException('You have already used this promo code. Please remove the promo code and checkout again.', null, 9874);
        }
    }

    /**
     * @param list<Promo> $promos
     */
    private function assertClientAbleToUsePromosForUpdate(Client $client, array $promos): void
    {
        foreach ($promos as $promo) {
            $this->assertClientAbleToUsePromoForUpdate($client, $promo);
        }
    }

    public function getCartProducts(Cart $model): array
    {
        return $this->findCartProducts($model);
    }

    public function checkoutCart(Cart $cart, Client $client, $gateway_id = null): array
    {
        $promoId = $cart->getPromoId();
        if ($promoId) {
            $promo = $this->getProductService()->findPromoById($promoId);
            if (!$this->isClientAbleToUsePromo($client, $promo)) {
                throw new \FOSSBilling\InformationException('You have already used this promo code. Please remove the promo code and checkout again.', null, 9874);
            }

            if (!$this->isPromoAvailableForClientGroup($promo)) {
                throw new \FOSSBilling\InformationException('Promo code cannot be applied to your account');
            }
        }

        $this->di['events_manager']->fire(
            [
                'event' => 'onBeforeClientCheckout',
                'params' => [
                    'ip' => $this->di['request']->getClientIp(),
                    'client_id' => (int) $client->getId(),
                    'cart_id' => $cart->getId(),
                ],
            ]
        );

        [$order, $invoice, $orders] = $this->createFromCart($client, $gateway_id);

        $this->rm($cart);

        $this->di['logger']->info('Checked out shopping cart');

        $this->di['events_manager']->fire(
            [
                'event' => 'onAfterClientOrderCreate',
                'params' => [
                    'ip' => $this->di['request']->getClientIp(),
                    'client_id' => (int) $client->getId(),
                    'id' => $order->getId(),
                ],
            ]
        );

        $result = [
            'gateway_id' => $gateway_id,
            'invoice_hash' => null,
            'order_id' => $order->getId(),
            'orders' => $orders,
        ];

        // invoice may not be created if total is 0
        $isInvoiceUnpaid = $invoice instanceof Invoice
            && $invoice->getStatus() === Invoice::STATUS_UNPAID;

        if ($isInvoiceUnpaid) {
            $result['invoice_hash'] = $invoice->getHash();
        }

        return $result;
    }

    public function createFromCart(Client $client, $gateway_id = null): array
    {
        $cart = $this->getSessionCart();
        $ca = $this->toApiArray($cart);
        if (\FOSSBilling\Tools::safeCount($ca['items']) == 0) {
            throw new \FOSSBilling\InformationException('Cannot checkout an empty cart');
        }

        $currencyService = $this->di['mod_service']('currency');
        /** @var \Box\Mod\Currency\Repository\CurrencyRepository $currencyRepository */
        $currencyRepository = $currencyService->getCurrencyRepository();
        $currency = $currencyRepository->find($cart->getCurrencyId());
        if (!$currency instanceof Currency) {
            $currency = $currencyRepository->findDefault();
            if (!$currency instanceof Currency) {
                throw new \FOSSBilling\Exception('Default currency not found.');
            }
        }
        $currencyCode = $currency->getCode();

        $clientService = $this->di['mod_service']('client');
        $taxed = $clientService->isClientTaxable($client);
        $promoId = $cart->getPromoId();
        $promoProductService = $promoId ? $this->getProductService() : null;
        $promo = $promoId ? $promoProductService?->findPromoById($promoId) : null;

        $reservedPromoUsage = [];
        $discountsByOrderAndPromo = [];
        $stockReservedOrders = [];

        if (!$client->getCurrency()) {
            $client->setCurrency($currencyCode);
            $this->di['em']->persist($client);
        }

        try {
            return $this->di['em']->wrapInTransaction(function () use ($ca, $cart, $client, $currency, $currencyCode, $gateway_id, $taxed, $promo, $promoProductService, &$reservedPromoUsage, &$discountsByOrderAndPromo, &$stockReservedOrders) {
                $effectivePromos = [];
                if ($promo instanceof Promo) {
                    $this->assertClientAbleToUsePromoForUpdate($client, $promo);
                    $effectivePromos = [$promo];
                }

                $cartProducts = $this->getCartProducts($cart);

                if ($effectivePromos === []) {
                    // A manual code suppresses automatic promos; resolve here
                    // so totals, reservations, and redemptions share one set.
                    $effectivePromos = $this->filterUsableAutoPromos($cart, $client, $cartProducts);
                    if ($effectivePromos !== []) {
                        $promoProductService ??= $this->getProductService();
                        $this->assertClientAbleToUsePromosForUpdate($client, $effectivePromos);
                    }
                }

                if ($client->getCurrency() != $currencyCode) {
                    throw new \FOSSBilling\InformationException('Selected currency :selected does not match your profile currency :code. Please change cart currency to continue.', [':selected' => $currencyCode, ':code' => $client->getCurrency()]);
                }

                $orders = [];
                $invoice_items = [];
                $invoiceModel = null;
                $master_order = null;
                $requestedProductQuantities = [];
                $familyGroupIds = [];
                $lastGroupIdByProductId = [];
                $familyIndex = 0;

                foreach ($cartProducts as $p) {
                    $item = $this->cartProductToApiArray($p, $cart, $cartProducts, $effectivePromos);
                    // Family bookkeeping is cart-transient: consume it for
                    // grouping, then keep it out of the stored order config.
                    $familyToken = $item[self::CART_FAMILY_KEY] ?? null;
                    unset($item[self::CART_FAMILY_KEY]);

                    $product = $this->getProductService()->findProductById((int) $item['product_id']);
                    if ($product->getStatus() !== 'enabled') {
                        throw new \FOSSBilling\InformationException('Unable to complete order. One or more of the selected products are invalid.');
                    }

                    $requestedQty = $this->getRequestedQuantity($item);
                    $productId = (int) $product->getId();
                    $requestedProductQuantities[$productId] = ($requestedProductQuantities[$productId] ?? 0) + $requestedQty;
                    if (!$this->isStockAvailable($product, $requestedProductQuantities[$productId])) {
                        throw new \FOSSBilling\InformationException('Unable to complete order. One or more selected products are out of stock.');
                    }

                    /*
                     * Convert the domain name to lowercase letters.
                     * Using a capital letter in a domain name still points to the same name, so this isn't going to break anything
                     * It will, however, avoid instances like this when a domain name is entered with a capital letter:
                     * https://github.com/boxbilling/boxbilling/discussions/1022#discussioncomment-1311819
                     */
                    if ($item['type'] === 'domain' || $item['type'] === 'hosting') {
                        $item['register_sld'] = (isset($item['register_sld'])) ? strtolower((string) $item['register_sld']) : null;
                        $item['transfer_sld'] = (isset($item['transfer_sld'])) ? strtolower((string) $item['transfer_sld']) : null;
                        $item['sld'] = (isset($item['sld'])) ? strtolower((string) $item['sld']) : null;
                        $item['domain']['owndomain_sld'] = (isset($item['domain']['owndomain_sld'])) ? strtolower((string) $item['domain']['owndomain_sld']) : null;
                        $item['domain']['register_sld'] = (isset($item['domain']['register_sld'])) ? strtolower((string) $item['domain']['register_sld']) : null;
                        $item['domain']['transfer_sld'] = (isset($item['domain']['transfer_sld'])) ? strtolower((string) $item['domain']['transfer_sld']) : null;

                        // Domain TLD must begin with a period - add if not present for owndomain.
                        $item['domain']['owndomain_tld'] = (isset($item['domain']['owndomain_tld'])) ? (str_contains((string) $item['domain']['owndomain_tld'], '.') ? $item['domain']['owndomain_tld'] : '.' . $item['domain']['owndomain_tld']) : null;
                    }

                    $order = new Order();
                    $order->setClientId((int) $client->getId());
                    // Primary promo for reporting/renewal lookups; every promo
                    // gets its own redemption row below.
                    $order->setPromoId($effectivePromos === [] ? null : (int) $effectivePromos[0]->getId());
                    $order->setProductId($item['product_id']);
                    $order->setFormId($item['form_id']);

                    // group_master marks "is not an addon", not "was first in
                    // the cart: one family shares one group_id, every
                    // non-addon order in it is a master, only genuine addons
                    // nest under one.
                    $groupId = $this->resolveFamilyGroupId($cart, $item, $familyToken, $familyGroupIds, $lastGroupIdByProductId, $familyIndex);
                    $order->setGroupId($groupId);
                    $order->setGroupMaster(!$product->isAddon());
                    $lastGroupIdByProductId[(int) $product->getId()] = $groupId;
                    $order->setInvoiceOption('issue-invoice');
                    $order->setTitle($item['title']);
                    $order->setCurrency($currencyCode);
                    $order->setServiceType($item['type']);
                    $order->setUnit($item['unit'] ?? null);
                    $order->setPeriod($item['period'] ?? null);
                    $order->setQuantity($item['quantity'] ?? null);
                    $order->setPrice($item['price'] * $currency->getConversionRate());
                    $order->setDiscount($item['discount_price'] * $currency->getConversionRate());
                    $order->setStatus(Order::STATUS_PENDING_SETUP);
                    $order->setNotes($item['notes'] ?? null);
                    $order->setConfig(json_encode($item));
                    $this->di['em']->persist($order);
                    $this->di['em']->flush();

                    $orders[] = $order;

                    // Reserve stock at order creation time instead of leaving it unclaimed until activation.
                    $this->getProductService()->reserveStockForOrder($order);
                    $stockReservedOrders[] = $order;

                    // Reserve promo capacity at order creation time.
                    if ($effectivePromos !== []) {
                        $promoProductService->reservePromosForOrder($effectivePromos, $order);
                        $orderId = (int) $order->getId();

                        // Split the capped item discount across promos so each
                        // redemption row carries its own share for renewals.
                        $shares = $this->getItemPromoDiscountShares($p, $effectivePromos, $cart, $cartProducts);
                        foreach ($effectivePromos as $effectivePromo) {
                            $effectivePromoId = (int) $effectivePromo->getId();
                            $share = $shares[$effectivePromoId] ?? 0.0;
                            $discountsByOrderAndPromo[$orderId][$effectivePromoId] = $share * $currency->getConversionRate();

                            $reservedPromoUsage[$effectivePromoId] ??= ['promo' => $effectivePromo, 'orderIds' => [], 'count' => 0];
                            $reservedPromoUsage[$effectivePromoId]['orderIds'][] = $orderId;
                            ++$reservedPromoUsage[$effectivePromoId]['count'];
                        }
                    }

                    $orderService = $this->di['mod_service']('order');
                    $orderService->saveStatusChange($order, 'Order Created');

                    $invoice_items[] = [
                        'title' => $order->getTitle(),
                        'price' => $order->getPrice(),
                        'quantity' => $order->getQuantity(),
                        'unit' => $order->getUnit(),
                        'period' => $order->getPeriod(),
                        'taxed' => $taxed,
                        'type' => \Box\Mod\Invoice\Entity\InvoiceItem::TYPE_ORDER,
                        'rel_id' => $order->getId(),
                        'task' => \Box\Mod\Invoice\Entity\InvoiceItem::TASK_ACTIVATE,
                    ];

                    if ((float) $order->getDiscount() > 0) {
                        $invoice_items[] = [
                            'title' => __trans('Discount: :product', [':product' => $order->getTitle()]),
                            'price' => (float) $order->getDiscount() * -1,
                            'quantity' => 1,
                            'unit' => 'discount',
                            'rel_id' => $order->getId(),
                            'taxed' => $taxed,
                        ];
                    }

                    if ($item['setup_price'] > 0) {
                        $setup_price = ($item['setup_price'] * $currency->getConversionRate()) - ($item['discount_setup'] * $currency->getConversionRate());
                        $invoice_items[] = [
                            'title' => __trans(':product setup', [':product' => $order->getTitle()]),
                            'price' => $setup_price,
                            'quantity' => 1,
                            'unit' => 'service',
                            'taxed' => $taxed,
                        ];
                    }

                    $master_order ??= $order;
                }

                if ($ca['total'] > 0) { // crete invoice if order total > 0
                    $invoiceService = $this->di['mod_service']('Invoice');
                    $invoiceModel = $invoiceService->prepareInvoice($client, ['client_id' => (int) $client->getId(), 'items' => $invoice_items, 'gateway_id' => $gateway_id]);

                    $clientBalanceService = $this->di['mod_service']('Client', 'Balance');
                    $balanceAmount = $clientBalanceService->getClientBalance($client);
                    $useCredits = $balanceAmount >= $ca['total'];

                    $invoiceService->approveInvoice($invoiceModel, ['id' => $invoiceModel->getId(), 'use_credits' => $useCredits]);

                    $isUnpaid = $invoiceModel instanceof Invoice
                        && $invoiceModel->getStatus() === Invoice::STATUS_UNPAID;

                    if ($isUnpaid) {
                        $invoiceId = $invoiceModel->getId();
                        foreach ($orders as $order) {
                            $order->setUnpaidInvoiceId($invoiceId);
                            $this->di['em']->persist($order);
                        }
                        $this->di['em']->flush();
                    }
                }

                if ($effectivePromos !== []) {
                    $redemptionStatus = $invoiceModel instanceof Invoice
                        && $invoiceModel->getStatus() === Invoice::STATUS_UNPAID
                        ? \Box\Mod\Product\Entity\PromoRedemption::STATUS_RESERVED
                        : \Box\Mod\Product\Entity\PromoRedemption::STATUS_COMMITTED;
                    $checkoutInvoice = $invoiceModel instanceof Invoice ? $invoiceModel : null;

                    $promoProductService->createCheckoutPromoRedemptionsForPromos($effectivePromos, $client, $orders, $checkoutInvoice, $redemptionStatus, $discountsByOrderAndPromo);
                }

                // Activate orders after the checkout state is durably persisted.
                $orderService = $this->di['mod_service']('Order');
                $ids = [];
                foreach ($orders as $order) {
                    $ids[] = $order->getId();
                    $oa = $orderService->toApiArray($order, false, $client);
                    $product = $this->getProductService()->findProductById((int) $oa['product_id']);

                    try {
                        if ($product->getSetup() == \Box\Mod\Product\Service::SETUP_AFTER_ORDER) {
                            $orderService->activateOrder($order);
                        }

                        if ($ca['total'] <= 0 && $product->getSetup() == \Box\Mod\Product\Service::SETUP_AFTER_PAYMENT && $oa['total'] - $oa['discount'] <= 0) {
                            $orderService->activateOrder($order);
                        }

                        $isPaid = $invoiceModel instanceof Invoice
                            && $invoiceModel->getStatus() === Invoice::STATUS_PAID;

                        if ($ca['total'] > 0 && $product->getSetup() == \Box\Mod\Product\Service::SETUP_AFTER_PAYMENT && $isPaid) {
                            $orderService->activateOrder($order);
                        }
                    } catch (\Throwable $e) {
                        // An escaped failure here would roll back the whole
                        // wrapInTransaction() below, including every order
                        // already created for this cart - not just this one.
                        $this->di['logger']->error('Order activation failed after checkout: {exception}', ['exception' => $e]);
                        $notes = "Order could not be activated after checkout due to error: {$e->getMessage()}.";
                        $orderService->orderStatusAdd($order, Order::STATUS_FAILED_SETUP, $notes);
                    }
                }

                return [
                    $master_order,
                    $invoiceModel ?? null,
                    $ids,
                ];
            });
        } catch (\Throwable $e) {
            foreach ($reservedPromoUsage as $usage) {
                try {
                    $this->getProductService()->compensateCheckoutPromoFailure($usage['promo'], $usage['orderIds'], $usage['count']);
                } catch (\Throwable $compensationError) {
                    $this->di['logger']->error('Failed to compensate promo checkout failure', [
                        'exception' => $compensationError,
                        'promo_id' => $usage['promo']->getId(),
                    ]);
                }
            }

            foreach ($stockReservedOrders as $stockReservedOrder) {
                try {
                    $this->getProductService()->releaseReservedStockForOrder($stockReservedOrder, 'checkout_failed');
                } catch (\Throwable $compensationError) {
                    $this->di['logger']->error('Failed to compensate stock checkout failure', [
                        'exception' => $compensationError,
                        'order_id' => $stockReservedOrder->getId(),
                    ]);
                }
            }

            throw $e;
        }
    }

    public function usePromo(Promo $promo): void
    {
        $this->getProductService()->usePromo($promo);
    }

    public function findActivePromoByCode($code): ?Promo
    {
        return $this->getProductService()->findActivePromoByCode($code);
    }

    /**
     * Function checks if product is related to other products in cart
     * If relation exists then count discount for this.
     */
    protected function getRelatedItemsDiscount(
        Cart $cart,
        CartProduct $model,
        ?array $cartProducts = null,
    ): float {
        $config = $this->getItemConfig($model);

        $list = [];
        $products = $cartProducts ?? $this->getCartProducts($cart);
        foreach ($products as $p) {
            $item = [
                'id' => $p->getId(),
                'cart_id' => $p->getCart()?->getId(),
                'product_id' => $p->getProductId(),
                'config' => $this->getItemConfig($p),
            ];
            $list[] = $item;
        }

        return $this->getProductService()->getRelatedProductDiscountByProductId((int) $model->getProductId(), $list, $config);
    }

    protected function getItemPromoDiscount(CartProduct $model, Promo $promo)
    {
        $config = $this->getItemConfig($model);

        return $this->getProductService()->getProductDiscountById((int) $model->getProductId(), $promo, $config);
    }

    public function getItemConfig(CartProduct $model): array
    {
        return json_decode($model->getConfig() ?? '', true) ?? [];
    }

    private function getProductService(): \Box\Mod\Product\Service
    {
        return $this->di['mod_service']('Product');
    }

    private function getProductId(Product $product): int
    {
        return (int) $product->getId();
    }

    private function getProductTitle(Product $product): string
    {
        return (string) $product->getTitle();
    }

    public function cartProductToApiArray(
        CartProduct $model,
        ?Cart $cart = null,
        ?array $cartProducts = null,
        ?array $promosOverride = null,
    ): array {
        $productView = $this->getProductService()->getCartProductViewData($model);
        $config = $productView['config'];
        $setup = $productView['setup_price'];
        $price = $productView['price'];
        $qty = $productView['quantity'];

        [$discount_price, $discount_setup] = $this->getProductDiscount($model, $setup, $cart, $cartProducts, $promosOverride);

        $discount_total = $discount_price + $discount_setup;

        $subtotal = ($price * $qty);
        if (abs($discount_total) > ($subtotal + $setup)) {
            $discount_total = $subtotal;
            $discount_price = $subtotal;
        }

        return array_merge($config, [
            'id' => $model->getId(),
            'product_id' => $productView['product_id'],
            'form_id' => $productView['form_id'],
            'title' => $productView['title'],
            'type' => $productView['type'],
            'quantity' => $qty,
            'unit' => $productView['unit'],
            'price' => $price,
            'setup_price' => $setup,
            'discount' => $discount_total,
            'discount_price' => $discount_price,
            'discount_setup' => $discount_setup,
            'total' => $subtotal,
        ]);
    }

    public function getProductDiscount(
        CartProduct $cartProduct,
        $setup,
        ?Cart $cart = null,
        ?array $cartProducts = null,
        ?array $promosOverride = null,
    ): array {
        $cart ??= $cartProduct->getCart();
        if (!$cart instanceof Cart) {
            throw new \FOSSBilling\Exception('Cart not found');
        }

        $promos = $promosOverride ?? $this->getCartManualPromo($cart);
        if ($promos === []) {
            return [$this->getRelatedItemsDiscount($cart, $cartProduct, $cartProducts), 0];
        }

        $shares = $this->getItemPromoDiscountShares($cartProduct, $promos, $cart, $cartProducts);
        $discount_price = array_sum($shares);

        $discount_setup = 0;
        foreach ($promos as $promo) {
            $promoApplies = $this->getProductService()->isPromoApplicableToProductById(
                (int) $cartProduct->getProductId(),
                $promo,
                $this->getItemConfig($cartProduct),
            );

            if ($promo->isFreeSetup() && $promoApplies) {
                $discount_setup = $setup;

                break;
            }
        }

        return [$discount_price, $discount_setup];
    }

    private function getCartManualPromo(Cart $cart): array
    {
        if (!$cart->getPromoId()) {
            return [];
        }

        return [$this->getProductService()->findPromoById((int) $cart->getPromoId())];
    }

    /**
     * Split one cart item's price discount across several promos.
     *
     * Shares add up to the item's capped discount_price, so per-promo amounts
     * stay consistent with what is actually charged and recorded.
     *
     * @param list<Promo> $promos
     *
     * @return array<int, float> promo id => discount share (base currency)
     */
    public function getItemPromoDiscountShares(
        CartProduct $cartProduct,
        array $promos,
        ?Cart $cart = null,
        ?array $cartProducts = null,
    ): array {
        $cart ??= $cartProduct->getCart();
        if (!$cart instanceof Cart) {
            throw new \FOSSBilling\Exception('Cart not found');
        }

        $raw = [];
        foreach ($promos as $promo) {
            $raw[(int) $promo->getId()] = (float) $this->getItemPromoDiscount($cartProduct, $promo);
        }

        $rawTotal = array_sum($raw);
        if ($rawTotal <= 0) {
            return array_map(static fn (): float => 0.0, $raw);
        }

        $productView = $this->getProductService()->getCartProductViewData($cartProduct);
        $subtotal = (float) $productView['price'] * (float) $productView['quantity'];
        $cappedTotal = min($rawTotal, $subtotal);

        $shares = [];
        foreach ($raw as $promoId => $amount) {
            $shares[$promoId] = round($amount / $rawTotal * $cappedTotal, 2);
        }

        // Rounding can leave the shares a cent off the capped total; fold the
        // remainder into the largest share so they always reconcile.
        $drift = round($cappedTotal - array_sum($shares), 2);
        if ($drift != 0.0) {
            $largest = array_keys($shares, max($shares))[0];
            $shares[$largest] = round($shares[$largest] + $drift, 2);
        }

        return $shares;
    }
}
