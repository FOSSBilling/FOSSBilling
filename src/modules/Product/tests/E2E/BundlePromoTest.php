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

test('bundle promo code is rejected until every required product is in the cart', function (): void {
    Tests\Helpers\ApiClient::resetCookies();
    $productA = null;
    $productB = null;
    $promoId = null;
    $promoCode = 'E2EBun' . strtoupper(uniqid());

    try {
        $productA = bundlePromoCreateProduct(100.0);
        $productB = bundlePromoCreateProduct(50.0);
        // 10% off product B, but only when A and B are both in the cart.
        $promoId = bundlePromoCreatePromo($promoCode, 'percentage', 10, [
            'active' => 1,
            'recurring' => 1,
            'products' => [$productB],
            'requires_products' => [$productA, $productB],
        ]);

        ['token' => $clientToken, 'password' => $password, 'email' => $email]
            = bundlePromoCreateClient();

        Tests\Helpers\ApiClient::resetCookies();
        $login = Tests\Helpers\ApiClient::request('guest/client/login', ['email' => $email, 'password' => $password]);
        assertApiSuccess($login);
        // Guest add_item resets the cart unless multiple=1 is passed.
        Tests\Helpers\ApiClient::request('guest/cart/add_item', ['id' => $productB, 'multiple' => 1]);

        // Only B is present: applying names the missing product.
        $rejected = Tests\Helpers\ApiClient::request('guest/cart/apply_promo', ['promocode' => $promoCode]);
        expect($rejected->wasSuccessful())->toBeFalse();
        expect($rejected->getErrorMessage())->toContain('requires the following products');

        $cart = bundlePromoGetCart();
        expect((float) $cart['discount'])->toEqual(0.0);

        // Completing the bundle lets the code apply with the scoped discount.
        Tests\Helpers\ApiClient::request('guest/cart/add_item', ['id' => $productA, 'multiple' => 1]);
        $applied = Tests\Helpers\ApiClient::request('guest/cart/apply_promo', ['promocode' => $promoCode]);
        assertApiSuccess($applied);

        $cart = bundlePromoGetCart();
        expect((float) $cart['discount'])->toEqual(5.0);

        $checkout = Tests\Helpers\ApiClient::request('client/cart/checkout', [
            'gateway_id' => bundlePromoCustomGatewayId(),
        ], 'client', $clientToken);
        assertApiSuccess($checkout);
        $orderIds = $checkout->getResult()['orders'];
        expect($orderIds)->toHaveCount(2);

        $discounted = 0;
        foreach ($orderIds as $orderId) {
            $order = bundlePromoGetOrder((int) $orderId);
            $discounted += (float) $order['discount'];
        }
        expect($discounted)->toEqual(5.0);
    } finally {
        bundlePromoCleanupClient();
        bundlePromoDeactivatePromo($promoId);
        bundlePromoDeleteProduct($productA);
        bundlePromoDeleteProduct($productB);
    }
});

