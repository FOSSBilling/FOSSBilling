<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Cart\Api;

use Box\Mod\Cart\Entity\Cart;
use Box\Mod\Client\Entity\Client;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Product\Entity\Promo;
use FOSSBilling\InformationException;
use FOSSBilling\PaginationOptions;
use FOSSBilling\Tools;
use FOSSBilling\Validation\Api\RequiredParams;

/**
 * Shopping cart management.
 */
class Admin extends \FOSSBilling\Api\AbstractApi
{
    /**
     * @param array $data
     *
     * @optional string $sort - sort by one of: id
     * @optional string $direction - sort direction: ASC or DESC
     *
     * @return array
     */
    public function get_list($data)
    {
        [$sql, $params] = $this->getService()->getSearchQuery($data);
        $pager = $this->getDi()['pager']->getPaginatedResultSet($sql, $params, PaginationOptions::fromArray($data));

        foreach ($pager['list'] as $key => $cartArr) {
            $cart = $this->getDi()['em']->getRepository(Cart::class)->find((int) $cartArr['id']);
            if (!$cart instanceof Cart) {
                throw new \FOSSBilling\Exception('Cart not found');
            }
            $pager['list'][$key] = $this->getService()->toApiArray($cart);
        }

        return $pager;
    }

    /**
     * Get the contents of a shopping cart by ID.
     *
     * @param array $data Data array
     *
     * @return array Contents of the shopping cart
     */
    #[RequiredParams(['id' => 'Shopping cart ID is missing'])]
    public function get($data)
    {
        $cart = $this->getDi()['em']->getRepository(Cart::class)->find((int) $data['id']);
        if (!$cart instanceof Cart) {
            throw new \FOSSBilling\Exception('Shopping cart not found');
        }

        return $this->getService()->toApiArray($cart);
    }

    /**
     * Get the staff basket for a client, creating it when needed. Baskets are
     * scoped to the calling admin, so two staff members never share one.
     *
     * @param array $data Data array
     *
     * @return array Basket contents with priced lines and promo totals
     */
    #[RequiredParams(['client_id' => 'Client ID was not passed'])]
    public function staff_basket_get($data)
    {
        $this->checkPermissions('order', 'manage');
        [$client, $basket] = $this->resolveStaffBasket($data);

        return $this->getService()->toApiArray($basket, false, null, $client);
    }

