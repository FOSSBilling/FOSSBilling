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

test('issuing a debit note charges extra without touching the original', function (): void {
    Tests\Helpers\ApiClient::resetCookies();

    try {
        ['id' => $clientId] = debitNoteCreateClient();

        $prepared = Tests\Helpers\ApiClient::request('admin/invoice/prepare', ['client_id' => $clientId]);
        assertApiSuccess($prepared);
        assertApiResultIsInt($prepared);
        $invoiceId = (int) $prepared->getResult();

        $added = Tests\Helpers\ApiClient::request('admin/invoice/update', [
            'id' => $invoiceId,
            'new_item' => ['title' => 'E2E hosting', 'price' => 100, 'quantity' => 1],
        ]);
        assertApiSuccess($added);

        // Draft invoices take lines directly; debit notes are for issued ones.
        $draftDebit = Tests\Helpers\ApiClient::request('admin/invoice/debit', [
            'id' => $invoiceId,
            'items' => [['title' => 'E2E too early', 'price' => 5, 'quantity' => 1]],
        ]);
        expect($draftDebit->wasSuccessful())->toBeFalse();
        expect($draftDebit->getErrorMessage())->toContain('Only issued unpaid or paid');

        $issued = Tests\Helpers\ApiClient::request('admin/invoice/issue', ['id' => $invoiceId]);
        assertApiSuccess($issued);

        $emptyDebit = Tests\Helpers\ApiClient::request('admin/invoice/debit', ['id' => $invoiceId]);
        expect($emptyDebit->wasSuccessful())->toBeFalse();
        expect($emptyDebit->getErrorMessage())->toContain('Debit lines are missing');

        $debited = Tests\Helpers\ApiClient::request('admin/invoice/debit', [
            'id' => $invoiceId,
            'note' => 'E2E undercharge',
            'items' => [['title' => 'E2E undercharge', 'price' => 25, 'quantity' => 1]],
        ]);
        assertApiSuccess($debited);
        assertApiResultIsInt($debited);
        $debitNoteId = (int) $debited->getResult();
        expect($debitNoteId)->not->toBe($invoiceId);

        $debitNote = debitNoteGetInvoice($debitNoteId);
        expect($debitNote['status'])->toBe('unpaid');
        expect($debitNote['issued'])->toBeTrue();
        expect((int) $debitNote['debit_note_for_invoice_id'])->toBe($invoiceId);
        expect($debitNote['serie'])->toBe('DN-');
        expect((float) $debitNote['total'])->toEqual(25.0);
        expect($debitNote['lines'])->toHaveCount(1);
        expect($debitNote['lines'][0]['type'])->toBe('custom');
        expect($debitNote['lines'][0]['task'])->toBe('void');

        $original = debitNoteGetInvoice($invoiceId);
        expect($original['status'])->toBe('unpaid');
        expect($original['debited_by_invoice_ids'])->toContain($debitNoteId);

        // Paying the debit note settles only itself.
        debitNoteMarkInvoicePaid($debitNoteId);
        expect(debitNoteGetInvoice($debitNoteId)['status'])->toBe('paid');
        expect(debitNoteGetInvoice($invoiceId)['status'])->toBe('unpaid');
    } finally {
        debitNoteCleanupClient();
    }
});

function debitNoteCreateClient(): array
{
    $email = 'debit_note_client_' . uniqid() . '@example.com';
    $created = Tests\Helpers\ApiClient::request('admin/client/create', [
        'email' => $email,
        'first_name' => 'Test',
        'password' => 'A1a' . bin2hex(random_bytes(6)),
        'send_welcome_email' => 0,
    ]);
    assertApiSuccess($created);
    $GLOBALS['debitNoteClientId'] = (int) $created->getResult();

    return ['id' => $GLOBALS['debitNoteClientId']];
}

function debitNoteGetInvoice(int $invoiceId): array
{
    $result = Tests\Helpers\ApiClient::request('admin/invoice/get', ['id' => $invoiceId]);
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return $result->getResult();
}

function debitNoteMarkInvoicePaid(int $invoiceId): void
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

function debitNoteCleanupClient(): void
{
    if (!isset($GLOBALS['debitNoteClientId'])) {
        return;
    }

    $deleted = Tests\Helpers\ApiClient::request('admin/client/delete', ['id' => $GLOBALS['debitNoteClientId']]);
    assertApiSuccess($deleted);
    unset($GLOBALS['debitNoteClientId']);
}
