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
 * Invoice processing.
 */

namespace Box\Mod\Invoice\Api;

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\InvoiceOperation;
use FOSSBilling\Core\Validation\Api\RequiredParams;

class Guest extends \FOSSBilling\Core\Api\AbstractApi
{
    /**
     * Get invoice details.
     *
     * @return array
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['hash' => 'Invoice hash was not passed'])]
    public function get($data)
    {
        if (!preg_match('/^[a-f0-9]{30,60}$/', (string) $data['hash'])) {
            throw new \FOSSBilling\Core\Exception\InformationException('Invalid invoice hash', null, 4001);
        }

        // The per-IP bucket is charged first: it is what stops someone enumerating invoice hashes.
        // The per-hash bucket is charged only once the read is allowed. Charging it before the
        // authorization check would let anyone who holds the link spend the invoice's own budget
        // with refused requests and lock the owner out for the rest of the window.
        $this->getDi()['rate_limiter']->consumeOrThrow('invoice_get_ip', $this->getIp());

        // Fail fast when the per-hash budget is already exhausted, before touching the database.
        // The probe spends nothing; the budget is charged below, after authorization.
        $this->getDi()['rate_limiter']->consumeOrThrow('invoice_get_hash', (string) $data['hash'], 0);

        $model = $this->getService()->getInvoiceRepository()->findByHash((string) $data['hash']);
        if (!$model) {
            throw new \FOSSBilling\Core\Exception\InformationException('Invoice was not found');
        }
        $service = $this->getService();
        $service->checkInvoiceAuth($model, InvoiceOperation::READ);

        $this->getDi()['rate_limiter']->consumeOrThrow('invoice_get_hash', (string) $data['hash']);

        $result = $service->toApiArray($model, true, $this->getIdentity());
        $result['debited_by_invoice_ids'] = $service->getDebitingInvoiceIds($model);
        $result['related_invoices'] = $service->getRelatedInvoiceReferences($model);

        return $result;
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
     * Per-gateway payment fees for an invoice, so the client sees each
     * method's surcharge before paying. Read-only: nothing is changed.
     *
     * @return array
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['hash' => 'Invoice hash was not passed'])]
    public function gateway_fees($data)
    {
        if (!preg_match('/^[a-f0-9]{30,60}$/', (string) $data['hash'])) {
            throw new \FOSSBilling\Core\Exception\InformationException('Invalid invoice hash', null, 4001);
        }

        // Same dual-bucket pattern as invoice_get: per-IP first so hash
        // links cannot be enumerated, per-hash only after the read is allowed.
        $this->getDi()['rate_limiter']->consumeOrThrow('invoice_gateway_fees_ip', $this->getIp());
        $this->getDi()['rate_limiter']->consumeOrThrow('invoice_gateway_fees_hash', (string) $data['hash'], 0);

        $model = $this->getService()->getInvoiceRepository()->findByHash((string) $data['hash']);
        if (!$model) {
            throw new \FOSSBilling\Core\Exception\InformationException('Invoice was not found');
        }
        $service = $this->getService();
        $service->checkInvoiceAuth($model, InvoiceOperation::READ);

        $this->getDi()['rate_limiter']->consumeOrThrow('invoice_gateway_fees_hash', (string) $data['hash']);

        if ($model->getStatus() !== Invoice::STATUS_UNPAID) {
            return ['base' => 0.0, 'fees' => []];
        }

        $feesService = $this->getDi()['mod_service']('Invoice', 'GatewayFees');
        $payGatewayService = $this->getDi()['mod_service']('Invoice', 'PayGateway');
        $subscribeService = $this->getDi()['mod_service']('Invoice', 'Subscription');

        $base = $feesService->base($model);
        $currency = (string) $model->getCurrency();
        $clientId = $model->getClientId();
        $subscribable = $subscribeService->isSubscribable((int) $model->getId());

        $fees = [];
        foreach ($payGatewayService->getPayGatewayRepository()->findEnabledOrderedByIdDesc() as $gateway) {
            if (!in_array($currency, $payGatewayService->getAcceptedCurrencies($gateway), true)) {
                continue;
            }
            if (!$gateway->isAllowSingle() && !($subscribable && $gateway->isAllowRecurrent())) {
                continue;
            }
            $charge = $feesService->charge($model, $gateway, $base);
            $fees[(int) $gateway->getId()] = [
                'fee' => $charge,
                'total' => round($base + $charge, 2),
            ];
        }

        return ['base' => $base, 'fees' => $fees];
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
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    public function payment($data)
    {
        if (empty($data['hash'])) {
            throw new \FOSSBilling\Core\Exception\BaseException('Invoice hash not passed. Missing param hash', null, 810);
        }

        if (!preg_match('/^[a-f0-9]{30,60}$/', (string) $data['hash'])) {
            throw new \FOSSBilling\Core\Exception\BaseException('Invalid invoice hash', null, 4001);
        }

        if (empty($data['gateway_id'])) {
            throw new \FOSSBilling\Core\Exception\InformationException('Payment method not found. Missing param gateway_id', null, 811);
        }

        $this->getDi()['rate_limiter']->consumeOrThrow('invoice_payment_ip', $this->getIp());

        // Check the per-hash budget without spending it, so an exhausted invoice is refused before the
        // gateway is contacted. It is spent below, after processInvoice() has run its own authorization
        // check, so a refused request does not count against the invoice. The probe is best-effort:
        // in a race the final charge below remains authoritative and may still throw afterwards.
        $this->getDi()['rate_limiter']->consumeOrThrow('invoice_payment_hash', (string) $data['hash'], 0);

        $result = $this->getService()->processInvoice($data);

        $this->getDi()['rate_limiter']->consumeOrThrow('invoice_payment_hash', (string) $data['hash']);

        return $result;
    }

    /**
     * Get whether the client add funds feature is enabled, or not.
     */
    public function funds_enabled(): bool
    {
        return $this->getService()->isFundsEnabled();
    }

    /**
     * Generates PDF for given invoice.
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['hash' => 'Invoice hash was not passed'])]
    public function pdf($data)
    {
        if (!preg_match('/^[a-f0-9]{30,60}$/', (string) $data['hash'])) {
            throw new \FOSSBilling\Core\Exception\BaseException('Invalid invoice hash', null, 4001);
        }

        $this->getDi()['rate_limiter']->consumeOrThrow('invoice_pdf_ip', $this->getIp());

        // Fail fast when the per-hash budget is already exhausted, before rendering the PDF.
        // The probe spends nothing; generatePDF() performs the authorization check itself and the
        // budget is charged below, only for a PDF the caller was allowed to have.
        $this->getDi()['rate_limiter']->consumeOrThrow('invoice_pdf_hash', (string) $data['hash'], 0);

        // generatePDF() performs the authorization check itself; charge the per-hash bucket only
        // for a PDF the caller was allowed to have.
        $pdf = $this->getService()->generatePDF($data['hash'], $this->getIdentity());

        $this->getDi()['rate_limiter']->consumeOrThrow('invoice_pdf_hash', (string) $data['hash']);

        return $pdf;
    }
}
