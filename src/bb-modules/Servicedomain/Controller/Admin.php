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

namespace Box\Mod\Servicedomain\Controller;

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

    public function fetchNavigation()
    {
        return [
            'subpages' => [
                [
                    'location' => 'system',
                    'index' => 150,
                    'label' => 'Domain registration',
                    'uri' => $this->di['url']->adminLink('servicedomain'),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Box_App &$app)
    {
        $app->get('/servicedomain', 'get_index', null, get_class($this));
        $app->get('/servicedomain/tld/:tld', 'get_tld', ['tld' => '[/.a-z0-9]+'], get_class($this));
        $app->get('/servicedomain/registrar/:id', 'get_registrar', ['id' => '[0-9]+'], get_class($this));
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_servicedomain_index');
    }

    public function get_tld(\Box_App $app, $tld)
    {
        $api = $this->di['api_admin'];
        $m = $api->servicedomain_tld_get(['tld' => $tld]);

        return $app->render('mod_servicedomain_tld', ['tld' => $m]);
    }

    public function get_registrar(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $registrar = $api->servicedomain_registrar_get(['id' => $id]);

        return $app->render('mod_servicedomain_registrar', ['registrar' => $registrar]);
    }
}
