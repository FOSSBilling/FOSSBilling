<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Client\Controller;

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
        $app->get('/client/reset-password-confirm/:hash', 'get_reset_password_confirm', ['hash' => '[a-z0-9]+'], static::class);
        $app->get('/client', 'get_client_index', [], static::class);
        $app->get('/client/logout', 'get_client_logout', [], static::class);
        $app->get('/client/:page', 'get_client_page', ['page' => '[a-z0-9-]+'], static::class);
        $app->get('/client/confirm-email/:hash', 'get_client_confirmation', ['page' => '[a-z0-9-]+'], static::class);
    }

    public function get_client_index(\Box_App $app)
    {
        $this->di['is_client_logged'];

        return $app->render('mod_client_index');
    }

    public function get_client_confirmation(\Box_App $app, $hash)
    {
        $service = $this->di['mod_service']('client');
        $service->approveClientEmailByHash($hash);
        $systemService = $this->di['mod_service']('System');
        $systemService->setPendingMessage(__trans('Email address was confirmed'));
        $app->redirect('/');
    }

    public function get_client_logout(\Box_App $app)
    {
        $api = $this->di['api_client'];
        $api->profile_logout();
        $app->redirect('/');
    }

    public function get_client_page(\Box_App $app, $page)
    {
        $this->di['is_client_logged'];
        $template = 'mod_client_' . $page;

        return $app->render($template);
    }

    public function get_reset_password_confirm(\Box_App $app, $hash)
    {
        $api = $this->di['api_guest'];
        $this->di['events_manager']->fire(['event' => 'onBeforePasswordResetClient']);
        $data = [
            'hash' => $hash,
        ];
        $api->client_confirm_reset($data);
        $app->redirect('/login');
    }
}
