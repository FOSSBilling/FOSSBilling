<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * Invoice management API.
 */

namespace Box\Mod\Invoice\Api;

use Box\Mod\Client\Entity\Client;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\Subscription;
use Box\Mod\Invoice\Entity\Tax;
use Box\Mod\Invoice\Entity\Transaction;
use Box\Mod\Invoice\Event\BeforeAdminTransactionProcessEvent;
use Box\Mod\Invoice\PendingPayments;
use Box\Mod\Order\Entity\Order;
use FOSSBilling\Core\Exception\InformationException;
use FOSSBilling\Core\Pagination\Options;
use FOSSBilling\Core\Validation\Api\RequiredParams;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class Admin extends \FOSSBilling\Core\Api\AbstractApi
{
    /**
     * Returns paginated list of invoices.
     *
     * @optional bool $summary - return only fields needed by invoice list views, without expanding related records
     * @optional string $sort - sort column: 'id', 'nr', 'status', 'currency', 'created_at', 'updated_at', 'paid_at' or 'due_at'
     * @optional string $direction - sort direction: 'ASC' or 'DESC'
     *
     * @return array
     */
    public function get_list($data)
    {
        $this->checkPermissions('invoice', 'view');

        $service = $this->getService();
        $qb = $service->getInvoiceRepository()->getSearchQueryBuilder($data);
        if (!empty($data['summary'])) {
            $pager = $this->getDi()['pager']->paginateMappedQuery($qb, Options::fromArray($data), static fn ($invoice) => $invoice);
            $totals = $service->getInvoiceRepository()->getInvoiceTotals(array_map(static fn ($invoice): int => (int) $invoice->getId(), $pager['list']));
            $pager['list'] = array_map(
                static fn ($invoice): array => $service->toApiSummaryFromEntity($invoice, $totals[$invoice->getId()] ?? []),
                $pager['list'],
            );

            return $pager;
        }

        $identity = $this->getIdentity();

        return $this->getDi()['pager']->paginateMappedQuery(
            $qb,
            Options::fromArray($data),
            static fn ($invoice): array => $service->toApiArray($invoice, true, $identity),
        );
    }

    /**
     * Get invoice details.
     *
     * @return array
     */
    #[RequiredParams(['id' => 'Invoice ID is missing'])]
    public function get($data)
    {
        $this->checkPermissions('invoice', 'view');

        $model = $this->_getInvoice($data);

        // Detail view only: the list endpoint serializes every row through
        // toApiArray, where per-invoice promo lookups would be N+1 queries.
        $result = $this->getService()->toApiArray($model, true, $this->getIdentity());
        $result['promo_applications'] = $this->getService()->getInvoicePromoApplications($model);
        $result['client_credit'] = $model->getClientId() ? $this->getService()->getFreeCredit((int) $model->getClientId()) : 0.0;
        $result['debited_by_invoice_ids'] = $this->getService()->getDebitingInvoiceIds($model);

        if (!empty($result['lines'])) {
            $remaining = $this->getService()->getLineRemainingQuantities($model);
            foreach ($result['lines'] as $i => $line) {
                $result['lines'][$i]['remaining_quantity'] = $remaining[$line['id']] ?? $line['quantity'];
            }
        }

        return $result;
    }

    /**
     * Get the audit journal of an invoice: one entry per lifecycle
     * transition with a trimmed snapshot of the invoice at that time.
     *
     * @return array
     */
    #[RequiredParams(['id' => 'Invoice ID is missing'])]
    public function journal($data)
    {
        $this->checkPermissions('invoice', 'view');

        $model = $this->_getInvoice($data);

        return $this->getService()->getJournalForInvoice((int) $model->getId());
    }

    /**
     * Sets invoice status to paid. This method differs from invoice update method
     * in a way that it sends notification to Events system, so emails are sent.
     * The gateway must be enabled; when the invoice has no gateway, gateway_id
     * is required and is saved on the invoice, including on locked issued invoices.
     *
     * @optional bool $execute - execute related tasks on invoice items. Default false.
     * @optional int $gateway_id - Payment gateway to associate with the invoice. Required when the invoice has no gateway.
     * @optional string $transactionId - Custom transaction ID to use when the selected gateway is Custom
     * @optional string $paid_at - payment date to record instead of now, e.g. "2026-09-01 14:00:00"
     *
     * @return bool
     */
    #[RequiredParams(['id' => 'Invoice ID is missing'])]
    public function mark_as_paid($data)
    {
        $this->checkPermissions('invoice', 'manage_invoices');

        $invoice = $this->_getInvoice($data);

        // Marking a deposit invoice as paid credits the client balance, so it
        // requires the same permission as crediting the balance directly.
        if ($this->getService()->isInvoiceTypeDeposit($invoice)) {
            $this->checkPermissions('client', 'manage_balance');
        }

        return $this->getService()->markAsPaidByAdmin($invoice, $data);
    }

    /**
     * Prepare invoice for editing and updating.
     * Uses clients details, such as currency assigned to client.
     * If client currency is not defined, sets default currency for client.
     *
     * @optional bool $issue - set true to issue invoice after preparation. Defaults to false
     * @optional int $gateway_id - Selected payment gateway id
     * @optional array $items - list of invoice lines. One line is array of line parameters
     * @optional string $text_1 - text to be displayed before invoice items table
     * @optional string $text_2 - text to be displayed after invoice items table
     *
     * @return int $id - newly generated invoice ID
     */
    #[RequiredParams(['client_id' => 'Client ID is missing'])]
    public function prepare($data)
    {
        $this->checkPermissions('invoice', 'manage_invoices');

        $client = $this->getDi()['em']->getRepository(Client::class)->find((int) $data['client_id']) ?? throw new \FOSSBilling\Core\Exception\BaseException('Client not found');

        $invoice = $this->getService()->prepareInvoice($client, $data);

        return $invoice->getId();
    }

    /**
     * Issue invoice.
     *
     * @return bool
     */
    #[RequiredParams(['id' => 'Invoice ID is missing'])]
    public function issue($data)
    {
        $this->checkPermissions('invoice', 'manage_invoices');

        $model = $this->_getInvoice($data);

        return $this->getService()->issueInvoice($model, $data);
    }

    /**
     * Add refunds.
     *
     * @optional string $note - note for refund
     * @optional float $expected_amount - require this exact refund amount under the invoice lock
     * @optional array $items - line id => quantity map for a partial refund; omit for a full refund
     * @optional bool $gateway_refund - also return the money through the payment gateway when it supports refunds (default true). Pass false to record an offline-only refund.
     * @optional bool $to_credit - add the refunded amount to the client's account credit instead of returning it
     *
     * @return int $id - newly generated refund document ID
     */
    #[RequiredParams(['id' => 'Invoice ID is missing'])]
    public function refund($data)
    {
        $this->checkPermissions('invoice', 'manage_invoices');

        $model = $this->_getInvoice($data);
        $note = $data['note'] ?? null;
        $items = $data['items'] ?? null;
        if ($items !== null && (!is_array($items) || $items === [])) {
            throw new InformationException('Refund lines are invalid');
        }
        $gatewayRefund = !array_key_exists('gateway_refund', $data) || \FOSSBilling\Core\Utils\Normalizer::normalizeBoolean($data['gateway_refund'], true);
        $options = [];
        if (array_key_exists('expected_amount', $data)) {
            $options['expected_amount'] = $data['expected_amount'];
        }
        if (array_key_exists('gateway_refund', $data)) {
            $options['gateway_refund'] = $gatewayRefund;
        }
        $toCredit = array_key_exists('to_credit', $data) && \FOSSBilling\Core\Utils\Normalizer::normalizeBoolean($data['to_credit'], false);
        if ($toCredit) {
            // Adding funds to the balance needs the same permission as crediting it directly.
            $this->checkPermissions('client', 'manage_balance');
            $options['to_credit'] = true;
            // The credit is the refund destination instead of the gateway.
            $options['gateway_refund'] = false;
        }

        // Refunding a deposit returns wallet credit as card money, so it
        // needs the same permission as crediting the balance directly.
        if ($gatewayRefund && $this->getService()->isInvoiceTypeDeposit($model)) {
            $this->checkPermissions('client', 'manage_balance');
        }

        return $this->getService()->refundInvoice($model, $note, $items, $options);
    }

    /** Get the unresolved PayPal refund attempt for this invoice. */
    #[RequiredParams(['id' => 'Invoice ID is missing'])]
    public function refund_attempt_get($data): ?array
    {
        $this->checkPermissions('invoice', 'manage_invoices');

        return $this->getService()->pendingGatewayRefund($this->_getInvoice($data));
    }

    /** Record the result staff verified in PayPal. */
    #[RequiredParams(['id' => 'Invoice ID is missing', 'attempt_id' => 'Refund attempt is missing', 'outcome' => 'Refund outcome is missing'])]
    public function refund_attempt_reconcile($data): int|bool
    {
        $this->checkPermissions('invoice', 'manage_invoices');
        $invoice = $this->_getInvoice($data);
        $attemptId = (string) $data['attempt_id'];
        if (!preg_match('/^[a-f0-9]{32}$/', $attemptId)) {
            throw new InformationException('Invalid refund attempt.');
        }
        if (($data['verified'] ?? null) !== '1') {
            throw new InformationException('Confirm that you checked PayPal before continuing.');
        }
        $adminId = (int) $this->getIdentity()->getId();
        if ($data['outcome'] === 'not_refunded') {
            return $this->getService()->dismissGatewayRefundAttempt($invoice, $attemptId, $adminId);
        }
        if ($data['outcome'] !== 'refunded') {
            throw new InformationException('Choose the verified refund outcome.');
        }
        if ($this->getService()->isInvoiceTypeDeposit($invoice)) {
            $this->checkPermissions('client', 'manage_balance');
        }
        $items = ($data['selection'] ?? '') === 'partial' ? ($data['items'] ?? null) : null;
        if (!in_array($data['selection'] ?? '', ['full', 'partial'], true) || (($data['selection'] ?? '') === 'partial' && (!is_array($items) || $items === []))) {
            throw new InformationException('Select the original refund lines.');
        }

        return $this->getService()->reconcileGatewayRefund($invoice, $attemptId, (string) ($data['refund_id'] ?? ''), $items, $adminId);
    }

    /**
     * Issue a debit note against an invoice.
     *
     * @optional string $note - note for the debit note
     * @optional array $items - list of charge lines, each with title, price, and optional quantity, taxed and unit
     *
     * @return int $id - newly generated debit note ID
     */
    #[RequiredParams(['id' => 'Invoice ID is missing'])]
    public function debit($data)
    {
        $this->checkPermissions('invoice', 'manage_invoices');

        $model = $this->_getInvoice($data);
        $note = $data['note'] ?? null;
        $items = $data['items'] ?? null;
        if (!is_array($items) || $items === []) {
            throw new InformationException('Debit lines are missing');
        }

        return $this->getService()->debitInvoice($model, array_values($items), $note);
    }

    /**
     * Attach a product to an editable invoice. Creates the order and adds it
     * as an order line, so paying the invoice provisions the service.
     *
     * @optional int $order_id - attach an existing pending order instead of creating one
     * @optional int $product_id - product to order and attach
     * @optional int $quantity - quantity to order. Default 1
     * @optional float $price - overridden unit price, zero allowed. Default is the product price.
     * @optional string $period - billing period for recurrent products
     * @optional array $config - product custom order form values
     * @optional string $group_id - attach as an addon of this order group
     * @optional string $title - order title. Default is the product title
     *
     * @return int $id - attached order ID
     */
    #[RequiredParams(['id' => 'Invoice ID is missing'])]
    public function attach_order($data)
    {
        $this->checkPermissions('invoice', 'manage_invoices');
        $this->checkPermissions('order', 'manage');

        $model = $this->_getInvoice($data);

        return $this->getService()->attachOrderToInvoice($model, $data);
    }

    /**
     * Cancel an issued unpaid invoice and issue a replacement carrying its
     * lines forward. The replacement takes the next invoice number; the
     * original number stays with the canceled record.
     *
     * @optional string $reason - reason recorded on the replacement invoice
     * @optional int $order_id - attach an existing pending order to the replacement
     * @optional int $product_id - product to order and attach to the replacement
     * @optional int $quantity - quantity to order. Default 1
     * @optional float $price - overridden unit price, zero allowed
     * @optional string $period - billing period for recurrent products
     * @optional array $config - product custom order form values
     * @optional string $group_id - attach as an addon of this order group
     * @optional string $title - order title
     *
     * @return int $id - replacement invoice ID
     */
    #[RequiredParams(['id' => 'Invoice ID is missing'])]
    public function reissue($data)
    {
        $this->checkPermissions('invoice', 'manage_invoices');
        if (!empty($data['order_id']) || !empty($data['product_id'])) {
            $this->checkPermissions('order', 'manage');
        }

        $model = $this->_getInvoice($data);

        return $this->getService()->reissueInvoice($model, $data);
    }

    /**
     * Cancel (void) an issued unpaid invoice without issuing a replacement.
     * The invoice keeps its number with a canceled status so the audit trail
     * survives. Linked orders keep pointing at it for history; transactions
     * are detached but kept.
     *
     * @optional string $reason - reason recorded in the invoice notes
     *
     * @return bool
     */
    #[RequiredParams(['id' => 'Invoice ID is missing'])]
    public function cancel($data)
    {
        $this->checkPermissions('invoice', 'manage_invoices');

        $model = $this->_getInvoice($data);

        return $this->getService()->cancelInvoice($model, $data);
    }

    /**
     * Update invoice details.
     *
     * @optional string $paid_at - Invoice payment date (Y-m-d) or empty to remove
     * @optional string $due_at - Invoice due date (Y-m-d)or empty to remove
     * @optional string $created_at - Invoice issue date (Y-m-d) or empty to remove
     * @optional string $serie - Invoice serie
     * @optional string $nr - Invoice number
     * @optional string $status - Invoice status for drafts: unpaid|canceled
     * @optional string $taxrate - Invoice tax rate
     * @optional string $taxname - Invoice tax name
     * @optional bool $issued - read-only display of the issue state. Changing it is
     *                         refused: issue a draft through the issue action or the
     *                         $issue flag below instead.
     * @optional string $notes - notes
     * @optional int $gateway_id - selected payment method - gateway id
     * @optional array $new_item - [title] [price]
     * @optional string $text_1 - Custom invoice text 1
     * @optional string $text_2 - Custom invoice text 2
     * @optional bool $issue - issue the invoice after saving the supplied changes
     *
     * Buyer and seller details are not accepted here: they are frozen from the
     * live client and company records when the invoice is issued.
     *
     * @return bool
     */
    #[RequiredParams(['id' => 'Invoice ID is missing'])]
    public function update($data)
    {
        $this->checkPermissions('invoice', 'manage_invoices');

        $model = $this->_getInvoice($data);
        $result = $this->getService()->updateInvoice($model, $data);

        if ($result && !empty($data['issue'])) {
            return $this->getService()->issueInvoice($model, $data);
        }

        return $result;
    }

    /**
     * Remove one line from invoice.
     *
     * @return bool
     */
    #[RequiredParams(['id' => 'Invoice item ID was not passed'])]
    public function item_delete($data)
    {
        $this->checkPermissions('invoice', 'manage_invoices');

        $model = $this->getDi()['em']->getRepository(\Box\Mod\Invoice\Entity\InvoiceItem::class)->find((int) $data['id']);
        if (!$model instanceof \Box\Mod\Invoice\Entity\InvoiceItem) {
            throw new InformationException('Invoice item was not found');
        }
        $invoiceItemService = $this->getDi()['mod_service']('Invoice', 'InvoiceItem');

        return $invoiceItemService->remove($model);
    }

    /**
     * Returns invoice items whose task execution permanently failed.
     *
     * @return array
     */
    public function get_failed_items($data)
    {
        $this->checkPermissions('invoice', 'view');

        $invoiceItemService = $this->getDi()['mod_service']('Invoice', 'InvoiceItem');
        $items = $invoiceItemService->getFailedItems();

        return array_map(function (\Box\Mod\Invoice\Entity\InvoiceItem $item): array {
            $data = $item->toApiArray();
            if ($item->getType() === \Box\Mod\Invoice\Entity\InvoiceItem::TYPE_ORDER && $item->getTask() === \Box\Mod\Invoice\Entity\InvoiceItem::TASK_RENEW) {
                $order = $this->getDi()['em']->find(Order::class, (int) $item->getRelId());
                $product = $order instanceof Order ? $this->getDi()['em']->find(\Box\Mod\Product\Entity\Product::class, (int) $order->getProductId()) : null;
                $data['product_type'] = $product?->getType();
                $meta = $this->getDi()['mod_service']('Order')->getOrderMetaRepository()->getPairsForOrder((int) $item->getRelId());
                if (isset($meta[\Box\Mod\Order\Service::META_PAID_RENEWAL_OUTCOME_PREFIX . $item->getId()])) {
                    $data['renewal_outcome'] = $meta[\Box\Mod\Order\Service::META_PAID_RENEWAL_OUTCOME_PREFIX . $item->getId()];
                }
            }

            return $data;
        }, $items);
    }

    /**
     * Re-queue a failed invoice item for execution by resetting its status and attempt counter.
     */
    #[RequiredParams(['id' => 'Invoice item ID was not passed'])]
    public function item_requeue($data): bool
    {
        $this->checkPermissions('invoice', 'manage_invoices');

        $invoiceItemService = $this->getDi()['mod_service']('Invoice', 'InvoiceItem');
        $invoiceItemService->requeueItem((int) $data['id']);

        return true;
    }

    /** Resolve an interrupted paid renewal after checking its provider outcome. */
    #[RequiredParams(['id' => 'Invoice item ID was not passed', 'decision' => 'Review decision was not passed'])]
    public function item_review_paid_renewal($data): bool
    {
        $this->checkPermissions('invoice', 'manage_invoices');
        $this->getDi()['mod_service']('Invoice', 'InvoiceItem')->reviewPaidRenewal((int) $data['id'], (string) $data['decision']);

        return true;
    }

    /** Record a staff decision after checking a held zero-priced domain renewal. */
    #[RequiredParams(['id' => 'Invoice item ID was not passed', 'decision' => 'Review decision was not passed'])]
    public function item_review_domain_renewal($data): bool
    {
        $this->checkPermissions('invoice', 'manage_invoices');
        $this->getDi()['mod_service']('Invoice', 'InvoiceItem')->reviewDomainRenewal((int) $data['id'], (string) $data['decision']);

        return true;
    }

    /**
     * Link a legacy gateway receipt after checking the payment at the provider.
     */
    #[RequiredParams(['receipt_id' => 'Receipt ID is missing', 'transaction_id' => 'Transaction ID is missing', 'provider_object_id' => 'Provider payment ID is missing'])]
    public function transaction_reconcile_receipt($data): bool
    {
        $this->checkPermissions('invoice', 'manage_invoices');
        $this->checkPermissions('client', 'manage_balance');
        $this->getService()->reconcileGatewayReceipt((int) $data['receipt_id'], (int) $data['transaction_id'], (string) $data['provider_object_id']);

        return true;
    }

    /**
     * Delete invoice.
     *
     * Unissued unpaid drafts are always deletable. Issued unpaid and
     * canceled invoices additionally require relaxed invoice immutability.
     * Paid, refunded, and note/reissue-linked invoices cannot be deleted;
     * cancel, reissue, or refund them instead.
     *
     * @return bool
     */
    #[RequiredParams(['id' => 'Invoice ID is missing'])]
    public function delete($data)
    {
        $this->checkPermissions('invoice', 'manage_invoices');

        $model = $this->_getInvoice($data);

        return $this->getService()->deleteInvoiceByAdmin($model);
    }

    /**
     * Generates new invoice for order. If unpaid invoice for selected order
     * already exists, new invoice will not be generated, and existing invoice id
     * is returned.
     *
     * @optional int $due_days - Days number until invoice is due
     *
     * @return string - invoice id
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['id' => 'Order ID was not passed'])]
    public function renewal_invoice($data)
    {
        $this->checkPermissions('invoice', 'manage_invoices');

        $model = $this->getDi()['em']->getRepository(Order::class)->find((int) $data['id']);
        if (!$model instanceof Order) {
            throw new \FOSSBilling\Core\Exception\BaseException('Order not found');
        }

        return $this->getService()->renewInvoice($model, $data);
    }

    /**
     * Use credits to pay for invoices
     * if credits are available in clients balance.
     *
     * @optional int $client_id - cover only one client invoices
     *
     * @return bool
     */
    public function batch_pay_with_credits($data)
    {
        $this->checkPermissions('invoice', 'manage_invoices');

        return $this->getService()->doBatchPayWithCredits($data);
    }

    /**
     * Cover one invoice with credits.
     *
     * @return bool
     */
    #[RequiredParams(['id' => 'Invoice ID is missing'])]
    public function pay_with_credits($data)
    {
        $this->checkPermissions('invoice', 'manage_invoices');

        $invoice = $this->_getInvoice($data);

        return $this->getService()->payInvoiceWithCredits($invoice);
    }

    /**
     * Apply part or all of the client's credit to an unpaid invoice.
     *
     * @return bool
     */
    #[RequiredParams(['id' => 'Invoice ID is missing', 'amount' => 'Amount is missing'])]
    public function apply_credit($data)
    {
        $this->checkPermissions('invoice', 'manage_invoices');

        return $this->getService()->applyCredit($this->_getInvoice($data), (float) $data['amount']);
    }

    /**
     * Take the credit applied to an unpaid invoice back to the client's credit.
     *
     * @return bool
     */
    #[RequiredParams(['id' => 'Invoice ID is missing'])]
    public function remove_credit($data)
    {
        $this->checkPermissions('invoice', 'manage_invoices');

        return $this->getService()->removeCredit($this->_getInvoice($data));
    }

    /**
     * Generate invoices for expiring orders.
     *
     * @return bool
     */
    public function batch_generate()
    {
        $this->checkPermissions('invoice', 'manage_invoices');

        return $this->getService()->generateInvoicesForExpiringOrders();
    }

    /**
     * Action to activate paid invoices lines.
     */
    public function batch_deliver_issued_notifications(): bool
    {
        $this->checkPermissions('invoice', 'manage_invoices');

        return $this->getService()->doBatchDeliverIssuedNotifications();
    }

    public function batch_activate_paid()
    {
        $this->checkPermissions('invoice', 'manage_invoices');

        return $this->getService()->doBatchPaidInvoiceActivation();
    }

    /**
     * Attempts the invoice due event batch (see batch_invoke_due_event()), limited to once per
     * rolling 24-hour period. If that batch ran within the previous 24 hours, it falls back to
     * dispatching the same due-date events directly so newly-eligible invoices are not missed
     * for up to 24 hours. Either way, the built-in reminder handlers atomically claim each
     * invoice via reminded_at before sending anything, so the same invoice is never reminded
     * twice even across overlapping cron runs or repeated dispatch of the underlying events.
     *
     * @return bool
     */
    public function batch_send_reminders($data)
    {
        $this->checkPermissions('invoice', 'manage_invoices');

        return $this->getService()->doBatchRemindersSend();
    }

    /**
     * Calls due events on unpaid and issued invoices.
     * Extensions can listen to BeforeInvoiceIsDueEvent and AfterInvoiceIsDueEvent.
     *
     * @optional bool $once_per_day - default true. Pass false if you want to execute this action more than once per day
     *
     * @return bool - true if executed, false - if it was already executed
     */
    public function batch_invoke_due_event($data)
    {
        $this->checkPermissions('invoice', 'manage_invoices');

        return $this->getService()->doBatchInvokeDueEvent($data);
    }

    /**
     * Charges the saved payment methods of invoices whose next automatic
     * try is due (see ServiceSavedCards). Run from the cron.
     *
     * @return bool
     */
    public function batch_charge_saved_cards($data)
    {
        $this->checkPermissions('invoice', 'manage_invoices');
        $this->getDi()['mod_service']('Invoice', 'SavedCards')->runDue();

        return true;
    }

    /**
     * Updates the automatic saved-method charge schedule (days before the
     * due date, retries, days between retries).
     *
     * @return bool
     */
    public function card_settings_update($data)
    {
        $this->checkPermissions('invoice', 'manage_invoices');
        $this->getDi()['mod_service']('Invoice', 'SavedCards')->saveSettings($data);

        return true;
    }

    /** The saved method an invoice's client is charged to, or null (also while a payment is still clearing). */
    #[RequiredParams(['id' => 'Invoice ID is missing'])]
    public function card_get($data): ?string
    {
        $this->checkPermissions('invoice', 'view');
        $invoice = $this->getService()->getInvoiceRepository()->find((int) $data['id'])
            ?? throw new InformationException('Invoice was not found');
        $savedCards = $this->getDi()['mod_service']('Invoice', 'SavedCards');
        if (PendingPayments::forInvoice($this->getDi()['em']->getConnection(), (int) $invoice->getId()) !== null) {
            return null;
        }

        return $savedCards->card((int) $invoice->getClientId())['card'] ?? null;
    }

    /** Charges the client's saved payment method for an unpaid invoice now; a failed charge is an error. */
    #[RequiredParams(['id' => 'Invoice ID is missing'])]
    public function card_charge($data): bool
    {
        $this->checkPermissions('invoice', 'manage_transactions');
        $invoice = $this->getService()->getInvoiceRepository()->find((int) $data['id'])
            ?? throw new InformationException('Invoice was not found');
        $savedCards = $this->getDi()['mod_service']('Invoice', 'SavedCards');
        $method = $savedCards->method((int) $invoice->getClientId());
        $adapter = $method['adapter'] ?? null;
        if (!$adapter instanceof \Payment_Adapter_Stripe && !$adapter instanceof \Payment_Adapter_PayPalAutoPay && !$adapter instanceof \Payment_Adapter_PayPalEmail) {
            throw new InformationException('The client has no saved payment method.');
        }

        $result = $savedCards->charge($invoice, $adapter, true);
        if (!$result['paid']) {
            throw new InformationException((string) $result['error']);
        }

        return true;
    }

    /**
     * Finds saved cards at Stripe for clients without one (a move from
     * another system), 25 clients after `after_id` per call:
     * {checked, found, next}.
     */
    public function card_find($data): array
    {
        $this->checkPermissions('invoice', 'manage_invoices');
        $adapter = $this->getDi()['mod_service']('Invoice', 'SavedCards')->stripe()
            ?? throw new InformationException('Saved cards are not available.');
        $result = $adapter->findCards((int) ($data['after_id'] ?? 0), 25);
        $this->getDi()['logger']->info('Invoice: looked for saved cards at Stripe for {checked} clients, found {found}', ['checked' => $result['checked'], 'found' => $result['found']]);

        return $result;
    }

    /** The balance of each enabled PayPal gateway whose dashboard balance is on, as the gateway balance widget. PayPal is asked at most every 10 minutes. */
    public function paypal_balances(): array
    {
        $this->checkPermissions('invoice', 'view');
        $di = $this->getDi();
        $result = [];
        foreach ($di['em']->getRepository(PayGateway::class)->findEnabledOrderedByIdDesc() as $gateway) {
            if (!in_array($gateway->getGateway(), ['PayPalEmail', 'PayPalAutoPay'], true)) {
                continue;
            }
            if ((string) (($gateway->getConfig() ?? [])['balance'] ?? '1') === '0') {
                continue;
            }
            $name = (string) $gateway->getName() === $gateway->getGateway()
                ? ($gateway->getGateway() === 'PayPalAutoPay' ? 'PayPal Auto-Pay' : 'PayPal')
                : (string) $gateway->getName();
            $key = 'fossbilling_paypal_balance_' . $gateway->getId() . '_' . (int) $gateway->isTestMode();
            $item = $di['cache']->getItem($key);
            if (!$item->isHit()) {
                try {
                    $balances = ['balances' => $di['mod_service']('Invoice', 'PayGateway')->getPaymentAdapter($gateway)->balances(), 'error' => null];
                } catch (\Throwable $e) {
                    $balances = ['balances' => [], 'error' => $e instanceof InformationException ? $e->getMessage() : 'PayPal did not answer. Please try again later.'];
                }
                $item->set($balances);
                $item->expiresAfter(600);
                $di['cache']->save($item);
            }
            $result[] = ['gateway' => $name, 'test_mode' => $gateway->isTestMode()] + (is_array($item->get()) ? $item->get() : []);
        }

        return $result;
    }

    /**
     * Send payment reminder notification for client.
     * Dispatches typed reminder events for custom notification code.
     *
     * @return bool
     */
    #[RequiredParams(['id' => 'Invoice ID is missing'])]
    public function send_reminder($data)
    {
        $this->checkPermissions('invoice', 'manage_invoices');

        $invoice = $this->_getInvoice($data);

        return $this->getService()->sendInvoiceReminder($invoice);
    }

    /**
     * Return invoice statuses with counter.
     *
     * @return array
     */
    public function get_statuses($data)
    {
        $this->checkPermissions('invoice', 'view');

        return $this->getService()->counter();
    }

    /**
     * Process all received transactions.
     *
     * @return bool
     */
    public function transaction_process_all($data)
    {
        $this->checkPermissions('invoice', 'manage_transactions');

        $transactionService = $this->getDi()['mod_service']('Invoice', 'Transaction');

        return $transactionService->processReceivedATransactions();
    }

    /**
     * Process selected transaction.
     *
     * Automated gateways only: offline payments settle through the approve
     * action instead.
     */
    #[RequiredParams(['id' => 'Transaction ID is missing'])]
    public function transaction_process($data): bool
    {
        $this->checkPermissions('invoice', 'manage_transactions');

        $model = $this->getDi()['em']->getRepository(Transaction::class)->find((int) $data['id']);
        if (!$model instanceof Transaction) {
            throw new \FOSSBilling\Core\Exception\BaseException('Transaction not found');
        }

        $transactionService = $this->getDi()['mod_service']('Invoice', 'Transaction');
        $gateway = $model->getGateway();
        // Offline payments settle through approval, never callback retries.
        // Processed rows stay idempotent: the claim below reports them as
        // already handled instead of throwing.
        if ($gateway instanceof PayGateway
            && $model->getStatus() !== Transaction::STATUS_PROCESSED
            && \Box\Mod\Invoice\ServicePayGateway::isManualApprovalGateway($gateway->getGateway())
        ) {
            throw $transactionService->manualApprovalException($gateway);
        }

        $this->getDi()['event_dispatcher']->dispatch(new BeforeAdminTransactionProcessEvent((int) $model->getId()));

        return $transactionService->preProcessTransaction($model);
    }

    /**
     * Approve an offline payment after confirming the money arrived.
     *
     * Only for gateways that settle through manual approval (e.g. Custom).
     * Credits the client and marks the invoice paid.
     */
    #[RequiredParams(['id' => 'Transaction ID is missing'])]
    public function transaction_approve($data): bool
    {
        $this->checkPermissions('invoice', 'manage_transactions');

        $model = $this->getDi()['em']->getRepository(Transaction::class)->find((int) $data['id']);
        if (!$model instanceof Transaction) {
            throw new \FOSSBilling\Core\Exception\BaseException('Transaction not found');
        }

        $this->getDi()['event_dispatcher']->dispatch(new BeforeAdminTransactionProcessEvent((int) $model->getId()));

        $transactionService = $this->getDi()['mod_service']('Invoice', 'Transaction');

        return $transactionService->approveTransaction($model);
    }

    /**
     * Update transaction details.
     *
     * @optional int $invoice_id - new invoice id
     * @optional string $txn_id - transaction id on payment gateway
     * @optional string $txn_status - transaction status on payment gateway
     * @optional int $gateway_id - Payment gateway ID on FOSSBilling
     * @optional float $amount - Transaction amount
     * @optional string $currency - Currency code. Must be available on FOSSBilling
     * @optional string $type - Currency code. Must be available on FOSSBilling
     * @optional string $status - Transaction status on FOSSBilling
     * @optional string $note - Custom note
     *
     * @return bool
     */
    #[RequiredParams(['id' => 'Transaction ID is missing'])]
    public function transaction_update($data)
    {
        $this->checkPermissions('invoice', 'manage_transactions');

        $model = $this->getDi()['em']->getRepository(Transaction::class)->find((int) $data['id']);
        if (!$model instanceof Transaction) {
            throw new \FOSSBilling\Core\Exception\BaseException('Transaction not found');
        }

        $transactionService = $this->getDi()['mod_service']('Invoice', 'Transaction');

        return $transactionService->update($model, $data);
    }

    /**
     * Create custom transaction.
     *
     * @optional array $get - $_GET data
     * @optional array $post - $_POST data
     * @optional array $server - $_SERVER data
     * @optional array $http_raw_post_data - php://input
     * @optional string $txn_id - transaction id on payment gateway
     * @optional bool $skip_validation - makes params invoice_id and gateway_id optional
     *
     * @return int $id - new transaction id
     */
    public function transaction_create($data)
    {
        $this->checkPermissions('invoice', 'manage_transactions');

        $transactionService = $this->getDi()['mod_service']('Invoice', 'Transaction');
        $data['source'] ??= 'admin';

        return $transactionService->create($data);
    }

    /**
     * Remove transaction.
     *
     * @return bool
     */
    #[RequiredParams(['id' => 'Transaction ID is missing'])]
    public function transaction_delete($data)
    {
        $this->checkPermissions('invoice', 'manage_transactions');

        $model = $this->getDi()['em']->getRepository(Transaction::class)->find((int) $data['id']);
        if (!$model instanceof Transaction) {
            throw new \FOSSBilling\Core\Exception\BaseException('Transaction not found');
        }

        $transactionService = $this->getDi()['mod_service']('Invoice', 'Transaction');

        return $transactionService->delete($model);
    }

    /**
     * Get transaction details.
     *
     * @return array
     */
    #[RequiredParams(['id' => 'Transaction ID is missing'])]
    public function transaction_get($data)
    {
        $this->checkPermissions('invoice', 'manage_transactions');

        $model = $this->getDi()['em']->getRepository(Transaction::class)->find((int) $data['id']);
        if (!$model instanceof Transaction) {
            throw new \FOSSBilling\Core\Exception\BaseException('Transaction not found');
        }

        $transactionService = $this->getDi()['mod_service']('Invoice', 'Transaction');

        return ['claim_token' => $model->getProcessingToken(), 'credit_recovery_state' => $model->getCreditRecoveryState()] + $transactionService->toApiArray($model, true);
    }

    /**
     * Get paginated list of transactions.
     *
     * @optional string $txn_id - search for transactions by transaction id on payment gateway
     * @optional string $sort - sort column: 'id', 'status', 'currency', 'type', 'txn_id', 'amount', 'gateway', 'created_at' or 'updated_at'
     * @optional string $direction - sort direction: 'ASC' or 'DESC'
     *
     * @return array
     */
    public function transaction_get_list($data)
    {
        $this->checkPermissions('invoice', 'manage_transactions');

        $transactionService = $this->getDi()['mod_service']('Invoice', 'Transaction');
        $qb = $transactionService->getTransactionRepository()->getSearchQueryBuilder($data);

        return $this->getDi()['pager']->paginateMappedQuery(
            $qb,
            Options::fromArray($data),
            static fn ($row): array => $transactionService->transactionResultToApiArray($row[0], $row['gateway'] ?? null, $row['gateway_code'] ?? null),
        );
    }

    /**
     * Return transactions statuses with counter.
     *
     * @return array
     */
    public function transaction_get_statuses($data)
    {
        $this->checkPermissions('invoice', 'view');

        $transactionService = $this->getDi()['mod_service']('Invoice', 'Transaction');

        return $transactionService->counter();
    }

    /**
     * Get available transaction statuses.
     *
     * @return array
     */
    public function transaction_get_statuses_pairs($data)
    {
        $this->checkPermissions('invoice', 'view');

        $transactionService = $this->getDi()['mod_service']('Invoice', 'Transaction');

        return $transactionService->getStatusPairs();
    }

    /**
     * Get available transaction statuses.
     *
     * @return array
     */
    public function transaction_statuses($data)
    {
        $this->checkPermissions('invoice', 'view');

        $transactionService = $this->getDi()['mod_service']('Invoice', 'Transaction');

        return $transactionService->getStatuses();
    }

    /**
     * Get available transaction statuses on gateways.
     *
     * @return array
     */
    public function transaction_gateway_statuses($data)
    {
        $this->checkPermissions('invoice', 'view');

        $transactionService = $this->getDi()['mod_service']('Invoice', 'Transaction');

        return $transactionService->getGatewayStatuses();
    }

    /**
     * Get available transaction types.
     *
     * @return array
     */
    public function transaction_types($data)
    {
        $this->checkPermissions('invoice', 'view');

        $transactionService = $this->getDi()['mod_service']('Invoice', 'Transaction');

        return $transactionService->getTypes();
    }

    /**
     * Atomically claim a transaction for processing.
     * Uses conditional UPDATE to prevent race conditions when multiple
     * workers attempt to process the same transaction simultaneously.
     *
     * @param array $data Contains 'id' - the transaction ID to claim
     *
     * @return bool True if the transaction was successfully claimed, false if already claimed/processed
     *
     * @throws \FOSSBilling\Core\Exception\BaseException if transaction ID is missing
     */
    public function transaction_claim_for_processing($data)
    {
        $this->checkPermissions('invoice', 'manage_transactions');

        $required = [
            'id' => 'Transaction ID is required',
        ];
        $this->getDi()['validator']->checkRequiredParamsForArray($required, $data);

        $transactionService = $this->getDi()['mod_service']('Invoice', 'Transaction');

        return $transactionService->claimForProcessing((int) $data['id']);
    }

    #[RequiredParams(['id' => 'Transaction ID is required'])]
    public function transaction_inspect_payment($data): array
    {
        $this->checkPermissions('invoice', 'manage_transactions');

        return $this->getDi()['mod_service']('Invoice', 'PaymentRecovery')->inspectTransaction((int) $data['id']);
    }

    public function payment_recovery_list($data): array
    {
        $this->checkPermissions('invoice', 'manage_transactions');

        return $this->getDi()['mod_service']('Invoice', 'PaymentRecovery')->queue();
    }

    #[RequiredParams(['id' => 'Payment attempt ID is required'])]
    public function payment_recovery_inspect($data): array
    {
        $this->checkPermissions('invoice', 'manage_transactions');

        return $this->getDi()['mod_service']('Invoice', 'PaymentRecovery')->inspect((string) $data['id'], isset($data['reference']) ? (string) $data['reference'] : null);
    }

    #[RequiredParams(['id' => 'Payment attempt ID is required'])]
    public function payment_recovery_reconcile($data): array
    {
        $this->checkPermissions('invoice', 'manage_transactions');
        if ((string) ($data['confirmed'] ?? '') !== '1') {
            throw new InformationException('Confirm that the previous worker has stopped and the gateway outcome has been checked.');
        }

        return $this->getDi()['mod_service']('Invoice', 'PaymentRecovery')->reconcile(
            (string) $data['id'], isset($data['reference']) ? (string) $data['reference'] : null,
            (string) ($data['no_payment'] ?? '') === '1', (string) ($data['note'] ?? ''), (int) $this->getIdentity()->getId()
        );
    }

    #[RequiredParams(['id' => 'Transaction ID is required', 'note' => 'Reconciliation note is required'])]
    public function transaction_reconcile_wallet($data): bool
    {
        $this->checkPermissions('invoice', 'manage_transactions');
        if ((string) ($data['confirmed'] ?? '') !== '1' || !array_key_exists('claim_token', $data) || !is_string($data['claim_token'])
            || ($data['claim_token'] !== '' && preg_match('/^[a-f0-9]{32}$/', $data['claim_token']) !== 1)) {
            throw new InformationException('Stop the previous worker, verify the gateway and wallet history, and reload the current processing claim.');
        }
        $creditId = ($data['credit_id'] ?? '') === '' ? null : filter_var($data['credit_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($creditId === false) {
            throw new InformationException('Enter a valid wallet credit ID.');
        }

        return $this->getDi()['mod_service']('Invoice', 'Transaction')->reconcileWalletCredit(
            (int) $data['id'], $creditId, (string) ($data['no_credit'] ?? '') === '1', $data['claim_token'] === '' ? null : $data['claim_token'],
            (int) $this->getIdentity()->getId(), (string) $data['note']
        );
    }

    /** Release an interrupted transaction after checking the worker and gateway. */
    #[RequiredParams(['id' => 'Transaction ID is required'])]
    public function transaction_release_stale($data): bool
    {
        $this->checkPermissions('invoice', 'manage_transactions');
        if (($data['confirmed'] ?? null) !== '1' && ($data['confirmed'] ?? null) !== 1) {
            throw new InformationException('Confirm that the previous worker has stopped and check the gateway outcome.');
        }

        if (!array_key_exists('claim_token', $data) || !is_string($data['claim_token']) || ($data['claim_token'] !== '' && preg_match('/^[a-f0-9]{32}$/', $data['claim_token']) !== 1)) {
            throw new InformationException('Reload this transaction and review its current processing claim before releasing it.');
        }

        return $this->getDi()['mod_service']('Invoice', 'Transaction')->releaseStaleProcessing((int) $data['id'], $this->getIdentity()->getId(), $data['claim_token'] === '' ? null : $data['claim_token']);
    }

    /**
     * Get available gateways.
     *
     * @optional string $sort - sort column: 'id', 'title' or 'code'
     * @optional string $direction - sort direction: 'ASC' or 'DESC'
     *
     * @return array
     */
    public function gateway_get_list($data)
    {
        $this->checkPermissions('invoice', 'manage_gateways');

        $gatewayService = $this->getDi()['mod_service']('Invoice', 'PayGateway');
        $qb = $gatewayService->getPayGatewayRepository()->getSearchQueryBuilder($data);
        $identity = $this->getIdentity();

        return $this->getDi()['pager']->paginateMappedQuery($qb, Options::fromArray($data), static fn ($entity): array => $gatewayService->toApiArray($entity, false, $identity));
    }

    /**
     * Get available gateways pairs.
     *
     * @return array
     */
    public function gateway_get_pairs($data)
    {
        $this->checkPermissions('invoice', 'manage_gateways');

        $gatewayService = $this->getDi()['mod_service']('Invoice', 'PayGateway');

        return $gatewayService->getPairs();
    }

    /**
     * Return existing module but not activated.
     *
     * @return array
     */
    public function gateway_get_available(array $data)
    {
        $this->checkPermissions('invoice', 'manage_gateways');

        $gatewayService = $this->getDi()['mod_service']('Invoice', 'PayGateway');

        return $gatewayService->getAvailable();
    }

    /**
     * Install available payment gateway.
     *
     * @return true
     */
    #[RequiredParams(['code' => 'Payment gateway code is missing'])]
    public function gateway_install(array $data)
    {
        $this->checkPermissions('invoice', 'manage_gateways');

        $code = $data['code'];
        $gatewayService = $this->getDi()['mod_service']('Invoice', 'PayGateway');

        return $gatewayService->install($code);
    }

    /**
     * Get gateway details.
     *
     * @return array
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['id' => 'Gateway ID was not passed'])]
    public function gateway_get($data)
    {
        $this->checkPermissions('invoice', 'manage_gateways');

        $model = $this->getDi()['em']->getRepository(PayGateway::class)->find((int) $data['id']);
        if (!$model instanceof PayGateway) {
            throw new \FOSSBilling\Core\Exception\BaseException('Gateway not found');
        }

        $gatewayService = $this->getDi()['mod_service']('Invoice', 'PayGateway');

        return $gatewayService->toApiArray($model, true, $this->getIdentity());
    }

    /**
     * Copy gateway from existing one.
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['id' => 'Gateway ID was not passed'])]
    public function gateway_copy($data)
    {
        $this->checkPermissions('invoice', 'manage_gateways');

        $model = $this->getDi()['em']->getRepository(PayGateway::class)->find((int) $data['id']);
        if (!$model instanceof PayGateway) {
            throw new \FOSSBilling\Core\Exception\BaseException('Gateway not found');
        }
        $gatewayService = $this->getDi()['mod_service']('Invoice', 'PayGateway');

        return $gatewayService->copy($model);
    }

    /**
     * Change gateway settings.
     *
     * @optional string $title - gateway title
     * @optional string $logo_url - public HTTP(S) image URL to download and store locally; blank keeps the current image
     * @optional file $logo_file - custom image uploaded in a multipart request; takes precedence over logo_url
     * @optional bool $remove_custom_logo - restore the adapter's default image
     * @optional array $config - gateway config array
     * @optional array $accepted_currencies - list of currencies this gateway supports
     * @optional bool $enabled - flag to enable or disable gateway
     * @optional int $replacement_id - when disabling, move unpaid invoices to this enabled gateway
     * @optional bool $allow_single - flag to enable or disable single payment option
     * @optional bool $allow_recurrent - flag to enable or disable recurrent payment option
     * @optional bool $test_mode - flag to enable or disable test mode for gateway
     *
     * @return bool
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['id' => 'Gateway ID was not passed'])]
    public function gateway_update($data)
    {
        $this->checkPermissions('invoice', 'manage_gateways');

        $model = $this->getDi()['em']->getRepository(PayGateway::class)->find((int) $data['id']);
        if (!$model instanceof PayGateway) {
            throw new \FOSSBilling\Core\Exception\BaseException('Gateway not found');
        }
        $gatewayService = $this->getDi()['mod_service']('Invoice', 'PayGateway');

        $file = $this->getDi()['request']->files->get('logo_file');
        if ($file !== null && !$file instanceof UploadedFile) {
            throw new \FOSSBilling\Core\Exception\BaseException('Payment gateway image upload failed.');
        }

        return $gatewayService->update($model, $data, $file);
    }

    /**
     * Remove payment gateway from system.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['id' => 'Gateway ID was not passed'])]
    public function gateway_delete($data)
    {
        $this->checkPermissions('invoice', 'manage_gateways');

        $model = $this->getDi()['em']->getRepository(PayGateway::class)->find((int) $data['id']);
        if (!$model instanceof PayGateway) {
            throw new \FOSSBilling\Core\Exception\BaseException('Gateway not found');
        }
        $gatewayService = $this->getDi()['mod_service']('Invoice', 'PayGateway');

        return $gatewayService->delete($model);
    }

    /**
     * Get list of subscriptions.
     *
     * @optional string $sort - sort column: 'id', 'sid', 'status', 'currency', 'period', 'amount', 'created_at' or 'updated_at'
     * @optional string $direction - sort direction: 'ASC' or 'DESC'
     *
     * @return array
     */
    public function subscription_get_list($data)
    {
        $this->checkPermissions('invoice', 'manage_subscriptions');

        $subscriptionService = $this->getDi()['mod_service']('Invoice', 'Subscription');
        $qb = $subscriptionService->getSubscriptionRepository()->getSearchQueryBuilder($data);
        $identity = $this->getIdentity();

        return $this->getDi()['pager']->paginateMappedQuery($qb, Options::fromArray($data), static fn ($entity): array => $subscriptionService->toApiArray($entity, false, $identity));
    }

    /**
     * Add new subscription.
     *
     * @optional string $sid - subscription id on payment gateway
     * @optional string $status - status: active|canceled
     * @optional string $period - example: 1W - every week, 2M - every 2 months
     * @optional string $amount - billed amount
     * @optional string $rel_type - related item type
     * @optional string $rel_id - related item id
     *
     * @return int - id
     *
     * @throws InformationException
     */
    #[RequiredParams([
        'client_id' => 'Client ID was not passed',
        'gateway_id' => 'Payment gateway ID was not passed',
        'currency' => 'Subscription currency was not passed',
    ])]
    public function subscription_create($data)
    {
        $this->checkPermissions('invoice', 'manage_subscriptions');

        $client = $this->getDi()['em']->getRepository(Client::class)->find((int) $data['client_id']) ?? throw new \FOSSBilling\Core\Exception\BaseException('Client not found');
        $payGateway = $this->getDi()['em']->getRepository(PayGateway::class)->find((int) $data['gateway_id']);
        if (!$payGateway instanceof PayGateway) {
            throw new \FOSSBilling\Core\Exception\BaseException('Payment gateway not found');
        }

        if (strtoupper((string) $client->getCurrency()) !== strtoupper((string) $data['currency'])) {
            throw new InformationException('Client currency must match subscription currency. Check if clients currency is defined.');
        }
        $subscriptionService = $this->getDi()['mod_service']('Invoice', 'Subscription');

        return $subscriptionService->create($client, $payGateway, $data);
    }

    /**
     * Update subscription options.
     *
     * @optional int $status - subscription status
     * @optional string $sid - subscription id on payment gateway
     * @optional string $period - subscription period code
     * @optional string $amount - subscription amount
     * @optional string $currency - subscription currency
     *
     * @return bool
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['id' => 'Subscription ID was not passed'])]
    public function subscription_update($data)
    {
        $this->checkPermissions('invoice', 'manage_subscriptions');

        $model = $this->getDi()['em']->getRepository(Subscription::class)->find((int) $data['id']);
        if (!$model instanceof Subscription) {
            throw new \FOSSBilling\Core\Exception\BaseException('Subscription not found');
        }
        $subscriptionService = $this->getDi()['mod_service']('Invoice', 'Subscription');

        return $subscriptionService->update($model, $data);
    }

    /**
     * Get subscription details.
     *
     * @return array
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    public function subscription_get($data)
    {
        $this->checkPermissions('invoice', 'manage_subscriptions');

        if (!isset($data['id']) && !isset($data['sid'])) {
            $required = [
                'id' => 'Subscription ID was not passed',
                'sid' => 'Subscription sID was not passed',
            ];
            $this->getDi()['validator']->checkRequiredParamsForArray($required, $data);
        }
        $subscriptionRepository = $this->getDi()['em']->getRepository(Subscription::class);
        $model = null;
        if (isset($data['id'])) {
            $model = $subscriptionRepository->find((int) $data['id']);
        }

        if (!$model && isset($data['sid'])) {
            $model = $subscriptionRepository->findOneBy(['sid' => $data['sid']]);
        }

        if (!$model instanceof Subscription) {
            throw new \FOSSBilling\Core\Exception\BaseException('Subscription not found');
        }

        $subscriptionService = $this->getDi()['mod_service']('Invoice', 'Subscription');

        return $subscriptionService->toApiArray($model, true, $this->getIdentity());
    }

    /**
     * Remove subscription.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['id' => 'Subscription ID was not passed'])]
    public function subscription_delete($data)
    {
        $this->checkPermissions('invoice', 'manage_subscriptions');

        $model = $this->getDi()['em']->getRepository(Subscription::class)->find((int) $data['id']);
        if (!$model instanceof Subscription) {
            throw new \FOSSBilling\Core\Exception\BaseException('Subscription not found');
        }
        $subscriptionService = $this->getDi()['mod_service']('Invoice', 'Subscription');

        return $subscriptionService->delete($model);
    }

    /**
     * Remove tax rule.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['id' => 'Tax ID was not passed'])]
    public function tax_delete($data)
    {
        $this->checkPermissions('invoice', 'manage_tax');

        $taxService = $this->getDi()['mod_service']('Invoice', 'Tax');
        $model = $taxService->getTaxRepository()->find($data['id']);
        if (!$model instanceof Tax) {
            throw new InformationException('Tax rule not found');
        }

        return $taxService->delete($model);
    }

    /**
     * Create new tax rule.
     *
     * @return int - new tax id
     */
    #[RequiredParams([
        'name' => 'Tax name is missing',
        'taxrate' => 'Tax rate is missing or is invalid',
    ])]
    public function tax_create($data)
    {
        $this->checkPermissions('invoice', 'manage_tax');

        $taxService = $this->getDi()['mod_service']('Invoice', 'Tax');

        return $taxService->create($data);
    }

    /**
     * Update tax rule.
     *
     * @return array
     */
    #[RequiredParams(['id' => 'Tax ID was not passed'])]
    public function tax_get($data)
    {
        $this->checkPermissions('invoice', 'manage_tax');

        $taxService = $this->getDi()['mod_service']('Invoice', 'Tax');
        $tax = $taxService->getTaxRepository()->find($data['id']);
        if (!$tax instanceof Tax) {
            throw new InformationException('Tax rule not found');
        }

        return $taxService->toApiArray($tax);
    }

    /**
     * Update tax rule.
     *
     * @return bool
     */
    #[RequiredParams([
        'id' => 'Tax ID is missing',
        'taxrate' => 'Tax rate is missing',
        'name' => 'Tax name is missing',
    ])]
    public function tax_update($data)
    {
        $this->checkPermissions('invoice', 'manage_tax');

        $taxService = $this->getDi()['mod_service']('Invoice', 'Tax');
        $tax = $taxService->getTaxRepository()->find($data['id']);
        if (!$tax instanceof Tax) {
            throw new InformationException('Tax rule not found');
        }

        return $taxService->update($tax, $data);
    }

    /**
     * Get list of taxes.
     *
     * @optional string $sort - sort column: 'id', 'name', 'country', 'state', 'taxrate', 'created_at' or 'updated_at'
     * @optional string $direction - sort direction: 'ASC' or 'DESC'
     *
     * @return array
     */
    public function tax_get_list($data)
    {
        $this->checkPermissions('invoice', 'manage_tax');

        $taxService = $this->getDi()['mod_service']('Invoice', 'Tax');
        $qb = $taxService->getTaxRepository()->getSearchQueryBuilder($data);

        return $this->getDi()['pager']->paginateDoctrineQuery($qb, Options::fromArray($data));
    }

    /**
     * Apply an existing promotion to an order on an unpaid invoice.
     *
     * @optional string $promo_code - promo code to apply (takes precedence over promo_id)
     * @optional int $promo_id - promo ID to apply
     * @optional int $order_id - order on the invoice to discount; required when the invoice covers several orders
     *
     * @return float - applied discount amount in the invoice currency
     */
    #[RequiredParams(['id' => 'Invoice ID is missing'])]
    public function promo_add($data): float
    {
        $this->checkPermissions('invoice', 'manage_invoices');
        $this->checkPermissions('product', 'manage_promos');

        $model = $this->_getInvoice($data);
        [$promo, $order] = $this->resolvePromoAndOrder($data);

        return $this->getService()->promoAddToInvoice($model, $promo, $order);
    }

    /**
     * Remove a previously applied promotion from an order on an unpaid invoice.
     *
     * @optional string $promo_code - promo code to remove (takes precedence over promo_id)
     * @optional int $promo_id - promo ID to remove
     * @optional int $order_id - order on the invoice to remove the discount from; required when the invoice covers several orders
     *
     * @return float - removed discount amount in the invoice currency
     */
    #[RequiredParams(['id' => 'Invoice ID is missing'])]
    public function promo_remove($data): float
    {
        $this->checkPermissions('invoice', 'manage_invoices');
        $this->checkPermissions('product', 'manage_promos');

        $model = $this->_getInvoice($data);
        [$promo, $order] = $this->resolvePromoAndOrder($data);

        return $this->getService()->promoRemoveFromInvoice($model, $promo, $order);
    }

    /**
     * @return array{0: \Box\Mod\Product\Entity\Promo, 1: ?Order}
     */
    private function resolvePromoAndOrder(array $data): array
    {
        $productService = $this->getDi()['mod_service']('Product');
        $promo = $productService->resolvePromoReference(
            isset($data['promo_code']) ? (string) $data['promo_code'] : null,
            isset($data['promo_id']) ? (int) $data['promo_id'] : null
        ) ?? throw new InformationException('Promo code or promo ID was not passed');

        $order = null;
        if (!empty($data['order_id'])) {
            $order = $this->getDi()['em']->getRepository(Order::class)->find((int) $data['order_id'])
                ?? throw new InformationException('Order not found');
        }

        return [$promo, $order];
    }

    #[RequiredParams(['id' => 'Invoice ID was not passed'])]
    private function _getInvoice($data)
    {
        return $this->getService()->getInvoiceRepository()->find((int) $data['id'])
            ?? throw new InformationException('Invoice was not found');
    }

    /**
     * Deletes invoices with given IDs.
     */
    #[RequiredParams(['ids' => 'IDs were not passed'])]
    public function batch_delete($data): bool
    {
        $this->checkPermissions('invoice', 'manage_invoices');

        foreach ($data['ids'] as $id) {
            $this->delete(['id' => $id]);
        }

        return true;
    }

    /**
     * Deletes subscriptions with given IDs.
     */
    #[RequiredParams(['ids' => 'IDs were not passed'])]
    public function batch_delete_subscription($data): bool
    {
        $this->checkPermissions('invoice', 'manage_subscriptions');

        foreach ($data['ids'] as $id) {
            $this->subscription_delete(['id' => $id]);
        }

        return true;
    }

    /**
     * Deletes transactions with given IDs.
     */
    #[RequiredParams(['ids' => 'IDs were not passed'])]
    public function batch_delete_transaction($data): bool
    {
        $this->checkPermissions('invoice', 'manage_transactions');

        foreach ($data['ids'] as $id) {
            $this->transaction_delete(['id' => $id]);
        }

        return true;
    }

    /**
     * Deletes taxes with given IDs.
     */
    #[RequiredParams(['ids' => 'IDs were not passed'])]
    public function batch_delete_tax($data): bool
    {
        $this->checkPermissions('invoice', 'manage_tax');

        foreach ($data['ids'] as $id) {
            $this->tax_delete(['id' => $id]);
        }

        return true;
    }

    public function export_csv($data): Response
    {
        $this->checkPermissions('invoice', 'view');
        $this->checkPermissions('invoice', 'export');

        $data['headers'] ??= [];

        return $this->getService()->exportCSV($data['headers']);
    }
}
