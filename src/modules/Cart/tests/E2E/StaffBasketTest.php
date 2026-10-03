<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

// Skip E2E tests if environment is not configured
if (!getenv('APP_URL') || !getenv('TEST_API_KEY')) {
    return;
}

use function Tests\Helpers\assertApiResultIsArray;
use function Tests\Helpers\assertApiResultIsInt;
use function Tests\Helpers\assertApiSuccess;

test('staff basket checkout creates one invoice for several orders', function (): void {
    $clientId = staffBasketCreateClient('basket');
    $productOne = staffBasketCreateProduct(10.0, true);
    $productTwo = staffBasketCreateProduct(20.0, true);

    try {
        $addOne = Tests\Helpers\ApiClient::request('admin/cart/staff_basket_add_item', [
            'client_id' => $clientId,
            'id' => $productOne,
            'quantity' => 2,
        ]);
        assertApiSuccess($addOne);

        $addTwo = Tests\Helpers\ApiClient::request('admin/cart/staff_basket_add_item', [
            'client_id' => $clientId,
            'id' => $productTwo,
            'price' => 15.0,
        ]);
        assertApiSuccess($addTwo);

        $basket = Tests\Helpers\ApiClient::request('admin/cart/staff_basket_get', ['client_id' => $clientId]);
        assertApiSuccess($basket);
        assertApiResultIsArray($basket);
        $basketArray = $basket->getResult();
        expect($basketArray['items'])->toHaveCount(2);

        $checkout = Tests\Helpers\ApiClient::request('admin/cart/staff_basket_checkout', ['client_id' => $clientId]);
        assertApiSuccess($checkout);
        assertApiResultIsArray($checkout);
        $result = $checkout->getResult();
        expect($result['orders'])->toHaveCount(2);
        expect((int) $result['invoice_id'])->toBeGreaterThan(0);

        $invoice = staffBasketGetInvoice((int) $result['invoice_id']);
        expect($invoice['status'])->toBe('unpaid');

        $orderIds = array_map(intval(...), $result['orders']);
        foreach ($orderIds as $orderId) {
            expect(staffBasketHasOrderLine($invoice, $orderId))->toBeTrue();
            $order = staffBasketGetOrder($orderId);
            expect((int) $order['unpaid_invoice_id'])->toBe((int) $result['invoice_id']);
        }

        // Two separate add-to-basket actions land in two order families.
        $groups = array_map(fn (int $orderId): ?string => staffBasketGetOrder($orderId)['group_id'] ?? null, $orderIds);
        expect($groups[0])->not->toBe($groups[1]);

        // Quantity flows through, and the override replaces the catalog price.
        expect(staffBasketHasOrderLine($invoice, $orderIds[0], 10.0))->toBeTrue();
        expect(staffBasketHasOrderLine($invoice, $orderIds[1], 15.0))->toBeTrue();
    } finally {
        staffBasketCleanupClient($clientId);
    }
});

test('staff basket can check out a disabled product', function (): void {
    $clientId = staffBasketCreateClient('basket_disabled');
    $productId = staffBasketCreateProduct(5.0, false);

    try {
        $added = Tests\Helpers\ApiClient::request('admin/cart/staff_basket_add_item', [
            'client_id' => $clientId,
            'id' => $productId,
        ]);
        assertApiSuccess($added);

        $checkout = Tests\Helpers\ApiClient::request('admin/cart/staff_basket_checkout', ['client_id' => $clientId]);
        assertApiSuccess($checkout);
        $result = $checkout->getResult();
        expect($result['orders'])->toHaveCount(1);
        expect((int) $result['invoice_id'])->toBeGreaterThan(0);
    } finally {
        staffBasketCleanupClient($clientId);
    }
});

test('staff basket rejects a forged standalone addon', function (): void {
    $clientId = staffBasketCreateClient('basket_addon');

    try {
        $addonId = staffBasketCreateAddon();
        $add = Tests\Helpers\ApiClient::request('admin/cart/staff_basket_add_item', [
            'client_id' => $clientId,
            'id' => $addonId,
        ]);
        expect($add->wasSuccessful())->toBeFalse();
        expect($add->getErrorMessage())->toContain('Addon products cannot be added separately.');
    } finally {
        staffBasketCleanupClient($clientId);
    }
});

function staffBasketCreateProduct(float $price, bool $enabled): int
{
    $result = Tests\Helpers\ApiClient::request('admin/product/prepare', [
        'title' => 'E2E Basket Product ' . uniqid(),
        'type' => 'custom',
        'product_category_id' => 1,
    ]);
    assertApiSuccess($result);
    assertApiResultIsInt($result);
    $productId = (int) $result->getResult();

    $update = Tests\Helpers\ApiClient::request('admin/product/update', [
        'id' => $productId,
        'status' => $enabled ? 'enabled' : 'disabled',
        'pricing' => ['type' => 'once', 'once' => ['price' => $price, 'setup' => 0]],
    ]);
    assertApiSuccess($update);

    return $productId;
}

function staffBasketCreateAddon(): int
{
    $result = Tests\Helpers\ApiClient::request('admin/product/addon_create', [
        'title' => 'E2E Basket Addon ' . uniqid(),
        'status' => 'enabled',
    ]);
    assertApiSuccess($result);
    assertApiResultIsInt($result);

    return (int) $result->getResult();
}

function staffBasketCreateClient(string $prefix): int
{
    $created = Tests\Helpers\ApiClient::request('admin/client/create', [
        'email' => $prefix . uniqid() . '@example.com',
        'first_name' => 'Test',
        'password' => 'A1a' . bin2hex(random_bytes(6)),
        'send_welcome_email' => 0,
    ]);
    assertApiSuccess($created);

    return (int) $created->getResult();
}

function staffBasketGetOrder(int $orderId): array
{
    $result = Tests\Helpers\ApiClient::request('admin/order/get', ['id' => $orderId]);
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return $result->getResult();
}

function staffBasketGetInvoice(int $invoiceId): array
{
    $result = Tests\Helpers\ApiClient::request('admin/invoice/get', ['id' => $invoiceId]);
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return $result->getResult();
}

function staffBasketHasOrderLine(array $invoice, int $orderId, ?float $price = null): bool
{
    foreach ($invoice['lines'] ?? [] as $line) {
        if ((int) ($line['order_id'] ?? 0) === $orderId) {
            if ($price === null || (float) ($line['price'] ?? 0) === $price) {
                return true;
            }
        }
    }

    return false;
}

function staffBasketCleanupClient(int $clientId): void
{
    $result = Tests\Helpers\ApiClient::request('admin/client/delete', ['id' => $clientId]);
    assertApiSuccess($result);
}