    /**
     * Add a product to the staff basket. Unlike the client cart, disabled
     * products are allowed and a per-line price override can be set.
     *
     * @param array $data Data array
     *
     * @optional string $period - billing period, required for recurrent products
     * @optional int $quantity - quantity, default 1
     * @optional float $price - overridden unit price in the basket currency. Zero allowed. Domain products always use TLD pricing.
     * @optional array $addons - selected addons, keyed by addon ID (selected, period, quantity)
     * @optional array $config - product configuration options, depending on product type
     *
     * @return bool
     */
    #[RequiredParams([
        'client_id' => 'Client ID was not passed',
        'id' => 'Product ID was not passed',
    ])]
    public function staff_basket_add_item($data)
    {
        $this->checkPermissions('order', 'manage');
        [, $basket] = $this->resolveStaffBasket($data);

        $productService = $this->di['mod_service']('product');
        $product = $productService->findProductById((int) $data['id']);
        if (!$product instanceof Product) {
            throw new InformationException('Product not found');
        }

        if ($product->isAddon()) {
            throw new InformationException('Addon products cannot be added separately.');
        }

        if (is_array($data['addons'] ?? '')) {
            $productService->validateSelectedAddonsForProduct($product, $data['addons']);
        }

        $priceOverride = null;
        if (isset($data['price']) && $data['price'] !== '') {
            if (!is_numeric($data['price']) || (float) $data['price'] < 0) {
                throw new InformationException('Price override must be a non-negative number');
            }
            $priceOverride = (float) $data['price'];
        }

        unset($data['client_id'], $data['price']);

        return $this->getService()->addItem($basket, $product, $data, $priceOverride);
    }

    /**
     * Remove a line from the staff basket (addons of a removed line go too).
     *
     * @param array $data Data array
     *
     * @return bool
     */
    #[RequiredParams([
        'client_id' => 'Client ID was not passed',
        'id' => 'Basket item ID was not passed',
    ])]
    public function staff_basket_remove_item($data)
    {
        $this->checkPermissions('order', 'manage');
        [, $basket] = $this->resolveStaffBasket($data);

        return $this->getService()->removeProduct($basket, (int) $data['id'], true);
    }

    /**
     * Apply a promo code to the staff basket.
     *
     * @param array $data Data array
     *
     * @return bool
     */
    #[RequiredParams([
        'client_id' => 'Client ID was not passed',
        'promocode' => 'Promo code was not passed',
    ])]
    public function staff_basket_apply_promo($data)
    {
        $this->checkPermissions('order', 'manage');
        $this->checkPermissions('product', 'manage_promos');
        [$client, $basket] = $this->resolveStaffBasket($data);

        $promo = $this->getService()->findActivePromoByCode($data['promocode']);
        if (!$promo instanceof Promo) {
            throw new InformationException('The promo code has expired or does not exist');
        }

        if (!$this->getService()->isPromoAvailableForClientGroup($promo, $client)) {
            throw new InformationException('Promo code cannot be applied to this client account');
        }

        if (!$this->getService()->promoCanBeApplied($promo)) {
            throw new InformationException('The promo code has expired or does not exist');
        }

        return $this->getService()->applyPromo($basket, $promo);
    }

    /**
     * Remove the promo code from the staff basket.
     *
     * @param array $data Data array
     *
     * @return bool
     */
    #[RequiredParams(['client_id' => 'Client ID was not passed'])]
    public function staff_basket_remove_promo($data)
    {
        $this->checkPermissions('order', 'manage');
        [, $basket] = $this->resolveStaffBasket($data);

        return $this->getService()->removePromo($basket);
    }

    /**
     * Discard the staff basket without checking out.
     *
     * @param array $data Data array
     *
     * @return bool
     */
    #[RequiredParams(['client_id' => 'Client ID was not passed'])]
    public function staff_basket_discard($data)
    {
        $this->checkPermissions('order', 'manage');
        [, $basket] = $this->resolveStaffBasket($data);

        return $this->getService()->rm($basket);
    }

    /**
     * Check out the staff basket: one order per line sharing family groups,
     * a single invoice for all of them.
     *
     * @param array $data Data array
     *
     * @optional int $gateway_id - payment gateway for the invoice
     * @optional bool $activate - activate orders on checkout. Default true (cart semantics: after-order services activate immediately)
     * @optional bool $mark_invoice_paid - mark the generated invoice as paid after checkout
     * @optional string $transactionId - custom transaction ID when the selected gateway is Custom
     *
     * @return array Checkout result with order IDs and the invoice reference
     */
    #[RequiredParams(['client_id' => 'Client ID was not passed'])]
    public function staff_basket_checkout($data)
    {
        $this->checkPermissions('order', 'manage');

        $markInvoicePaid = Tools::normalizeBoolean($data['mark_invoice_paid'] ?? false);
        if ($markInvoicePaid) {
            $this->checkPermissions('invoice');
            $this->getDi()['mod_service']('Invoice')->validateAdminMarkAsPaidRequest($data);
        }

        [$client, $basket, $adminId] = $this->resolveStaffBasket($data);

        return $this->getService()->checkoutStaffBasket($basket, $client, $adminId, [
            'gateway_id' => isset($data['gateway_id']) && $data['gateway_id'] !== '' ? (int) $data['gateway_id'] : null,
            'activate' => array_key_exists('activate', $data) ? Tools::normalizeBoolean($data['activate']) : true,
            'mark_invoice_paid' => $markInvoicePaid,
            'transactionId' => $data['transactionId'] ?? null,
        ]);
    }

    /**
     * @return array{0: Client, 1: Cart, 2: int}
     */
    private function resolveStaffBasket(array $data): array
    {
        $client = $this->di['em']->getRepository(Client::class)->find($data['client_id'])
            ?? throw new InformationException('Client not found');
        $adminId = (int) $this->getDi()['loggedin_admin']->getId();

        return [$client, $this->getService()->getStaffBasket($client, $adminId), $adminId];
    }

    /**
     * Remove shopping carts that are older than a week and was not ordered.
     *
     * @BOXBILLING_CRON
     */
    public function batch_expire($data): bool
    {
        $this->getDi()['logger']->info('Executed action to clear expired shopping carts from database');

        $conn = $this->getDi()['em']->getConnection();
        // created_at < :cutoff is a portable stand-in for MySQL's DATEDIFF(CURDATE(), created_at) > 7 -
        // DATEDIFF compares calendar dates only (ignoring time-of-day), so "more than 7 days ago"
        // means created_at's date is strictly before 7 days before today.
        $cutoff = (new \DateTimeImmutable('today'))->modify('-7 days')->format('Y-m-d H:i:s');
        // Staff baskets carry a synthetic 'staff:' session key and are managed
        // explicitly (discard/checkout), never swept by the expiry cron.
        $expiredCarts = $conn->fetchAllKeyValue("SELECT id, created_at FROM cart WHERE created_at < :cutoff AND session_id NOT LIKE 'staff:%'", ['cutoff' => $cutoff]);
        if ($expiredCarts) {
            foreach ($expiredCarts as $id => $created_at) {
                $conn->executeStatement('DELETE FROM cart_product WHERE cart_id = :id', ['id' => $id]);
                $conn->executeStatement('DELETE FROM cart WHERE id = :id', ['id' => $id]);
            }
            $this->getDi()['logger']->info('Removed {expired_count} expired shopping carts', ['expired_count' => Tools::safeCount($expiredCarts)]);
        }

        return true;
    }
}
