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

namespace Box\Mod\Support\Controller;

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
                'location' => 'support',
                'index' => 500,
                'label' => 'Support',
                'class' => 'support',
                'sprite_class' => 'dark-sprite-icon sprite-dialog',
            ],
            'subpages' => [
                [
                    'location' => 'support',
                    'label' => 'Client tickets',
                    'uri' => $this->di['url']->adminLink('support', ['status' => 'open']),
                    'index' => 100,
                    'class' => '',
                ],
                [
                    'location' => 'support',
                    'label' => 'Advanced ticket search',
                    'uri' => $this->di['url']->adminLink('support', ['show_filter' => 1]),
                    'index' => 200,
                    'class' => '',
                ],
                [
                    'location' => 'support',
                    'label' => 'Public tickets',
                    'uri' => $this->di['url']->adminLink('support/public-tickets', ['status' => 'open']),
                    'index' => 300,
                    'class' => '',
                ],
                [
                    'location' => 'support',
                    'label' => 'Canned responses',
                    'uri' => $this->di['url']->adminLink('support/canned-responses'),
                    'index' => 400,
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Box_App &$app)
    {
        $app->get('/support', 'get_index', [], get_class($this));
        $app->get('/support/', 'get_index', [], get_class($this));
        $app->get('/support/index', 'get_index', [], get_class($this));
        $app->get('/support/ticket/:id', 'get_ticket', ['id' => '[0-9]+'], get_class($this));
        $app->get('/support/ticket/:id/message/:messageid', 'get_ticket', ['id' => '[0-9]+', 'messageid' => '[0-9]+'], get_class($this));
        $app->get('/support/public-tickets', 'get_public_tickets', [], get_class($this));
        $app->get('/support/public-ticket/:id', 'get_public_ticket', ['id' => '[0-9]+'], get_class($this));
        $app->get('/support/helpdesks', 'get_helpdesks', [], get_class($this));
        $app->get('/support/helpdesk/:id', 'get_helpdesk', ['id' => '[0-9]+'], get_class($this));
        $app->get('/support/canned-responses', 'get_canned_list', [], get_class($this));
        $app->get('/support/canned/:id', 'get_canned', ['id' => '[0-9]+'], get_class($this));
        $app->get('/support/canned-category/:id', 'get_canned_cat', ['id' => '[0-9]+'], get_class($this));
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
}
