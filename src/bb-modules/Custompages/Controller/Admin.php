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

namespace Box\Mod\Custompages\Controller;

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
                    'location' => 'extensions',
                    'label' => 'Custom Pages',
                    'index' => 2000,
                    'uri' => $this->di['url']->adminLink('custompages'),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Box_App &$app)
    {
        $app->get('/custompages', 'get_index', [], get_class($this));
        $app->get('/custompages/:id', 'get_page', ['id' => '[0-9]+'], get_class($this));
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_custompages_index');
    }

    public function get_page(\Box_App $app, $id)
    {
        return $app->render('mod_custompages_page', ['page_id' => $id]);
    }
}
