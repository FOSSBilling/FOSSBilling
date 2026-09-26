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

test('invoice journal records lifecycle transitions oldest first', function (): void {
    Tests\Helpers\ApiClient::resetCookies();

    try {
        ['id' => $clientId] = journalCreateClient();

        // Draft invoice: journal starts with created.
        $prepared = Tests\Helpers\ApiClient::request('admin/invoice/prepare', [
            'client_id' => $clientId,
            'items' => [
                ['title' => 'E2E journal line', 'price' => 75, 'quantity' => 1],
            ],
        ]);
        assertApiSuccess($prepared);
        assertApiResultIsInt($prepared);
        $invoiceId = (int) $prepared->getResult();

        expect(array_column(journalGetJournal($invoiceId), 'type'))->toBe(['created']);

        // An edit records updated with only the consumed fields.
        $updated = Tests\Helpers\ApiClient::request('admin/invoice/update', [
            'id' => $invoiceId,
            'notes' => 'E2E journal note',
        ]);
        assertApiSuccess($updated);

        $journal = journalGetJournal($invoiceId);
        expect(array_column($journal, 'type'))->toBe(['created', 'updated']);
        $updateEntry = $journal[array_key_last($journal)];
        expect($updateEntry)->toHaveKeys(['id', 'type', 'admin_id', 'client_id', 'snapshot', 'created_at'])
            ->and($updateEntry['snapshot']['changed_fields'] ?? null)->toContain('notes')
            ->and($updateEntry['snapshot']['changed_fields'] ?? null)->not->toContain('id');

        // Issuing records issued.
        $issued = Tests\Helpers\ApiClient::request('admin/invoice/issue', ['id' => $invoiceId]);
        assertApiSuccess($issued);

        $journal = journalGetJournal($invoiceId);
        expect(array_column($journal, 'type'))->toBe(['created', 'updated', 'issued']);

        // A void records canceled.
        $canceled = Tests\Helpers\ApiClient::request('admin/invoice/cancel', [
            'id' => $invoiceId,
            'reason' => 'E2E journal void',
        ]);
        assertApiSuccess($canceled);

        $journal = journalGetJournal($invoiceId);
        expect(array_column($journal, 'type'))->toBe(['created', 'updated', 'issued', 'canceled']);
        $cancelEntry = $journal[array_key_last($journal)];
        expect($cancelEntry['snapshot']['reason'] ?? null)->toBe('E2E journal void');

        // Missing id is refused.
        $missing = Tests\Helpers\ApiClient::request('admin/invoice/journal', []);
        expect($missing->wasSuccessful())->toBeFalse();

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
    } finally {
        journalCleanupClient();
    }
});

function journalCreateClient(): array
{
    $email = 'invoice_journal_' . uniqid() . '@example.com';
    $created = Tests\Helpers\ApiClient::request('admin/client/create', [
        'email' => $email,
        'first_name' => 'Test',
        'password' => 'A1a' . bin2hex(random_bytes(6)),
        'send_welcome_email' => 0,
    ]);
    assertApiSuccess($created);
    $GLOBALS['journalClientId'] = (int) $created->getResult();

    return ['id' => $GLOBALS['journalClientId']];
}

function journalGetJournal(int $invoiceId): array
{
    $result = Tests\Helpers\ApiClient::request('admin/invoice/journal', ['id' => $invoiceId]);
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return $result->getResult();
}

function journalCleanupClient(): void
{
    if (!isset($GLOBALS['journalClientId'])) {
        return;
    }

    $deleted = Tests\Helpers\ApiClient::request('admin/client/delete', ['id' => $GLOBALS['journalClientId']]);
    assertApiSuccess($deleted);
    unset($GLOBALS['journalClientId']);
}
