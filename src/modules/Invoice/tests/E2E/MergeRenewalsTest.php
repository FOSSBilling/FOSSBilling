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

/*
 * End-to-end coverage for #4118: the renewal batch merges orders sharing a
 * client, currency, due date, and billing period onto one invoice, while a
 * per-order opt-out and a different billing period stay on separate
 * invoices. The staff basket drives checkout without guest logins, so this
 * test spends none of the shared api_login budget.
 */
test('renewal batch merges same-bucket orders and respects the opt-out', function (): void {
    $clientId = null;
    $products = [];
    $previousFlag = null;
    $renewalInvoiceIds = [];

    try {
        $clientId = mergeRenewalsCreateClient();
        $productA = mergeRenewalsCreateProduct(10.0, '1M');
        $productB = mergeRenewalsCreateProduct(20.0, '1M');
        $productC = mergeRenewalsCreateProduct(30.0, '1M');
        $productD = mergeRenewalsCreateProduct(100.0, '1Y');
        $products = [$productA, $productB, $productC, $productD];
        $periods = [$productA => '1M', $productB => '1M', $productC => '1M', $productD => '1Y'];

        foreach ($products as $productId) {
            $added = Tests\Helpers\ApiClient::request('admin/cart/staff_basket_add_item', [
                'client_id' => $clientId,
                'id' => $productId,
                'period' => $periods[$productId],
            ]);
            assertApiSuccess($added);
        }
        $checkout = Tests\Helpers\ApiClient::request('admin/cart/staff_basket_checkout', ['client_id' => $clientId]);
        assertApiSuccess($checkout);
        $checkoutResult = $checkout->getResult();
        expect($checkoutResult['orders'])->toHaveCount(4);
        [$orderA, $orderB, $orderC, $orderD] = array_map(intval(...), $checkoutResult['orders']);
        $checkoutInvoiceId = (int) $checkoutResult['invoice_id'];

        // Voiding the checkout invoice releases the orders into the renewal
        // batch selection (which skips orders tied to a live unpaid invoice).
        $voided = Tests\Helpers\ApiClient::request('admin/invoice/cancel', ['id' => $checkoutInvoiceId]);
        assertApiSuccess($voided);

        // Activate everything, then align the renewals onto one due date.
        $dueDate = date('Y-m-d', strtotime('+3 days'));
        foreach ([$orderA, $orderB, $orderC, $orderD] as $orderId) {
            $order = mergeRenewalsGetOrder($orderId);
            if (($order['status'] ?? null) !== 'active') {
                $activated = Tests\Helpers\ApiClient::request('admin/order/activate', ['id' => $orderId]);
                assertApiSuccess($activated);
            }
        }
        foreach ([$orderA, $orderB, $orderC, $orderD] as $orderId) {
            $updated = Tests\Helpers\ApiClient::request('admin/order/update', [
                'id' => $orderId,
                'expires_at' => $dueDate,
                'invoice_option' => 'issue-invoice',
            ]);
            assertApiSuccess($updated);
        }
        // Order C opts out of merging while sharing the bucket otherwise.
        $optOut = Tests\Helpers\ApiClient::request('admin/order/update', [
            'id' => $orderC,
            'merge_renewals' => '0',
        ]);
        assertApiSuccess($optOut);

        $params = Tests\Helpers\ApiClient::request('admin/system/get_params', []);
        assertApiSuccess($params);
        $previousFlag = $params->getResult()['invoice_merge_renewals'] ?? null;
        $enabled = Tests\Helpers\ApiClient::request('admin/system/update_params', ['invoice_merge_renewals' => '1']);
        assertApiSuccess($enabled);

        $beforeIds = mergeRenewalsInvoiceIds($clientId);

        $batch = Tests\Helpers\ApiClient::request('admin/invoice/batch_generate', []);
        assertApiSuccess($batch);

        $newIds = array_values(array_diff(mergeRenewalsInvoiceIds($clientId), $beforeIds));
        expect($newIds)->toHaveCount(3);
        $renewalInvoiceIds = $newIds;

        $invoices = [];
        foreach ($newIds as $invoiceId) {
            $invoices[$invoiceId] = mergeRenewalsGetInvoice($invoiceId);
        }

        // A and B merge onto one invoice; C (opt-out) and D (yearly period)
        // each get their own.
        $mergedId = mergeRenewalsFindInvoiceWithOrders($invoices, [$orderA, $orderB]);
        expect($mergedId)->not->toBeNull();
        $singleC = mergeRenewalsFindInvoiceWithOrders($invoices, [$orderC]);
        expect($singleC)->not->toBeNull()->not->toBe($mergedId);
        $singleD = mergeRenewalsFindInvoiceWithOrders($invoices, [$orderD]);
        expect($singleD)->not->toBeNull()->not->toBe($mergedId)->not->toBe($singleC);

        foreach ([$orderA, $orderB] as $orderId) {
            $order = mergeRenewalsGetOrder($orderId);
            expect((int) ($order['unpaid_invoice_id'] ?? 0))->toBe($mergedId);
        }
    } finally {
        foreach ($renewalInvoiceIds as $invoiceId) {
            Tests\Helpers\ApiClient::request('admin/invoice/cancel', ['id' => $invoiceId]);
        }
        if ($clientId !== null) {
            Tests\Helpers\ApiClient::request('admin/client/delete', ['id' => $clientId]);
        }
        foreach ($products as $productId) {
            Tests\Helpers\ApiClient::request('admin/product/delete', ['id' => $productId]);
        }
        if ($previousFlag !== null) {
            Tests\Helpers\ApiClient::request('admin/system/update_params', ['invoice_merge_renewals' => $previousFlag]);
        }
    }
});

