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

/**
 * This file connects FOSSBilling client area interface and API
 * Class does not extend any other class.
 */

namespace Box\Mod\Example\Controller;

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

    /**
     * Methods maps client areas urls to corresponding methods
     * Always use your module prefix to avoid conflicts with other modules
     * in future.
     *
     * @param \Box_App $app - returned by reference
     */
    public function register(\Box_App &$app)
    {
        $app->get('/example', 'get_index', [], get_class($this));
        $app->get('/example/protected', 'get_protected', [], get_class($this));
    }

    public function get_index(\Box_App $app)
    {
        return $app->render('mod_example_index');
    }

    public function get_protected(\Box_App $app)
    {
        // call $this->di['is_client_logged'] method to validate if client is logged in
        $this->di['is_client_logged'];

        return $app->render('mod_example_index', ['show_protected' => true]);
    }
}
