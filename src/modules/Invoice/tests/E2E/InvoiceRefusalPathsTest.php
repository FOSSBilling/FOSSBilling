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

test('promotions are refused on issued invoices', function (): void {
    Tests\Helpers\ApiClient::resetCookies();
    $productId = null;
    $promoId = null;

    try {
        $productId = refPathCreateProduct(100.0);
        $promoCode = 'E2ERef' . strtoupper(uniqid());
        $promoId = refPathCreatePromo($promoCode);
        ['id' => $clientId] = refPathCreateClient();

        // Promo edits happen on the draft.
        $created = Tests\Helpers\ApiClient::request('admin/order/create', [
            'client_id' => $clientId,
            'product_id' => $productId,
            'invoice_option' => 'no-invoice',
        ]);
        assertApiSuccess($created);
        assertApiResultIsInt($created);
        $orderId = (int) $created->getResult();

        $draft = Tests\Helpers\ApiClient::request('admin/invoice/prepare', ['client_id' => $clientId]);
        assertApiSuccess($draft);
        assertApiResultIsInt($draft);
        $draftId = (int) $draft->getResult();

        $attached = Tests\Helpers\ApiClient::request('admin/invoice/attach_order', [
            'id' => $draftId,
            'order_id' => $orderId,
        ]);
        assertApiSuccess($attached);

        $added = Tests\Helpers\ApiClient::request('admin/invoice/promo_add', [
            'id' => $draftId,
            'promo_code' => $promoCode,
            'order_id' => $orderId,
        ]);
        assertApiSuccess($added);

        $issued = Tests\Helpers\ApiClient::request('admin/invoice/issue', ['id' => $draftId]);
        assertApiSuccess($issued);

        $journalBefore = refPathJournalTypes($draftId);

        // Issued: attaching another promo is refused.
        $addBlocked = Tests\Helpers\ApiClient::request('admin/invoice/promo_add', [
            'id' => $draftId,
            'promo_code' => $promoCode,
            'order_id' => $orderId,
        ]);
        expect($addBlocked->wasSuccessful())->toBeFalse();
        expect($addBlocked->getErrorMessage())->toContain('can no longer be edited');

        // Issued: removing the applied promo is refused the same way.
        $removeBlocked = Tests\Helpers\ApiClient::request('admin/invoice/promo_remove', [
            'id' => $draftId,
            'promo_id' => $promoId,
            'order_id' => $orderId,
        ]);
        expect($removeBlocked->wasSuccessful())->toBeFalse();
        expect($removeBlocked->getErrorMessage())->toContain('can no longer be edited');

        // Refusals write no journal rows.
        expect(refPathJournalTypes($draftId))->toBe($journalBefore);
    } finally {
        refPathCleanupClient();
        refPathDeactivatePromo($promoId);
        refPathDeleteProduct($productId);
    }
});

