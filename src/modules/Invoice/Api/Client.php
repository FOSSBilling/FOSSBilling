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
 *Invoice management.
 */

namespace Box\Mod\Invoice\Api;

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\Transaction;
use Box\Mod\Invoice\Repository\InvoiceRepository;
use Box\Mod\Order\Entity\Order;
use FOSSBilling\PaginationOptions;
use FOSSBilling\Validation\Api\RequiredParams;

class Client extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Get paginated list of invoices.
     *
     * @return array
     */
    public function get_list($data)
    {
        $data['client_id'] = $this->getIdentity()->getId();
        $data['approved'] = true;

        [$sql, $params] = $this->getService()->getSearchQuery($data);
        $pager = $this->getDi()['pager']->getPaginatedResultSet($sql, $params, PaginationOptions::fromArray($data));

        foreach ($pager['list'] as $key => $item) {
            $invoice = $this->getDi()['em']->getRepository(Invoice::class)->find($item['id']) ?? throw new \FOSSBilling\InformationException('Invoice not found');
            $pager['list'][$key] = $this->getService()->toApiArray($invoice);
        }

        return $pager;
    }

    /**
     * Get invoice details.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['hash' => 'Invoice hash was not passed'])]
    public function get($data)
    {
        $identity = $this->getIdentity();
        $model = $this->getDi()['em']->getRepository(Invoice::class)->findOneBy(['hash' => $data['hash'], 'clientId' => $this->getIdentity()->getId()]);
        if (!$model) {
            throw new \FOSSBilling\InformationException('Invoice was not found');
        }

        return $this->getService()->toApiArray($model, true, $identity);
    }

    /**
     * Generates new invoice for selected order. If unpaid invoice for selected order
     * already exists, new invoice will not be generated, and old invoice hash
     * is returned.
     *
     * @return string - invoice hash
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['order_id' => 'Order ID (order_id) was not passed'])]
    public function renewal_invoice($data)
    {
        $model = $this->getDi()['em']->getRepository(Order::class)->findOneBy(['clientId' => $this->getIdentity()->getId(), 'id' => $data['order_id']]);
        if (!$model instanceof Order) {
            throw new \FOSSBilling\InformationException('Order not found');
        }
        $service = $this->getService();
        $invoice = $service->generateForOrder($model);
        $service->approveInvoice($invoice, ['id' => $invoice->id, 'use_credits' => true]);
        $this->getDi()['logger']->info('Generated new renewal invoice #%s', $invoice->id);

        return $invoice->hash;
    }

    /**
     * Deposit money in advance. Generates new invoice for depositing money.
     * Clients currency must be defined.
     *
     * @return string - invoice hash
     */
    #[RequiredParams(['amount' => 'Amount is required'])]
    public function funds_invoice($data)
    {
        if (!is_numeric($data['amount'])) {
            throw new \FOSSBilling\InformationException('You need to enter numeric value');
        }

        $service = $this->getService();
        $invoice = $service->generateFundsInvoice($this->getIdentity(), $data['amount']);
        $service->approveInvoice($invoice, ['id' => $invoice->id]);
        $this->getDi()['logger']->info('Generated add funds invoice #%s', $invoice->id);

        return $invoice->hash;
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
     *
     * @return array
     */
    public function transaction_get_list($data)
    {
        $data['client_id'] = $this->getIdentity()->getId();
        $data['status'] = 'processed';
        $transactionService = $this->getDi()['mod_service']('Invoice', 'Transaction');
        [$sql, $params] = $transactionService->getSearchQuery($data);

        $pager = $this->getDi()['pager']->getPaginatedResultSet($sql, $params, PaginationOptions::fromArray($data));

        foreach ($pager['list'] as $key => $item) {
            $transaction = $this->getDi()['em']->getRepository(Transaction::class)->find($item['id']) ?? throw new \FOSSBilling\InformationException('Transaction not found');
            $pager['list'][$key] = $transactionService->toApiArray($transaction);
        }

        return $pager;
    }

    public function get_tax_rate()
    {
        $service = $this->getDi()['mod_service']('Invoice', 'Tax');

        return $service->getTaxRateForClient($this->identity);
    }

    private function getInvoiceRepository(): InvoiceRepository
    {
        return $this->di['em']->getRepository(Invoice::class);
    }
}
