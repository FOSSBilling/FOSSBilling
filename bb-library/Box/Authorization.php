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


class Box_Authorization
{
    private $di = null;
    private $session = null;

    public function __construct(Box_Di $di)
    {
        $this->di = $di;
        $this->session = $di['session'];
    }

    public function isClientLoggedIn()
    {
        return (bool)($this->session->get('client_id'));
    }

    public function isAdminLoggedIn()
    {
        return (bool)($this->session->get('admin'));
    }

    public function authorizeUser($user, $plainTextPassword)
    {
        $user = $this->passwordBackwardCompatibility($user, $plainTextPassword);
        if ($this->di['password']->verify($plainTextPassword, $user->pass)){
            if ($this->di['password']->needsRehash($user->pass)){
                $user->pass = $this->di['password']->hashIt($plainTextPassword);
                $this->di['db']->store($user);
            }
            return $user;
        }
        return null;
    }

    public function passwordBackwardCompatibility($user, $plainTextPassword)
    {
        if (sha1($plainTextPassword) == $user->pass){
            $user->pass = $this->di['password']->hashIt($plainTextPassword);
            $this->di['db']->store($user);
        }
        return $user;
    }

}
