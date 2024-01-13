<?php

/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Server_Manager_Custom extends Server_Manager
{
    /**
     * Return server manager parameters.
     * @return string[]
     */
    public static function getForm(): array
    {
        return [
            'label' => 'Custom Server Manager',
        ];
    }

    /**
     * Method is called just after obejct contruct is complete.
     * Add required parameters checks here.
     */
    public function init()
    {

    }

    /**
     * Returns link to account management page
     *
     * @param Server_Account|null $account
     * @return string
     */
    public function getLoginUrl(Server_Account $account = null): string
    {
        return 'http://www.google.com?q=cpanel';
    }

    /**
     * Returns link to reseller account management
     * @param Server_Account|null $account
     * @return string
     */
    public function getResellerLoginUrl(Server_Account $account = null): string
    {
        return 'http://www.google.com?q=whm';
    }

    /**
     * This method is called to check if configuration is correct
     * and class can connect to server
     *
     * @return boolean
     */
    public function testConnection(): bool
    {
        return true;
    }

    /**
     * Methods retrieves information from server, assigns new values to
     * cloned Server_Account object and returns it.
     * @param Server_Account $account
     * @return Server_Account
     */
    public function synchronizeAccount(Server_Account $account): Server_Account
    {
        $this->getLog()->info('Synchronizing account with server ' . $account->getUsername());

        // @example - retrieve username from server and set it to cloned object
        // $new->setUsername('newusername');
        return clone $account;
    }

    /**
     * Create new account on server
     *
     * @param Server_Account $account
     * @return bool
     */
    public function createAccount(Server_Account $account): bool
    {
        if ($account->getReseller()) {
            $this->getLog()->info('Creating reseller hosting account');
        } else {
            $this->getLog()->info('Creating shared hosting account');
        }

        return true;
    }

    /**
     * Suspend account on server
     * @param Server_Account $account
     * @return bool
     */
    public function suspendAccount(Server_Account $account): bool
    {
        if ($account->getReseller()) {
            $this->getLog()->info('Suspending reseller hosting account');
        } else {
            $this->getLog()->info('Suspending shared hosting account');
        }

        return true;
    }

    /**
     * Unsuspend account on server
     * @param Server_Account $account
     * @return bool
     */
    public function unsuspendAccount(Server_Account $account): bool
    {
        if ($account->getReseller()) {
            $this->getLog()->info('Unsuspending reseller hosting account');
        } else {
            $this->getLog()->info('Unsuspending shared hosting account');
        }

        return true;
    }

    /**
     * Cancel account on server
     * @param Server_Account $account
     * @return bool
     */
    public function cancelAccount(Server_Account $account): bool
    {
        if ($account->getReseller()) {
            $this->getLog()->info('Canceling reseller hosting account');
        } else {
            $this->getLog()->info('Canceling shared hosting account');
        }

        return true;
    }

    /**
     * Change account package on server
     * @param Server_Account $account
     * @param Server_Package $package
     * @return bool
     */
    public function changeAccountPackage(Server_Account $account, Server_Package $package): bool
    {
        if ($account->getReseller()) {
            $this->getLog()->info('Updating reseller hosting account');
        } else {
            $this->getLog()->info('Updating shared hosting account');
        }

        $package->getName();
        $package->getQuota();
        $package->getBandwidth();
        $package->getMaxSubdomains();
        $package->getMaxParkedDomains();
        $package->getMaxDomains();
        $package->getMaxFtp();
        $package->getMaxSql();
        $package->getMaxPop();

        $package->getCustomValue('param_name');

        return true;
    }

    /**
     * Change account username on server.
     *
     * @param type $newUsername - new account username
     */
    public function changeAccountUsername(Server_Account $account, $newUsername): bool
    {
        if ($account->getReseller()) {
            $this->getLog()->info('Changing reseller hosting account username');
        } else {
            $this->getLog()->info('Changing shared hosting account username');
        }

        return true;
    }

    /**
     * Change account domain on server.
     *
     * @param Server_Account $account
     * @param string $newDomain - new domain name
     * @return bool
     */
    public function changeAccountDomain(Server_Account $account, string $newDomain): bool
    {
        if ($account->getReseller()) {
            $this->getLog()->info('Changing reseller hosting account domain');
        } else {
            $this->getLog()->info('Changing shared hosting account domain');
        }

        return true;
    }

    /**
     * Change account password on server.
     *
     * @param string $newPassword - new password
     */
    public function changeAccountPassword(Server_Account $account, string $newPassword): bool
    {
        if ($account->getReseller()) {
            $this->getLog()->info('Changing reseller hosting account password');
        } else {
            $this->getLog()->info('Changing shared hosting account password');
        }

        return true;
    }

    /**
     * Change account IP on server.
     *
     * @param Server_Account $account
     * @param string $newIp - account IP
     * @return bool
     */
    public function changeAccountIp(Server_Account $account, string $newIp): bool
    {
        if ($account->getReseller()) {
            $this->getLog()->info('Changing reseller hosting account ip');
        } else {
            $this->getLog()->info('Changing shared hosting account ip');
        }

        return true;
    }
}
