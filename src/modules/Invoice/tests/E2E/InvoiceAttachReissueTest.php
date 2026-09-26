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

test('attaching a product adds a provisioning order line to an editable invoice', function (): void {
    Tests\Helpers\ApiClient::resetCookies();
    $productId = $extraProductId = $otherClientId = null;

    try {
        $productId = attachReissueCreateProduct(100.0);
        $extraProductId = attachReissueCreateProduct(50.0);
        ['id' => $clientId] = attachReissueCreateClient('attach_reissue_client_');
        $GLOBALS['attachReissueClientId'] = $clientId;

        $created = Tests\Helpers\ApiClient::request('admin/order/create', [
            'client_id' => $clientId,
            'product_id' => $productId,
            'invoice_option' => 'issue-invoice',
        ]);
        assertApiSuccess($created);
        $orderId = (int) $created->getResult();

        // Drafts accept attaches without the opt-in setting and stay drafts;
        // sending is left to the approval path.
        $draftOrder = Tests\Helpers\ApiClient::request('admin/order/create', [
            'client_id' => $clientId,
            'product_id' => $productId,
            'invoice_option' => 'no-invoice',
        ]);
        assertApiSuccess($draftOrder);
        $draftOrderId = (int) $draftOrder->getResult();

        $draftPrepared = Tests\Helpers\ApiClient::request('admin/invoice/prepare', ['client_id' => $clientId]);
        assertApiSuccess($draftPrepared);
        $draftInvoiceId = (int) $draftPrepared->getResult();

        $draftAttach = Tests\Helpers\ApiClient::request('admin/invoice/attach_order', [
            'id' => $draftInvoiceId,
            'order_id' => $draftOrderId,
        ]);
        assertApiSuccess($draftAttach);

        $draft = attachReissueGetInvoice($draftInvoiceId);
        expect($draft['approved'])->toBeFalse();
        expect(attachReissueHasOrderLine($draft, $draftOrderId))->toBeTrue();

        $order = attachReissueGetOrder($orderId);
        $invoiceId = (int) $order['unpaid_invoice_id'];

        $invoice = attachReissueGetInvoice($invoiceId);
        expect($invoice['approved'])->toBeTrue();
        expect($invoice['editable'])->toBeFalse();

        // Locked: attaching is refused like any other edit.
        $blocked = Tests\Helpers\ApiClient::request('admin/invoice/attach_order', [
            'id' => $invoiceId,
            'product_id' => $extraProductId,
        ]);
        expect($blocked->wasSuccessful())->toBeFalse();
        expect($blocked->getErrorMessage())->toContain('can no longer be edited');

        $params = Tests\Helpers\ApiClient::request('admin/system/get_params');
        assertApiSuccess($params);
        $originalSetting = $params->getResult()['invoice_immutability'] ?? 'strict';
        $enabled = Tests\Helpers\ApiClient::request('admin/system/update_params', [
            'invoice_immutability' => 'relaxed',
        ]);
        assertApiSuccess($enabled);

        try {
            $attached = Tests\Helpers\ApiClient::request('admin/invoice/attach_order', [
                'id' => $invoiceId,
                'product_id' => $extraProductId,
            ]);
            assertApiSuccess($attached);
            assertApiResultIsInt($attached);
            $attachedOrderId = (int) $attached->getResult();
            expect($attachedOrderId)->not->toBe($orderId);

            $invoice = attachReissueGetInvoice($invoiceId);
            expect(attachReissueHasOrderLine($invoice, $attachedOrderId))->toBeTrue();

            $attachedOrder = attachReissueGetOrder($attachedOrderId);
            expect((int) $attachedOrder['unpaid_invoice_id'])->toBe($invoiceId);

            // A zero price override is allowed: the line charges nothing.
            $free = Tests\Helpers\ApiClient::request('admin/invoice/attach_order', [
                'id' => $invoiceId,
                'product_id' => $extraProductId,
                'price' => 0,
            ]);
            assertApiSuccess($free);
            $freeOrderId = (int) $free->getResult();
            $invoice = attachReissueGetInvoice($invoiceId);
            expect(attachReissueHasOrderLine($invoice, $freeOrderId, 0.0))->toBeTrue();

            // Re-attaching the same order is blocked and names the holding invoice.
            $twice = Tests\Helpers\ApiClient::request('admin/invoice/attach_order', [
                'id' => $invoiceId,
                'order_id' => $attachedOrderId,
            ]);
            expect($twice->wasSuccessful())->toBeFalse();
            expect($twice->getErrorMessage())->toContain('already attached');

            // Orders of another client are blocked.
            ['id' => $otherClientId] = attachReissueCreateClient('attach_reissue_other_');
            $foreign = Tests\Helpers\ApiClient::request('admin/order/create', [
                'client_id' => $otherClientId,
                'product_id' => $extraProductId,
                'invoice_option' => 'no-invoice',
            ]);
            assertApiSuccess($foreign);
            $foreignOrderId = (int) $foreign->getResult();

            $crossClient = Tests\Helpers\ApiClient::request('admin/invoice/attach_order', [
                'id' => $invoiceId,
                'order_id' => $foreignOrderId,
            ]);
            expect($crossClient->wasSuccessful())->toBeFalse();
            expect($crossClient->getErrorMessage())->toContain('does not belong');
        } finally {
            $restore = Tests\Helpers\ApiClient::request('admin/system/update_params', [
                'invoice_immutability' => $originalSetting,
            ]);
            assertApiSuccess($restore);
        }
    } finally {
        attachReissueCleanupClient();
        if ($otherClientId !== null) {
            $deleted = Tests\Helpers\ApiClient::request('admin/client/delete', ['id' => $otherClientId]);
            assertApiSuccess($deleted);
        }
        attachReissueDeleteProduct($productId);
        attachReissueDeleteProduct($extraProductId);
    }
});

