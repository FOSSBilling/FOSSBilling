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

namespace Box\Mod\Index\Controller;

use Box\InjectionAwareInterface;

class Admin implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    /**
     * @param \Pimple\Container $di
     * @return void
     */
    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    /**
     * @return \Pimple\Container|null
     */
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
