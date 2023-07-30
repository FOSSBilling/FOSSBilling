<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Support\Controller;

class Client implements \FOSSBilling\InjectionAwareInterface
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
        $app->get('/support', 'get_tickets', [], static::class);
        $app->get('/support/ticket/:id', 'get_ticket', [], static::class);
        $app->get('/support/contact-us', 'get_contact_us', [], static::class);
        $app->get('/support/contact-us/conversation/:hash', 'get_contact_us_conversation', ['hash' => '[a-z0-9]+'], static::class);
    }

    public function get_tickets(\Box_App $app)
    {
        $this->di['is_client_logged'];

        return $app->render('mod_support_tickets');
    }

    public function get_ticket(\Box_App $app, $id)
    {
        $api = $this->di['api_client'];
        $ticket = $api->support_ticket_get(['id' => $id]);

        return $app->render('mod_support_ticket', ['ticket' => $ticket]);
    }

    public function get_contact_us(\Box_App $app)
    {
        return $app->render('mod_support_contact_us');
    }

    public function get_contact_us_conversation(\Box_App $app, $hash)
    {
        $api = $this->di['api_guest'];
        $data = [
            'hash' => $hash,
        ];
        $array = $api->support_ticket_get($data);

        return $app->render('mod_support_contact_us_conversation', ['ticket' => $array]);
    }
}