function mergeRenewalsCreateClient(): int
{
    $created = Tests\Helpers\ApiClient::request('admin/client/create', [
        'email' => 'merge_renewals_' . uniqid() . '@example.com',
        'first_name' => 'Test',
        'password' => 'A1a' . bin2hex(random_bytes(6)),
        'send_welcome_email' => 0,
    ]);
    assertApiSuccess($created);

    return (int) $created->getResult();
}

function mergeRenewalsCreateProduct(float $price, string $period): int
{
    $result = Tests\Helpers\ApiClient::request('admin/product/prepare', [
        'title' => 'E2E Merge Product ' . uniqid(),
        'type' => 'custom',
        'product_category_id' => 1,
    ]);
    assertApiSuccess($result);
    assertApiResultIsInt($result);
    $productId = (int) $result->getResult();

    $update = Tests\Helpers\ApiClient::request('admin/product/update', [
        'id' => $productId,
        'status' => 'enabled',
        'pricing' => ['type' => 'recurrent', 'recurrent' => [$period => ['price' => $price, 'setup' => 0, 'enabled' => true]]],
    ]);
    assertApiSuccess($update);

    return $productId;
}

function mergeRenewalsGetOrder(int $orderId): array
{
    $result = Tests\Helpers\ApiClient::request('admin/order/get', ['id' => $orderId]);
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return $result->getResult();
}

function mergeRenewalsGetInvoice(int $invoiceId): array
{
    $result = Tests\Helpers\ApiClient::request('admin/invoice/get', ['id' => $invoiceId]);
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return $result->getResult();
}

function mergeRenewalsInvoiceIds(int $clientId): array
{
    $result = Tests\Helpers\ApiClient::request('admin/invoice/get_list', ['client_id' => $clientId, 'per_page' => 50]);
    assertApiSuccess($result);
    assertApiResultIsArray($result);

    return array_map(static fn (array $invoice): int => (int) $invoice['id'], $result->getResult()['list'] ?? []);
}

function mergeRenewalsFindInvoiceWithOrders(array $invoices, array $orderIds): ?int
{
    sort($orderIds);
    foreach ($invoices as $invoiceId => $invoice) {
        $lineOrders = [];
        foreach ($invoice['lines'] ?? [] as $line) {
            if (($line['type'] ?? null) === 'order') {
                $lineOrders[] = (int) ($line['order_id'] ?? 0);
            }
        }
        sort($lineOrders);
        if ($lineOrders === $orderIds) {
            return $invoiceId;
        }
    }

    return null;
}
