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

class Admin implements \FOSSBilling\InjectionAwareInterface
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

    public function fetchNavigation()
    {
        return [
            'group' => [
                'index' => 400,
                'location' => 'invoice',
                'label' => __trans('Invoices'),
                'uri' => 'invoice',
                'class' => 'invoices',
                'sprite_class' => 'dark-sprite-icon sprite-money',
            ],
            'subpages' => [
                [
                    'location' => 'invoice',
                    'label' => __trans('Overview'),
                    'uri' => $this->di['url']->adminLink('invoice'),
                    'index' => 100,
                    'class' => '',
                ],
                [
                    'location' => 'invoice',
                    'label' => __trans('Advanced search'),
                    'uri' => $this->di['url']->adminLink('invoice', ['show_filter' => 1]),
                    'index' => 200,
                    'class' => '',
                ],
                [
                    'location' => 'invoice',
                    'label' => __trans('Subscriptions'),
                    'uri' => $this->di['url']->adminLink('invoice/subscriptions'),
                    'index' => 300,
                    'class' => '',
                ],
                [
                    'location' => 'invoice',
                    'label' => __trans('Transactions overview'),
                    'uri' => $this->di['url']->adminLink('invoice/transactions'),
                    'index' => 400,
                    'class' => '',
                ],
                [
                    'location' => 'invoice',
                    'label' => __trans('Transactions search'),
                    'uri' => $this->di['url']->adminLink('invoice/transactions', ['show_filter' => 1]),
                    'index' => 500,
                    'class' => '',
                ],
                [
                    'location' => 'system',
                    'label' => __trans('Tax rules'),
                    'uri' => $this->di['url']->adminLink('invoice/tax'),
                    'index' => 180,
                    'class' => '',
                ],
                [
                    'location' => 'system',
                    'label' => __trans('Payment gateways'),
                    'uri' => $this->di['url']->adminLink('invoice/gateways'),
                    'index' => 160,
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Box_App &$app)
    {
        $app->get('/invoice', 'get_index', [], static::class);
        $app->get('/invoice/subscriptions', 'get_subscriptions', [], static::class);
        $app->get('/invoice/transactions', 'get_transactions', [], static::class);
        $app->get('/invoice/gateways', 'get_gateways', [], static::class);
        $app->get('/invoice/gateway/:id', 'get_gateway', ['id' => '[0-9]+'], static::class);
        $app->get('/invoice/manage/:id', 'get_invoice', ['id' => '[0-9]+'], static::class);
        $app->get('/invoice/transaction/:id', 'get_transaction', ['id' => '[0-9]+'], static::class);
        $app->get('/invoice/subscription/:id', 'get_subscription', ['id' => '[0-9]+'], static::class);
        $app->get('/invoice/tax', 'get_taxes', [], static::class);
        $app->get('/invoice/tax/:id', 'get_tax', [], static::class);
        $app->get('/invoice/pdf/:hash', 'get_pdf', ['hash' => '[a-z0-9]+'], static::class);
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
