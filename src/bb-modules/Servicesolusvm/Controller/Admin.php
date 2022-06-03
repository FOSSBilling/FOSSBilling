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

namespace Box\Mod\Servicesolusvm\Controller;

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
        $app->get('/servicesolusvm', 'get_index', [], get_class($this));
        $app->get('/servicesolusvm/import/clients', 'get_import_clients', [], get_class($this));
        $app->get('/servicesolusvm/import/servers', 'get_import_servers', [], get_class($this));
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];
        $app->redirect('/extension/settings/servicesolusvm');
    }

    public function get_import_clients(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_servicesolusvm_import_clients');
    }

    public function get_import_servers(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_servicesolusvm_import_servers');
    }
}