test('reissuing cancels the original and moves its lines to a numbered replacement', function (): void {
    Tests\Helpers\ApiClient::resetCookies();
    $productId = null;

    try {
        $productId = attachReissueCreateProduct(100.0);
        ['id' => $clientId] = attachReissueCreateClient('attach_reissue_swap_');
        $GLOBALS['attachReissueClientId'] = $clientId;

        $created = Tests\Helpers\ApiClient::request('admin/order/create', [
            'client_id' => $clientId,
            'product_id' => $productId,
            'invoice_option' => 'issue-invoice',
        ]);
        assertApiSuccess($created);
        $orderId = (int) $created->getResult();

        $order = attachReissueGetOrder($orderId);
        $invoiceId = (int) $order['unpaid_invoice_id'];
        $original = attachReissueGetInvoice($invoiceId);
        $originalLineCount = count($original['lines']);

        // Drafts cannot be reissued; there is nothing to preserve.
        $draftPrepared = Tests\Helpers\ApiClient::request('admin/invoice/prepare', ['client_id' => $clientId]);
        assertApiSuccess($draftPrepared);
        $draftId = (int) $draftPrepared->getResult();
        $draftReissue = Tests\Helpers\ApiClient::request('admin/invoice/reissue', ['id' => $draftId]);
        expect($draftReissue->wasSuccessful())->toBeFalse();
        expect($draftReissue->getErrorMessage())->toContain('Only approved unpaid');

        $reissued = Tests\Helpers\ApiClient::request('admin/invoice/reissue', [
            'id' => $invoiceId,
            'reason' => 'E2E client asked to add hosting',
        ]);
        assertApiSuccess($reissued);
        assertApiResultIsInt($reissued);
        $replacementId = (int) $reissued->getResult();
        expect($replacementId)->not->toBe($invoiceId);

        $canceled = attachReissueGetInvoice($invoiceId);
        expect($canceled['status'])->toBe('canceled');
        expect((int) $canceled['replaced_by_invoice_id'])->toBe($replacementId);

        $replacement = attachReissueGetInvoice($replacementId);
        expect($replacement['status'])->toBe('unpaid');
        expect($replacement['approved'])->toBeTrue();
        expect((int) $replacement['replaces_invoice_id'])->toBe($invoiceId);
        expect($replacement['serie_nr'])->not->toBe($original['serie_nr']);
        expect($replacement['lines'])->toHaveCount($originalLineCount);
        expect(attachReissueHasOrderLine($replacement, $orderId))->toBeTrue();

        $movedOrder = attachReissueGetOrder($orderId);
        expect((int) $movedOrder['unpaid_invoice_id'])->toBe($replacementId);

        // A canceled invoice cannot be reissued twice.
        $twice = Tests\Helpers\ApiClient::request('admin/invoice/reissue', ['id' => $invoiceId]);
        expect($twice->wasSuccessful())->toBeFalse();
        expect($twice->getErrorMessage())->toContain('Only approved unpaid');

        // The canceled original can no longer be paid either.
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
        $payCanceled = Tests\Helpers\ApiClient::request('admin/invoice/mark_as_paid', [
            'id' => $invoiceId,
            'gateway_id' => $gatewayId,
            'transactionId' => 'txn' . uniqid(),
        ]);
        expect($payCanceled->wasSuccessful())->toBeFalse();
        expect($payCanceled->getErrorMessage())->toContain('canceled and cannot be marked as paid');

        // Paying the replacement settles only itself.
        attachReissueMarkInvoicePaid($replacementId);
        expect(attachReissueGetInvoice($replacementId)['status'])->toBe('paid');
        expect(attachReissueGetInvoice($invoiceId)['status'])->toBe('canceled');
    } finally {
        attachReissueCleanupClient();
        attachReissueDeleteProduct($productId);
    }
});

