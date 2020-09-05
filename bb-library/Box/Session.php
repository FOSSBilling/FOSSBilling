<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */


class Box_Session
{
    public function __construct($handler)
    {
        session_set_save_handler(
            array($handler, 'open'),
            array($handler, 'close'),
            array($handler, 'read'),
            array($handler, 'write'),
            array($handler, 'destroy'),
            array($handler, 'gc')
        );
        if(php_sapi_name() !== 'cli'){
            $currentCookieParams = session_get_cookie_params();
            $currentCookieParams["httponly"] = true;

            session_set_cookie_params(
                $currentCookieParams["lifetime"],
                $currentCookieParams["path"],
                $currentCookieParams["domain"],
                $currentCookieParams["secure"],
                $currentCookieParams["httponly"]
            );
            session_start();
        }
    }

    public function getId()
    {
        return session_id();
    }

    public function delete($key)
    {
        unset($_SESSION[$key]);
    }

    public function get($key)
    {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
    }
    
    public function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    public function destroy()
    {
        session_destroy();
    }
}