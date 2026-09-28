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

test('issued unpaid invoices can be canceled, and deleted when relaxed', function (): void {
    Tests\Helpers\ApiClient::resetCookies();
    $productId = null;

    try {
        $productId = cancelCreateProduct(50.0);
        ['id' => $clientId] = cancelCreateClient();

        $created = Tests\Helpers\ApiClient::request('admin/order/create', [
            'client_id' => $clientId,
            'product_id' => $productId,
            'invoice_option' => 'issue-invoice',
        ]);
        assertApiSuccess($created);
        assertApiResultIsInt($created);
        $orderId = (int) $created->getResult();

        $order = cancelGetOrder($orderId);
        $invoiceId = (int) $order['unpaid_invoice_id'];

        $invoice = cancelGetInvoice($invoiceId);
        expect($invoice['issued'])->toBeTrue();
        expect($invoice['cancellable'])->toBeTrue();

        // Issued invoices cannot be hard-deleted by default.
        $deleteBlocked = Tests\Helpers\ApiClient::request('admin/invoice/delete', ['id' => $invoiceId]);
        expect($deleteBlocked->wasSuccessful())->toBeFalse();

        // Cancel (void) without replacement.
        $canceled = Tests\Helpers\ApiClient::request('admin/invoice/cancel', [
            'id' => $invoiceId,
            'reason' => 'E2E voided',
        ]);
        assertApiSuccess($canceled);

        $invoice = cancelGetInvoice($invoiceId);
        expect($invoice['status'])->toBe('canceled');

        // Order links are kept for history.
        $order = cancelGetOrder($orderId);
        expect((int) $order['unpaid_invoice_id'])->toBe($invoiceId);

        // Canceled invoices still cannot be deleted while the setting is off.
        $deleteStillBlocked = Tests\Helpers\ApiClient::request('admin/invoice/delete', ['id' => $invoiceId]);
        expect($deleteStillBlocked->wasSuccessful())->toBeFalse();

        // Cancel is idempotent-safe: a second cancel is refused.
        $cancelAgain = Tests\Helpers\ApiClient::request('admin/invoice/cancel', ['id' => $invoiceId]);
        expect($cancelAgain->wasSuccessful())->toBeFalse();

        // Opt in to deleting issued invoices for cleanup.
        $params = Tests\Helpers\ApiClient::request('admin/system/get_params');
        assertApiSuccess($params);
        $originalSetting = $params->getResult()['invoice_immutability'] ?? 'strict';
        $enabled = Tests\Helpers\ApiClient::request('admin/system/update_params', [
            'invoice_immutability' => 'relaxed',
        ]);
        assertApiSuccess($enabled);

        try {
            $deleted = Tests\Helpers\ApiClient::request('admin/invoice/delete', ['id' => $invoiceId]);
            assertApiSuccess($deleted);
        } finally {
            $restore = Tests\Helpers\ApiClient::request('admin/system/update_params', [
                'invoice_immutability' => $originalSetting,
            ]);
            assertApiSuccess($restore);
        }

        // Paid invoices can neither be canceled nor deleted.
        $created2 = Tests\Helpers\ApiClient::request('admin/order/create', [
            'client_id' => $clientId,
            'product_id' => $productId,
            'invoice_option' => 'issue-invoice',
        ]);
        assertApiSuccess($created2);
        $orderId2 = (int) $created2->getResult();
        $order2 = cancelGetOrder($orderId2);
        $invoiceId2 = (int) $order2['unpaid_invoice_id'];
        cancelMarkInvoicePaid($invoiceId2);

        $cancelPaid = Tests\Helpers\ApiClient::request('admin/invoice/cancel', ['id' => $invoiceId2]);
        expect($cancelPaid->wasSuccessful())->toBeFalse();

        $deletePaid = Tests\Helpers\ApiClient::request('admin/invoice/delete', ['id' => $invoiceId2]);
        expect($deletePaid->wasSuccessful())->toBeFalse();
    } finally {
        cancelCleanupClient();
        cancelDeleteProduct($productId);
    }
});

function cancelCreateProduct(float $price): int
{
    $result = Tests\Helpers\ApiClient::request('admin/product/prepare', [
        'title' => 'E2E Cancel Product ' . uniqid(),
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

function cancelCreateClient(): array
{
    $email = 'invoice_cancel_' . uniqid() . '@example.com';
    $created = Tests\Helpers\ApiClient::request('admin/client/create', [
        'email' => $email,
        'first_name' => 'Test',
        'password' => 'A1a' . bin2hex(random_bytes(6)),
        'send_welcome_email' => 0,
    ]);
    assertApiSuccess($created);
    $GLOBALS['cancelClientId'] = (int) $created->getResult();

    return ['id' => $GLOBALS['cancelClientId']];
}

function cancelGetOrder(int $orderId): array
{
    $result = Tests\Helpers\ApiClient::request('admin/order/get', ['id' => $orderId]);
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return $result->getResult();
}

function cancelGetInvoice(int $invoiceId): array
{
    $result = Tests\Helpers\ApiClient::request('admin/invoice/get', ['id' => $invoiceId]);
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return $result->getResult();
}

function cancelMarkInvoicePaid(int $invoiceId): void
{
    $gateways = Tests\Helpers\ApiClient::request('admin/invoice/gateway_get_pairs');
    assertApiSuccess($gateways);
    $gatewayId = null;
    foreach ($gateways->getResult() as $id => $title) {
        if ($title === 'Custom') {
            $gatewayId = (int) $id;
        }
    }
    if ($gatewayId === null) {
        throw new RuntimeException('Custom payment gateway not found');
    }

    $result = Tests\Helpers\ApiClient::request('admin/invoice/mark_as_paid', [
        'id' => $invoiceId,
        'gateway_id' => $gatewayId,
        'transactionId' => 'txn' . uniqid(),
    ]);
    assertApiSuccess($result);
}

function cancelCleanupClient(): void
{
    if (!isset($GLOBALS['cancelClientId'])) {
        return;
    }

    $deleted = Tests\Helpers\ApiClient::request('admin/client/delete', ['id' => $GLOBALS['cancelClientId']]);
    assertApiSuccess($deleted);
    unset($GLOBALS['cancelClientId']);
}

function cancelDeleteProduct(?int $productId): void
{
    if ($productId === null) {
        return;
    }

    $deleted = Tests\Helpers\ApiClient::request('admin/product/delete', ['id' => $productId]);
    assertApiSuccess($deleted);
}
