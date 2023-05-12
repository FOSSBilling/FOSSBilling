<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc. 
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use \FOSSBilling\Environment;

class Box_Session
{
    public function setRandomId()
    {
        $id = random_bytes(64);
        session_id($id);
    }


    public function __construct($handler, $securityMode = 'regular', $cookieLifespan = 7200, $secure = true)
    {
        if (!headers_sent()) {
            session_set_save_handler(
                array($handler, 'open'),
                array($handler, 'close'),
                array($handler, 'read'),
                array($handler, 'write'),
                array($handler, 'destroy'),
                array($handler, 'gc')
            );
        }
        if (Environment::isCLI()) {
            $currentCookieParams = session_get_cookie_params();
            $currentCookieParams["httponly"] = true;
            $currentCookieParams["lifetime"] = $cookieLifespan;
            $currentCookieParams["secure"] = $secure;

            if ($securityMode == 'strict') {
                session_set_cookie_params([
                    'lifetime' => $currentCookieParams["lifetime"],
                    'path' => $currentCookieParams["path"],
                    'domain' => $currentCookieParams["domain"],
                    'secure' => $currentCookieParams["secure"],
                    'httponly' => $currentCookieParams["httponly"],
                    'samesite' => 'Strict'
                ]);
                // TODO: Adjust the DB to support 64 character long session IDs
                // Currently adjusting it causing issues within this file: https://github.com/FOSSBilling/FOSSBilling/blob/main/src/library/PdoSessionHandler.php
                //$this->setRandomId();
            } else {
                session_set_cookie_params(
                    $currentCookieParams["lifetime"],
                    $currentCookieParams["path"],
                    $currentCookieParams["domain"],
                    $currentCookieParams["secure"],
                    $currentCookieParams["httponly"]
                );
            }

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
