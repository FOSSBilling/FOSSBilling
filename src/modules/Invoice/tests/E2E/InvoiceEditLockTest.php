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

test('issued invoices are locked for editing unless the setting permits it', function (): void {
    Tests\Helpers\ApiClient::resetCookies();
    $productId = null;

    try {
        $productId = lockEditCreateProduct(100.0);
        ['id' => $clientId] = lockEditCreateClient();

        $created = Tests\Helpers\ApiClient::request('admin/order/create', [
            'client_id' => $clientId,
            'product_id' => $productId,
            'invoice_option' => 'issue-invoice',
        ]);
        assertApiSuccess($created);
        assertApiResultIsInt($created);
        $orderId = (int) $created->getResult();

        $order = lockEditGetOrder($orderId);
        $invoiceId = (int) $order['unpaid_invoice_id'];

        $invoice = lockEditGetInvoice($invoiceId);
        expect($invoice['issued'])->toBeTrue();
        expect($invoice['editable'])->toBeFalse();

        // Locked: adding a line is refused.
        $blocked = Tests\Helpers\ApiClient::request('admin/invoice/update', [
            'id' => $invoiceId,
            'new_item' => ['title' => 'E2E late fee', 'price' => 5, 'quantity' => 1],
        ]);
        expect($blocked->wasSuccessful())->toBeFalse();
        expect($blocked->getErrorMessage())->toContain('can no longer be edited');

        // Locked: deleting the issued invoice is refused.
        $deleteBlocked = Tests\Helpers\ApiClient::request('admin/invoice/delete', ['id' => $invoiceId]);
        expect($deleteBlocked->wasSuccessful())->toBeFalse();

        // Opt in to quote-like editing of issued unpaid invoices.
        $params = Tests\Helpers\ApiClient::request('admin/system/get_params');
        assertApiSuccess($params);
        $originalSetting = $params->getResult()['invoice_immutability'] ?? 'strict';
        $enabled = Tests\Helpers\ApiClient::request('admin/system/update_params', [
            'invoice_immutability' => 'relaxed',
        ]);
        assertApiSuccess($enabled);

        try {
            $updated = Tests\Helpers\ApiClient::request('admin/invoice/update', [
                'id' => $invoiceId,
                'new_item' => ['title' => 'E2E discount line', 'price' => -10, 'quantity' => 1],
            ]);
            assertApiSuccess($updated);

            $invoice = lockEditGetInvoice($invoiceId);
            expect($invoice['editable'])->toBeTrue();
            expect(lockEditHasLine($invoice, 'E2E discount line', -10.0))->toBeTrue();
        } finally {
            $restore = Tests\Helpers\ApiClient::request('admin/system/update_params', [
                'invoice_immutability' => $originalSetting,
            ]);
            assertApiSuccess($restore);
        }

        // Paid invoices stay locked regardless of the setting.
        lockEditMarkInvoicePaid($invoiceId);
        $paidBlocked = Tests\Helpers\ApiClient::request('admin/invoice/update', [
            'id' => $invoiceId,
            'new_item' => ['title' => 'E2E after payment', 'price' => 5, 'quantity' => 1],
        ]);
        expect($paidBlocked->wasSuccessful())->toBeFalse();
        expect($paidBlocked->getErrorMessage())->toContain('can no longer be edited');
    } finally {
        lockEditCleanupClient();
        lockEditDeleteProduct($productId);
    }
});

function lockEditCreateProduct(float $price): int
{
    $result = Tests\Helpers\ApiClient::request('admin/product/prepare', [
        'title' => 'E2E Edit Lock Product ' . uniqid(),
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

function lockEditCreateClient(): array
{
    $email = 'invoice_lock_' . uniqid() . '@example.com';
    $created = Tests\Helpers\ApiClient::request('admin/client/create', [
        'email' => $email,
        'first_name' => 'Test',
        'password' => 'A1a' . bin2hex(random_bytes(6)),
        'send_welcome_email' => 0,
    ]);
    assertApiSuccess($created);
    $GLOBALS['lockEditClientId'] = (int) $created->getResult();

    return ['id' => $GLOBALS['lockEditClientId']];
}

function lockEditGetOrder(int $orderId): array
{
    $result = Tests\Helpers\ApiClient::request('admin/order/get', ['id' => $orderId]);
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return $result->getResult();
}

function lockEditGetInvoice(int $invoiceId): array
{
    $result = Tests\Helpers\ApiClient::request('admin/invoice/get', ['id' => $invoiceId]);
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return $result->getResult();
}

function lockEditHasLine(array $invoice, string $title, float $price): bool
{
    foreach ($invoice['lines'] ?? [] as $line) {
        if (($line['title'] ?? null) === $title && (float) ($line['price'] ?? 0) === $price) {
            return true;
        }
    }

    return false;
}

function lockEditMarkInvoicePaid(int $invoiceId): void
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

function lockEditCleanupClient(): void
{
    if (!isset($GLOBALS['lockEditClientId'])) {
        return;
    }

    $deleted = Tests\Helpers\ApiClient::request('admin/client/delete', ['id' => $GLOBALS['lockEditClientId']]);
    assertApiSuccess($deleted);
    unset($GLOBALS['lockEditClientId']);
}

function lockEditDeleteProduct(?int $productId): void
{
    if ($productId === null) {
        return;
    }

    $deleted = Tests\Helpers\ApiClient::request('admin/product/delete', ['id' => $productId]);
    assertApiSuccess($deleted);
}
