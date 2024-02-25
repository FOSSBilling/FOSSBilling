<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Index\Controller;

use FOSSBilling\InjectionAwareInterface;

class Admin implements InjectionAwareInterface
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
        $app->get('', 'get_index', [], static::class);
        $app->get('/', 'get_index', [], static::class);
        $app->get('/index', 'get_index', [], static::class);
        $app->get('/index/', 'get_index', [], static::class);
    }

    public function get_index(\Box_App $app)
    {
        if ($this->di['auth']->isAdminLoggedIn()) {
            return $app->render('mod_index_dashboard');
        } else {
            return $app->redirect('/staff/login');
        }
    }
}
