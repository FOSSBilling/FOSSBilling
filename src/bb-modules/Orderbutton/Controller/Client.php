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

namespace Box\Mod\Orderbutton\Controller;

class Client implements \Box\InjectionAwareInterface
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
        $app->get('/orderbutton', 'get_index', [], get_class($this));
        $app->get('/orderbutton/js', 'get_js', [], get_class($this));
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
