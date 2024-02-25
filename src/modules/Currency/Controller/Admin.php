<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Currency\Controller;

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

    public function register(\Box_App &$app)
    {
        $app->get('/currency/manage/:code', 'get_manage', ['code' => '[a-zA-Z]+'], static::class);
    }

    public function get_manage(\Box_App $app, $code)
    {
        $this->di['is_admin_logged'];
        $guest_api = $this->di['api_guest'];
        $currency = $guest_api->currency_get(['code' => $code]);

        return $app->render('mod_currency_manage', ['currency' => $currency]);
    }
}
