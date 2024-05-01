<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Cart;

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

    public function getSearchQuery($data)
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
    public function getSessionCart(string $sessionID = null)
    {
        $sessionID ??= $this->di['session']->getId();
        $sqlBindings = [':session_id' => $sessionID];
        $cart = $this->di['db']->findOne('Cart', 'session_id = :session_id', $sqlBindings);

        if ($cart instanceof \Model_Cart) {
            return $cart;
        }

        $cc = $this->di['mod_service']('currency');

        if ($this->di['session']->get('client_id')) {
            $client_id = $this->di['session']->get('client_id');
            $currency = $cc->getCurrencyByClientId($client_id);
        } else {
            $currency = $cc->getDefault();
        }

        $cart = $this->di['db']->dispense('Cart');
        $cart->session_id = $sessionID;
        $cart->currency_id = $currency->id;
        $cart->created_at = date('Y-m-d H:i:s');
        $cart->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($cart);

        return $cart;
    }

    public function addItem(\Model_Cart $cart, \Model_Product $product, array $data)
    {
        $event_params = [...$data, 'cart_id' => $cart->id, 'product_id' => $product->id];
        $this->di['events_manager']->fire(['event' => 'onBeforeProductAddedToCart', 'params' => $event_params]);

        $productService = $product->getService();

        if ($this->isRecurrentPricing($product)) {
            $required = [
                'period' => 'Period parameter not passed',
            ];
            $this->di['validator']->checkRequiredParamsForArray($required, $data);

            if (!$this->isPeriodEnabledForProduct($product, $data['period'])) {
                throw new \FOSSBilling\InformationException('Selected billing period is invalid');
            }
        }

        $qty = $data['quantity'] ?? 1;
        // check stock
        if (!$this->isStockAvailable($product, $qty)) {
            throw new \FOSSBilling\InformationException('This item is currently out of stock');
        }

        $addons = $data['addons'] ?? [];
        unset($data['id']);
        unset($data['addons']);

        $list = [];
        $list[] = [
            'product' => $product,
            'config' => $data,
        ];

        // check for required domain product
        if (method_exists($productService, 'getDomainProductFromConfig')) {
            $dc = $productService->getDomainProductFromConfig($product, $data);
            if (isset($dc['config']) && $dc['product'] && $dc['product'] instanceof \Model_Product) {
                $list[] = $dc;
            }
        }

        $productService = $this->di['mod_service']('Product');
        foreach ($addons as $id => $ac) {
            if (isset($ac['selected']) && (bool) $ac['selected']) {
                $addon = $productService->getAddonById($id);
                if ($addon instanceof \Model_Product) {
                    if ($this->isRecurrentPricing($addon)) {
                        $required = [
                            'period' => 'Addon period parameter not passed',
                        ];
                        $this->di['validator']->checkRequiredParamsForArray($required, $ac);

                        if (!$this->isPeriodEnabledForProduct($addon, $ac['period'])) {
                            throw new \FOSSBilling\InformationException('Selected billing period is invalid for the selected add-on');
                        }
                    }
                    $ac['parent_id'] = $product->id;

                    $list[] = [
                        'product' => $addon,
                        'config' => $ac,
                    ];
                } else {
                    error_log('Addon not found by id ' . $id);
                }
            }
        }

        foreach ($list as $c) {
            $productFromList = $c['product'];
            $productFromListConfig = $c['config'];

            $productServiceFromList = $productFromList->getService();

            // @deprecated logic
            if (method_exists($productServiceFromList, 'prependOrderConfig')) {
                $productFromListConfig = $productServiceFromList->prependOrderConfig($productFromList, $productFromListConfig);
            }

            if (method_exists($productServiceFromList, 'attachOrderConfig')) {
                $model = $this->di['db']->load('Product', $productFromList->id);
                $productFromListConfig = $productServiceFromList->attachOrderConfig($model, $productFromListConfig);
            }
            if (method_exists($productServiceFromList, 'validateOrderData')) {
                $productServiceFromList->validateOrderData($productFromListConfig);
            }
            if (method_exists($productServiceFromList, 'validateCustomForm')) {
                $productServiceFromList->validateCustomForm($productFromListConfig, $this->di['db']->toArray($productFromList));
            }
            $this->addProduct($cart, $productFromList, $productFromListConfig);
        }

        $this->di['logger']->info('Added "%s" to shopping cart', $product->title);

        $this->di['events_manager']->fire(['event' => 'onAfterProductAddedToCart', 'params' => $event_params]);

        return true;
    }

    public function isStockAvailable(\Model_Product $product, $qty)
    {
        if ($product->stock_control) {
            return $product->quantity_in_stock >= $qty;
        }

        return true;
    }

    public function isRecurrentPricing(\Model_Product $model)
    {
        $productTable = $model->getTable();
        $pricing = $productTable->getPricingArray($model);

        return isset($pricing['type']) && $pricing['type'] == \Model_ProductPayment::RECURRENT;
    }

    public function isPeriodEnabledForProduct(\Model_Product $model, $period)
    {
        $productTable = $model->getTable();
        $pricing = $productTable->getPricingArray($model);
        if ($pricing['type'] == \Model_ProductPayment::RECURRENT) {
            return (bool) $pricing['recurrent'][$period]['enabled'];
        }

        return true;
    }

    protected function addProduct(\Model_Cart $cart, \Model_Product $product, array $data)
    {
        $item = $this->di['db']->dispense('CartProduct');
        $item->cart_id = $cart->id;
        $item->product_id = $product->id;
        $item->config = json_encode($data);
        $this->di['db']->store($item);

        return true;
    }

    public function removeProduct(\Model_Cart $cart, $id, $removeAddons = true)
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
            $allCartProducts = $this->di['db']->find('CartProduct', 'cart_id = :cart_id', [':cart_id' => $cart->id]);
            foreach ((array) $allCartProducts as $cProduct) {
                $config = json_decode($cProduct->config, true);
                if (isset($config['parent_id']) && $config['parent_id'] == $cartProduct->product_id) {
                    $this->di['db']->trash($cProduct);
                    $this->di['logger']->info('Removed product addon from shopping cart');
                }
            }
        }

        $this->di['db']->trash($cartProduct);

        $this->di['logger']->info('Removed product from shopping cart');

        return true;
    }

    public function changeCartCurrency(\Model_Cart $cart, \Model_Currency $currency)
    {
        $cart->currency_id = $currency->id;
        $this->di['db']->store($cart);

        $this->di['logger']->info('Changed shopping cart #%s currency to %s', $cart->id, $currency->title);

        return true;
    }

    public function resetCart(\Model_Cart $cart)
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

    public function removePromo(\Model_Cart $cart)
    {
        $cart->promo_id = null;
        $cart->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($cart);

        $this->di['logger']->info('Removed promo code from shopping cart #%s', $cart->id);

        return true;
    }

    public function applyPromo(\Model_Cart $cart, \Model_Promo $promo)
    {
        if ($cart->promo_id == $promo->id) {
            return true;
        }

        if ($this->isEmptyCart($cart)) {
            throw new \FOSSBilling\InformationException('Add products to your cart before applying promo code');
        }

        $cart->promo_id = $promo->id;
        $this->di['db']->store($cart);

        $this->di['logger']->info('Applied promo code %s to shopping cart', $promo->code);

        return true;
    }

    protected function isEmptyCart(\Model_Cart $cart)
    {
        $cartProducts = $this->di['db']->find('CartProduct', 'cart_id = :cart_id', [':cart_id' => $cart->id]);

        return (is_countable($cartProducts) ? count($cartProducts) : 0) == 0;
    }

    public function rm(\Model_Cart $cart)
    {
        $cartProducts = $this->di['db']->find('CartProduct', 'cart_id = :cart_id', [':cart_id' => $cart->id]);

        foreach ($cartProducts as $cartProduct) {
            $this->di['db']->trash($cartProduct);
        }

        $this->di['db']->trash($cart);

        return true;
    }

    public function toApiArray(\Model_Cart $model, $deep = false, $identity = null)
    {
        $products = $this->getCartProducts($model);

        $currency = $this->di['db']->getExistingModelById('Currency', $model->currency_id);

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
            $promo = $this->di['db']->getExistingModelById('Promo', $model->promo_id, 'Promo not found');
            $promocode = $promo->code;
        } else {
            $promocode = null;
        }

        $currencyService = $this->di['mod_service']('currency');

        return [
            'promocode' => $promocode,
            'discount' => $items_discount,
            'subtotal' => $total,
            'total' => $total - $items_discount,
            'items' => $items,
            'currency' => $currencyService->toApiArray($currency),
        ];
    }

    public function isClientAbleToUsePromo(\Model_Client $client, \Model_Promo $promo)
    {
        if (!$this->promoCanBeApplied($promo)) {
            return false;
        }

        if (!$promo->once_per_client) {
            return true;
        }

        return !$this->clientHadUsedPromo($client, $promo);
    }

    public function promoCanBeApplied(\Model_Promo $promo)
    {
        if (!$promo->active) {
            return false;
        }

        if ($promo->maxuses && $promo->maxuses <= $promo->used) {
            return false;
        }

        if ($promo->start_at && (strtotime($promo->start_at) - time() > 0)) {
            return false;
        }

        if ($promo->end_at && (strtotime($promo->end_at) - time() < 0)) {
            return false;
        }

        return true;
    }

    public function isPromoAvailableForClientGroup(\Model_Promo $promo)
    {
        if (is_string($promo->client_groups) && json_validate($promo->client_groups)) {
            $clientGroups = json_decode($promo->client_groups, true);
        } else {
            $clientGroups = [];
        }

        if (empty($clientGroups)) {
            return true;
        }

        try {
            $client = $this->di['loggedin_client'];
        } catch (\Exception) {
            $client = null;
        }

        if (is_null($client)) {
            return false;
        }

        if (!$client->client_group_id) {
            return false;
        }

        return in_array($client->client_group_id, $clientGroups);
    }

    protected function clientHadUsedPromo(\Model_Client $client, \Model_Promo $promo)
    {
        $sql = 'SELECT id FROM client_order WHERE promo_id = :promo AND client_id = :cid LIMIT 1';
        $promoId = $this->di['db']->getCell($sql, [':promo' => $promo->id, ':cid' => $client->id]);

        return $promoId !== null;
    }

    public function getCartProducts(\Model_Cart $model)
    {
        return $this->di['db']->find('CartProduct', 'cart_id = :cart_id ORDER BY id ASC', [':cart_id' => $model->id]);
    }

    public function checkoutCart(\Model_Cart $cart, \Model_Client $client, $gateway_id = null): array
    {
        if ($cart->promo_id) {
            $promo = $this->di['db']->getExistingModelById('Promo', $cart->promo_id, 'Promo not found');
            if (!$this->isClientAbleToUsePromo($client, $promo)) {
                throw new \FOSSBilling\InformationException('You have already used this promo code. Please remove the promo code and checkout again.', null, 9874);
            }

            if (!$promo instanceof \Model_Promo) {
                throw new \FOSSBilling\InformationException('The promo code has expired or does not exist');
            }

            if (!$this->isPromoAvailableForClientGroup($promo)) {
                throw new \FOSSBilling\InformationException('Promo code cannot be applied to your account');
            }
        }

        $this->di['events_manager']->fire(
            [
                'event' => 'onBeforeClientCheckout',
                'params' => [
                    'ip' => $this->di['request']->getClientAddress(),
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
                    'ip' => $this->di['request']->getClientAddress(),
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

    public function createFromCart(\Model_Client $client, $gateway_id = null)
    {
        $cart = $this->getSessionCart();
        $ca = $this->toApiArray($cart);
        if ((is_countable($ca['items']) ? count($ca['items']) : 0) == 0) {
            throw new \FOSSBilling\InformationException('Cannot checkout an empty cart');
        }

        $currency = $this->di['db']->getExistingModelById('Currency', $cart->currency_id, 'Currency not found.');

        // set default client currency
        if (!$client->currency) {
            $client->currency = $currency->code;
            $this->di['db']->store($client);
        }

        if ($client->currency != $currency->code) {
            throw new \FOSSBilling\InformationException('Selected currency :selected does not match your profile currency :code. Please change cart currency to continue.', [':selected' => $currency->code, ':code' => $client->currency]);
        }

        $clientService = $this->di['mod_service']('client');
        $taxed = $clientService->isClientTaxable($client);

        $orders = [];
        $invoice_items = [];
        $master_order = null;
        $i = 0;

        foreach ($this->getCartProducts($cart) as $p) {
            $item = $this->cartProductToApiArray($p);

            $product = $this->di['db']->getExistingModelById('Product', $item['product_id']);
            if (is_null($product) || $product->status !== 'enabled') {
                throw new \FOSSBilling\InformationException('Unable to complete order. One or more of the selected products are invalid.');
            }

            /*
             * Convert the domain name to lowercase letters.
             * Using a capital letter in a domain name still points to the same name, so this isn't going to break anything
             * It will, however, avoid instances like this when a domain name is entered with a capital letter:
             * https://github.com/boxbilling/boxbilling/discussions/1022#discussioncomment-1311819
             */
            if ($item['type'] === 'domain' || $item['type'] === 'hosting') {
                $item['register_sld'] = (isset($item['register_sld'])) ? strtolower($item['register_sld']) : null;
                $item['transfer_sld'] = (isset($item['transfer_sld'])) ? strtolower($item['transfer_sld']) : null;
                $item['sld'] = (isset($item['sld'])) ? strtolower($item['sld']) : null;
                $item['domain']['owndomain_sld'] = (isset($item['domain']['owndomain_sld'])) ? strtolower($item['domain']['owndomain_sld']) : null;
                $item['domain']['register_sld'] = (isset($item['domain']['register_sld'])) ? strtolower($item['domain']['register_sld']) : null;
                $item['domain']['transfer_sld'] = (isset($item['domain']['transfer_sld'])) ? strtolower($item['domain']['transfer_sld']) : null;

                // Domain TLD must begin with a period - add if not present for owndomain.
                $item['domain']['owndomain_tld'] = (isset($item['domain']['owndomain_tld'])) ? (str_contains($item['domain']['owndomain_tld'], '.') ? $item['domain']['owndomain_tld'] : '.' . $item['domain']['owndomain_tld']) : null;
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
            $order->currency = $currency->code;
            $order->service_type = $item['type'];
            $order->unit = $item['unit'] ?? null;
            $order->period = $item['period'] ?? null;
            $order->quantity = $item['quantity'] ?? null;
            $order->price = $item['price'] * $currency->conversion_rate;
            $order->discount = $item['discount_price'] * $currency->conversion_rate;
            $order->status = \Model_ClientOrder::STATUS_PENDING_SETUP;
            $order->notes = $item['notes'] ?? null;
            $order->config = json_encode($item);
            $order->created_at = date('Y-m-d H:i:s');
            $order->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($order);

            $orders[] = $order;

            // mark promo as used
            if ($cart->promo_id) {
                $promo = $this->di['db']->getExistingModelById('Promo', $cart->promo_id, 'Promo not found.');
                $this->usePromo($promo);

                // set promo info for later use
                $order->promo_recurring = $promo->recurring;
                $order->promo_used = 1;
                $this->di['db']->store($order);
            }

            $orderService = $this->di['mod_service']('order');
            $orderService->saveStatusChange($order, 'Order created');

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
                $setup_price = ($item['setup_price'] * $currency->conversion_rate) - ($item['discount_setup'] * $currency->conversion_rate);
                $invoice_items[] = [
                    'title' => __trans(':product setup', [':product' => $order->title]),
                    'price' => $setup_price,
                    'quantity' => 1,
                    'unit' => 'service',
                    'taxed' => $taxed,
                ];
            }

            // define master order to be returned
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

        // activate orders if product is setup to be activated after order place or order total is $0
        $orderService = $this->di['mod_service']('Order');
        $ids = [];
        foreach ($orders as $order) {
            $ids[] = $order->id;
            $oa = $orderService->toApiArray($order, false, $client);
            $product = $this->di['db']->getExistingModelById('Product', $oa['product_id']);

            try {
                if ($product->setup == \Model_ProductTable::SETUP_AFTER_ORDER) {
                    $orderService->activateOrder($order);
                }

                if ($ca['total'] <= 0 && $product->setup == \Model_ProductTable::SETUP_AFTER_PAYMENT && $oa['total'] - $oa['discount'] <= 0) {
                    $orderService->activateOrder($order);
                }

                if ($ca['total'] > 0 && $product->setup == \Model_ProductTable::SETUP_AFTER_PAYMENT && $invoiceModel->status == \Model_Invoice::STATUS_PAID) {
                    $orderService->activateOrder($order);
                }
            } catch (\Exception $e) {
                error_log($e->getMessage());
                $status = 'error';
                $notes = 'Order could not be activated after checkout due to error: ' . $e->getMessage();
                $orderService->orderStatusAdd($order, $status, $notes);
            }
        }

        return [
            $master_order,
            $invoiceModel ?? null,
            $ids,
        ];
    }

    public function usePromo(\Model_Promo $promo)
    {
        ++$promo->used;
        $promo->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($promo);
    }

    public function findActivePromoByCode($code)
    {
        return $this->di['db']->findOne('Promo', 'code = :code AND active = 1 ORDER BY id ASC', [':code' => $code]);
    }

    private function getItemPrice(\Model_CartProduct $model)
    {
        $product = $this->di['db']->load('Product', $model->product_id);
        $config = $this->getItemConfig($model);
        $repo = $product->getTable();

        return $repo->getProductPrice($product, $config);
    }

    private function getItemSetupPrice(\Model_CartProduct $model)
    {
        $product = $this->di['db']->load('Product', $model->product_id);
        $config = $this->getItemConfig($model);
        $repo = $product->getTable();

        return $repo->getProductSetupPrice($product, $config);
    }

    /**
     * Function checks if product is related to other products in cart
     * If relation exists then count discount for this.
     *
     * @return number
     */
    protected function getRelatedItemsDiscount(\Model_Cart $cart, \Model_CartProduct $model)
    {
        $product = $this->di['db']->load('Product', $model->product_id);
        $repo = $product->getTable();
        $config = $this->getItemConfig($model);

        $discount = 0;
        if (method_exists($repo, 'getRelatedDiscount')) {
            $list = [];
            $products = $this->getCartProducts($cart);
            foreach ($products as $p) {
                $item = $this->di['db']->toArray($p);
                $item['config'] = $this->getItemConfig($p);
                $list[] = $item;
            }
            $discount = $repo->getRelatedDiscount($list, $product, $config);
        }

        return $discount;
    }

    private function getItemTitle(\Model_CartProduct $model)
    {
        $product = $this->di['db']->load('Product', $model->product_id);
        $config = $this->getItemConfig($model);
        $service = $product->getService();
        if (method_exists($service, 'getCartProductTitle')) {
            return $service->getCartProductTitle($product, $config);
        } else {
            return $product->title;
        }
    }

    protected function getItemPromoDiscount(\Model_CartProduct $model, \Model_Promo $promo)
    {
        $product = $this->di['db']->load('Product', $model->product_id);
        $repo = $this->di['mod_service']('product');
        $config = $this->getItemConfig($model);

        return $repo->getProductDiscount($product, $promo, $config);
    }

    public function getItemConfig(\Model_CartProduct $model)
    {
        if (is_string($model->config) && json_validate($model->config)) {
            return json_decode($model->config, true);
        }

        return [];
    }

    public function cartProductToApiArray(\Model_CartProduct $model)
    {
        $product = $this->di['db']->load('Product', $model->product_id);
        $repo = $product->getTable();
        $config = $this->getItemConfig($model);
        $setup = $repo->getProductSetupPrice($product, $config);
        $price = $repo->getProductPrice($product, $config);
        $qty = $config['quantity'] ?? 1;

        [$discount_price, $discount_setup] = $this->getProductDiscount($model, $setup);

        $discount_total = $discount_price + $discount_setup;

        $subtotal = ($price * $qty);
        if (abs($discount_total) > ($subtotal + $setup)) {
            $discount_total = $subtotal;
            $discount_price = $subtotal;
        }

        return array_merge($config, [
            'id' => $model->id,
            'product_id' => $product->id,
            'form_id' => $product->form_id,
            'title' => $this->getItemTitle($model),
            'type' => $product->type,
            'quantity' => $qty,
            'unit' => $repo->getUnit($product),
            'price' => $price,
            'setup_price' => $setup,
            'discount' => $discount_total,
            'discount_price' => $discount_price,
            'discount_setup' => $discount_setup,
            'total' => $subtotal,
        ]);
    }

    public function getProductDiscount(\Model_CartProduct $cartProduct, $setup)
    {
        $cart = $this->di['db']->load('Cart', $cartProduct->cart_id);
        $discount_price = $this->getRelatedItemsDiscount($cart, $cartProduct);
        $discount_setup = 0; // discount for setup price
        if ($cart->promo_id) {
            $promo = $this->di['db']->getExistingModelById('Promo', $cart->promo_id, 'Promo not found');
            // Promo discount should override related item discount
            $discount_price = $this->getItemPromoDiscount($cartProduct, $promo);

            if ($promo->freesetup) {
                $discount_setup = $setup;
            }
        }

        return [$discount_price, $discount_setup];
    }
}
