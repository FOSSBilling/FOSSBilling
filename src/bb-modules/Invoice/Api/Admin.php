<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * Invoice management API
 */

namespace Box\Mod\Invoice\Api;

class Admin extends \Api_Abstract
{
    /**
     * Returns paginated list of invoices
     * @return array
     */
    public function get_list($data)
    {
        $service = $this->getService();
        list ($sql, $params) = $service->getSearchQuery($data);
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        $pager = $this->di['pager']->getAdvancedResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $invoice             = $this->di['db']->getExistingModelById('Invoice', $item['id'], 'Invoice not found');
            $pager['list'][$key] = $this->getService()->toApiArray($invoice, true, $this->getIdentity());
        }

        return $pager;
    }

    /**
     * Get invoice details
     * @param int $id - invoice id
     * @return array
     */
    public function get($data)
    {
        $model = $this->_getInvoice($data);
        return $this->getService()->toApiArray($model, true, $this->getIdentity());
    }

    /**
     * Sets invoce status to paid. This method differs from invoice update method
     * in a way that it sends notification to Events system, so emails are sent.
     * Also this will try to automatically apply payment if clients balance is
     * available
     * 
     * @param int $id - invoice id
     * 
     * @optional bool $execute - execute related tasks on invoice items. Default false.
     * 
     * @return array
     */
    public function mark_as_paid($data)
    {
        $execute = false;
        if(isset($data['execute']) && $data['execute']) {
            $execute = true;
        }
        $model = $this->_getInvoice($data);
        return $this->getService()->markAsPaid($model, FALSE, $execute);
    }

    /**
     * Prepare invoice for editing and updating.
     * Uses clients details, such as currency assigned to client.
     * If client currency is not defined, sets default currency for client
     *
     * @param int $client_id - Client id. Client must have defined currency on profile.
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
        $required = array(
            'client_id' => 'Client id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->di['db']->getExistingModelById('Client', $data['client_id'], 'Client not found');

        $invoice = $this->getService()->prepareInvoice($client, $data);
        return $invoice->id;
    }

    /**
     * Approve invoice.
     * 
     * @param int $id - invoice id
     * @param bool $use_credits - default = false
     * @return bool
     */
    public function approve($data)
    {
        $model = $this->_getInvoice($data);

        return $this->getService()->approveInvoice($model, $data);
    }

    /**
     * Add refunds
     * @param int $id - invoice id
     *
     * @optional string $note - note for refund
     *
     * @return bool
     */
    public function refund($data)
    {
        $model = $this->_getInvoice($data);
        $note = $this->di['array_get']($data, 'note', NULL);

        return $this->getService()->refundInvoice($model, $note);
    }

    /**
     * Update invoice details
     * 
     * @param int $id - invoice id
     * 
     * @optional string $paid_at - Invoice payment date (Y-m-d) or empty to remove
     * @optional string $due_at - Invoice due date (Y-m-d)or empty to remove
     * @optional string $created_at - Invoice issue date (Y-m-d) or empty to remove
     * 
     * @optional string $serie - Invoice serie
     * @optional string $nr - Invoice number
     * @optional string $status - Invoice status: paid|unpaid
     * @optional string $taxrate - Invoice tax rate
     * @optional string $taxname - Invoice tax name
     * @optional bool $approved - flag to set invoice as approved. Approved invoices are visible to clients
     * @optional string $notes - notes
     * @optional int $gateway_id - selected payment method - gateway id

     * @optional array $new_item - [title] [price]
     * 
     * @optional string $text_1 - Custom invoice text 1
     * @optional string $text_2 - Custom invoice text 1
     * 
     * @optional string $seller_company - Seller company name
     * @optional string $seller_company_vat - Seller company VAT number
     * @optional string $seller_company_number - Seller company number
     * @optional string $seller_address - Seller address
     * @optional string $seller_phone - Seller phone
     * @optional string $seller_email - Seller email
     * 
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
     * Remove one line from invoice
     *
     * @param int $id - invoice line id
     * @return bool
     */
    public function item_delete($data)
    {
        $required = array(
            'id' => 'Invoice item id not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('InvoiceItem', $data['id'], 'Invoice item was not found');
        $invoiceItemService = $this->di['mod_service']('Invoice', 'InvoiceItem');
        return $invoiceItemService->remove($model);
    }

    /**
     * Delete invoice
     *
     * @param int $id - Invoice id
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
     * is returned
     * 
     * @param int $id - ID of order to generate new invoice for
     * @optional int $due_days - Days number until invoice is due
     * 
     * @return string - invoice id
     * @throws Exception
     * @throws LogicException 
     */
    public function renewal_invoice($data)
    {
        $required = array(
            'id' => 'Order id required',
        );

        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ClientOrder', $data['id'], 'Order not found');
        if($model->price <= 0) {
            throw new \Box_Exception('Order :id is free. No need to generate invoice.', array(':id'=>$model->id));
        }

        return $this->getService()->renewInvoice($model, $data);
    }

    /**
     * Use credits to pay for invoices
     * if credits are available in clients balance
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
     * Cover one invoice with credits
     *
     * @param int $id - Invoice id
     *
     * @return bool
     */
    public function pay_with_credits($data)
    {
        $invoice = $this->_getInvoice($data);
        return $this->getService()->payInvoiceWithCredits($invoice);
    }

    /**
     * Generate invoices for expiring orders
     *
     * @return bool
     */
    public function batch_generate()
    {
        return $this->getService()->generateInvoicesForExpiringOrders();
    }

    /**
     * Action to activate paid invoices lines
     *
     * @return bool
     */
    public function batch_activate_paid()
    {
        return $this->getService()->doBatchPaidInvoiceActivation();
    }

    /**
     * Send buyer reminders about upcoming payment
     *
     * @return bool
     */
    public function batch_send_reminders($data)
    {
        return $this->getService()->doBatchRemindersSend();
    }
    
    /**
     * Calls due events on unpaid and approved invoices.
     * Attach custom event hooks events:
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
     * Calls event hook, so you can attach your custom notification code
     *
     * @param int $id - invoice id
     * @return bool
     */
    public function send_reminder($data)
    {
        $invoice = $this->_getInvoice($data);
        return $this->getService()->sendInvoiceReminder($invoice);
    }

    /**
     * Return invoice statuses with counter
     * 
     * @return array
     */
    public function get_statuses($data)
    {
        return $this->getService()->counter();
    }

    /**
     * Process all received transactions
     * 
     * @return bool
     */
    public function transaction_process_all($data)
    {
        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');
        return $transactionService->proccessReceivedATransactions();
    }

    /**
     * Process selected transaction
     * @param int $id - Transaction id
     * 
     * @return transaction output or true;
     */
    public function transaction_process($data)
    {
        $required = array(
            'id' => 'Transaction id is missing',
        );

        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Transaction', $data['id'], 'Transaction not found');

        $output = null;
        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminTransactionProcess', 'params'=>array('id'=>$model->id)));

        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');
        return $transactionService->preProcessTransaction ($model);
    }
    
    /**
     * Update transaction details
     * @param int $id - transaction id
     *
     * @optional int $invoice_id - new invoice id
     * @optional string $txn_id - transaction id on payment gateway
     * @optional string $txn_status - transaction status on payment gateway
     * @optional int $gateway_id - Payment gateway ID on BoxBilling
     * @optional float $amount - Transaction amount
     * @optional string $currency - Currency code. Must be available on BoxBilling
     * @optional string $type - Currency code. Must be available on BoxBilling
     * @optional string $status - Transaction status on BoxBilling
     * @optional bool $validate_ipn - Flag to enable and disable IPN validation for this transaction
     * @optional string $note - Custom note
     *
     * @return bool
     */
    public function transaction_update($data)
    {
        $required = array(
            'id' => 'Transaction id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        $model = $this->di['db']->getExistingModelById('Transaction', $data['id'], 'Transaction not found');

        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');
        return $transactionService->update($model, $data);
    }

    /**
     * Create custom transaction
     *
     * @param int $bb_invoice_id - BoxBilling invoice id
     * @param int $bb_gateway_id - BoxBilling gateway id
     * 
     * @optional array $get - $_GET data
     * @optional array $post - $_POST data
     * @optional array $server - $_SERVER data
     * @optional array $http_raw_post_data - file_get_contents("php://input")
     * @optional string $txn_id - transaction id on payment gateway
     * @optional bool $skip_validation - makes params bb_invoice_id and bb_gateway_id optional
     * 
     * @return int $id - new transaction id
     */
    public function transaction_create($data)
    {
        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');
        return $transactionService->create($data);
    }

    /**
     * Remove transaction
     *
     * @param int $id - Transaction id
     * @return bool
     */
    public function transaction_delete($data)
    {
        $required = array(
            'id' => 'Transaction id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        $model = $this->di['db']->getExistingModelById('Transaction', $data['id'], 'Transaction not found');

        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');
        return $transactionService->delete($model);
    }

    /**
     * Get transaction details
     * @param int $id - Transaction id
     * @return array
     */
    public function transaction_get($data)
    {
        $required = array(
            'id' => 'Transaction id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        $model = $this->di['db']->getExistingModelById('Transaction', $data['id'], 'Transaction not found');

        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');
        return $transactionService->toApiArray($model, true);
    }

    /**
     * Get paginated list of transactions
     * 
     * @optional string $txn_id - search for transactions by transaction id on payment gateway
     * 
     * @return array
     */
    public function transaction_get_list($data)
    {
        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');
        list ($sql, $params) = $transactionService->getSearchQuery($data);
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $transaction               = $this->di['db']->getExistingModelById('Transaction', $item['id'], 'Transaction not found');
            $pager['list'][$key] = $transactionService->toApiArray($transaction);
        }

        return $pager;
    }

    /**
     * Return transactions statuses with counter
     * @return array
     */
    public function transaction_get_statuses($data)
    {
        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');
        return $transactionService->counter();
    }

    /**
     * Get available transaction statuses
     * @return array
     */
    public function transaction_get_statuses_pairs($data)
    {
        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');
        return $transactionService->getStatusPairs();
    }

    /**
     * Get available transaction statuses
     * @return array
     */
    public function transaction_statuses($data)
    {
        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');
        return $transactionService->getStatuses();
    }

    /**
     * Get available transaction statuses on gateways
     * 
     * @return array
     */
    public function transaction_gateway_statuses($data)
    {
        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');
        return $transactionService->getGatewayStatuses();
    }

    /**
     * Get available transaction types
     * @return array
     */
    public function transaction_types($data)
    {
        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');
        return $transactionService->getTypes();
    }

    /**
     * Get available gateways
     * 
     * @return array
     */
    public function gateway_get_list($data)
    {
        $gatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        list ($sql, $params) = $gatewayService->getSearchQuery($data);

        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $gateway               = $this->di['db']->getExistingModelById('PayGateway', $item['id'], 'Gateway not found');
            $pager['list'][$key] = $gatewayService->toApiArray($gateway, false, $this->getIdentity());
        }

        return $pager;
    }

    /**
     * Get available gateways pairs
     * @return array
     */
    public function gateway_get_pairs($data)
    {
        $gatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        return $gatewayService->getPairs();
    }

    /**
     * Return existing module but not activated
     *
     * @param none
     * @return array
     */
    public function gateway_get_available($data)
    {
        $gatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        return $gatewayService->getAvailable();
    }

    /**
     * Install available payment gateway
     * @param code - available payment gateway code
     * @return true
     */
    public function gateway_install($data)
    {
        $required = array(
            'code' => 'Payment gateway code is missing'
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        $code = $data['code'];
        $gatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        return $gatewayService->install($code);
    }

    /**
     * Get gateway details
     * 
     * @param int $id - gateway id
     * @return array
     * @throws Box_Exception 
     */
    public function gateway_get($data)
    {
        $required = array(
            'id' => 'Gateway id not passed'
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('PayGateway', $data['id'], 'Gateway not found');

        $gatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        return $gatewayService->toApiArray($model, true, $this->getIdentity());
    }

    /**
     * Copy gateway from existing one
     * 
     * @param int $id - id of gateway to be copied
     * @return int - new id of gateway
     * @throws Box_Exception 
     */
    public function gateway_copy($data)
    {
        $required = array(
            'id' => 'Gateway id not passed'
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        $model = $this->di['db']->getExistingModelById('PayGateway', $data['id'], 'Gateway not found');
        $gatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        return $gatewayService->copy($model);
    }

    /**
     * Change gateway settings
     * 
     * @param int $id - gateway id
     * @optional string $title - gateway title
     * @optional array $config - gateway config array
     * @optional array $accepted_currencies - list of currencies this gateway supports
     * @optional bool $enabled - flag to enable or disable gateway
     * @optional bool $allow_single - flag to enable or disable single payment option
     * @optional bool $allow_recurrent - flag to enable or disable recurrent payment option
     * @optional bool $test_mode - flag to enable or disable test mode for gateway
     * 
     * @return boolean
     * @throws Box_Exception 
     */
    public function gateway_update($data)
    {
        $required = array(
            'id' => 'Gateway id not passed'
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('PayGateway', $data['id'], 'Gateway not found');
        $gatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        return $gatewayService->update($model, $data);
    }

    /**
     * Remove payment gateway from system
     * 
     * @param int $id - gateway id
     * @return boolean
     * @throws Box_Exception 
     */
    public function gateway_delete($data)
    {
        $required = array(
            'id' => 'Gateway id not passed'
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('PayGateway', $data['id'], 'Gateway not found');
        $gatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        return $gatewayService->delete($model);
    }

    /**
     * Get list of subscribtions 
     * 
     * @return array
     */
    public function subscription_get_list($data)
    {
        $subscriptionService = $this->di['mod_service']('Invoice', 'Subscription');
        list ($sql, $params) = $subscriptionService->getSearchQuery($data);
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $subscription               = $this->di['db']->getExistingModelById('Subscription', $item['id'], 'Subscription not found');
            $pager['list'][$key] = $subscriptionService->toApiArray($subscription);
        }

        return $pager;
    }
    
    /**
     * Add new subscription
     * 
     * @param int $client_id - client id
     * @param int $gateway_id - payment gateway id
     * @param string $currency - currency
     * 
     * @optional string $sid - subscription id on payment gateway
     * @optional string $status - status: active|canceled
     * @optional string $period - example: 1W - every week, 2M - every 2 months
     * @optional string $amount - billed amount
     * @optional string $rel_type - related item type
     * @optional string $rel_id - related item id
     * 
     * @return int - id
     * @throws Box_Exception 
     */
    public function subscription_create($data)
    {
        $required = array(
            'client_id'     => 'Client id not passed',
            'gateway_id'    => 'Payment gateway id not passed',
            'currency'      => 'Subscription currency not passed'
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->di['db']->getExistingModelById('Client', $data['client_id'], 'Client not found');
        $payGateway = $this->di['db']->getExistingModelById('PayGateway', $data['gateway_id'], 'Payment gateway not found');

        if($client->currency != $data['currency']) {
            throw new \Box_Exception('Client currency must match subscription currency. Check if clients currency is defined.');
        }
        $subscriptionService = $this->di['mod_service']('Invoice', 'Subscription');
        return $subscriptionService->create($client, $payGateway, $data);
    }
    
    /**
     * Update subscription options
     * 
     * @param int $id - subscription id
     * 
     * @optional int $status - subscription status
     * @optional string $sid - subscription id on payment gateway
     * @optional string $period - subscription period code
     * @optional string $amount - subscription amount
     * @optional string $currency - subscription currency
     * 
     * @return boolean
     * @throws Box_Exception 
     */
    public function subscription_update($data)
    {
        $required = array(
            'id' => 'Subscription id not passed'
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Subscription', $data['id'], 'Subscription not found');
        $subscriptionService = $this->di['mod_service']('Invoice', 'Subscription');
        return $subscriptionService->update($model, $data);
    }
    
    /**
     * Get subscription details.
     * 
     * @param int $id - subscription id
     * @param string $sid - subscription id on payment gateway - required if id is not passed
     * 
     * @return array
     * @throws Box_Exception 
     */
    public function subscription_get($data)
    {
        if (!isset($data['id']) && !isset($data['sid'])) {
            $required = array(
                'id'  => 'Subscription id not passed',
                'sid' => 'Subscription sid not passed',
            );
            $this->di['validator']->checkRequiredParamsForArray($required, $data);
        }
        $model = null;
        if(isset($data['id'])) {
            $model = $this->di['db']->load('Subscription', $data['id']);
        }
        
        if(!$model && isset($data['sid'])) {
            $model = $this->di['db']->findOne('Subscription', 'sid = ?', array($data['sid']));
        }
        
        if(!$model instanceof \Model_Subscription) {
            throw new \Box_Exception('Subscription not found');
        }

        $subscriptionService = $this->di['mod_service']('Invoice', 'Subscription');
        return $subscriptionService->toApiArray($model, true, $this->getIdentity());
    }

    /**
     * Remove subscription
     * 
     * @param int $id - subscription id
     * 
     * @return boolean
     * @throws Box_Exception 
     */
    public function subscription_delete($data)
    {
        $required = array(
            'id' => 'Subscription id not passed'
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Subscription', $data['id'], 'Subscription not found');
        $subscriptionService = $this->di['mod_service']('Invoice', 'Subscription');
        return $subscriptionService->delete($model);
    }

    /**
     * Remove tax rule
     * 
     * @param int $id - tax id
     * 
     * @return boolean
     * @throws \Box_Exception
     */
    public function tax_delete($data)
    {
        $required = array(
            'id' => 'Tax id is missing'
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Tax', $data['id'], 'Tax rule not found');
        $taxService = $this->di['mod_service']('Invoice', 'Tax');
        return $taxService->delete($model);
    }

    /**
     * Create new tax rule
     * 
     * @param string $name - tax name
     * @param float $taxrate - tax rate
     * 
     * @return int - new tax id
     */
    public function tax_create($data)
    {
        $required = array(
            'name' => 'Tax name is missing',
            'taxrate' => 'Tax rate is missing or is not valid',
        );

        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        $taxService = $this->di['mod_service']('Invoice', 'Tax');
        return $taxService->create($data);
    }

    /**
     * Update tax rule
     *
     * @param string $id - tax ID
     *
     * @return array
     */
    public function tax_get($data)
    {
        $required = array(
            'id' => 'Tax id is missing'
        );

        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $tax = $this->di['db']->getExistingModelById('Tax', $data['id'], 'Tax rule not found');

        $taxService = $this->di['mod_service']('Invoice', 'Tax');
        return $taxService->toApiArray($tax);
    }

    /**
     * Update tax rule
     *
     * @param int $id - tax ID
     * @param string $name - tax name
     * @param float $taxrate - tax rate
     *
     * @return boolean
     */
    public function tax_update($data)
    {
        $required = array(
            'id' => 'Tax id is missing',
            'taxrate' => 'Tax rate is missing',
            'name' => 'Tax name is missing'
        );

        $tax = $this->di['db']->getExistingModelById('Tax', $data['id'], 'Tax rule not found');

        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        $taxService = $this->di['mod_service']('Invoice', 'Tax');

        return $taxService->update($tax, $data);
    }

    /**
     * Get list of taxes
     * 
     * @return array
     */
    public function tax_get_list($data)
    {
        $taxService = $this->di['mod_service']('Invoice', 'Tax');
        list ($sql, $params) = $taxService->getSearchQuery($data);
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        return $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
    }
    
    /**
     * Automatically setup the EU VAT tax rules for you for all EU Member States.
     * This action will delete any existing tax rules and configure the VAT rates 
     * for all EU countries.
     * 
     * @param string $name - VAT label
     * @param string $taxrate - VAT rate
     * @return type 
     */
    public function tax_setup_eu($data)
    {
        $taxService = $this->di['mod_service']('Invoice', 'Tax');
        return $taxService->setupEUTaxes($data);
    }

    private function _getInvoice($data)
    {
        $required = array(
            'id' => 'Invoice id not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Invoice', $data['id'], 'Invoice was not found');

        return $model;
    }

    /**
     * Deletes invoices with given IDs
     *
     * @param array $ids - IDs for deletion
     *
     * @return bool
     */
    public function batch_delete($data)
    {
        $required = array(
            'ids' => 'IDs not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        foreach ($data['ids'] as $id) {
            $this->delete(array('id' => $id));
        }

        return true;
    }

    /**
     * Deletes subscriptions with given IDs
     *
     * @param array $ids - IDs for deletion
     *
     * @return bool
     */
    public function batch_delete_subscription($data)
    {
        $required = array(
            'ids' => 'IDs not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        foreach ($data['ids'] as $id) {
            $this->subscription_delete(array('id' => $id));
        }

        return true;
    }

    /**
     * Deletes transactions with given IDs
     *
     * @param array $ids - IDs for deletion
     *
     * @return bool
     */
    public function batch_delete_transaction($data)
    {
        $required = array(
            'ids' => 'IDs not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        foreach ($data['ids'] as $id) {
            $this->transaction_delete(array('id' => $id));
        }

        return true;
    }

    /**
     * Deletes taxes with given IDs
     *
     * @param array $ids - IDs for deletion
     *
     * @return bool
     */
    public function batch_delete_tax($data)
    {
        $required = array(
            'ids' => 'IDs not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        foreach ($data['ids'] as $id) {
            $this->tax_delete(array('id' => $id));
        }

        return true;
    }
}