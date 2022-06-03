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

namespace Box\Mod\Index\Controller;

use Box\InjectionAwareInterface;

class Admin implements InjectionAwareInterface
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
        $app->get('', 'get_index', [], get_class($this));
        $app->get('/', 'get_index', [], get_class($this));
        $app->get('/index', 'get_index', [], get_class($this));
        $app->get('/index/', 'get_index', [], get_class($this));
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
