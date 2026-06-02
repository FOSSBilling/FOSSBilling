<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Payment_Adapter_Custom
{
    protected ?Pimple\Container $di = null;
    private const string TRUSTED_SOURCE = 'admin';

    public function __construct(private $config)
    {
    }

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public static function getConfig(): array
    {
        return [
            'can_load_in_iframe' => true,
            'supports_one_time_payments' => true,
            'supports_subscriptions' => true,
            'description' => 'Custom payment gateway allows you to give instructions how can your client pay invoice. All system, client, order and invoice details can be printed. HTML code is supported.',
            'logo' => [
                'logo' => 'custom.png',
                'height' => '50px',
                'width' => '50px',
            ],
            'form' => [
                'single' => [
                    'textarea', [
                        'label' => 'Enter Your Text for Single Payment Information',
                    ],
                ],
                'recurrent' => [
                    'textarea', [
                        'label' => 'Enter Your Text for Subscription Information',
                    ],
                ],
            ],
        ];
    }

    /**
     * Generate payment text.
     *
     * @return string - html form with auto submit javascript
     */
    public function getHtml(Api_Handler $api_admin, int $invoice_id, bool $subscription): string
    {
        $invoiceModel = $this->di['db']->load('Invoice', $invoice_id);
        $invoiceService = $this->di['mod_service']('Invoice');
        $invoice = $invoiceService->toApiArray($invoiceModel, true);

        $tpl = $subscription ? ($this->config['recurrent'] ?? '"Custom" payment adapter is not fully configured.') : ($this->config['single'] ?? '"Custom" payment adapter is not fully configured.');
        $vars = [
            'invoice' => $invoice,
        ];
        $systemService = $this->di['mod_service']('System');

        return $systemService->renderAdapterTplString($tpl, $vars);
    }

    /**
     * Processes a transaction using a custom payment adapter.
     *
     * @param Api_Handler $api_admin  the API admin object
     * @param int         $id         the ID of the transaction to process
     * @param array       $data       the data associated with the transaction
     * @param int         $gateway_id the ID of the payment gateway to use
     *
     * @return bool returns true if the transaction was processed successfully, false otherwise
     */
    public function processTransaction(Api_Handler $api_admin, int $id, array $data, int $gateway_id)
    {
        if (!$this->isIpnValid($data)) {
            throw new Payment_Exception('Custom payment gateway callbacks must be confirmed by an administrator.');
        }

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
            $tx->status = Model_Transaction::STATUS_PROCESSED;
            $tx->amount = $invoiceTotal;
            $tx->note = $gateway->title . ' transaction No: ' . $tx->txn_id;
            $tx->currency = $invoice->currency;
            $tx->updated_at = date('Y-m-d H:i:s');

            // Store the updated transaction and use its return to indicate a success or failure.
            return $this->di['db']->store($tx);
        } catch (Exception) {
            return false;
        }
    }

    public function isIpnValid(array $data): bool
    {
        return ($data['source'] ?? null) === self::TRUSTED_SOURCE;
    }
}
