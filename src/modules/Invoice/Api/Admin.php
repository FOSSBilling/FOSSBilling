<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * Invoice management API.
 */

namespace Box\Mod\Invoice\Api;

use FOSSBilling\InformationException;

class Admin extends \Api_Abstract
{
    /**
     * Returns paginated list of invoices.
     *
     * @return array
     */
    public function get_list($data)
    {
        $service = $this->getService();
        [$sql, $params] = $service->getSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
        $pager = $this->di['pager']->getAdvancedResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $invoice = $this->di['db']->getExistingModelById('Invoice', $item['id'], 'Invoice not found');
            $pager['list'][$key] = $this->getService()->toApiArray($invoice, true, $this->getIdentity());
        }

        return $pager;
    }

    /**
     * Get invoice details.
     *
     * @return array
     */
    public function get($data)
    {
        $model = $this->_getInvoice($data);

        return $this->getService()->toApiArray($model, true, $this->getIdentity());
    }

    /**
     * Sets invoice status to paid. This method differs from invoice update method
     * in a way that it sends notification to Events system, so emails are sent.
     * Also this will try to automatically apply payment if clients balance is
     * available.
     *
     * @optional bool $execute - execute related tasks on invoice items. Default false.
     *
     * @return array
     */
    public function mark_as_paid($data)
    {
        $execute = false;
        if (isset($data['execute']) && $data['execute']) {
            $execute = true;
        }
        $invoice = $this->_getInvoice($data);
        $gateway_id = ['id' => $invoice->gateway_id];

        if (!$gateway_id['id']) {
            throw new InformationException('You must set the payment gateway in the invoice manage tab before marking it as paid.');
        }

        $payGateway = $this->gateway_get($gateway_id);
        $charge = false;

        // Check if the payment type is "Custom Payment", Add the transaction and process it.
        if ($payGateway['code'] == 'Custom' && $payGateway['enabled'] == 1) {
            // create transaction
            $transactionService = $this->di['mod_service']('Invoice', 'Transaction');
            $newtx = $transactionService->create([
                'invoice_id' => $invoice->id,
                'gateway_id' => $invoice->gateway_id,
                'currency' => $invoice->currency,
                'status' => 'received',
                'txn_id' => $data['transactionId'],
            ]);

            try {
                return $transactionService->processTransaction($newtx);
            } catch (\Exception $e) {
                $this->di['logger']->info('Error processing transaction: ' . $e->getMessage());
            }
        }

        return $this->getService()->markAsPaid($invoice, $charge, $execute);
    }

    /**
     * Prepare invoice for editing and updating.
     * Uses clients details, such as currency assigned to client.
     * If client currency is not defined, sets default currency for client.
     *
     * @optional bool $approve - set true to approve invoice after preparation. Defaults to false
     * @optional int $gateway_id - Selected payment gateway id
     * @optional array $items - list of invoice lines. One line is array of line parameters
     * @optional string $text_1 - text to be displayed before invoice items table
     * @optional string $text_2 - text to be displayed after invoice items table
     *
     * @return int $id - newly generated invoice ID
     */
    public function prepare($data)
    {
        $required = [
            'client_id' => 'Client id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->di['db']->getExistingModelById('Client', $data['client_id'], 'Client not found');

        $invoice = $this->getService()->prepareInvoice($client, $data);

        return $invoice->id;
    }

    /**
     * Approve invoice.
     *
     * @return bool
     */
    public function approve($data)
    {
        $model = $this->_getInvoice($data);

        return $this->getService()->approveInvoice($model, $data);
    }

    /**
     * Add refunds.
     *
     * @optional string $note - note for refund
     *
     * @return bool
     */
    public function refund($data)
    {
        $model = $this->_getInvoice($data);
        $note = $data['note'] ?? null;

        return $this->getService()->refundInvoice($model, $note);
    }

    /**
     * Update invoice details.
     *
     * @optional string $paid_at - Invoice payment date (Y-m-d) or empty to remove
     * @optional string $due_at - Invoice due date (Y-m-d)or empty to remove
     * @optional string $created_at - Invoice issue date (Y-m-d) or empty to remove
     * @optional string $serie - Invoice serie
     * @optional string $nr - Invoice number
     * @optional string $status - Invoice status: paid|unpaid
     * @optional string $taxrate - Invoice tax rate
     * @optional string $taxname - Invoice tax name
     * @optional bool $approved - flag to set invoice as approved. Approved invoices are visible to clients
     * @optional string $notes - notes
     * @optional int $gateway_id - selected payment method - gateway id
     * @optional array $new_item - [title] [price]
     * @optional string $text_1 - Custom invoice text 1
     * @optional string $text_2 - Custom invoice text 1
     * @optional string $seller_company - Seller company name
     * @optional string $seller_company_vat - Seller company VAT number
     * @optional string $seller_company_number - Seller company number
     * @optional string $seller_address - Seller address
     * @optional string $seller_phone - Seller phone
     * @optional string $seller_email - Seller email
     * @optional string $buyer_first_name - Buyer first name
     * @optional string $buyer_last_name - Buyer last name
     * @optional string $buyer_company - Buyer company name
     * @optional string $buyer_company_vat - Buyer company VAT number
     * @optional string $buyer_company_number - Buyer company number
     * @optional string $buyer_address - Buyer address
     * @optional string $buyer_city - Buyer city
     * @optional string $buyer_state - Buyer state
     * @optional string $buyer_country - Buyer country
     * @optional string $buyer_zip - Buyer zip
     * @optional string $buyer_phone - Buyer phone
     * @optional string $buyer_email - Buyer email
     *
     * @return bool
     */
    public function update($data)
    {
        $model = $this->_getInvoice($data);

        return $this->getService()->updateInvoice($model, $data);
    }

    /**
     * Remove one line from invoice.
     *
     * @return bool
     */
    public function item_delete($data)
    {
        $required = [
            'id' => 'Invoice item id not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('InvoiceItem', $data['id'], 'Invoice item was not found');
        $invoiceItemService = $this->di['mod_service']('Invoice', 'InvoiceItem');

        return $invoiceItemService->remove($model);
    }

    /**
     * Delete invoice.
     *
     * @return bool
     */
    public function delete($data)
    {
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
     * @throws \FOSSBilling\Exception
     */
    public function renewal_invoice($data)
    {
        $required = [
            'id' => 'Order id required',
        ];

        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ClientOrder', $data['id'], 'Order not found');
        if ($model->price <= 0) {
            throw new InformationException('Order :id is free. No need to generate invoice.', [':id' => $model->id]);
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
        return $this->getService()->doBatchPayWithCredits($data);
    }

    /**
     * Cover one invoice with credits.
     *
     * @return bool
     */
    public function pay_with_credits($data)
    {
        $invoice = $this->_getInvoice($data);

        return $this->getService()->payInvoiceWithCredits($invoice);
    }

    /**
     * Generate invoices for expiring orders.
     *
     * @return bool
     */
    public function batch_generate()
    {
        return $this->getService()->generateInvoicesForExpiringOrders();
    }

    /**
     * Action to activate paid invoices lines.
     *
     * @return bool
     */
    public function batch_activate_paid()
    {
        return $this->getService()->doBatchPaidInvoiceActivation();
    }

    /**
     * Send buyer reminders about upcoming payment.
     *
     * @return bool
     */
    public function batch_send_reminders($data)
    {
        return $this->getService()->doBatchRemindersSend();
    }

    /**
     * Calls due events on unpaid and approved invoices.
     * Attach custom event hooks events:.
     *
     * onEventBeforeInvoiceIsDue - event receives params: id and days_left
     * onEventAfterInvoiceIsDue - event receives params: id and days_passed
     *
     * @optional bool $once_per_day - default true. Pass false if you want to execute this action more than once per day
     *
     * @return bool - true if executed, false - if it was already executed
     */
    public function batch_invoke_due_event($data)
    {
        return $this->getService()->doBatchInvokeDueEvent($data);
    }

    /**
     * Send payment reminder notification for client.
     * Calls event hook, so you can attach your custom notification code.
     *
     * @return bool
     */
    public function send_reminder($data)
    {
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
        return $this->getService()->counter();
    }

    /**
     * Process all received transactions.
     *
     * @return bool
     */
    public function transaction_process_all($data)
    {
        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');

        return $transactionService->proccessReceivedATransactions();
    }

    /**
     * Process selected transaction.
     */
    public function transaction_process($data): bool
    {
        $required = [
            'id' => 'Transaction id is missing',
        ];

        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Transaction', $data['id'], 'Transaction not found');

        $output = null;
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminTransactionProcess', 'params' => ['id' => $model->id]]);

        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');

        return $transactionService->preProcessTransaction($model);
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
     * @optional bool $validate_ipn - Flag to enable and disable IPN validation for this transaction
     * @optional string $note - Custom note
     *
     * @return bool
     */
    public function transaction_update($data)
    {
        $required = [
            'id' => 'Transaction id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        $model = $this->di['db']->getExistingModelById('Transaction', $data['id'], 'Transaction not found');

        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');

        return $transactionService->update($model, $data);
    }

    /**
     * Create custom transaction.
     *
     * @optional array $get - $_GET data
     * @optional array $post - $_POST data
     * @optional array $server - $_SERVER data
     * @optional array $http_raw_post_data - file_get_contents("php://input")
     * @optional string $txn_id - transaction id on payment gateway
     * @optional bool $skip_validation - makes params invoice_id and gateway_id optional
     *
     * @return int $id - new transaction id
     */
    public function transaction_create($data)
    {
        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');

        return $transactionService->create($data);
    }

    /**
     * Remove transaction.
     *
     * @return bool
     */
    public function transaction_delete($data)
    {
        $required = [
            'id' => 'Transaction id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        $model = $this->di['db']->getExistingModelById('Transaction', $data['id'], 'Transaction not found');

        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');

        return $transactionService->delete($model);
    }

    /**
     * Get transaction details.
     *
     * @return array
     */
    public function transaction_get($data)
    {
        $required = [
            'id' => 'Transaction id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        $model = $this->di['db']->getExistingModelById('Transaction', $data['id'], 'Transaction not found');

        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');

        return $transactionService->toApiArray($model, true);
    }

    /**
     * Get paginated list of transactions.
     *
     * @optional string $txn_id - search for transactions by transaction id on payment gateway
     *
     * @return array
     */
    public function transaction_get_list($data)
    {
        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');
        [$sql, $params] = $transactionService->getSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $transaction = $this->di['db']->getExistingModelById('Transaction', $item['id'], 'Transaction not found');
            $pager['list'][$key] = $transactionService->toApiArray($transaction);
        }

        return $pager;
    }

    /**
     * Return transactions statuses with counter.
     *
     * @return array
     */
    public function transaction_get_statuses($data)
    {
        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');

        return $transactionService->counter();
    }

    /**
     * Get available transaction statuses.
     *
     * @return array
     */
    public function transaction_get_statuses_pairs($data)
    {
        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');

        return $transactionService->getStatusPairs();
    }

    /**
     * Get available transaction statuses.
     *
     * @return array
     */
    public function transaction_statuses($data)
    {
        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');

        return $transactionService->getStatuses();
    }

    /**
     * Get available transaction statuses on gateways.
     *
     * @return array
     */
    public function transaction_gateway_statuses($data)
    {
        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');

        return $transactionService->getGatewayStatuses();
    }

    /**
     * Get available transaction types.
     *
     * @return array
     */
    public function transaction_types($data)
    {
        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');

        return $transactionService->getTypes();
    }

    /**
     * Get available gateways.
     *
     * @return array
     */
    public function gateway_get_list($data)
    {
        $gatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        [$sql, $params] = $gatewayService->getSearchQuery($data);

        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $gateway = $this->di['db']->getExistingModelById('PayGateway', $item['id'], 'Gateway not found');
            $pager['list'][$key] = $gatewayService->toApiArray($gateway, false, $this->getIdentity());
        }

        return $pager;
    }

    /**
     * Get available gateways pairs.
     *
     * @return array
     */
    public function gateway_get_pairs($data)
    {
        $gatewayService = $this->di['mod_service']('Invoice', 'PayGateway');

        return $gatewayService->getPairs();
    }

    /**
     * Return existing module but not activated.
     *
     * @return array
     */
    public function gateway_get_available(array $data)
    {
        $gatewayService = $this->di['mod_service']('Invoice', 'PayGateway');

        return $gatewayService->getAvailable();
    }

    /**
     * Install available payment gateway.
     *
     * @return true
     */
    public function gateway_install(array $data)
    {
        $required = [
            'code' => 'Payment gateway code is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        $code = $data['code'];
        $gatewayService = $this->di['mod_service']('Invoice', 'PayGateway');

        return $gatewayService->install($code);
    }

    /**
     * Get gateway details.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    public function gateway_get($data)
    {
        $required = [
            'id' => 'Gateway id not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('PayGateway', $data['id'], 'Gateway not found');

        $gatewayService = $this->di['mod_service']('Invoice', 'PayGateway');

        return $gatewayService->toApiArray($model, true, $this->getIdentity());
    }

    /**
     * Copy gateway from existing one.
     *
     * @throws \FOSSBilling\Exception
     */
    public function gateway_copy($data)
    {
        $required = [
            'id' => 'Gateway id not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        $model = $this->di['db']->getExistingModelById('PayGateway', $data['id'], 'Gateway not found');
        $gatewayService = $this->di['mod_service']('Invoice', 'PayGateway');

        return $gatewayService->copy($model);
    }

    /**
     * Change gateway settings.
     *
     * @optional string $title - gateway title
     * @optional array $config - gateway config array
     * @optional array $accepted_currencies - list of currencies this gateway supports
     * @optional bool $enabled - flag to enable or disable gateway
     * @optional bool $allow_single - flag to enable or disable single payment option
     * @optional bool $allow_recurrent - flag to enable or disable recurrent payment option
     * @optional bool $test_mode - flag to enable or disable test mode for gateway
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function gateway_update($data)
    {
        $required = [
            'id' => 'Gateway id not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('PayGateway', $data['id'], 'Gateway not found');
        $gatewayService = $this->di['mod_service']('Invoice', 'PayGateway');

        return $gatewayService->update($model, $data);
    }

    /**
     * Remove payment gateway from system.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function gateway_delete($data)
    {
        $required = [
            'id' => 'Gateway id not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('PayGateway', $data['id'], 'Gateway not found');
        $gatewayService = $this->di['mod_service']('Invoice', 'PayGateway');

        return $gatewayService->delete($model);
    }

    /**
     * Get list of subscriptions.
     *
     * @return array
     */
    public function subscription_get_list($data)
    {
        $subscriptionService = $this->di['mod_service']('Invoice', 'Subscription');
        [$sql, $params] = $subscriptionService->getSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $subscription = $this->di['db']->getExistingModelById('Subscription', $item['id'], 'Subscription not found');
            $pager['list'][$key] = $subscriptionService->toApiArray($subscription);
        }

        return $pager;
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
    public function subscription_create($data)
    {
        $required = [
            'client_id' => 'Client id not passed',
            'gateway_id' => 'Payment gateway id not passed',
            'currency' => 'Subscription currency not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->di['db']->getExistingModelById('Client', $data['client_id'], 'Client not found');
        $payGateway = $this->di['db']->getExistingModelById('PayGateway', $data['gateway_id'], 'Payment gateway not found');

        if ($client->currency != $data['currency']) {
            throw new InformationException('Client currency must match subscription currency. Check if clients currency is defined.');
        }
        $subscriptionService = $this->di['mod_service']('Invoice', 'Subscription');

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
     * @throws \FOSSBilling\Exception
     */
    public function subscription_update($data)
    {
        $required = [
            'id' => 'Subscription id not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Subscription', $data['id'], 'Subscription not found');
        $subscriptionService = $this->di['mod_service']('Invoice', 'Subscription');

        return $subscriptionService->update($model, $data);
    }

    /**
     * Get subscription details.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    public function subscription_get($data)
    {
        if (!isset($data['id']) && !isset($data['sid'])) {
            $required = [
                'id' => 'Subscription id not passed',
                'sid' => 'Subscription sid not passed',
            ];
            $this->di['validator']->checkRequiredParamsForArray($required, $data);
        }
        $model = null;
        if (isset($data['id'])) {
            $model = $this->di['db']->load('Subscription', $data['id']);
        }

        if (!$model && isset($data['sid'])) {
            $model = $this->di['db']->findOne('Subscription', 'sid = ?', [$data['sid']]);
        }

        if (!$model instanceof \Model_Subscription) {
            throw new \FOSSBilling\Exception('Subscription not found');
        }

        $subscriptionService = $this->di['mod_service']('Invoice', 'Subscription');

        return $subscriptionService->toApiArray($model, true, $this->getIdentity());
    }

    /**
     * Remove subscription.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function subscription_delete($data)
    {
        $required = [
            'id' => 'Subscription id not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Subscription', $data['id'], 'Subscription not found');
        $subscriptionService = $this->di['mod_service']('Invoice', 'Subscription');

        return $subscriptionService->delete($model);
    }

    /**
     * Remove tax rule.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function tax_delete($data)
    {
        $required = [
            'id' => 'Tax id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Tax', $data['id'], 'Tax rule not found');
        $taxService = $this->di['mod_service']('Invoice', 'Tax');

        return $taxService->delete($model);
    }

    /**
     * Create new tax rule.
     *
     * @return int - new tax id
     */
    public function tax_create($data)
    {
        $required = [
            'name' => 'Tax name is missing',
            'taxrate' => 'Tax rate is missing or is invalid',
        ];

        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        $taxService = $this->di['mod_service']('Invoice', 'Tax');

        return $taxService->create($data);
    }

    /**
     * Update tax rule.
     *
     * @return array
     */
    public function tax_get($data)
    {
        $required = [
            'id' => 'Tax id is missing',
        ];

        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $tax = $this->di['db']->getExistingModelById('Tax', $data['id'], 'Tax rule not found');

        $taxService = $this->di['mod_service']('Invoice', 'Tax');

        return $taxService->toApiArray($tax);
    }

    /**
     * Update tax rule.
     *
     * @return bool
     */
    public function tax_update($data)
    {
        $required = [
            'id' => 'Tax id is missing',
            'taxrate' => 'Tax rate is missing',
            'name' => 'Tax name is missing',
        ];

        $tax = $this->di['db']->getExistingModelById('Tax', $data['id'], 'Tax rule not found');

        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        $taxService = $this->di['mod_service']('Invoice', 'Tax');

        return $taxService->update($tax, $data);
    }

    /**
     * Get list of taxes.
     *
     * @return array
     */
    public function tax_get_list($data)
    {
        $taxService = $this->di['mod_service']('Invoice', 'Tax');
        [$sql, $params] = $taxService->getSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();

        return $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
    }

    /**
     * Automatically setup the EU VAT tax rules for you for all EU Member States.
     * This action will delete any existing tax rules and configure the VAT rates
     * for all EU countries.
     *
     * @return bool
     */
    public function tax_setup_eu($data)
    {
        $taxService = $this->di['mod_service']('Invoice', 'Tax');

        return $taxService->setupEUTaxes($data);
    }

    private function _getInvoice($data)
    {
        $required = [
            'id' => 'Invoice id not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->di['db']->getExistingModelById('Invoice', $data['id'], 'Invoice was not found');
    }

    /**
     * Deletes invoices with given IDs.
     *
     * @return bool
     */
    public function batch_delete($data)
    {
        $required = [
            'ids' => 'IDs not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        foreach ($data['ids'] as $id) {
            $this->delete(['id' => $id]);
        }

        return true;
    }

    /**
     * Deletes subscriptions with given IDs.
     *
     * @return bool
     */
    public function batch_delete_subscription($data)
    {
        $required = [
            'ids' => 'IDs not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        foreach ($data['ids'] as $id) {
            $this->subscription_delete(['id' => $id]);
        }

        return true;
    }

    /**
     * Deletes transactions with given IDs.
     *
     * @return bool
     */
    public function batch_delete_transaction($data)
    {
        $required = [
            'ids' => 'IDs not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        foreach ($data['ids'] as $id) {
            $this->transaction_delete(['id' => $id]);
        }

        return true;
    }

    /**
     * Deletes taxes with given IDs.
     *
     * @return bool
     */
    public function batch_delete_tax($data)
    {
        $required = [
            'ids' => 'IDs not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        foreach ($data['ids'] as $id) {
            $this->tax_delete(['id' => $id]);
        }

        return true;
    }

    public function export_csv($data)
    {
        $data['headers'] ??= [];

        return $this->getService()->exportCSV($data['headers']);
    }
}
