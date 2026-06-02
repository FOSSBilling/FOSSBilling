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

/**
 * Invoice processing.
 */

namespace Box\Mod\Invoice\Api;

use Box\Mod\Invoice\InvoiceOperation;
use FOSSBilling\Validation\Api\RequiredParams;

class Guest extends \Api_Abstract
{
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
        if (!preg_match('/^[a-f0-9]{30,60}$/', (string) $data['hash'])) {
            throw new \FOSSBilling\Exception('Invalid invoice hash', null, 4001);
        }

        $this->getDi()['rate_limiter']->consumeOrThrow('invoice_get_ip', (string) $this->getIp());
        $this->getDi()['rate_limiter']->consumeOrThrow('invoice_get_hash', (string) $data['hash']);

        $model = $this->getDi()['db']->findOne('Invoice', 'hash = :hash', ['hash' => $data['hash']]);
        if (!$model) {
            throw new \FOSSBilling\Exception('Invoice was not found');
        }
        $service = $this->getService();
        $service->checkInvoiceAuth($model, InvoiceOperation::READ);

        return $service->toApiArray($model, true, $this->getIdentity());
    }

    /**
     * Get list of available payment gateways to pay for invoices.
     *
     * @optional string $format - if format is "pairs" then id=>name values are returned
     *
     * @return array
     */
    public function gateways($data)
    {
        $gatewayService = $this->getDi()['mod_service']('Invoice', 'PayGateway');

        return $gatewayService->getActive($data);
    }

    /**
     * Process invoice for selected gateway. Returned result can be processed
     * to redirect or to show required information. Returned result depends
     * on payment gateway.
     *
     * Tries to detect if invoice can be subscribed and if payment gateway supports subscriptions
     * uses subscription payment.
     *
     * @optional bool $auto_redirect - should payment adapter automatically redirect client or just print pay now button
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    public function payment($data)
    {
        if (empty($data['hash'])) {
            throw new \FOSSBilling\Exception('Invoice hash not passed. Missing param hash', null, 810);
        }

        if (!preg_match('/^[a-f0-9]{30,60}$/', (string) $data['hash'])) {
            throw new \FOSSBilling\Exception('Invalid invoice hash', null, 4001);
        }

        if (empty($data['gateway_id'])) {
            throw new \FOSSBilling\Exception('Payment method not found. Missing param gateway_id', null, 811);
        }

        $this->getDi()['rate_limiter']->consumeOrThrow('invoice_payment_ip', (string) $this->getIp());
        $this->getDi()['rate_limiter']->consumeOrThrow('invoice_payment_hash', (string) $data['hash']);

        return $this->getService()->processInvoice($data);
    }

    /**
     * Generates PDF for given invoice.
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['hash' => 'Invoice hash was not passed'])]
    public function pdf($data)
    {
        if (!preg_match('/^[a-f0-9]{30,60}$/', (string) $data['hash'])) {
            throw new \FOSSBilling\Exception('Invalid invoice hash', null, 4001);
        }

        $this->getDi()['rate_limiter']->consumeOrThrow('invoice_pdf_ip', (string) $this->getIp());
        $this->getDi()['rate_limiter']->consumeOrThrow('invoice_pdf_hash', (string) $data['hash']);

        return $this->getService()->generatePDF($data['hash'], $this->getIdentity());
    }
}
