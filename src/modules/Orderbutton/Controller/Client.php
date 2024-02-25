<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Orderbutton\Controller;

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
        $app->get('/orderbutton', 'get_index', [], static::class);
        $app->get('/orderbutton/js', 'get_js', [], static::class);
    }

    public function get_index(\Box_App $app)
    {
        return $app->render('mod_orderbutton_index');
    }

    public function get_js(\Box_App $app)
    {
        header('Content-Type: application/javascript');

        return $app->render('mod_orderbutton_js');
    }
}
