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

test('addon quantity flows from cart to order and invoice', function (): void {
    Tests\Helpers\ApiClient::resetCookies();
    ['parent' => $parentId, 'addon' => $addonId] = addonQtyCreateParentWithAddon(true);

    // The API client shares one cookie jar, so drop the admin session from
    // setup: profile_api_key_reset prefers session auth and would otherwise
    // demand a CSRF token instead of accepting the admin API token.
    Tests\Helpers\ApiClient::resetCookies();

    $addResult = Tests\Helpers\ApiClient::request('guest/cart/add_item', [
        'id' => $parentId,
        'multiple' => 1,
        'addons' => [$addonId => ['selected' => 1, 'quantity' => 3]],
    ]);
    assertApiSuccess($addResult);

    $cart = addonQtyGetCart();
    $addonLine = addonQtyFindLine($cart, $addonId);
    expect($addonLine['quantity'])->toBe(3);
    expect((float) $addonLine['total'])->toBe(6.0);

    $clientToken = addonQtyCreateClientWithToken();
    $checkout = Tests\Helpers\ApiClient::request('client/cart/checkout', [
        'gateway_id' => addonQtyCustomGatewayId(),
    ], 'client', $clientToken);
    assertApiSuccess($checkout);

    $orders = $checkout->getResult()['orders'];
    expect($orders)->toHaveCount(2);

    $addonOrder = null;
    foreach ($orders as $orderId) {
        $order = addonQtyGetOrder($orderId);
        if ((int) $order['product_id'] === $addonId) {
            $addonOrder = $order;
        }
    }
    expect($addonOrder)->not->toBeNull();
    expect((int) $addonOrder['quantity'])->toBe(3);
    expect($addonOrder['group_master'])->toBeFalse();

    $invoice = addonQtyGetInvoice((int) $addonOrder['unpaid_invoice_id']);
    $addonInvoiceLine = addonQtyFindInvoiceLine($invoice, (int) $addonOrder['id']);
    expect((int) $addonInvoiceLine['quantity'])->toBe(3);

    addonQtyCleanupClient();
});

test('addon quantity is rejected when the addon disallows it', function (): void {
    Tests\Helpers\ApiClient::resetCookies();
    ['parent' => $parentId, 'addon' => $addonId] = addonQtyCreateParentWithAddon(false);

    $result = Tests\Helpers\ApiClient::request('guest/cart/add_item', [
        'id' => $parentId,
        'multiple' => 1,
        'addons' => [$addonId => ['selected' => 1, 'quantity' => 3]],
    ]);
    expect($result->wasSuccessful())->toBeFalse();
    expect($result->getErrorMessage())->toContain('invalid for the associated product');

    addonQtyDeleteProduct($addonId);
    addonQtyDeleteProduct($parentId);
});

test('addon quantity respects stock without disclosing it', function (): void {
    Tests\Helpers\ApiClient::resetCookies();
    ['parent' => $parentId, 'addon' => $addonId] = addonQtyCreateParentWithAddon(true, 2);

    $overstock = Tests\Helpers\ApiClient::request('guest/cart/add_item', [
        'id' => $parentId,
        'multiple' => 1,
        'addons' => [$addonId => ['selected' => 1, 'quantity' => 5]],
    ]);
    expect($overstock->wasSuccessful())->toBeFalse();
    expect($overstock->getErrorMessage())->toContain('out of stock');

    $withinStock = Tests\Helpers\ApiClient::request('guest/cart/add_item', [
        'id' => $parentId,
        'multiple' => 1,
        'addons' => [$addonId => ['selected' => 1, 'quantity' => 2]],
    ]);
    assertApiSuccess($withinStock);

    $product = addonQtyGetProduct($parentId);
    foreach ($product['addons'] as $addon) {
        expect($addon)->not->toHaveKey('quantity_in_stock');
        expect($addon)->not->toHaveKey('stock_control');
    }

    addonQtyDeleteProduct($addonId);
    addonQtyDeleteProduct($parentId);
});

test('addon quantity input is only rendered for enabled addons without stock hints', function (): void {
    Tests\Helpers\ApiClient::resetCookies();
    ['parent' => $parentId, 'addon' => $addonId] = addonQtyCreateParentWithAddon(true, 7);
    $plainAddonId = addonQtyCreateAddon(false);
    addonQtyLinkAddons($parentId, [$addonId, $plainAddonId]);

    $page = @file_get_contents(rtrim((string) getenv('APP_URL'), '/') . '/order?product=' . $parentId);
    expect($page)->not->toBeFalse();
    expect($page)->toContain("addons[{$addonId}][quantity]");
    expect($page)->not->toContain("addons[{$plainAddonId}][quantity]");
    expect((bool) preg_match('/addons\[\d+\]\[quantity\][^>]*max=/', (string) $page))->toBeFalse();

    addonQtyDeleteProduct($plainAddonId);
    addonQtyDeleteProduct($addonId);
    addonQtyDeleteProduct($parentId);
});

