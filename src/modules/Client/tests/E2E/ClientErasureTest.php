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

use function Tests\Helpers\assertApiResultIsInt;
use function Tests\Helpers\assertApiSuccess;

test('deleting a client erases personal data but keeps anonymized financial records', function (): void {
    Tests\Helpers\ApiClient::resetCookies();
    $productId = null;

    try {
        $productId = erasureCreateProduct();
        ['id' => $clientId, 'token' => $clientToken] = erasureCreateClient();

        $created = Tests\Helpers\ApiClient::request('admin/order/create', [
            'client_id' => $clientId,
            'product_id' => $productId,
            'invoice_option' => 'issue-invoice',
        ]);
        assertApiSuccess($created);
        assertApiResultIsInt($created);
        $orderId = (int) $created->getResult();

        $order = Tests\Helpers\ApiClient::request('admin/order/get', ['id' => $orderId]);
        assertApiSuccess($order);
        $invoiceId = (int) $order->getResult()['unpaid_invoice_id'];

        erasureMarkInvoicePaid($invoiceId);

        $txList = Tests\Helpers\ApiClient::request('admin/invoice/transaction_get_list', ['invoice_id' => $invoiceId]);
        assertApiSuccess($txList);
        $txRows = $txList->getResult()['list'] ?? [];
        expect($txRows)->not->toBeEmpty();
        $txId = (int) $txRows[0]['id'];

        // Plant identifiable data on the transaction, as a gateway IPN would.
        $marker = uniqid('erasure_');
        $noted = Tests\Helpers\ApiClient::request('admin/invoice/transaction_update', [
            'id' => $txId,
            'note' => 'PII note ' . $marker,
        ]);
        assertApiSuccess($noted);

        $txBefore = Tests\Helpers\ApiClient::request('admin/invoice/transaction_get', ['id' => $txId]);
        assertApiSuccess($txBefore);
        expect($txBefore->getResult()['note'])->toContain($marker);

        // A client ticket with a reply carrying personal content.
        $helpdesks = Tests\Helpers\ApiClient::request('client/support/helpdesk_get_pairs', [], 'client', $clientToken);
        assertApiSuccess($helpdesks);
        $helpdeskIds = array_keys($helpdesks->getResult());
        expect($helpdeskIds)->not->toBeEmpty();

        $ticket = Tests\Helpers\ApiClient::request('client/support/ticket_create', [
            'support_helpdesk_id' => $helpdeskIds[0],
            'subject' => 'Erasure probe ' . $marker,
            'content' => 'Personal content ' . $marker,
        ], 'client', $clientToken);
        assertApiSuccess($ticket);
        assertApiResultIsInt($ticket);
        $ticketId = (int) $ticket->getResult();

        $reply = Tests\Helpers\ApiClient::request('client/support/ticket_reply', [
            'id' => $ticketId,
            'content' => 'Follow-up personal content ' . $marker,
        ], 'client', $clientToken);
        assertApiSuccess($reply);

        // Erase the client.
        $deleted = Tests\Helpers\ApiClient::request('admin/client/delete', ['id' => $clientId]);
        assertApiSuccess($deleted);

        // Client, invoice, and ticket are gone.
        expect(Tests\Helpers\ApiClient::request('admin/client/get', ['id' => $clientId])->wasSuccessful())->toBeFalse();
        expect(Tests\Helpers\ApiClient::request('admin/invoice/get', ['id' => $invoiceId])->wasSuccessful())->toBeFalse();
        expect(Tests\Helpers\ApiClient::request('admin/support/ticket_get', ['id' => $ticketId])->wasSuccessful())->toBeFalse();

        // The transaction survives as an anonymized financial record.
        $txAfter = Tests\Helpers\ApiClient::request('admin/invoice/transaction_get', ['id' => $txId]);
        assertApiSuccess($txAfter);
        $txData = $txAfter->getResult();
        expect($txData['note'])->toBeNull();
        expect($txData['ip'])->toBeNull();
        expect($txData['amount'])->not->toBeNull();
    } finally {
        erasureDeleteProduct($productId);
    }
});

function erasureCreateProduct(): int
{
    $result = Tests\Helpers\ApiClient::request('admin/product/prepare', [
        'title' => 'E2E Erasure Product ' . uniqid(),
        'type' => 'custom',
        'product_category_id' => 1,
    ]);
    assertApiSuccess($result);
    assertApiResultIsInt($result);
    $productId = (int) $result->getResult();

    $update = Tests\Helpers\ApiClient::request('admin/product/update', [
        'id' => $productId,
        'status' => 'enabled',
        'pricing' => ['type' => 'once', 'once' => ['price' => 75.0, 'setup' => 0]],
    ]);
    assertApiSuccess($update);

    return $productId;
}

function erasureCreateClient(): array
{
    $email = 'erasure_client_' . uniqid() . '@example.com';
    $created = Tests\Helpers\ApiClient::request('admin/client/create', [
        'email' => $email,
        'first_name' => 'Test',
        'password' => 'A1a' . bin2hex(random_bytes(6)),
        'send_welcome_email' => 0,
    ]);
    assertApiSuccess($created);
    $clientId = (int) $created->getResult();

    Tests\Helpers\ApiClient::resetCookies();
    $token = Tests\Helpers\ApiClient::request('admin/profile/api_key_reset', ['id' => $clientId]);
    assertApiSuccess($token);

    return ['id' => $clientId, 'token' => (string) $token->getResult()];
}

function erasureMarkInvoicePaid(int $invoiceId): void
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

function erasureDeleteProduct(?int $productId): void
{
    if ($productId === null) {
        return;
    }
    Tests\Helpers\ApiClient::request('admin/product/delete', ['id' => $productId]);
}