test('bundle condition gates automatic promos', function (): void {
    Tests\Helpers\ApiClient::resetCookies();
    $productA = null;
    $productB = null;
    $promoId = null;
    $promoCode = 'E2EBun' . strtoupper(uniqid());

    try {
        $productA = bundlePromoCreateProduct(100.0);
        $productB = bundlePromoCreateProduct(50.0);
        $promoId = bundlePromoCreatePromo($promoCode, 'percentage', 10, [
            'active' => 1,
            'recurring' => 1,
            'auto_apply' => 1,
            'products' => [$productB],
            'requires_products' => [$productA, $productB],
        ]);

        ['token' => $clientToken, 'password' => $password, 'email' => $email]
            = bundlePromoCreateClient();

        Tests\Helpers\ApiClient::resetCookies();
        $login = Tests\Helpers\ApiClient::request('guest/client/login', ['email' => $email, 'password' => $password]);
        assertApiSuccess($login);
        // Guest add_item resets the cart unless multiple=1 is passed.
        Tests\Helpers\ApiClient::request('guest/cart/add_item', ['id' => $productB, 'multiple' => 1]);

        // Incomplete bundle: no automatic promo resolves.
        $cart = bundlePromoGetCart();
        expect((float) $cart['discount'])->toEqual(0.0);
        expect($cart['auto_promos'] ?? [])->toBe([]);

        Tests\Helpers\ApiClient::request('guest/cart/add_item', ['id' => $productA, 'multiple' => 1]);

        $cart = bundlePromoGetCart();
        expect((float) $cart['discount'])->toEqual(5.0);
        expect($cart['promo_source'] ?? null)->toBe('auto');
        expect($cart['auto_promos'])->toHaveCount(1);
        expect($cart['auto_promos'][0]['code'])->toBe($promoCode);

        $checkout = Tests\Helpers\ApiClient::request('client/cart/checkout', [
            'gateway_id' => bundlePromoCustomGatewayId(),
        ], 'client', $clientToken);
        assertApiSuccess($checkout);
        expect($checkout->getResult()['orders'])->toHaveCount(2);
    } finally {
        bundlePromoCleanupClient();
        bundlePromoDeactivatePromo($promoId);
        bundlePromoDeleteProduct($productA);
        bundlePromoDeleteProduct($productB);
    }
});

test('staff basket enforces the bundle condition on apply and checkout', function (): void {
    $clientId = bundlePromoCreateStaffClient();
    $productA = bundlePromoCreateProduct(100.0);
    $productB = bundlePromoCreateProduct(50.0);
    $promoCode = 'E2EBun' . strtoupper(uniqid());
    $promoId = bundlePromoCreatePromo($promoCode, 'percentage', 10, [
        'active' => 1,
        'recurring' => 1,
        'products' => [$productB],
        'requires_products' => [$productA, $productB],
    ]);

    try {
        $added = Tests\Helpers\ApiClient::request('admin/cart/staff_basket_add_item', [
            'client_id' => $clientId,
            'id' => $productB,
        ]);
        assertApiSuccess($added);

        $rejected = Tests\Helpers\ApiClient::request('admin/cart/staff_basket_apply_promo', [
            'client_id' => $clientId,
            'promocode' => $promoCode,
        ]);
        expect($rejected->wasSuccessful())->toBeFalse();
        expect($rejected->getErrorMessage())->toContain('requires the following products');

        $added = Tests\Helpers\ApiClient::request('admin/cart/staff_basket_add_item', [
            'client_id' => $clientId,
            'id' => $productA,
        ]);
        assertApiSuccess($added);

        $applied = Tests\Helpers\ApiClient::request('admin/cart/staff_basket_apply_promo', [
            'client_id' => $clientId,
            'promocode' => $promoCode,
        ]);
        assertApiSuccess($applied);

        $basket = Tests\Helpers\ApiClient::request('admin/cart/staff_basket_get', ['client_id' => $clientId]);
        assertApiSuccess($basket);
        expect((float) $basket->getResult()['discount'])->toEqual(5.0);

        $checkout = Tests\Helpers\ApiClient::request('admin/cart/staff_basket_checkout', ['client_id' => $clientId]);
        assertApiSuccess($checkout);
        $result = $checkout->getResult();
        expect($result['orders'])->toHaveCount(2);

        $invoice = bundlePromoGetInvoice((int) $result['invoice_id']);
        $discount = 0.0;
        foreach ($invoice['lines'] ?? [] as $line) {
            if (($line['unit'] ?? null) === 'discount') {
                $discount += (float) ($line['price'] ?? 0);
            }
        }
        expect($discount)->toEqual(-5.0);
    } finally {
        // Tear down in dependency order: the checked-out orders belong to
        // the client, so discard any leftover basket and delete the client
        // before deactivating the promo and deleting the products.
        Tests\Helpers\ApiClient::request('admin/cart/staff_basket_discard', ['client_id' => $clientId]);
        bundlePromoCleanupClient($clientId);
        bundlePromoDeactivatePromo($promoId);
        bundlePromoDeleteProduct($productA);
        bundlePromoDeleteProduct($productB);
    }
});