function addonQtyCreateParentWithAddon(bool $allowQuantity, ?int $stock = null): array
{
    $parent = Tests\Helpers\ApiClient::request('admin/product/prepare', [
        'title' => 'E2E Parent ' . uniqid(),
        'type' => 'custom',
        'product_category_id' => 1,
    ]);
    assertApiSuccess($parent);
    assertApiResultIsInt($parent);
    $parentId = (int) $parent->getResult();

    $update = Tests\Helpers\ApiClient::request('admin/product/update', [
        'id' => $parentId,
        'status' => 'enabled',
        'pricing' => ['type' => 'once', 'once' => ['price' => 10, 'setup' => 0]],
    ]);
    assertApiSuccess($update);

    $addonId = addonQtyCreateAddon($allowQuantity, $stock);
    addonQtyLinkAddons($parentId, [$addonId]);

    return ['parent' => $parentId, 'addon' => $addonId];
}

function addonQtyCreateAddon(bool $allowQuantity, ?int $stock = null): int
{
    $addon = Tests\Helpers\ApiClient::request('admin/product/addon_create', [
        'title' => 'E2E Addon ' . uniqid(),
    ]);
    assertApiSuccess($addon);
    assertApiResultIsInt($addon);
    $addonId = (int) $addon->getResult();

    $data = [
        'id' => $addonId,
        'status' => 'enabled',
        'pricing' => ['type' => 'once', 'once' => ['price' => 2, 'setup' => 0]],
        'allow_quantity_select' => $allowQuantity ? 1 : 0,
    ];
    if ($stock !== null) {
        $data['stock_control'] = 1;
        $data['quantity_in_stock'] = $stock;
    }
    $update = Tests\Helpers\ApiClient::request('admin/product/addon_update', $data);
    assertApiSuccess($update);

    return $addonId;
}

function addonQtyLinkAddons(int $parentId, array $addonIds): void
{
    $link = Tests\Helpers\ApiClient::request('admin/product/update', [
        'id' => $parentId,
        'addons' => array_values($addonIds),
    ]);
    assertApiSuccess($link);
}

function addonQtyGetCart(): array
{
    $result = Tests\Helpers\ApiClient::request('guest/cart/get');
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return $result->getResult();
}

function addonQtyFindLine(array $cart, int $productId): array
{
    foreach ($cart['items'] as $item) {
        if ((int) $item['product_id'] === $productId) {
            return $item;
        }
    }

    throw new RuntimeException("Product {$productId} not found in cart");
}

function addonQtyGetProduct(int $productId): array
{
    $result = Tests\Helpers\ApiClient::request('guest/product/get', ['id' => $productId]);
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return $result->getResult();
}

function addonQtyGetOrder(int $orderId): array
{
    $result = Tests\Helpers\ApiClient::request('admin/order/get', ['id' => $orderId]);
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return $result->getResult();
}

function addonQtyGetInvoice(int $invoiceId): array
{
    $result = Tests\Helpers\ApiClient::request('admin/invoice/get', ['id' => $invoiceId]);
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return $result->getResult();
}

function addonQtyFindInvoiceLine(array $invoice, int $orderId): array
{
    $lines = $invoice['lines'] ?? $invoice['items'] ?? [];
    foreach ($lines as $line) {
        if (($line['type'] ?? null) === 'order' && (int) ($line['rel_id'] ?? 0) === $orderId) {
            return $line;
        }
    }

    throw new RuntimeException("Invoice line for order {$orderId} not found");
}

function addonQtyCustomGatewayId(): int
{
    $result = Tests\Helpers\ApiClient::request('admin/invoice/gateway_get_pairs');
    assertApiSuccess($result);
    foreach ($result->getResult() as $id => $title) {
        if ($title === 'Custom') {
            return (int) $id;
        }
    }

    throw new RuntimeException('Custom payment gateway not found');
}

function addonQtyCreateClientWithToken(): string
{
    $password = 'A1a' . bin2hex(random_bytes(6));
    $email = 'client_' . uniqid() . '@example.com';
    $created = Tests\Helpers\ApiClient::request('guest/client/create', [
        'email' => $email,
        'first_name' => 'Test',
        'password' => $password,
        'password_confirm' => $password,
    ]);
    assertApiSuccess($created);

    $login = Tests\Helpers\ApiClient::request('guest/client/login', [
        'email' => $email,
        'password' => $password,
    ]);
    assertApiSuccess($login);
    $GLOBALS['addonQtyClientId'] = (int) $login->getResult()['id'];

    // Mint a client API token via the admin API (no session/CSRF involved),
    // which can then authenticate client/* calls made with basic auth.
    $token = Tests\Helpers\ApiClient::request('admin/profile/api_key_reset', [
        'id' => $GLOBALS['addonQtyClientId'],
    ]);
    assertApiSuccess($token);

    return (string) $token->getResult();
}

function addonQtyCleanupClient(): void
{
    if (!isset($GLOBALS['addonQtyClientId'])) {
        return;
    }

    $deleted = Tests\Helpers\ApiClient::request('admin/client/delete', ['id' => $GLOBALS['addonQtyClientId']]);
    assertApiSuccess($deleted);
    unset($GLOBALS['addonQtyClientId']);
}

function addonQtyDeleteProduct(int $productId): void
{
    $result = Tests\Helpers\ApiClient::request('admin/product/delete', ['id' => $productId]);
    assertApiSuccess($result);
}
