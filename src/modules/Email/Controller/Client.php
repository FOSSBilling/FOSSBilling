<?php

/**
 * FOSSBilling.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * Copyright FOSSBilling 2022
 * This software may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Email\Controller;

class Client implements \Box\InjectionAwareInterface
{
    protected ?\Pimple\Container $di;

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
        $app->get('/email', 'get_emails', [], static::class);
        $app->get('/email/:id', 'get_email', ['id' => '[0-9]+'], static::class);
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
