<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */


class Box_Cookie implements \Box\InjectionAwareInterface
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

    public function delete($key)
    {
        unset($_COOKIE[$key]);
    }

    public function has($key)
    {
        return isset($_COOKIE[$key]);
    }

    public function get($key)
    {
        return isset($_COOKIE[$key]) ? $_COOKIE[$key] : null;
    }
    
    public function set($key, $value)
    {
        $_COOKIE[$key] = $value;
    }

    public function reset()
    {
        $_COOKIE = null;
    }
}