<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Email\Controller;

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
