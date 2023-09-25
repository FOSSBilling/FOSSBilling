<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

class Payment_Adapter_Custom
{
    protected ?\Pimple\Container $di = null;

    public function __construct(private $config)
    {
    }

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public static function getConfig()
    {
        return array(
            'can_load_in_iframe'   =>  true,
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'       =>  true,
            'description'     =>  'Custom payment gateway allows you to give instructions how can your client pay invoice. All system, client, order and invoice details can be printed. HTML and JavaScript code is supported.',
            'logo' => array(
                'logo' => 'custom.png',
                'height' => '50px',
                'width' => '50px',
            ),
            'form'  => array(
                'single' => array('textarea', array(
                            'label' => 'Enter your text for single payment information',
                    ),
                ),
                'recurrent' => array('textarea', array(
                            'label' => 'Enter your text for subscription information',
                    ),
                ),
            ),
        );
    }

    /**
     * Generate payment text
     *
     * @param Api_Admin $api_admin
     * @param int $invoice_id
     * @param bool $subscription
     *
     * @since FOSSBilling v2.9.15
     *
     * @return string - html form with auto submit javascript
     */
    public function getHtml($api_admin, $invoice_id, $subscription)
    {
        $invoiceModel = $this->di['db']->load('Invoice', $invoice_id);
        $invoiceService = $this->di['mod_service']("Invoice");
        $invoice = $invoiceService->toApiArray($invoiceModel, true);

        $vars = array(
            '_client_id'    => $invoice['client']['id'],
            'invoice'   =>  $invoice,
            '_tpl'      =>  $subscription ? ($this->config['recurrent'] ?? '"Custom" payment adapter is not fully configured.') : ($this->config['single'] ?? '"Custom" payment adapter is not fully configured.'),
        );
        $systemService = $this->di['mod_service']('System');
        return $systemService->renderString($vars['_tpl'], true, $vars);
    }

    public function process($tx)
    {
        return true;
    }

    /**
     * Processes a transaction using a custom payment adapter.
     *
     * @param mixed $api_admin The API admin object.
     * @param int $id The ID of the transaction to process.
     * @param array $data The data associated with the transaction.
     * @param int $gateway_id The ID of the payment gateway to use.
     *
     * @return bool Returns true if the transaction was processed successfully, false otherwise.
     */
    public function processTransaction($api_admin, $id, $data, $gateway_id)
    {
        try {

            // Get the transaction and invoice associated with the transaction
            $tx = $this->di['db']->getExistingModelById('Transaction', $id);
            $invoice = $this->di['db']->getExistingModelById('Invoice', $tx->invoice_id);

            // Load the payment gateway and client associated with the transaction
            $gateway = $this->di['db']->load('PayGateway', $tx->gateway_id);
            $clientService = $this->di['mod_service']('Client');
            $client = $clientService->get(['id' => $invoice->client_id]);

            // Calculate the total amount of the invoice
            $invoiceService = $this->di['mod_service']('Invoice');
            $invoiceTotal = $invoiceService->getTotalWithTax($invoice);

            // Add funds to the client's account and mark the invoice as paid
            $tx_desc = $gateway->title . ' transaction No: ' . $tx->txn_id;
            $clientService->addFunds($client, $invoiceTotal, $tx_desc, []);
            $invoiceService->markAsPaid($invoice, true, true);

            // Update the transaction status and details
            $tx->status = 'succeeded';
            $tx->amount = $invoiceTotal;
            $tx->note = $gateway->title . ' transaction No: ' . $tx->txn_id;
            $tx->currency = $invoice->currency;
            $tx->updated_at = date('Y-m-d H:i:s');

            // Store the updated transaction and return true
            $this->di['db']->store($tx);
            return true;

        } catch (\Exception $e) {
            return false;
        }
    }

}
