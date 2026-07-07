<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Invoice\Controller;

use FOSSBilling\InformationException;
use Symfony\Component\HttpFoundation\Response;

class Client implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function register(\Box_App &$app): void
    {
        $app->get('/invoice', 'get_invoices', [], static::class);
        $app->post('/invoice', 'get_invoices', [], static::class);
        $app->get('/invoice/:hash', 'get_invoice', ['hash' => '[a-z0-9]+'], static::class);
        $app->post('/invoice/:hash', 'get_invoice', ['hash' => '[a-z0-9]+'], static::class);
        $app->get('/invoice/print/:hash', 'get_invoice_print', ['hash' => '[a-z0-9]+'], static::class);
        $app->post('/invoice/print/:hash', 'get_invoice_print', ['hash' => '[a-z0-9]+'], static::class);
        $app->get('/invoice/banklink/:hash/:id', 'get_banklink', ['id' => '[0-9]+', 'hash' => '[a-z0-9]+'], static::class);
        $app->get('/invoice/thank-you/:hash', 'get_thankyoupage', ['hash' => '[a-z0-9]+'], static::class);
        $app->post('/invoice/thank-you/:hash', 'get_thankyoupage', ['hash' => '[a-z0-9]+'], static::class);
        $app->get('/invoice/pdf/:hash', 'get_pdf', ['hash' => '[a-z0-9]+'], static::class);
    }

    public function get_invoices(\Box_App $app): string
    {
        $this->di['is_client_logged'];

        return $app->render('mod_invoice_index');
    }

    public function get_invoice(\Box_App $app, $hash): string|Response
    {
        $data = [
            'hash' => $hash,
        ];
        $invoice = $this->getInvoiceOrRedirect($app, $data);
        if ($invoice instanceof Response) {
            return $invoice;
        }

        return $app->render('mod_invoice_invoice', ['invoice' => $invoice]);
    }

    public function get_invoice_print(\Box_App $app, $hash): string|Response
    {
        $data = [
            'hash' => $hash,
        ];
        $invoice = $this->getInvoiceOrRedirect($app, $data);
        if ($invoice instanceof Response) {
            return $invoice;
        }

        return $app->render('mod_invoice_print', ['invoice' => $invoice]);
    }

    public function get_thankyoupage(\Box_App $app, $hash): string|Response
    {
        $data = [
            'hash' => $hash,
        ];
        $invoice = $this->getInvoiceOrRedirect($app, $data);
        if ($invoice instanceof Response) {
            return $invoice;
        }

        return $app->render('mod_invoice_thankyou', ['invoice' => $invoice]);
    }

    public function get_banklink(\Box_App $app, $hash, $id): string|Response
    {
        $data = [
            'allow_subscription' => $app->getRequest()->query->getBoolean('allow_subscription', true),
            'hash' => $hash,
            'gateway_id' => $id,
            'auto_redirect' => true,
        ];

        $api = $this->di['api_guest'];
        $invoice = $this->getInvoiceOrRedirect($app, $data);
        if ($invoice instanceof Response) {
            return $invoice;
        }

        try {
            $payment = $api->invoice_payment($data);
        } catch (InformationException $e) {
            if ($this->isInvoiceAccessDenied($e)) {
                return $app->redirect('invoice');
            }

            throw $e;
        }

        return $app->render('mod_invoice_banklink', ['payment' => $payment, 'invoice' => $invoice]);
    }

    public function get_pdf(\Box_App $app, $hash): Response
    {
        $api = $this->di['api_guest'];
        $data = [
            'hash' => $hash,
        ];

        try {
            $response = $api->invoice_pdf($data);
        } catch (InformationException $e) {
            if ($this->isInvoiceAccessDenied($e)) {
                return $app->redirect('invoice');
            }

            throw $e;
        }

        if (!$response instanceof Response) {
            throw new \FOSSBilling\Exception('Invoice PDF response could not be generated');
        }

        return $response;
    }

    private function getInvoiceOrRedirect(\Box_App $app, array $data): array|Response
    {
        $api = $this->di['api_guest'];

        try {
            return $api->invoice_get($data);
        } catch (InformationException $e) {
            if ($this->isInvoiceAccessDenied($e)) {
                return $app->redirect('invoice');
            }

            throw $e;
        }
    }

    private function isInvoiceAccessDenied(InformationException $e): bool
    {
        return (int) $e->getCode() === 403;
    }
}
