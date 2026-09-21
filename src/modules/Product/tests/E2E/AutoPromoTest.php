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

test('automatic promo applies at cart display and checkout without a code', function (): void {
    Tests\Helpers\ApiClient::resetCookies();
    $productId = null;
    $promoId = null;
    $promoCode = 'E2EAuto' . strtoupper(uniqid());

    try {
        $productId = autoPromoCreateProduct(100.0);
        $promoId = autoPromoCreatePromo($promoCode, 'percentage', 50, ['active' => 1, 'recurring' => 1, 'auto_apply' => 1]);

        ['id' => $clientId, 'token' => $clientToken, 'password' => $password, 'email' => $email]
            = autoPromoCreateClient();

        // Guest carts see no automatic promos: eligibility needs a client.
        Tests\Helpers\ApiClient::request('guest/cart/add_item', ['id' => $productId]);
        $guestCart = autoPromoGetCart();
        expect((float) $guestCart['discount'])->toEqual(0.0);
        expect($guestCart['auto_promos'] ?? [])->toBe([]);

        // The same cart content discounts itself once the client is known.
        Tests\Helpers\ApiClient::resetCookies();
        $login = Tests\Helpers\ApiClient::request('guest/client/login', ['email' => $email, 'password' => $password]);
        assertApiSuccess($login);
        Tests\Helpers\ApiClient::request('guest/cart/add_item', ['id' => $productId]);

        $cart = autoPromoGetCart();
        expect((float) $cart['discount'])->toEqual(50.0);
        expect($cart['promo_source'] ?? null)->toBe('auto');
        expect($cart['auto_promos'])->toHaveCount(1);
        expect($cart['auto_promos'][0]['code'])->toBe($promoCode);

        $checkout = Tests\Helpers\ApiClient::request('client/cart/checkout', [
            'gateway_id' => autoPromoCustomGatewayId(),
        ], 'client', $clientToken);
        assertApiSuccess($checkout);
        $orderIds = $checkout->getResult()['orders'];
        expect($orderIds)->toHaveCount(1);

        $order = autoPromoGetOrder((int) $orderIds[0]);
        expect((int) $order['promo_id'])->toBe($promoId);
        expect((float) $order['discount'])->toEqual(50.0);

        $invoice = autoPromoGetInvoice((int) $order['unpaid_invoice_id']);
        expect(autoPromoHasDiscountLine($invoice, (int) $orderIds[0], -50.0))->toBeTrue();

        $applications = $invoice['promo_applications'] ?? [];
        expect($applications)->toHaveCount(1);
        expect($applications[0]['code'])->toBe($promoCode);
        expect($applications[0]['removable'])->toBeTrue();
    } finally {
        autoPromoCleanupClient();
        autoPromoDeactivatePromo($promoId);
        autoPromoDeleteProduct($productId);
    }
});

