<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Box_Authorization
{
    private $session;

    public function __construct(private Pimple\Container $di)
    {
        $this->session = $di['session'];
    }

    public function isClientLoggedIn()
    {
        return (bool) $this->session->get('client_id');
    }

    public function isAdminLoggedIn()
    {
        return (bool) $this->session->get('admin');
    }

    public function authorizeUser($user, $plainTextPassword)
    {
        $user = $this->passwordBackwardCompatibility($user, $plainTextPassword);
        if ($this->di['password']->verify($plainTextPassword, $user->pass)) {
            if ($this->di['password']->needsRehash($user->pass)) {
                $user->pass = $this->di['password']->hashIt($plainTextPassword);
                $user->updated_at = date('Y-m-d H:i:s');
                $this->di['db']->store($user);
            }

            return $user;
        }

        return null;
    }

    public function passwordBackwardCompatibility($user, $plainTextPassword)
    {
        if (sha1($plainTextPassword) == $user->pass) {
            $user->pass = $this->di['password']->hashIt($plainTextPassword);
            $this->di['db']->store($user);
        }

        return $user;
    }
}
