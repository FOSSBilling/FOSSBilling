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

test('offline payments settle through approval instead of processing', function (): void {
    Tests\Helpers\ApiClient::resetCookies();

    try {
        ['id' => $clientId] = approvePathCreateClient();

        $draft = Tests\Helpers\ApiClient::request('admin/invoice/prepare', [
            'client_id' => $clientId,
            'items' => [['title' => 'E2E offline service', 'price' => 30, 'quantity' => 1]],
        ]);
        assertApiSuccess($draft);
        assertApiResultIsInt($draft);
        $invoiceId = (int) $draft->getResult();

        $issued = Tests\Helpers\ApiClient::request('admin/invoice/issue', ['id' => $invoiceId]);
        assertApiSuccess($issued);

        $created = Tests\Helpers\ApiClient::request('admin/invoice/transaction_create', [
            'invoice_id' => $invoiceId,
            'gateway_id' => approvePathCustomGatewayId(),
            // Unique payload: admin-created transactions with identical
            // envelopes share an IPN hash, and the hash dedupe would hand
            // back a stale transaction from an earlier suite file instead.
            'txn_id' => 'e2e-approve-' . uniqid(),
            'post' => ['e2e_approval_test' => uniqid('', true)],
        ]);
        assertApiSuccess($created);
        assertApiResultIsInt($created);
        $txId = (int) $created->getResult();

        // Callback-style processing has nothing verifiable to run for an
        // offline gateway, so it refuses and points at approval instead.
        $processBlocked = Tests\Helpers\ApiClient::request('admin/invoice/transaction_process', ['id' => $txId]);
        expect($processBlocked->wasSuccessful())->toBeFalse();
        expect($processBlocked->getErrorMessage())->toContain('approved by an administrator');

        // The transaction is untouched by the refusal: still awaiting approval.
        $pending = Tests\Helpers\ApiClient::request('admin/invoice/transaction_get', ['id' => $txId]);
        assertApiSuccess($pending);
        assertApiResultIsArray($pending);
        expect($pending->getResult()['status'])->toBe('received')
            ->and($pending->getResult()['invoice_id'])->toBe($invoiceId)
            ->and($pending->getResult()['gateway_code'])->toBe('Custom')
            ->and($pending->getResult()['requires_manual_approval'])->toBeTrue();

        $approved = Tests\Helpers\ApiClient::request('admin/invoice/transaction_approve', ['id' => $txId]);
        assertApiSuccess($approved);

        $paidInvoice = Tests\Helpers\ApiClient::request('admin/invoice/get', ['id' => $invoiceId]);
        assertApiSuccess($paidInvoice);
        expect($paidInvoice->getResult()['status'])->toBe('paid');

        $settled = Tests\Helpers\ApiClient::request('admin/invoice/transaction_get', ['id' => $txId]);
        assertApiSuccess($settled);
        assertApiResultIsArray($settled);
        expect($settled->getResult()['status'])->toBe('processed');

        // Approving again stays a success: settlement is idempotent.
        $approvedAgain = Tests\Helpers\ApiClient::request('admin/invoice/transaction_approve', ['id' => $txId]);
        assertApiSuccess($approvedAgain);
    } finally {
        approvePathCleanupClient();
    }
});

function approvePathCreateClient(): array
{
    $email = 'invoice_approve_' . uniqid() . '@example.com';
    $created = Tests\Helpers\ApiClient::request('admin/client/create', [
        'email' => $email,
        'first_name' => 'Test',
        'password' => 'A1a' . bin2hex(random_bytes(6)),
        'send_welcome_email' => 0,
    ]);
    assertApiSuccess($created);
    $GLOBALS['approvePathClientId'] = (int) $created->getResult();

    return ['id' => $GLOBALS['approvePathClientId']];
}

function approvePathCustomGatewayId(): int
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

function approvePathCleanupClient(): void
{
    if (!isset($GLOBALS['approvePathClientId'])) {
        return;
    }

    $deleted = Tests\Helpers\ApiClient::request('admin/client/delete', ['id' => $GLOBALS['approvePathClientId']]);
    assertApiSuccess($deleted);
    unset($GLOBALS['approvePathClientId']);
}
