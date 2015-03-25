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

class Payment_Adapter_ClientBalance implements \Box\InjectionAwareInterface
{
    protected $di;

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }
    
    public function __construct()
    {

    }

    public static function getConfig()
    {
        return array();
    }

    public function enoughInBalanceToCoverInvoice(\Model_Invoice $invoice)
    {
        $clientModel = $this->di['db']->load('Client', $invoice->client_id);
        $clientBalanceService = $this->di['mod_service']('Client', 'Balance');
        $sumInBalance = $clientBalanceService->getClientBalance($clientModel);

        $invoiceService = $this->di['mod_service']('Invoice');
        $totalSumWithTaxes = $invoiceService->getTotalWithTax($invoice);
        if ($totalSumWithTaxes > $sumInBalance)
            return false;
        return true;
    }

    public function getHtml($api_admin, $invoice_id, $subscription)
    {
        $invoiceModel = $this->di['db']->load('Invoice', $invoice_id);

        if (!$this->enoughInBalanceToCoverInvoice($invoiceModel)){
            return __('Not enough in balance');
        }

        $invoiceService = $this->di['mod_service']('Invoice');
        if ($invoiceService->isInvoiceTypeDeposit($invoiceModel)){
            return __('Forbidden to pay deposit invoice with this gateway');
        }

        $ipnUrl = $this->getServiceUrl($invoice_id);
        $invoiceUrl = $this->di['tools']->url('invoice/'.$invoiceModel->hash);

        $out = "<script type='text/javascript'>
                $(document).ready(function(){
                    bb.post('$ipnUrl', null, function(result){
                        window.location.href = '$invoiceUrl';
                    });
                });
                </script>";
       return $out;
    }

    public function processTransaction($api_admin, $id, $data, $gateway_id)
    {
        if(!$this->isIpnValid($data)) {
            throw new Payment_Exception('IPN is not valid');
        }

        $tx = $this->di['db']->load('Transaction', $id);

        $invoice_id = isset($data['get']['bb_invoice_id']) ? $data['get']['bb_invoice_id'] : 0;
        $invoiceModel = $this->di['db']->load('Invoice', $invoice_id);

        $invoiceService = $this->di['mod_service']('Invoice');
        if ($invoiceService->isInvoiceTypeDeposit($invoiceModel)){
            throw new Payment_Exception('Forbidden to pay deposit invoice with this gateway', array(), 303);
        }

        if($invoice_id) {
            $invoiceService->payInvoiceWithCredits($invoiceModel);
        }
        $invoiceService->doBatchPayWithCredits(array('client_id' => $invoiceModel->client_id));

        $tx->error = '';
        $tx->error_code = '';
        $tx->status = 'processed';
        $tx->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($tx);
        return true;
    }

    public function isIpnValid($data)
    {
        /**
         * @TODO do we need validation here? -_-
         */
        return true;
    }

    public function getServiceUrl($invoice_id = 0)
    {
        $gatewayModel = $this->di['db']->findOne('PayGateway', 'gateway = ? and enabled = 1', array('ClientBalance'));
        if (!$gatewayModel instanceof \Model_PayGateway){
            throw new Payment_Exception('ClientBalance gateway is not enabled', null, 301);
        }

        $invoiceModel = $this->di['db']->load('Invoice', $invoice_id);
        $invoiceService = $this->di['mod_service']('Invoice');
        if ($invoiceService->isInvoiceTypeDeposit($invoiceModel)){
            throw new Payment_Exception('Forbidden to pay deposit invoice with this gateway', null, 302);
        }

        $gatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        return $gatewayService->getCallbackUrl($gatewayModel, $invoiceModel);
    }
}
