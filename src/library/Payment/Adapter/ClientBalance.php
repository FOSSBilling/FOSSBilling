<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Payment_Adapter_ClientBalance implements FOSSBilling\InjectionAwareInterface
{
    protected ?Pimple\Container $di = null;

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Pimple\Container
    {
        return $this->di;
    }

    public function __construct()
    {
    }

    public static function getConfig()
    {
        return [
            'logo' => [
                'logo' => 'clientbalance.png',
                'height' => '50px',
                'width' => '50px',
            ],
        ];
    }

    public function enoughInBalanceToCoverInvoice(Model_Invoice $invoice)
    {
        $clientModel = $this->di['db']->load('Client', $invoice->client_id);
        $clientBalanceService = $this->di['mod_service']('Client', 'Balance');
        $sumInBalance = $clientBalanceService->getClientBalance($clientModel);

        $invoiceService = $this->di['mod_service']('Invoice');
        $totalSumWithTaxes = $invoiceService->getTotalWithTax($invoice);
        if ($totalSumWithTaxes > $sumInBalance) {
            return false;
        }

        return true;
    }

    public function getHtml($api_admin, $invoice_id, $subscription)
    {
        $invoiceModel = $this->di['db']->load('Invoice', $invoice_id);

        if (!$this->enoughInBalanceToCoverInvoice($invoiceModel)) {
            return __trans('Your account balance is insufficient to cover this invoice.');
        }

        $invoiceService = $this->di['mod_service']('Invoice');
        if ($invoiceService->isInvoiceTypeDeposit($invoiceModel)) {
            return __trans('You may not pay a deposit invoice with this payment gateway.');
        }

        $ipnUrl = $this->getServiceUrl($invoice_id);
        $invoiceUrl = $this->di['tools']->url('invoice/' . $invoiceModel->hash);

        return "<script type='text/javascript'>
                $(document).ready(function(){
                    bb.post('$ipnUrl', null, function(result){
                        window.location.href = '$invoiceUrl';
                    });
                });
                </script>";
    }

    public function processTransaction($api_admin, $id, $data, $gateway_id)
    {
        if (!$this->isIpnValid($data)) {
            throw new Payment_Exception('IPN is invalid');
        }

        $tx = $this->di['db']->load('Transaction', $id);

        $invoice_id = $data['invoice_id'] ?? 0;
        $invoiceModel = $this->di['db']->load('Invoice', $invoice_id);

        $invoiceService = $this->di['mod_service']('Invoice');
        if ($invoiceService->isInvoiceTypeDeposit($invoiceModel)) {
            throw new Payment_Exception('You may not pay a deposit invoice with this payment gateway.', [], 303);
        }

        if ($invoice_id) {
            $invoiceService->payInvoiceWithCredits($invoiceModel);
        }
        $invoiceService->doBatchPayWithCredits(['client_id' => $invoiceModel->client_id]);

        $tx->error = '';
        $tx->error_code = '';
        $tx->status = 'processed';
        $tx->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($tx);

        return true;
    }

    public function isIpnValid($data)
    {
        /*
         * @TODO do we need validation here? -_-
         */
        return true;
    }

    public function getServiceUrl($invoice_id = 0)
    {
        $gatewayModel = $this->di['db']->findOne('PayGateway', 'gateway = ? and enabled = 1', ['ClientBalance']);
        if (!$gatewayModel instanceof Model_PayGateway) {
            throw new Payment_Exception('ClientBalance gateway is not enabled', null, 301);
        }

        $invoiceModel = $this->di['db']->load('Invoice', $invoice_id);
        $invoiceService = $this->di['mod_service']('Invoice');
        if ($invoiceService->isInvoiceTypeDeposit($invoiceModel)) {
            throw new Payment_Exception('You may not pay a deposit invoice with this payment gateway.', null, 302);
        }

        $gatewayService = $this->di['mod_service']('Invoice', 'PayGateway');

        return $gatewayService->getCallbackUrl($gatewayModel, $invoiceModel);
    }
}