test('admin can apply and remove promos on orders and unpaid invoices', function (): void {
    Tests\Helpers\ApiClient::resetCookies();
    $productId = null;
    $promoId = null;
    $promoCode = 'E2EAdm' . strtoupper(uniqid());

    try {
        $productId = autoPromoCreateProduct(100.0);
        // Manual (non-automatic) recurring promo applied by the admin below.
        $promoId = autoPromoCreatePromo($promoCode, 'absolute', 15, ['active' => 1, 'recurring' => 1]);
        ['id' => $clientId] = autoPromoCreateClient();

        $created = Tests\Helpers\ApiClient::request('admin/order/create', [
            'client_id' => $clientId,
            'product_id' => $productId,
            'invoice_option' => 'issue-invoice',
            'promo_code' => $promoCode,
        ]);
        assertApiSuccess($created);
        assertApiResultIsInt($created);
        $orderId = (int) $created->getResult();

        $order = autoPromoGetOrder($orderId);
        expect((int) $order['promo_id'])->toBe($promoId);
        expect((float) $order['discount'])->toEqual(15.0);

        $invoice = autoPromoGetInvoice((int) $order['unpaid_invoice_id']);
        expect(autoPromoHasDiscountLine($invoice, $orderId, -15.0))->toBeTrue();

        $removed = Tests\Helpers\ApiClient::request('admin/invoice/promo_remove', [
            'id' => $order['unpaid_invoice_id'],
            'promo_id' => $promoId,
            'order_id' => $orderId,
        ]);
        assertApiSuccess($removed);
        expect((float) $removed->getResult())->toEqual(15.0);

        $order = autoPromoGetOrder($orderId);
        expect((float) $order['discount'])->toEqual(0.0);
        $invoice = autoPromoGetInvoice((int) $order['unpaid_invoice_id']);
        expect(autoPromoHasDiscountLine($invoice, $orderId, -15.0))->toBeFalse();

        $added = Tests\Helpers\ApiClient::request('admin/invoice/promo_add', [
            'id' => $order['unpaid_invoice_id'],
            'promo_code' => $promoCode,
            'order_id' => $orderId,
        ]);
        assertApiSuccess($added);
        expect((float) $added->getResult())->toEqual(15.0);

        // Pay the checkout invoice first: renewing against an unpaid invoice
        // would simply return that same invoice instead of a renewal.
        autoPromoMarkInvoicePaid((int) $order['unpaid_invoice_id']);

        // Recurring promos carry forward to renewal invoices.
        $renewal = Tests\Helpers\ApiClient::request('admin/invoice/renewal_invoice', ['id' => $orderId]);
        assertApiSuccess($renewal);
        expect((int) $renewal->getResult())->not->toBe((int) $order['unpaid_invoice_id']);
        $renewalInvoice = autoPromoGetInvoice((int) $renewal->getResult());
        expect(autoPromoHasDiscountLine($renewalInvoice, $orderId, -15.0))->toBeTrue();
    } finally {
        autoPromoCleanupClient();
        autoPromoDeactivatePromo($promoId);
        autoPromoDeleteProduct($productId);
    }
});