test('processed transactions freeze their money history', function (): void {
    Tests\Helpers\ApiClient::resetCookies();
    $productId = null;

    try {
        $productId = refPathCreateProduct(50.0);
        ['id' => $clientId] = refPathCreateClient();

        $created = Tests\Helpers\ApiClient::request('admin/order/create', [
            'client_id' => $clientId,
            'product_id' => $productId,
            'invoice_option' => 'issue-invoice',
        ]);
        assertApiSuccess($created);
        assertApiResultIsInt($created);
        $orderId = (int) $created->getResult();
        $order = refPathGetOrder($orderId);
        $invoiceId = (int) $order['unpaid_invoice_id'];

        refPathMarkInvoicePaid($invoiceId);

        $list = Tests\Helpers\ApiClient::request('admin/invoice/transaction_get_list', ['invoice_id' => $invoiceId]);
        assertApiSuccess($list);
        assertApiResultIsArray($list);
        $txId = (int) $list->getResult()['list'][0]['id'];

        // Money fields are frozen once processed.
        $amountBlocked = Tests\Helpers\ApiClient::request('admin/invoice/transaction_update', [
            'id' => $txId,
            'amount' => '1.00',
        ]);
        expect($amountBlocked->wasSuccessful())->toBeFalse();
        expect($amountBlocked->getErrorMessage())->toContain('already moved');

        // Operational annotations still work.
        $noted = Tests\Helpers\ApiClient::request('admin/invoice/transaction_update', [
            'id' => $txId,
            'note' => 'E2E annotation',
        ]);
        assertApiSuccess($noted);

        // A refunded invoice cannot accept transactions: relinking onto it is refused.
        // Manual mode records the refund as an offline document on the credit note.
        $params = Tests\Helpers\ApiClient::request('admin/system/get_params');
        assertApiSuccess($params);
        // There is no API to delete a setting, so when the key never existed
        // restore the service default its readers would have observed.
        $allParams = $params->getResult();
        $originalLogic = array_key_exists('invoice_refund_logic', $allParams)
            ? $allParams['invoice_refund_logic']
            : 'manual';
        $manual = Tests\Helpers\ApiClient::request('admin/system/update_params', [
            'invoice_refund_logic' => 'manual',
        ]);
        assertApiSuccess($manual);

        try {
            $refunded = Tests\Helpers\ApiClient::request('admin/invoice/refund', ['id' => $invoiceId]);
            assertApiSuccess($refunded);
            assertApiResultIsInt($refunded);
            $creditNoteId = (int) $refunded->getResult();
        } finally {
            $restore = Tests\Helpers\ApiClient::request('admin/system/update_params', [
                'invoice_refund_logic' => $originalLogic,
            ]);
            assertApiSuccess($restore);
        }

        // The journal marks the offline refund and links its credit note.
        $journal = Tests\Helpers\ApiClient::request('admin/invoice/journal', ['id' => $invoiceId]);
        assertApiSuccess($journal);
        assertApiResultIsArray($journal);
        $refundedEvents = array_values(array_filter(
            $journal->getResult(),
            fn (array $entry): bool => ($entry['type'] ?? null) === 'refunded'
        ));
        expect($refundedEvents)->toHaveCount(1);
        expect($refundedEvents[0]['snapshot']['offline'] ?? null)->toBeTrue();
        expect((int) ($refundedEvents[0]['snapshot']['credit_note_id'] ?? 0))->toBe($creditNoteId);
        // Frozen at refund time: later gateway renames must not rewrite history.
        expect($refundedEvents[0]['snapshot']['gateway'] ?? null)->toBe('Custom');

        $second = Tests\Helpers\ApiClient::request('admin/invoice/prepare', ['client_id' => $clientId]);
        assertApiSuccess($second);
        assertApiResultIsInt($second);
        $secondId = (int) $second->getResult();

        $gatewayId = refPathCustomGatewayId();
        $newTx = Tests\Helpers\ApiClient::request('admin/invoice/transaction_create', [
            'invoice_id' => $secondId,
            'gateway_id' => $gatewayId,
        ]);
        assertApiSuccess($newTx);
        assertApiResultIsInt($newTx);

        $relinkBlocked = Tests\Helpers\ApiClient::request('admin/invoice/transaction_update', [
            'id' => (int) $newTx->getResult(),
            'invoice_id' => $invoiceId,
        ]);
        expect($relinkBlocked->wasSuccessful())->toBeFalse();
        expect($relinkBlocked->getErrorMessage())->toContain('canceled, refunded, or replaced');
    } finally {
        refPathCleanupClient();
        refPathDeleteProduct($productId);
    }
});

test('claiming and processing are idempotent', function (): void {
    Tests\Helpers\ApiClient::resetCookies();
    $productId = null;

    try {
        $productId = refPathCreateProduct(25.0);
        ['id' => $clientId] = refPathCreateClient();

        $created = Tests\Helpers\ApiClient::request('admin/order/create', [
            'client_id' => $clientId,
            'product_id' => $productId,
            'invoice_option' => 'issue-invoice',
        ]);
        assertApiSuccess($created);
        assertApiResultIsInt($created);
        $order = refPathGetOrder((int) $created->getResult());
        $invoiceId = (int) $order['unpaid_invoice_id'];

        refPathMarkInvoicePaid($invoiceId);

        $list = Tests\Helpers\ApiClient::request('admin/invoice/transaction_get_list', ['invoice_id' => $invoiceId]);
        assertApiSuccess($list);
        assertApiResultIsArray($list);
        $txId = (int) $list->getResult()['list'][0]['id'];
        $journalBefore = refPathJournalTypes($invoiceId);
        // The paid event landed in the same transaction as the payment;
        // the retry assertions below prove nothing is added to it.
        expect($journalBefore)->toContain('paid');

        // Already processed: the claim reports false instead of erroring.
        $claim = Tests\Helpers\ApiClient::request('admin/invoice/transaction_claim_for_processing', ['id' => $txId]);
        assertApiSuccess($claim);
        expect($claim->getResult())->toBeFalse();

        // Reprocessing a processed transaction stays a success.
        $process = Tests\Helpers\ApiClient::request('admin/invoice/transaction_process', ['id' => $txId]);
        assertApiSuccess($process);

        // Idempotent retries add no journal rows.
        expect(refPathJournalTypes($invoiceId))->toBe($journalBefore);
    } finally {
        refPathCleanupClient();
        refPathDeleteProduct($productId);
    }
});

