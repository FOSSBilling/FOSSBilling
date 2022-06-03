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

namespace Box\Mod\Currency\Controller;

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

    public function register(\Box_App &$app)
    {
        $app->get('/currency/manage/:code', 'get_manage', ['code' => '[a-zA-Z]+'], get_class($this));
    }

    public function get_manage(\Box_App $app, $code)
    {
        $this->di['is_admin_logged'];
        $guest_api = $this->di['api_guest'];
        $currency = $guest_api->currency_get(['code' => $code]);

        return $app->render('mod_currency_manage', ['currency' => $currency]);
    }
}
