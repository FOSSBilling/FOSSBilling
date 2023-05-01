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

namespace Box\Mod\Currency\Controller;

class Admin implements \Box\InjectionAwareInterface
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