test('canceled invoices refuse payment and issued invoices lock their identity', function (): void {
    Tests\Helpers\ApiClient::resetCookies();

    try {
        ['id' => $clientId] = refPathCreateClient();

        $draft = Tests\Helpers\ApiClient::request('admin/invoice/prepare', [
            'client_id' => $clientId,
            'items' => [['title' => 'E2E service', 'price' => 30, 'quantity' => 1]],
        ]);
        assertApiSuccess($draft);
        assertApiResultIsInt($draft);
        $invoiceId = (int) $draft->getResult();

        $issued = Tests\Helpers\ApiClient::request('admin/invoice/issue', ['id' => $invoiceId]);
        assertApiSuccess($issued);

        // The number is locked once issued: even in relaxed mode (where
        // issued invoices are editable) the identity guard refuses it.
        $params = Tests\Helpers\ApiClient::request('admin/system/get_params');
        assertApiSuccess($params);
        $originalSetting = $params->getResult()['invoice_immutability'] ?? 'strict';
        $relaxed = Tests\Helpers\ApiClient::request('admin/system/update_params', [
            'invoice_immutability' => 'relaxed',
        ]);
        assertApiSuccess($relaxed);

        try {
            $nrBlocked = Tests\Helpers\ApiClient::request('admin/invoice/update', [
                'id' => $invoiceId,
                'nr' => '99999',
            ]);
            expect($nrBlocked->wasSuccessful())->toBeFalse();
            expect($nrBlocked->getErrorMessage())->toContain('locked once issued');
        } finally {
            $restore = Tests\Helpers\ApiClient::request('admin/system/update_params', [
                'invoice_immutability' => $originalSetting,
            ]);
            assertApiSuccess($restore);
        }

        $canceled = Tests\Helpers\ApiClient::request('admin/invoice/cancel', [
            'id' => $invoiceId,
            'reason' => 'E2E void',
        ]);
        assertApiSuccess($canceled);

        // A void invoice cannot be paid.
        $payBlocked = Tests\Helpers\ApiClient::request('admin/invoice/mark_as_paid', [
            'id' => $invoiceId,
            'gateway_id' => refPathCustomGatewayId(),
            'transactionId' => 'txn' . uniqid(),
        ]);
        expect($payBlocked->wasSuccessful())->toBeFalse();
        expect($payBlocked->getErrorMessage())->toContain('canceled and cannot be marked as paid');
    } finally {
        refPathCleanupClient();
    }
});

function refPathCreateProduct(float $price): int
{
    $result = Tests\Helpers\ApiClient::request('admin/product/prepare', [
        'title' => 'E2E Refusal Product ' . uniqid(),
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

function refPathCreatePromo(string $code): int
{
    $result = Tests\Helpers\ApiClient::request('admin/product/promo_create', [
        'code' => $code,
        'type' => 'absolute',
        'value' => 10,
        'active' => 1,
        'recurring' => 1,
    ]);
    assertApiSuccess($result);
    assertApiResultIsInt($result);

    return (int) $result->getResult();
}

function refPathCreateClient(): array
{
    $email = 'invoice_refusal_' . uniqid() . '@example.com';
    $created = Tests\Helpers\ApiClient::request('admin/client/create', [
        'email' => $email,
        'first_name' => 'Test',
        'password' => 'A1a' . bin2hex(random_bytes(6)),
        'send_welcome_email' => 0,
    ]);
    assertApiSuccess($created);
    $GLOBALS['refPathClientId'] = (int) $created->getResult();

    return ['id' => $GLOBALS['refPathClientId']];
}

function refPathGetOrder(int $orderId): array
{
    $result = Tests\Helpers\ApiClient::request('admin/order/get', ['id' => $orderId]);
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return $result->getResult();
}

function refPathJournalTypes(int $invoiceId): array
{
    $result = Tests\Helpers\ApiClient::request('admin/invoice/journal', ['id' => $invoiceId]);
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return array_column($result->getResult(), 'type');
}

function refPathCustomGatewayId(): int
{
    $gateways = Tests\Helpers\ApiClient::request('admin/invoice/gateway_get_pairs');
    assertApiSuccess($gateways);
    foreach ($gateways->getResult() as $id => $title) {
        if ($title === 'Custom') {
            return (int) $id;
        }
    }

    throw new RuntimeException('Custom payment gateway not found');
}

function refPathMarkInvoicePaid(int $invoiceId): void
{
    $result = Tests\Helpers\ApiClient::request('admin/invoice/mark_as_paid', [
        'id' => $invoiceId,
        'gateway_id' => refPathCustomGatewayId(),
        'transactionId' => 'txn' . uniqid(),
    ]);
    assertApiSuccess($result);
}

function refPathCleanupClient(): void
{
    if (!isset($GLOBALS['refPathClientId'])) {
        return;
    }

    $deleted = Tests\Helpers\ApiClient::request('admin/client/delete', ['id' => $GLOBALS['refPathClientId']]);
    assertApiSuccess($deleted);
    unset($GLOBALS['refPathClientId']);
}

function refPathDeactivatePromo(?int $promoId): void
{
    if ($promoId === null) {
        return;
    }

    $result = Tests\Helpers\ApiClient::request('admin/product/promo_update', [
        'id' => $promoId,
        'active' => 0,
        'auto_apply' => 0,
    ]);
    assertApiSuccess($result);
}

function refPathDeleteProduct(?int $productId): void
{
    if ($productId === null) {
        return;
    }

    $deleted = Tests\Helpers\ApiClient::request('admin/product/delete', ['id' => $productId]);
    assertApiSuccess($deleted);
}
