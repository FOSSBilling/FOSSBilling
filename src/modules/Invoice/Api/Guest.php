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
 * Invoice processing.
 */

namespace Box\Mod\Invoice\Api;

class Guest extends \Api_Abstract
{
    /**
     * Get invoice details.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    public function get($data)
    {
        $required = [
            'hash' => 'Invoice hash not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->findOne('Invoice', 'hash = :hash', ['hash' => $data['hash']]);
        if (!$model) {
            throw new \FOSSBilling\Exception('Invoice was not found');
        }

        return $this->getService()->toApiArray($model, true, $this->getIdentity());
    }

    /**
     * Update Invoice details. Only unpaid invoice details can be updated.
     *
     * @optional int $gateway_id - selected payment gateway id
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function update($data)
    {
        $required = [
            'hash' => 'Invoice hash not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $invoice = $this->di['db']->findOne('Invoice', 'hash = :hash', ['hash' => $data['hash']]);
        if (!$invoice) {
            throw new \FOSSBilling\Exception('Invoice was not found');
        }
        if ($invoice->status == 'paid') {
            throw new \FOSSBilling\InformationException('Paid Invoice cannot be modified');
        }

        $updateParams = [];
        $updateParams['gateway_id'] = $data['gateway_id'] ?? null;

        return $this->getService()->updateInvoice($invoice, $updateParams);
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
        $gatewayService = $this->di['mod_service']('Invoice', 'PayGateway');

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
        if (!isset($data['hash'])) {
            throw new \FOSSBilling\Exception('Invoice hash not passed. Missing param hash', null, 810);
        }

        if (!isset($data['gateway_id'])) {
            throw new \FOSSBilling\Exception('Payment method not found. Missing param gateway_id', null, 811);
        }

        return $this->getService()->processInvoice($data);
    }

    /**
     * Generates PDF for given invoice.
     *
     * @throws \FOSSBilling\Exception
     */
    public function pdf($data)
    {
        $required = [
            'hash' => 'Invoice hash is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->generatePDF($data['hash'], $this->getIdentity());
    }
}
