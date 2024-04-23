<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Support\Controller;

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

    public function fetchNavigation(): array
    {
        $nav = [
            'group' => [
                'location' => 'support',
                'index' => 500,
                'label' => __trans('Support'),
                'class' => 'support',
                'sprite_class' => 'dark-sprite-icon sprite-dialog',
            ],
            'subpages' => [
                [
                    'location' => 'support',
                    'label' => __trans('Client Tickets'),
                    'uri' => $this->di['url']->adminLink('support', ['status' => 'open']),
                    'index' => 100,
                    'class' => '',
                ],
                [
                    'location' => 'support',
                    'label' => __trans('Public Tickets'),
                    'uri' => $this->di['url']->adminLink('support/public-tickets', ['status' => 'open']),
                    'index' => 200,
                    'class' => '',
                ],
                [
                    'location' => 'support',
                    'label' => __trans('Advanced Ticket Search'),
                    'uri' => $this->di['url']->adminLink('support', ['show_filter' => 1]),
                    'index' => 300,
                    'class' => '',
                ],
                [
                    'location' => 'support',
                    'label' => __trans('Canned Responses'),
                    'uri' => $this->di['url']->adminLink('support/canned-responses'),
                    'index' => 400,
                    'class' => '',
                ],
            ],
        ];

        if ($this->di['mod']('support')->getService()->kbEnabled()) {
            $nav['subpages'][] =
                [
                    'location' => 'support',
                    'index' => 500,
                    'label' => __trans('Knowledge Base'),
                    'uri' => $this->di['url']->adminLink('support/kb'),
                    'class' => '',
                ];
        }

        return $nav;
    }

    public function register(\Box_App &$app)
    {
        $app->get('/support', 'get_index', [], static::class);
        $app->get('/support/', 'get_index', [], static::class);
        $app->get('/support/index', 'get_index', [], static::class);
        $app->get('/support/ticket/:id', 'get_ticket', ['id' => '[0-9]+'], static::class);
        $app->get('/support/ticket/:id/message/:messageid', 'get_ticket', ['id' => '[0-9]+', 'messageid' => '[0-9]+'], static::class);
        $app->get('/support/public-tickets', 'get_public_tickets', [], static::class);
        $app->get('/support/public-ticket/:id', 'get_public_ticket', ['id' => '[0-9]+'], static::class);
        $app->get('/support/helpdesks', 'get_helpdesks', [], static::class);
        $app->get('/support/helpdesk/:id', 'get_helpdesk', ['id' => '[0-9]+'], static::class);
        $app->get('/support/canned-responses', 'get_canned_list', [], static::class);
        $app->get('/support/canned/:id', 'get_canned', ['id' => '[0-9]+'], static::class);
        $app->get('/support/canned-category/:id', 'get_canned_cat', ['id' => '[0-9]+'], static::class);

        if ($this->di['mod']('support')->getService()->kbEnabled()) {
            $app->get('/support/kb', 'get_kb_index', [], static::class);
            $app->get('/support/kb/article/:id', 'get_kb_article', ['id' => '[0-9]+'], static::class);
            $app->get('/support/kb/category/:id', 'get_kb_category', ['id' => '[0-9]+'], static::class);
        }
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_support_tickets');
    }

    public function get_ticket(\Box_App $app, $id, $messageid = '')
    {
        $api = $this->di['api_admin'];
        $ticket = $api->support_ticket_get(['id' => $id]);

        $cdm = '';
        $mod = $this->di['mod']('support');
        $config = $mod->getConfig();

        try {
            if (isset($config['delay_enable']) && $config['delay_enable'] && isset($config['delay_hours']) && $config['delay_hours'] >= 0) {
                $last_message = end($ticket['messages']);
                reset($ticket);

                $hours_passed = (round((time() - strtotime($last_message['created_at'])) / 3600) > $config['delay_hours']);
                if ($hours_passed) {
                    $delay_canned = $api->support_canned_get(['id' => $config['delay_message_id']]);
                    $cdm = $delay_canned['content'];
                }
            }
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }

        return $app->render('mod_support_ticket', ['ticket' => $ticket, 'canned_delay_message' => $cdm, 'request_message' => $messageid]);
    }

    public function get_public_tickets(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_support_public_tickets');
    }

    public function get_public_ticket(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $ticket = $api->support_public_ticket_get(['id' => $id]);

        return $app->render('mod_support_public_ticket', ['ticket' => $ticket]);
    }

    public function get_helpdesk(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $helpdesk = $api->support_helpdesk_get(['id' => $id]);

        return $app->render('mod_support_helpdesk', ['helpdesk' => $helpdesk]);
    }

    public function get_helpdesks(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_support_helpdesks');
    }

    public function get_canned_list(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_support_canned_responses');
    }

    public function get_canned(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $c = $api->support_canned_get(['id' => $id]);

        return $app->render('mod_support_canned_response', ['response' => $c]);
    }

    public function get_canned_cat(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $c = $api->support_canned_category_get(['id' => $id]);

        return $app->render('mod_support_canned_category', ['category' => $c]);
    }

    /*
    * Support Knowledge Base.
    */
    public function get_kb_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_support_kb_index');
    }

    public function get_kb_article(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $post = $api->support_kb_article_get(['id' => $id]);

        return $app->render('mod_support_kb_article', ['post' => $post]);
    }

    public function get_kb_category(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $cat = $api->support_kb_category_get(['id' => $id]);

        return $app->render('mod_support_kb_category', ['category' => $cat]);
    }
}