function autoPromoCreateProduct(float $price): int
{
    $result = Tests\Helpers\ApiClient::request('admin/product/prepare', [
        'title' => 'E2E Promo Product ' . uniqid(),
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

function autoPromoCreatePromo(string $code, string $type, float $value, array $flags): int
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

function autoPromoCreateClient(): array
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
    $GLOBALS['autoPromoClientId'] = (int) $created->getResult();

    Tests\Helpers\ApiClient::resetCookies();
    $token = Tests\Helpers\ApiClient::request('admin/profile/api_key_reset', [
        'id' => $GLOBALS['autoPromoClientId'],
    ]);
    assertApiSuccess($token);

    return [
        'id' => $GLOBALS['autoPromoClientId'],
        'token' => (string) $token->getResult(),
        'password' => $password,
        'email' => $email,
    ];
}

function autoPromoGetCart(): array
{
    $result = Tests\Helpers\ApiClient::request('guest/cart/get');
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return $result->getResult();
}

function autoPromoGetOrder(int $orderId): array
{
    $result = Tests\Helpers\ApiClient::request('admin/order/get', ['id' => $orderId]);
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return $result->getResult();
}

function autoPromoGetInvoice(int $invoiceId): array
{
    $result = Tests\Helpers\ApiClient::request('admin/invoice/get', ['id' => $invoiceId]);
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return $result->getResult();
}

function autoPromoHasDiscountLine(array $invoice, int $orderId, float $price): bool
{
    foreach ($invoice['lines'] ?? [] as $line) {
        if (($line['unit'] ?? null) === 'discount'
            && (int) ($line['rel_id'] ?? 0) === $orderId
            && (float) ($line['price'] ?? 0) === $price
        ) {
            return true;
        }
    }

    return false;
}

function autoPromoMarkInvoicePaid(int $invoiceId): void
{
    $result = Tests\Helpers\ApiClient::request('admin/invoice/mark_as_paid', [
        'id' => $invoiceId,
        'gateway_id' => autoPromoCustomGatewayId(),
        'transactionId' => 'txn' . uniqid(),
    ]);
    assertApiSuccess($result);
}

function autoPromoCustomGatewayId(): int
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

function autoPromoCleanupClient(): void
{
    if (!isset($GLOBALS['autoPromoClientId'])) {
        return;
    }

    $deleted = Tests\Helpers\ApiClient::request('admin/client/delete', ['id' => $GLOBALS['autoPromoClientId']]);
    assertApiSuccess($deleted);
    unset($GLOBALS['autoPromoClientId']);
}

function autoPromoDeactivatePromo(?int $promoId): void
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

function autoPromoDeleteProduct(?int $productId): void
{
    if ($productId === null) {
        return;
    }

    $result = Tests\Helpers\ApiClient::request('admin/product/delete', ['id' => $productId]);
    assertApiSuccess($result);
}

test('stacked recurring promos renew without repeating a one-time primary', function (): void {
    Tests\Helpers\ApiClient::resetCookies();
    $productId = null;
    $promoAId = null;
    $promoBId = null;
    $originalMode = 'best_single';

    try {
        $params = Tests\Helpers\ApiClient::request('admin/system/get_params');
        assertApiSuccess($params);
        $originalMode = $params->getResult()['promo_stacking_mode'] ?? 'best_single';

        $setMode = Tests\Helpers\ApiClient::request('admin/system/update_params', [
            'promo_stacking_mode' => 'stack_all_eligible',
        ]);
        assertApiSuccess($setMode);

        $productId = autoPromoCreateProduct(100.0);
        // Stackable pair: 20% recurring plus a one-time absolute promo.
        // The absolute promo is the higher value, so it becomes primary.
        $promoAId = autoPromoCreatePromo('E2EStk' . strtoupper(uniqid()), 'absolute', 25, [
            'active' => 1, 'recurring' => 0, 'auto_apply' => 1, 'stackable' => 1,
        ]);
        $promoBId = autoPromoCreatePromo('E2EStk' . strtoupper(uniqid()), 'percentage', 20, [
            'active' => 1, 'recurring' => 1, 'auto_apply' => 1, 'stackable' => 1,
        ]);

        ['id' => $clientId, 'token' => $clientToken, 'password' => $password, 'email' => $email]
            = autoPromoCreateClient();

        Tests\Helpers\ApiClient::resetCookies();
        $login = Tests\Helpers\ApiClient::request('guest/client/login', ['email' => $email, 'password' => $password]);
        assertApiSuccess($login);
        Tests\Helpers\ApiClient::request('guest/cart/add_item', ['id' => $productId]);

        $cart = autoPromoGetCart();
        expect((float) $cart['discount'])->toEqual(45.0);
        expect($cart['auto_promos'])->toHaveCount(2);

        $checkout = Tests\Helpers\ApiClient::request('client/cart/checkout', [
            'gateway_id' => autoPromoCustomGatewayId(),
        ], 'client', $clientToken);
        assertApiSuccess($checkout);
        $orderIds = $checkout->getResult()['orders'];
        expect($orderIds)->toHaveCount(1);

        $order = autoPromoGetOrder((int) $orderIds[0]);
        expect((int) $order['promo_id'])->toBe($promoAId);
        expect($order['promo_recurring'])->toBeFalse();
        expect((float) $order['discount'])->toEqual(45.0);

        // Pay the checkout invoice first: renewing against an unpaid invoice
        // would simply return that same invoice instead of a renewal. Paying
        // also commits the checkout redemptions renewals are valued from.
        autoPromoMarkInvoicePaid((int) $order['unpaid_invoice_id']);

        // Renewal repeats only the recurring stacked promo, not the one-time primary.
        $renewal = Tests\Helpers\ApiClient::request('admin/invoice/renewal_invoice', ['id' => (int) $orderIds[0]]);
        assertApiSuccess($renewal);
        expect((int) $renewal->getResult())->not->toBe((int) $order['unpaid_invoice_id']);
        $renewalInvoice = autoPromoGetInvoice((int) $renewal->getResult());

        $renewalDiscount = 0.0;
        foreach ($renewalInvoice['lines'] ?? [] as $line) {
            if (($line['unit'] ?? null) === 'discount') {
                $renewalDiscount += (float) ($line['price'] ?? 0);
            }
        }
        expect($renewalDiscount)->toEqual(-20.0);
    } finally {
        $restore = Tests\Helpers\ApiClient::request('admin/system/update_params', [
            'promo_stacking_mode' => $originalMode,
        ]);
        assertApiSuccess($restore);
        autoPromoCleanupClient();
        autoPromoDeactivatePromo($promoAId);
        autoPromoDeactivatePromo($promoBId);
        autoPromoDeleteProduct($productId);
    }
});
