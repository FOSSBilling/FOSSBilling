<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Invoice\Controller;

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

    public function register(\Box_App &$app)
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

    public function get_invoices(\Box_App $app)
    {
        $this->di['is_client_logged'];

        return $app->render('mod_invoice_index');
    }

    public function get_invoice(\Box_App $app, $hash)
    {
        $api = $this->di['api_guest'];
        $data = [
            'hash' => $hash,
        ];
        $invoice = $api->invoice_get($data);
        $systemService = $this->di['mod_service']('system');
        $hash_access = $systemService->getParamValue('invoice_accessible_from_hash', '0');

        // If hash_access is not 0 or if a client is logged in, get the logged-in client
        if (!$this->di['auth']->isAdminLoggedIn() && $hash_access === '0') {
            $this->redirectIfNotInvoiceBuyer($app, $invoice);
        }

        return $app->render('mod_invoice_invoice', ['invoice' => $invoice]);
    }

    public function get_invoice_print(\Box_App $app, $hash)
    {
        $api = $this->di['api_guest'];
        $data = [
            'hash' => $hash,
        ];
        $invoice = $api->invoice_get($data);
        $systemService = $this->di['mod_service']('system');
        $hash_access = $systemService->getParamValue('invoice_accessible_from_hash', '0');

        // If hash_access is not 0 or if a client is logged in, get the logged-in client
        if (!$this->di['auth']->isAdminLoggedIn() && $hash_access === '0') {
            $this->redirectIfNotInvoiceBuyer($app, $invoice);
        }

        return $app->render('mod_invoice_print', ['invoice' => $invoice]);
    }

    public function get_thankyoupage(\Box_App $app, $hash)
    {
        $api = $this->di['api_guest'];
        $data = [
            'hash' => $hash,
        ];
        $invoice = $api->invoice_get($data);

        return $app->render('mod_invoice_thankyou', ['invoice' => $invoice]);
    }

    public function get_banklink(\Box_App $app, $hash, $id)
    {
        $api = $this->di['api_guest'];
        $data = [
            'allow_subscription' => $_GET['allow_subscription'] ?? true,
            'hash' => $hash,
            'gateway_id' => $id,
            'auto_redirect' => true,
        ];

        $invoice = $api->invoice_get($data);
        $result = $api->invoice_payment($data);

        return $app->render('mod_invoice_banklink', ['payment' => $result, 'invoice' => $invoice]);
    }

    public function get_pdf(\Box_App $app, $hash)
    {
        $api = $this->di['api_guest'];
        $data = [
            'hash' => $hash,
        ];
        $invoice = $api->invoice_pdf($data);
        $systemService = $this->di['mod_service']('system');
        $hash_access = $systemService->getParamValue('invoice_accessible_from_hash', '0');

        // If hash_access is not 0 or if a client is logged in, get the logged-in client
        if (!$this->di['auth']->isAdminLoggedIn() && $hash_access === '0') {
            $this->redirectIfNotInvoiceBuyer($app, $invoice);
        }

        return $app->render('mod_invoice_pdf', ['invoice' => $invoice]);
    }

    public function redirectIfNotInvoiceBuyer($app, $invoice)
    {
        $client = $this->di['loggedin_client'];
        if ($invoice['client']['id'] != $client->id) {
            // redirect to client invoices/invoice'));
            return $app->redirect('/invoice');
        }
    }
}
