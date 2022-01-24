<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 *Invoice management 
 */

namespace Box\Mod\Invoice\Api;

class Client extends \Api_Abstract
{
    /**
     * Get paginated list of invoices
     * 
     * @return array
     */
    public function get_list($data)
    {
        $data['client_id'] = $this->getIdentity()->id;
        $data['approved']  = true;
        list ($sql, $params) = $this->getService()->getSearchQuery($data);
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        $pager = $this->di['pager']->getAdvancedResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $invoice             = $this->di['db']->getExistingModelById('Invoice', $item['id'], 'Invoice not found');
            $pager['list'][$key] = $this->getService()->toApiArray($invoice);
        }

        return $pager;
    }

    /**
     * Get invoice details
     * 
     * @param string $hash - invoice hash
     * @return type
     * @throws Exception 
     */
    public function get($data)
    {
        $required = array(
            'hash' => 'Invoice hash not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->findOne('Invoice', 'hash = :hash', array('hash'=>$data['hash']));
        if(!$model) {
            throw new \Box_Exception('Invoice was not found');
        }
        return $this->getService()->toApiArray($model, true, $this->getIdentity());
    }
    
    /**
     * Update Invoice details. Only unpaid invoice details can be updated.
     * 
     * @param string $hash - invoice hash
     * 
     * @optional int $gateway_id - selected payment gateway id
     * 
     * @return bool
     * @throws Exception 
     */
    public function update($data)
    {
        $required = array(
            'hash' => 'Invoice hash not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $invoice = $this->di['db']->findOne('Invoice', 'hash = :hash', array('hash'=>$data['hash']));
        if(!$invoice) {
            throw new \Box_Exception('Invoice was not found');
        }
        if($invoice->status == 'paid') {
            throw new \Box_Exception('Paid Invoice can not be modified');
        }

        $updateParams = array();
        $updateParams['gateway_id'] = $this->di['array_get']($data, 'gateway_id', null);
        return $this->getService()->updateInvoice($invoice, $updateParams);
    }

    /**
     * Generates new invoice for selected order. If unpaid invoice for selected order
     * already exists, new invoice will not be generated, and old invoice hash
     * is returned
     * 
     * @param int $order_id - ID of order to generate new invoice for
     * @return string - invoice hash
     * @throws Exception
     * @throws LogicException 
     */
    public function renewal_invoice($data)
    {
        $required = array(
            'order_id' => 'Order id required',
        );

        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->findOne('ClientOrder', 'client_id = ? and id = ?', array($this->getIdentity()->id, $data['order_id']));
        if(!$model instanceof \Model_ClientOrder) {
            throw new \Box_Exception('Order not found');
        }
        if($model->price <= 0) {
            throw new \Box_Exception('Order :id is free. No need to generate invoice.', array(':id'=>$model->id));
        }
        $service = $this->getService();
        $invoice = $service->generateForOrder($model);
        $service->approveInvoice($invoice, array('id'=>$invoice->id, 'use_credits'=>true));
        $this->di['logger']->info('Generated new renewal invoice #%s', $invoice->id);
        return $invoice->hash;
    }

    /**
     * Deposit money in advance. Generates new invoice for depositing money.
     * Clients currency must be defined.
     *
     * @param float $amount - amount to be deposited.
     * @return string - invoice hash
     */
    public function funds_invoice($data)
    {
        $required = array(
            'amount' => 'Amount is required',
        );

        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        if (!is_numeric($data['amount'])) {
            throw new \Box_Exception('You need to enter numeric value');
        }

        $service = $this->getService();
        $invoice = $service->generateFundsInvoice($this->getIdentity(), $data['amount']);
        $service->approveInvoice($invoice, array('id'=>$invoice->id));
        $this->di['logger']->info('Generated add funds invoice #%s', $invoice->id);
        return $invoice->hash;
    }

    /**
     * Client removes unpaid invoice.
     * 
     * @param string $hash - invoice hash
     * @return boolean
     * @throws Exception 
     */
    public function delete($data)
    {
        $required = array(
            'hash' => 'Invoice hash not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->findOne('Invoice', 'hash = :hash', array('hash'=>$data['hash']));
        if(!$model) {
            throw new \Box_Exception('Invoice was not found');
        }

        return $this->getService()->deleteInvoiceByClient($model);
    }

    /**
     * Get paginated list of transactions.
     * 
     * @optional string $invoice_hash - filter transactions by invoice hash
     * @optional int $gateway_id - filter transactions by payment gateway id
     * @optional string $status - filter transactions by status
     * @optional string $currency - filter transactions by currency code
     * @optional string $date_from - filter transactions by date
     * @optional string $date_to - filter transactions by date
     * @return type 
     */
    public function transaction_get_list($data)
    {
        $data['client_id']  = $this->getIdentity()->id;
        $data['status']     = 'processed';
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

    public function get_tax_rate()
    {
        $service = $this->di['mod_service']('Invoice', 'Tax');
        return $service->getTaxRateForClient($this->identity);
    }
}