function attachReissueCreateProduct(float $price): int
{
    $result = Tests\Helpers\ApiClient::request('admin/product/prepare', [
        'title' => 'E2E Attach Product ' . uniqid(),
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

function attachReissueCreateClient(string $prefix): array
{
    $email = $prefix . uniqid() . '@example.com';
    $created = Tests\Helpers\ApiClient::request('admin/client/create', [
        'email' => $email,
        'first_name' => 'Test',
        'password' => 'A1a' . bin2hex(random_bytes(6)),
        'send_welcome_email' => 0,
    ]);
    assertApiSuccess($created);

    return ['id' => (int) $created->getResult()];
}

function attachReissueGetOrder(int $orderId): array
{
    $result = Tests\Helpers\ApiClient::request('admin/order/get', ['id' => $orderId]);
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return $result->getResult();
}

function attachReissueGetInvoice(int $invoiceId): array
{
    $result = Tests\Helpers\ApiClient::request('admin/invoice/get', ['id' => $invoiceId]);
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return $result->getResult();
}

function attachReissueHasOrderLine(array $invoice, int $orderId, ?float $price = null): bool
{
    foreach ($invoice['lines'] ?? [] as $line) {
        if ((int) ($line['order_id'] ?? 0) === $orderId) {
            if ($price === null || (float) ($line['price'] ?? 0) === $price) {
                return true;
            }
        }
    }

    return false;
}

function attachReissueMarkInvoicePaid(int $invoiceId): void
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

function attachReissueCleanupClient(): void
{
    if (!isset($GLOBALS['attachReissueClientId'])) {
        return;
    }

    $deleted = Tests\Helpers\ApiClient::request('admin/client/delete', ['id' => $GLOBALS['attachReissueClientId']]);
    assertApiSuccess($deleted);
    unset($GLOBALS['attachReissueClientId']);
}

function attachReissueDeleteProduct(?int $productId): void
{
    if ($productId === null) {
        return;
    }

    $deleted = Tests\Helpers\ApiClient::request('admin/product/delete', ['id' => $productId]);
    assertApiSuccess($deleted);
}
