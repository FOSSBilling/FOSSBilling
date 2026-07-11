<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Security\Api;

use FOSSBilling\Validation\Api\RequiredParams;

class Admin extends \FOSSBilling\Api\AbstractApi
{
    #[RequiredParams(['ip' => 'You must specify an IP address to lookup.'])]
    public function ip_lookup(array $data): array
    {
        $this->checkPermissions('security', 'view');

        return $this->getService()->lookupIP($data['ip']);
    }

    public function list_checks(array $data): array
    {
        $this->checkPermissions('security', 'view');

        $result = [];
        $checkInterfaces = $this->getService()->getAllChecks();
        foreach ($checkInterfaces as $id => $interface) {
            $result[] = [
                'id' => $id,
                'name' => $interface->getName(),
                'description' => $interface->getDescription(),
            ];
        }

        return $result;
    }

    #[RequiredParams(['id' => 'You must specify a check ID to run.'])]
    public function run_check(array $data): array
    {
        $this->checkPermissions('security', 'run_checks');

        return $this->getService()->runCheck($data['id']);
    }

    public function run_checks(array $data): array
    {
        $this->checkPermissions('security', 'run_checks');

        return $this->getService()->runAllChecks();
    }

    public function rate_limit_status(array $data): array
    {
        $this->getDi()['mod_service']('Staff')->checkPermissionsAndThrowException('security', 'view');

        return $this->getService()->getRateLimitStatus();
    }

    public function rate_limit_get_list(array $data): array
    {
        $this->getDi()['mod_service']('Staff')->checkPermissionsAndThrowException('security', 'view');

        return $this->getService()->getRateLimitList($data['ip'] ?? null, $data['search'] ?? null);
    }

    #[RequiredParams(['ip' => 'You must specify an IP address to reset.'])]
    public function rate_limit_reset_ip(array $data): array
    {
        $this->getDi()['mod_service']('Staff')->checkPermissionsAndThrowException('security', 'manage_rate_limits');

        return $this->getService()->resetRateLimitIp($data['ip'], $data['policy'] ?? null);
    }

    public function rate_limit_reset_all(array $data): array
    {
        $this->getDi()['mod_service']('Staff')->checkPermissionsAndThrowException('security', 'manage_rate_limits');

        return $this->getService()->resetAllRateLimits();
    }
}
