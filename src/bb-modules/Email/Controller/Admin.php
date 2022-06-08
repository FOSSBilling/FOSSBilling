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

namespace Box\Mod\Email\Controller;

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
            'subpages' => [
                [
                    'location' => 'activity',
                    'index' => 200,
                    'label' => 'Email history',
                    'uri' => $this->di['url']->adminLink('email/history'),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Box_App &$app)
    {
        $app->get('/email/history/', 'get_history', [], get_class($this));
        $app->get('/email/history', 'get_history', [], get_class($this));
        $app->get('/email/templates', 'get_index', [], get_class($this));
        $app->get('/email/template/:id', 'get_template', ['id' => '[0-9]+'], get_class($this));
        $app->get('/email/:id', 'get_email', ['id' => '[0-9]+'], get_class($this));
    }

    public function get_history(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_email_history');
    }

    public function get_template(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $template = $api->email_template_get(['id' => $id]);

        return $app->render('mod_email_template', ['template' => $template]);
    }

    public function get_email(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $template = $api->email_email_get(['id' => $id]);

        return $app->render('mod_email_details', ['email' => $template]);
    }
}
