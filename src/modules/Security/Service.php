<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Security;

use FOSSBilling\GeoIP\IncompleteRecord;
use FOSSBilling\InformationException;
use FOSSBilling\Interfaces\SecurityCheckInterface;

class Service
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getModulePermissions(): array
    {
        return [
            'can_always_access' => true,
            'hide_permissions' => true,
        ];
    }

    /**
     * Returns a list of all security checks.
     *
     * @return SecurityCheckInterface[]
     */
    public function getAllChecks(): array
    {
        $checks = [];
        foreach (scandir(__DIR__ . DIRECTORY_SEPARATOR . 'Checks') as $check) {
            $checkID = substr($check, 0, -4) ?: '';
            $className = "Box\Mod\Security\Checks\\$checkID";
            if (!class_exists($className)) {
                continue;
            }

            $newCheck = new $className();
            if ($newCheck instanceof SecurityCheckInterface) {
                $checks[$checkID] = $newCheck;
            } else {
                error_log("$className does not implement the SecurityCheckInterface interface.");
            }
        }

        return $checks;
    }

    /**
     * Runs all available security checks.
     */
    public function runAllChecks(): array
    {
        $results = [];
        $checks = $this->getAllChecks();
        foreach ($checks as $id => $check) {
            $checkResult = $check->performCheck();

            $result = json_decode(json_encode($checkResult), true);
            $result['id'] = $id;
            $result['name'] = $check->getName();

            $results[] = $result;
        }

        return $results;
    }

    /**
     * Runs a given check.
     *
     * @throws InformationException If the check does not exist or if it does not implement the correct interface
     */
    public function runCheck(string $checkID): array
    {
        $class = "Box\Mod\Security\Checks\\$checkID";
        if (!class_exists($class)) {
            throw new InformationException('The check :checkName: does not exist.', [':checkName:' => $checkID]);
        }

        $check = new $class();
        if (!$check instanceof SecurityCheckInterface) {
            throw new InformationException('The check :checkName: does not seem to be a valid check.', [':checkName:' => $checkID]);
        }

        $result = json_decode(json_encode($check->performCheck()), true);
        $result['id'] = $checkID;

        return $result;
    }

    /**
     * Looks up an IP address.
     *
     * @return array{ip: array{address: string, type: string}, country: mixed, asn: mixed}
     *
     * @throws \InvalidArgumentException
     */
    public function lookupIP(string $ip)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException('The provided input was not a valid IP address');
        }

        try {
            $countryInfo = $this->di['geoip']->country($ip);
        } catch (IncompleteRecord) {
            $countryInfo = [];
        }

        try {
            $asnReader = new \FOSSBilling\GeoIP\Reader(PATH_LIBRARY . '/FOSSBilling/GeoIP/Databases/PDDL-ASN.mmdb');
            $asnInfo = $asnReader->asn($ip);
        } catch (IncompleteRecord) {
            $asnInfo = [];
        }

        return [
            'ip' => [
                'address' => $ip,
                'type' => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 'IPv4' : 'IPv6',
            ],
            'country' => json_decode(json_encode($countryInfo), true),
            'asn' => json_decode(json_encode($asnInfo), true),
        ];
    }
}
