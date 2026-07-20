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
        if (!$client || $client->getStatus() !== Client::ACTIVE) {
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
        if (!$adminModel || $adminModel->getStatus() !== Admin::STATUS_ACTIVE || $adminModel->isCron()) {
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

        if ($this->di['password']->verify($plainTextPassword, $user->pass)) {
            if ($this->di['password']->needsRehash($user->pass)) {
                $user->pass = $this->di['password']->hashIt($plainTextPassword);
                $user->updated_at = date('Y-m-d H:i:s');
                $this->di['em']->persist($user);
                $this->di['em']->flush();
            }

            return $user;
        }

        return null;
    }
}