function bundlePromoCreateProduct(float $price): int
{
    $result = Tests\Helpers\ApiClient::request('admin/product/prepare', [
        'title' => 'E2E Bundle Product ' . uniqid(),
        'type' => 'custom',
        'product_category_id' => 1,
    ]);
    assertApiSuccess($result);
    assertApiResultIsInt($result);
    $productId = (int) $result->getResult();

    $update = Tests\Helpers\ApiClient::request('admin/product/update', [
        'id' => $productId,
        'status' => 'enabled',
        'pricing' => ['type' => 'once', 'once' => ['price' => $price, 'setup' => 0]],
    ]);
    assertApiSuccess($update);

    return $productId;
}

function bundlePromoCreatePromo(string $code, string $type, float $value, array $flags): int
{
    $result = Tests\Helpers\ApiClient::request('admin/product/promo_create', [
        'code' => $code,
        'type' => $type,
        'value' => $value,
        ...$flags,
    ]);
    assertApiSuccess($result);
    assertApiResultIsInt($result);

    return (int) $result->getResult();
}

function bundlePromoCreateClient(): array
{
    $email = 'client_' . uniqid() . '@example.com';
    $password = 'A1a' . bin2hex(random_bytes(6));
    $created = Tests\Helpers\ApiClient::request('admin/client/create', [
        'email' => $email,
        'first_name' => 'Test',
        'password' => $password,
        'send_welcome_email' => 0,
    ]);
    assertApiSuccess($created);
    $GLOBALS['bundlePromoClientId'] = (int) $created->getResult();

    Tests\Helpers\ApiClient::resetCookies();
    $token = Tests\Helpers\ApiClient::request('admin/profile/api_key_reset', [
        'id' => $GLOBALS['bundlePromoClientId'],
    ]);
    assertApiSuccess($token);

    return [
        'id' => $GLOBALS['bundlePromoClientId'],
        'token' => (string) $token->getResult(),
        'password' => $password,
        'email' => $email,
    ];
}

function bundlePromoCreateStaffClient(): int
{
    $created = Tests\Helpers\ApiClient::request('admin/client/create', [
        'email' => 'basket_' . uniqid() . '@example.com',
        'first_name' => 'Test',
        'password' => 'A1a' . bin2hex(random_bytes(6)),
        'send_welcome_email' => 0,
    ]);
    assertApiSuccess($created);
    assertApiResultIsInt($created);

    return (int) $created->getResult();
}

function bundlePromoGetCart(): array
{
    $result = Tests\Helpers\ApiClient::request('guest/cart/get');
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return $result->getResult();
}

function bundlePromoGetOrder(int $orderId): array
{
    $result = Tests\Helpers\ApiClient::request('admin/order/get', ['id' => $orderId]);
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return $result->getResult();
}

function bundlePromoGetInvoice(int $invoiceId): array
{
    $result = Tests\Helpers\ApiClient::request('admin/invoice/get', ['id' => $invoiceId]);
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return $result->getResult();
}

function bundlePromoCustomGatewayId(): int
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

function bundlePromoCleanupClient(?int $clientId = null): void
{
    $clientId ??= $GLOBALS['bundlePromoClientId'] ?? null;
    if ($clientId === null) {
        return;
    }

    $deleted = Tests\Helpers\ApiClient::request('admin/client/delete', ['id' => $clientId]);
    assertApiSuccess($deleted);
    unset($GLOBALS['bundlePromoClientId']);
}

function bundlePromoDeactivatePromo(?int $promoId): void
{
    if ($promoId === null) {
        return;
    }

    // Promos with redemption history cannot be deleted; disable instead so
    // later runs and other suites never match a stale automatic promo.
    $result = Tests\Helpers\ApiClient::request('admin/product/promo_update', [
        'id' => $promoId,
        'active' => 0,
        'auto_apply' => 0,
    ]);
    assertApiSuccess($result);
}

function bundlePromoDeleteProduct(?int $productId): void
{
    if ($productId === null) {
        return;
    }

    $result = Tests\Helpers\ApiClient::request('admin/product/delete', ['id' => $productId]);
    assertApiSuccess($result);
}
