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

test('refunding a paid invoice issues a linked credit note and settles the original', function (): void {
    Tests\Helpers\ApiClient::resetCookies();
    $productId = null;

    try {
        $productId = creditNoteCreateProduct(100.0);
        ['id' => $clientId, 'token' => $clientToken] = creditNoteCreateClient();

        $created = Tests\Helpers\ApiClient::request('admin/order/create', [
            'client_id' => $clientId,
            'product_id' => $productId,
            'invoice_option' => 'issue-invoice',
        ]);
        assertApiSuccess($created);
        assertApiResultIsInt($created);
        $orderId = (int) $created->getResult();

        $order = creditNoteGetOrder($orderId);
        $invoiceId = (int) $order['unpaid_invoice_id'];

        creditNoteMarkInvoicePaid($invoiceId);

        // Force credit-note numbering regardless of the box defaults.
        $params = Tests\Helpers\ApiClient::request('admin/system/get_params');
        assertApiSuccess($params);
        $originalLogic = $params->getResult()['invoice_refund_logic'] ?? 'credit_note';
        $originalSeries = $params->getResult()['invoice_cn_series'] ?? 'CN-';
        $setLogic = Tests\Helpers\ApiClient::request('admin/system/update_params', [
            'invoice_refund_logic' => 'credit_note',
            'invoice_cn_series' => 'CN-',
        ]);
        assertApiSuccess($setLogic);

        try {
            $refunded = Tests\Helpers\ApiClient::request('admin/invoice/refund', ['id' => $invoiceId]);
            assertApiSuccess($refunded);
            assertApiResultIsInt($refunded);
            $creditNoteId = (int) $refunded->getResult();
            expect($creditNoteId)->not->toBe($invoiceId);

            $creditNote = creditNoteGetInvoice($creditNoteId);
            expect($creditNote['status'])->toBe('refunded');
            expect((int) $creditNote['credit_note_for_invoice_id'])->toBe($invoiceId);
            expect($creditNote['serie'])->toBe('CN-');
            expect((int) $creditNote['nr'])->toBeGreaterThan(0);
            expect((float) $creditNote['total'])->toBeLessThan(0);

            $original = creditNoteGetInvoice($invoiceId);
            expect($original['status'])->toBe('refunded');
            expect($original['refunded_by_invoice_ids'])->toContain($creditNoteId);
            expect((float) $original['refunded_total'])->toEqual((float) $original['total']);

            // A settled invoice cannot be refunded again.
            $again = Tests\Helpers\ApiClient::request('admin/invoice/refund', ['id' => $invoiceId]);
            expect($again->wasSuccessful())->toBeFalse();
            expect($again->getErrorMessage())->toContain('Only paid invoices');

            // The client sees the credit note under refunded invoices.
            $list = Tests\Helpers\ApiClient::request('client/invoice/get_list', [
                'status' => 'refunded',
                'per_page' => 10,
            ], 'client', $clientToken);
            assertApiSuccess($list);
            $ids = array_map(fn (array $row): int => (int) $row['id'], $list->getResult()['list'] ?? []);
            expect($ids)->toContain($creditNoteId);
        } finally {
            $restore = Tests\Helpers\ApiClient::request('admin/system/update_params', [
                'invoice_refund_logic' => $originalLogic,
                'invoice_cn_series' => $originalSeries,
            ]);
            assertApiSuccess($restore);
        }
    } finally {
        creditNoteCleanupClient();
        creditNoteDeleteProduct($productId);
    }
});

