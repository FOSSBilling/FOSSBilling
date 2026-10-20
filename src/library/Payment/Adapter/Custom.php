<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Payment_Adapter_Custom
{
    protected ?Pimple\Container $di = null;

    public function __construct(private $config)
    {
    }

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    /**
     * Offline gateways have no verifiable callback: a stored IPN payload can
     * neither prove nor disprove payment. Approval is an explicit admin act,
     * never an adapter decision over decoded data.
     */
    public static function requiresManualApproval(): bool
    {
        return true;
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
    public function getHtml(FOSSBilling\Api\Proxy $api_admin, int $invoice_id, bool $subscription): string
    {
        $invoiceModel = $this->di['em']->getRepository(Box\Mod\Invoice\Entity\Invoice::class)->find($invoice_id);
        if (!$invoiceModel instanceof Box\Mod\Invoice\Entity\Invoice) {
            throw new Payment_Exception('Invoice not found');
        }
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
     * Approve an offline payment on behalf of an administrator.
     *
     * Takes no IPN payload by design: there is no signed callback to verify.
     * Only reachable through the permission-checked admin approve action.
     *
     * @throws Payment_Exception
     */
    public function approveTransaction(FOSSBilling\Api\Proxy $api_admin, int $id, int $gateway_id): bool
    {
        // Get the transaction and invoice associated with the transaction
        $tx = $this->di['em']->getRepository(Box\Mod\Invoice\Entity\Transaction::class)->find($id);
        if (!$tx instanceof Box\Mod\Invoice\Entity\Transaction) {
            throw new Payment_Exception('Transaction not found', [], 7010);
        }
        $invoice = $tx->getInvoice()
            ?? throw new FOSSBilling\InformationException('Invoice not found');

        // Load the payment gateway and client associated with the transaction
        $gateway = $tx->getGateway();
        if (!$gateway instanceof Box\Mod\Invoice\Entity\PayGateway) {
            throw new Payment_Exception('Transaction is not linked to a payment gateway', [], 7011);
        }
        $clientService = $this->di['mod_service']('Client');
        $client = $clientService->get(['id' => $invoice->getClientId()]);

        // Calculate the total amount of the invoice
        $invoiceService = $this->di['mod_service']('Invoice');
        $invoiceTotal = $invoiceService->getTotalWithTax($invoice);

        // Add funds to the client's account and mark the invoice as paid
        $gatewayName = $gateway->getName() ?: $gateway->getGateway();
        $tx_desc = $gatewayName . ' transaction No: ' . $tx->getTxnId();
        $clientService->addFunds($client, $invoiceTotal, $tx_desc, []);
        $invoiceService->markAsPaid($invoice, true, true);

        // Update the transaction status and details
        $tx->setStatus(Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSED);
        $tx->setAmount((string) $invoiceTotal);
        $tx->setNote($gatewayName . ' transaction No: ' . $tx->getTxnId());
        $tx->setCurrency($invoice->getCurrency());
        $tx->setUpdatedAt(new DateTime());

        // Store the updated transaction and use its return to indicate a success or failure.
        $this->di['em']->persist($tx);
        $this->di['em']->flush();

        return true;
    }
}
