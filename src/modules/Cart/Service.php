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

use Box\Mod\Currency\Entity\Currency;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Product\Entity\Promo;
use FOSSBilling\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
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
        $cart->session_id = $this->di['session']->getId();
        $this->di['db']->store($cart);

        return true;
    }

    /**
     * @return \Model_Cart
     */
    public function getSessionCart(?string $sessionID = null)
    {
        $sessionID ??= $this->di['session']->getId();
        $sqlBindings = [':session_id' => $sessionID];
        $cart = $this->di['db']->findOne('Cart', 'session_id = :session_id', $sqlBindings);

        if ($cart instanceof \Model_Cart) {
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

        $cart = $this->di['db']->dispense('Cart');
        $cart->session_id = $sessionID;
        $cart->currency_id = $currency->getId();
        $cart->created_at = date('Y-m-d H:i:s');
        $cart->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($cart);

        return $cart;
    }

    public function addItem(\Model_Cart $cart, Product $product, array $data): bool
    {
        $event_params = [...$data, 'cart_id' => $cart->id, 'product_id' => $this->getProductId($product)];
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
            $existingItems = $this->di['db']->find('CartProduct', 'cart_id = ?', [$cart->id]);
            foreach ($existingItems as $item) {
                $itemConfig = json_decode((string) $item->config, true);
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

        foreach ($list as $c) {
            $productFromList = $c['product'];
            $productFromListConfig = $this->getProductService()->prepareCartProductConfig($productFromList, $c['config']);
            $this->addProduct($cart, $productFromList, $productFromListConfig);
        }

        $this->di['logger']->info('Added "%s" to shopping cart', $this->getProductTitle($product));

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

    protected function addProduct(\Model_Cart $cart, Product $product, array $data): bool
    {
        $item = $this->di['db']->dispense('CartProduct');
        $item->cart_id = $cart->id;
        $item->product_id = $this->getProductId($product);
        $item->config = json_encode($data);
        $this->di['db']->store($item);

        return true;
    }

    protected function getReservedQuantityInCart(\Model_Cart $cart, int $productId): int
    {
        $reservedQty = 0;
        foreach ($this->getCartProducts($cart) as $cartProduct) {
            if ((int) $cartProduct->product_id !== $productId) {
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

    public function removeProduct(\Model_Cart $cart, $id, $removeAddons = true): bool
    {
        $bindings = [
            ':cart_id' => $cart->id,
            ':id' => $id,
        ];

        $cartProduct = $this->di['db']->findOne('CartProduct', 'id = :id AND cart_id = :cart_id', $bindings);
        if (!$cartProduct instanceof \Model_CartProduct) {
            throw new \FOSSBilling\Exception('Product not found');
        }

        if ($removeAddons) {
            $config_main = json_decode($cartProduct->config ?? '', true);
            $domain_name = $config_main['domain_name'] ?? '';
            $allCartProducts = $this->di['db']->find('CartProduct', 'cart_id = :cart_id', [':cart_id' => $cart->id]);
            foreach ((array) $allCartProducts as $cProduct) {
                $config = json_decode($cProduct->config ?? '', true);
                if (isset($config['parent_id']) && $config['parent_id'] == $cartProduct->product_id) {
                    $domain_name_addon = $config['domain_name'] ?? '';
                    if ($domain_name && $domain_name != $domain_name_addon) {
                        continue; // Delete addons only for the domain name
                    }
                    $this->di['db']->trash($cProduct);
                    $this->di['logger']->info('Removed product addon from shopping cart');
                }
            }
        }

        $this->di['db']->trash($cartProduct);

        $this->di['logger']->info('Removed product from shopping cart');

        return true;
    }

    public function changeCartCurrency(\Model_Cart $cart, Currency $currency): bool
    {
        $cart->currency_id = $currency->getId();
        $this->di['db']->store($cart);

        $this->di['logger']->info('Changed shopping cart #%s currency to %s', $cart->id, $currency->getCode());

        return true;
    }

    public function resetCart(\Model_Cart $cart): bool
    {
        $cartProducts = $this->di['db']->find('CartProduct', 'cart_id = :cart_id', [':cart_id' => $cart->id]);
        foreach ($cartProducts as $cartProduct) {
            $this->di['db']->trash($cartProduct);
        }
        $cart->promo_id = null;
        $cart->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($cart);

        return true;
    }

    public function removePromo(\Model_Cart $cart): bool
    {
        $cart->promo_id = null;
        $cart->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($cart);

        $this->di['logger']->info('Removed promo code from shopping cart #%s', $cart->id);

        return true;
    }

    public function applyPromo(\Model_Cart $cart, Promo $promo): bool
    {
        $promoId = $promo->getId();
        $promoCode = $promo->getCode();

        if ($cart->promo_id == $promoId) {
            return true;
        }

        if ($this->isEmptyCart($cart)) {
            throw new \FOSSBilling\InformationException('Add products to your cart before applying promo code');
        }

        $cart->promo_id = $promoId;
        $this->di['db']->store($cart);

        $this->di['logger']->info('Applied promo code %s to shopping cart', $promoCode);

        return true;
    }

    protected function isEmptyCart(\Model_Cart $cart): bool
    {
        $cartProducts = $this->di['db']->find('CartProduct', 'cart_id = :cart_id', [':cart_id' => $cart->id]);

        return \FOSSBilling\Tools::safeCount($cartProducts) == 0;
    }

    public function rm(\Model_Cart $cart): bool
    {
        $cartProducts = $this->di['db']->find('CartProduct', 'cart_id = :cart_id', [':cart_id' => $cart->id]);

        foreach ($cartProducts as $cartProduct) {
            $this->di['db']->trash($cartProduct);
        }

        $this->di['db']->trash($cart);

        return true;
    }

    public function toApiArray(\Model_Cart $model, $deep = false, $identity = null): array
    {
        $products = $this->getCartProducts($model);

        $currencyService = $this->di['mod_service']('currency');
        /** @var \Box\Mod\Currency\Repository\CurrencyRepository $currencyRepository */
        $currencyRepository = $currencyService->getCurrencyRepository();
        $currency = $currencyRepository->find($model->currency_id);
        if (!$currency instanceof Currency) {
            $currency = $currencyRepository->findDefault();
        }

        if (!$currency instanceof Currency) {
            throw new \FOSSBilling\Exception('Currency not found and no default currency is configured');
        }

        $items = [];
        $total = 0;
        $cart_discount = 0;
        $items_discount = 0;
        foreach ($products as $product) {
            $p = $this->cartProductToApiArray($product);
            $total += $p['total'] + $p['setup_price'];
            $items_discount += $p['discount'];
            $items[] = $p;
        }

        if ($model->promo_id) {
            $promo = $this->getProductService()->findPromoById((int) $model->promo_id);
            $promocode = $promo->getCode();
        } else {
            $promocode = null;
        }

        return [
            'promocode' => $promocode,
            'discount' => $items_discount,
            'subtotal' => $total,
            'total' => $total - $items_discount,
            'items' => $items,
            'currency' => $currency->toApiArray(),
        ];
    }

    public function isClientAbleToUsePromo(\Model_Client $client, Promo $promo)
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

    protected function clientHadUsedPromo(\Model_Client $client, Promo $promo): bool
    {
        return $this->getProductService()->clientHasActivePromoApplication($client, $promo);
    }

    public function getCartProducts(\Model_Cart $model)
    {
        return $this->di['db']->find('CartProduct', 'cart_id = :cart_id ORDER BY id ASC', [':cart_id' => $model->id]);
    }

    public function checkoutCart(\Model_Cart $cart, \Model_Client $client, $gateway_id = null): array
    {
        if ($cart->promo_id) {
            $promo = $this->getProductService()->findPromoById((int) $cart->promo_id);
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
                    'client_id' => $client->id,
                    'cart_id' => $cart->id,
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
                    'client_id' => $client->id,
                    'id' => $order->id,
                ],
            ]
        );

        $result = [
            'gateway_id' => $gateway_id,
            'invoice_hash' => null,
            'order_id' => $order->id,
            'orders' => $orders,
        ];

        // invoice may not be created if total is 0
        if ($invoice instanceof \Model_Invoice && $invoice->status == \Model_Invoice::STATUS_UNPAID) {
            $result['invoice_hash'] = $invoice->hash;
        }

        return $result;
    }

    public function createFromCart(\Model_Client $client, $gateway_id = null): array
    {
        $cart = $this->getSessionCart();
        $ca = $this->toApiArray($cart);
        if (\FOSSBilling\Tools::safeCount($ca['items']) == 0) {
            throw new \FOSSBilling\InformationException('Cannot checkout an empty cart');
        }

        $currencyService = $this->di['mod_service']('currency');
        /** @var \Box\Mod\Currency\Repository\CurrencyRepository $currencyRepository */
        $currencyRepository = $currencyService->getCurrencyRepository();
        $currency = $currencyRepository->find($cart->currency_id);
        if (!$currency instanceof Currency) {
            $currency = $currencyRepository->findDefault();
            if (!$currency instanceof Currency) {
                throw new \FOSSBilling\Exception('Default currency not found.');
            }
        }
        $currencyCode = $currency->getCode();

        $clientService = $this->di['mod_service']('client');
        $taxed = $clientService->isClientTaxable($client);
        $promoProductService = $cart->promo_id ? $this->getProductService() : null;
        $promo = $cart->promo_id ? $promoProductService?->findPromoById((int) $cart->promo_id) : null;

        $reservedOrderIds = [];
        $reservedCount = 0;

        try {
            return $this->di['db']->transaction(function () use ($ca, $cart, $client, $currency, $currencyCode, $gateway_id, $taxed, $promo, $promoProductService, &$reservedOrderIds, &$reservedCount) {
                // Set default client currency.
                if (!$client->currency) {
                    $client->currency = $currencyCode;
                    $this->di['db']->store($client);
                }

                if ($client->currency != $currencyCode) {
                    throw new \FOSSBilling\InformationException('Selected currency :selected does not match your profile currency :code. Please change cart currency to continue.', [':selected' => $currencyCode, ':code' => $client->currency]);
                }

                $orders = [];
                $invoice_items = [];
                $invoiceModel = null;
                $master_order = null;
                $requestedProductQuantities = [];
                $i = 0;

                foreach ($this->getCartProducts($cart) as $p) {
                    $item = $this->cartProductToApiArray($p);

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

                    $order = $this->di['db']->dispense('ClientOrder');
                    $order->client_id = $client->id;
                    $order->promo_id = $cart->promo_id;
                    $order->product_id = $item['product_id'];
                    $order->form_id = $item['form_id'];

                    $order->group_id = $cart->id;
                    $order->group_master = ($i == 0);
                    $order->invoice_option = 'issue-invoice';
                    $order->title = $item['title'];
                    $order->currency = $currencyCode;
                    $order->service_type = $item['type'];
                    $order->unit = $item['unit'] ?? null;
                    $order->period = $item['period'] ?? null;
                    $order->quantity = $item['quantity'] ?? null;
                    $order->price = $item['price'] * $currency->getConversionRate();
                    $order->discount = $item['discount_price'] * $currency->getConversionRate();
                    $order->status = \Model_ClientOrder::STATUS_PENDING_SETUP;
                    $order->notes = $item['notes'] ?? null;
                    $order->config = json_encode($item);
                    $order->created_at = date('Y-m-d H:i:s');
                    $order->updated_at = date('Y-m-d H:i:s');
                    $this->di['db']->store($order);

                    $orders[] = $order;

                    // Reserve promo capacity at order creation time.
                    if ($promo instanceof Promo && $promoProductService !== null) {
                        $promoProductService->reservePromoForOrder($promo, $order);
                        $reservedOrderIds[] = (int) $order->id;
                        ++$reservedCount;
                    }

                    $orderService = $this->di['mod_service']('order');
                    $orderService->saveStatusChange($order, 'Order Created');

                    $invoice_items[] = [
                        'title' => $order->title,
                        'price' => $order->price,
                        'quantity' => $order->quantity,
                        'unit' => $order->unit,
                        'period' => $order->period,
                        'taxed' => $taxed,
                        'type' => \Model_InvoiceItem::TYPE_ORDER,
                        'rel_id' => $order->id,
                        'task' => \Model_InvoiceItem::TASK_ACTIVATE,
                    ];

                    if ($order->discount > 0) {
                        $invoice_items[] = [
                            'title' => __trans('Discount: :product', [':product' => $order->title]),
                            'price' => $order->discount * -1,
                            'quantity' => 1,
                            'unit' => 'discount',
                            'rel_id' => $order->id,
                            'taxed' => $taxed,
                        ];
                    }

                    if ($item['setup_price'] > 0) {
                        $setup_price = ($item['setup_price'] * $currency->getConversionRate()) - ($item['discount_setup'] * $currency->getConversionRate());
                        $invoice_items[] = [
                            'title' => __trans(':product setup', [':product' => $order->title]),
                            'price' => $setup_price,
                            'quantity' => 1,
                            'unit' => 'service',
                            'taxed' => $taxed,
                        ];
                    }

                    if ($master_order === null) {
                        $master_order = $order;
                    }

                    ++$i;
                }

                if ($ca['total'] > 0) { // crete invoice if order total > 0
                    $invoiceService = $this->di['mod_service']('Invoice');
                    $invoiceModel = $invoiceService->prepareInvoice($client, ['client_id' => $client->id, 'items' => $invoice_items, 'gateway_id' => $gateway_id]);

                    $clientBalanceService = $this->di['mod_service']('Client', 'Balance');
                    $balanceAmount = $clientBalanceService->getClientBalance($client);
                    $useCredits = $balanceAmount >= $ca['total'];

                    $invoiceService->approveInvoice($invoiceModel, ['id' => $invoiceModel->id, 'use_credits' => $useCredits]);

                    if ($invoiceModel->status == \Model_Invoice::STATUS_UNPAID) {
                        foreach ($orders as $order) {
                            $order->unpaid_invoice_id = $invoiceModel->id;
                            $this->di['db']->store($order);
                        }
                    }
                }

                if ($promo instanceof Promo && $promoProductService !== null) {
                    $redemptionStatus = isset($invoiceModel) && $invoiceModel instanceof \Model_Invoice && $invoiceModel->status === \Model_Invoice::STATUS_UNPAID
                        ? \Box\Mod\Product\Entity\PromoRedemption::STATUS_RESERVED
                        : \Box\Mod\Product\Entity\PromoRedemption::STATUS_COMMITTED;
                    $checkoutInvoice = $invoiceModel instanceof \Model_Invoice ? $invoiceModel : null;

                    $promoProductService->createCheckoutPromoRedemptions($promo, $client, $orders, $checkoutInvoice, $redemptionStatus);
                }

                // Activate orders after the checkout state is durably persisted.
                $orderService = $this->di['mod_service']('Order');
                $ids = [];
                foreach ($orders as $order) {
                    $ids[] = $order->id;
                    $oa = $orderService->toApiArray($order, false, $client);
                    $product = $this->getProductService()->findProductById((int) $oa['product_id']);

                    try {
                        if ($product->getSetup() == \Box\Mod\Product\Service::SETUP_AFTER_ORDER) {
                            $orderService->activateOrder($order);
                        }

                        if ($ca['total'] <= 0 && $product->getSetup() == \Box\Mod\Product\Service::SETUP_AFTER_PAYMENT && $oa['total'] - $oa['discount'] <= 0) {
                            $orderService->activateOrder($order);
                        }

                        if ($ca['total'] > 0 && $product->getSetup() == \Box\Mod\Product\Service::SETUP_AFTER_PAYMENT && $invoiceModel->status == \Model_Invoice::STATUS_PAID) {
                            $orderService->activateOrder($order);
                        }
                    } catch (\Exception $e) {
                        $this->di['logger']->error('Order activation failed after checkout: %s', $e->getMessage());
                        $status = 'error';
                        $notes = "Order could not be activated after checkout due to error: {$e->getMessage()}.";
                        $orderService->orderStatusAdd($order, $status, $notes);
                    }
                }

                return [
                    $master_order,
                    $invoiceModel ?? null,
                    $ids,
                ];
            });
        } catch (\Throwable $e) {
            if ($promo instanceof Promo && $reservedCount > 0) {
                try {
                    $promoProductService->compensateCheckoutPromoFailure($promo, $reservedOrderIds, $reservedCount);
                } catch (\Throwable $compensationError) {
                    $this->di['logger']->error('Failed to compensate promo checkout failure', [
                        'exception' => $compensationError->getMessage(),
                        'promo_id' => $promo->getId(),
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
    protected function getRelatedItemsDiscount(\Model_Cart $cart, \Model_CartProduct $model): float
    {
        $config = $this->getItemConfig($model);

        $list = [];
        $products = $this->getCartProducts($cart);
        foreach ($products as $p) {
            $item = $this->di['db']->toArray($p);
            $item['config'] = $this->getItemConfig($p);
            $list[] = $item;
        }

        return $this->getProductService()->getRelatedProductDiscountByProductId((int) $model->product_id, $list, $config);
    }

    protected function getItemPromoDiscount(\Model_CartProduct $model, Promo $promo)
    {
        $config = $this->getItemConfig($model);

        return $this->getProductService()->getProductDiscountById((int) $model->product_id, $promo, $config);
    }

    public function getItemConfig(\Model_CartProduct $model)
    {
        return json_decode($model->config ?? '', true) ?? [];
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

    public function cartProductToApiArray(\Model_CartProduct $model): array
    {
        $productView = $this->getProductService()->getCartProductViewData($model);
        $config = $productView['config'];
        $setup = $productView['setup_price'];
        $price = $productView['price'];
        $qty = $productView['quantity'];

        [$discount_price, $discount_setup] = $this->getProductDiscount($model, $setup);

        $discount_total = $discount_price + $discount_setup;

        $subtotal = ($price * $qty);
        if (abs($discount_total) > ($subtotal + $setup)) {
            $discount_total = $subtotal;
            $discount_price = $subtotal;
        }

        return array_merge($config, [
            'id' => $model->id,
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

    public function getProductDiscount(\Model_CartProduct $cartProduct, $setup): array
    {
        $cart = $this->di['db']->load('Cart', $cartProduct->cart_id);
        $discount_price = $this->getRelatedItemsDiscount($cart, $cartProduct);
        $discount_setup = 0; // discount for setup price
        if ($cart->promo_id) {
            $promo = $this->getProductService()->findPromoById((int) $cart->promo_id);
            // Promo discount should override related item discount
            $discount_price = $this->getItemPromoDiscount($cartProduct, $promo);

            if ($promo->isFreeSetup()) {
                $discount_setup = $setup;
            }
        }

        return [$discount_price, $discount_setup];
    }
}