function creditNoteCreateProduct(float $price): int
{
    $result = Tests\Helpers\ApiClient::request('admin/product/prepare', [
        'title' => 'E2E Credit Note Product ' . uniqid(),
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

function creditNoteCreateClient(): array
{
    $email = 'credit_note_client_' . uniqid() . '@example.com';
    $created = Tests\Helpers\ApiClient::request('admin/client/create', [
        'email' => $email,
        'first_name' => 'Test',
        'password' => 'A1a' . bin2hex(random_bytes(6)),
        'send_welcome_email' => 0,
    ]);
    assertApiSuccess($created);
    $GLOBALS['creditNoteClientId'] = (int) $created->getResult();

    Tests\Helpers\ApiClient::resetCookies();
    $token = Tests\Helpers\ApiClient::request('admin/profile/api_key_reset', [
        'id' => $GLOBALS['creditNoteClientId'],
    ]);
    assertApiSuccess($token);

    return [
        'id' => $GLOBALS['creditNoteClientId'],
        'token' => (string) $token->getResult(),
    ];
}

function creditNoteGetOrder(int $orderId): array
{
    $result = Tests\Helpers\ApiClient::request('admin/order/get', ['id' => $orderId]);
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return $result->getResult();
}

function creditNoteGetInvoice(int $invoiceId): array
{
    $result = Tests\Helpers\ApiClient::request('admin/invoice/get', ['id' => $invoiceId]);
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return $result->getResult();
}

function creditNoteMarkInvoicePaid(int $invoiceId): void
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

function creditNoteCleanupClient(): void
{
    if (!isset($GLOBALS['creditNoteClientId'])) {
        return;
    }

    $deleted = Tests\Helpers\ApiClient::request('admin/client/delete', ['id' => $GLOBALS['creditNoteClientId']]);
    assertApiSuccess($deleted);
    unset($GLOBALS['creditNoteClientId']);
}

function creditNoteDeleteProduct(?int $productId): void
{
    if ($productId === null) {
        return;
    }

    $deleted = Tests\Helpers\ApiClient::request('admin/product/delete', ['id' => $productId]);
    assertApiSuccess($deleted);
}

test('partial refunds accumulate on the original until fully refunded', function (): void {
    Tests\Helpers\ApiClient::resetCookies();

    try {
        ['id' => $clientId] = creditNoteCreateClient();

        $prepared = Tests\Helpers\ApiClient::request('admin/invoice/prepare', ['client_id' => $clientId]);
        assertApiSuccess($prepared);
        assertApiResultIsInt($prepared);
        $invoiceId = (int) $prepared->getResult();

        foreach ([['E2E line A', 60.0], ['E2E line B', 40.0]] as [$title, $price]) {
            $added = Tests\Helpers\ApiClient::request('admin/invoice/update', [
                'id' => $invoiceId,
                'new_item' => ['title' => $title, 'price' => $price, 'quantity' => 1],
            ]);
            assertApiSuccess($added);
        }

        $approved = Tests\Helpers\ApiClient::request('admin/invoice/approve', ['id' => $invoiceId]);
        assertApiSuccess($approved);
        creditNoteMarkInvoicePaid($invoiceId);

        $params = Tests\Helpers\ApiClient::request('admin/system/get_params');
        assertApiSuccess($params);
        $originalLogic = $params->getResult()['invoice_refund_logic'] ?? 'credit_note';
        $setLogic = Tests\Helpers\ApiClient::request('admin/system/update_params', [
            'invoice_refund_logic' => 'credit_note',
        ]);
        assertApiSuccess($setLogic);

        try {
            $invoice = creditNoteGetInvoice($invoiceId);
            $lineIds = [];
            foreach ($invoice['lines'] as $line) {
                $lineIds[$line['title']] = (int) $line['id'];
            }

            $first = Tests\Helpers\ApiClient::request('admin/invoice/refund', [
                'id' => $invoiceId,
                'items' => [$lineIds['E2E line B'] => 1],
            ]);
            assertApiSuccess($first);
            $firstCnId = (int) $first->getResult();

            $firstCn = creditNoteGetInvoice($firstCnId);
            expect((float) $firstCn['total'])->toEqual(-40.0);

            $invoice = creditNoteGetInvoice($invoiceId);
            expect($invoice['status'])->toBe('paid');
            expect((float) $invoice['refunded_total'])->toEqual(40.0);
            expect((float) $invoice['remaining_refundable'])->toEqual(60.0);
            expect($invoice['refunded_by_invoice_ids'])->toContain($firstCnId);

            $second = Tests\Helpers\ApiClient::request('admin/invoice/refund', [
                'id' => $invoiceId,
                'items' => [$lineIds['E2E line A'] => 1],
            ]);
            assertApiSuccess($second);

            $invoice = creditNoteGetInvoice($invoiceId);
            expect($invoice['status'])->toBe('refunded');
            expect((float) $invoice['refunded_total'])->toEqual(100.0);
            expect($invoice['refunded_by_invoice_ids'])->toHaveCount(2);
        } finally {
            $restore = Tests\Helpers\ApiClient::request('admin/system/update_params', [
                'invoice_refund_logic' => $originalLogic,
            ]);
            assertApiSuccess($restore);
        }
    } finally {
        creditNoteCleanupClient();
    }
});
