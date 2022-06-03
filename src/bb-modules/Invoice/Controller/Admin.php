<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Invoice\Controller;

class Admin implements \Box\InjectionAwareInterface
{
    protected $di;

    /**
     * @param mixed $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return mixed
     */
    public function getDi()
    {
        return $this->di;
    }

    public function fetchNavigation()
    {
        return [
            'group' => [
                'index' => 400,
                'location' => 'invoice',
                'label' => 'Invoices',
                'uri' => 'invoice',
                'class' => 'invoices',
                'sprite_class' => 'dark-sprite-icon sprite-money',
            ],
            'subpages' => [
                [
                    'location' => 'invoice',
                    'label' => 'Overview',
                    'uri' => $this->di['url']->adminLink('invoice'),
                    'index' => 100,
                    'class' => '',
                ],
                [
                    'location' => 'invoice',
                    'label' => 'Advanced search',
                    'uri' => $this->di['url']->adminLink('invoice', ['show_filter' => 1]),
                    'index' => 200,
                    'class' => '',
                ],
                [
                    'location' => 'invoice',
                    'label' => 'Subscriptions',
                    'uri' => $this->di['url']->adminLink('invoice/subscriptions'),
                    'index' => 300,
                    'class' => '',
                ],
                [
                    'location' => 'invoice',
                    'label' => 'Transactions overview',
                    'uri' => $this->di['url']->adminLink('invoice/transactions'),
                    'index' => 400,
                    'class' => '',
                ],
                [
                    'location' => 'invoice',
                    'label' => 'Transactions search',
                    'uri' => $this->di['url']->adminLink('invoice/transactions', ['show_filter' => 1]),
                    'index' => 500,
                    'class' => '',
                ],
                [
                    'location' => 'system',
                    'label' => 'Tax rules',
                    'uri' => $this->di['url']->adminLink('invoice/tax'),
                    'index' => 180,
                    'class' => '',
                ],
                [
                    'location' => 'system',
                    'label' => 'Payment gateways',
                    'uri' => $this->di['url']->adminLink('invoice/gateways'),
                    'index' => 160,
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Box_App &$app)
    {
        $app->get('/invoice', 'get_index', [], get_class($this));
        $app->get('/invoice/subscriptions', 'get_subscriptions', [], get_class($this));
        $app->get('/invoice/transactions', 'get_transactions', [], get_class($this));
        $app->get('/invoice/gateways', 'get_gateways', [], get_class($this));
        $app->get('/invoice/gateway/:id', 'get_gateway', ['id' => '[0-9]+'], get_class($this));
        $app->get('/invoice/manage/:id', 'get_invoice', ['id' => '[0-9]+'], get_class($this));
        $app->get('/invoice/transaction/:id', 'get_transaction', ['id' => '[0-9]+'], get_class($this));
        $app->get('/invoice/subscription/:id', 'get_subscription', ['id' => '[0-9]+'], get_class($this));
        $app->get('/invoice/tax', 'get_taxes', [], get_class($this));
        $app->get('/invoice/tax/:id', 'get_tax', [], get_class($this));
        $app->get('/invoice/pdf/:hash', 'get_pdf', ['hash' => '[a-z0-9]+'], get_class($this));
    }

    public function get_taxes(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_invoice_tax');
    }

    public function get_tax(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $tax = $api->invoice_tax_get(['id' => $id]);

        return $app->render('mod_invoice_taxupdate', ['tax' => $tax]);
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_invoice_index');
    }

    public function get_invoice(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $invoice = $api->invoice_get(['id' => $id]);

        return $app->render('mod_invoice_invoice', ['invoice' => $invoice]);
    }

    public function get_transaction(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $tx = $api->invoice_transaction_get(['id' => $id]);

        return $app->render('mod_invoice_transaction', ['transaction' => $tx]);
    }

    public function get_transactions(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_invoice_transactions');
    }

    public function get_subscriptions(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_invoice_subscriptions');
    }

    public function get_subscription(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $tx = $api->invoice_subscription_get(['id' => $id]);

        return $app->render('mod_invoice_subscription', ['subscription' => $tx]);
    }

    public function get_gateways(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_invoice_gateways');
    }

    public function get_gateway(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $gateway = $api->invoice_gateway_get(['id' => $id]);

        return $app->render('mod_invoice_gateway', ['gateway' => $gateway]);
    }

    public function get_pdf(\Box_App $app, $hash)
    {
        $api = $this->di['api_guest'];
        $data = [
            'hash' => $hash,
        ];
        $invoice = $api->invoice_pdf($data);

        return $app->render('mod_invoice_pdf', ['invoice' => $invoice]);
    }
}