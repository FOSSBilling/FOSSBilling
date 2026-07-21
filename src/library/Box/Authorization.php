<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
use Box\Mod\Client\Entity\Client;
use Box\Mod\Staff\Entity\Admin;

class Box_Authorization
{
    private $session;

    public function __construct(private Pimple\Container $di)
    {
        $this->session = $di['session'];
    }

    public function isClientLoggedIn(): bool
    {
        $clientId = $this->session->get('client_id');
        if (!$clientId) {
            return false;
        }

        $client = $this->di['em']->getRepository(Client::class)->find($clientId);
        if (!$client || $client->getStatus() !== 'active') {
            $this->session->delete('client_id');

            return false;
        }

        return true;
    }

    public function isAdminLoggedIn(): bool
    {
        $admin = $this->session->get('admin');
        if (!$admin) {
            return false;
        }

        $adminModel = $this->di['em']->getRepository(Admin::class)->find($admin['id']);
        if (!$adminModel || $adminModel->getStatus() !== 'active' || $adminModel->isCron()) {
            $this->session->delete('admin');

            return false;
        }

        return true;
    }

    public function authorizeUser(?object $user, string $plainTextPassword): ?object
    {
        if ($user === null) {
            $this->di['password']->dummyVerify($plainTextPassword);

            return null;
        }

        $pass = $user instanceof Client ? $user->getPass() : ($user instanceof Admin ? $user->getPass() : throw new \RuntimeException('Unknown user type'));
        if ($this->di['password']->verify($plainTextPassword, $pass)) {
            if ($this->di['password']->needsRehash($pass)) {
                $newPass = $this->di['password']->hashIt($plainTextPassword);
                if ($user instanceof Client) {
                    $user->setPass($newPass);
                } elseif ($user instanceof Admin) {
                    $user->setPass($newPass);
                }
                $this->di['em']->persist($user);
                $this->di['em']->flush();
            }

            return $user;
        }

        return null;
    }
}
