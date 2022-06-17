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

class Client implements \Box\InjectionAwareInterface
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

    public function register(\Box_App &$app)
    {
        $app->get('/email', 'get_emails', [], get_class($this));
        $app->get('/email/:id', 'get_email', ['id' => '[0-9]+'], get_class($this));
    }

    public function get_emails(\Box_App $app)
    {
        $this->di['is_client_logged'];

        return $app->render('mod_email_index');
    }

    public function get_email(\Box_App $app, $id)
    {
        $api = $this->di['api_client'];
        $data = ['id' => $id];
        $email = $api->email_get($data);

        return $app->render('mod_email_email', ['email' => $email]);
    }
}
