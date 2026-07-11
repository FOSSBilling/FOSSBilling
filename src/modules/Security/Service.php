<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Security;

use FOSSBilling\GeoIP\IncompleteRecord;
use FOSSBilling\GeoIP\Reader;
use FOSSBilling\InformationException;
use FOSSBilling\Interfaces\SecurityCheckInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

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
            'view' => [
                'type' => 'bool',
                'display_name' => __trans('View security information'),
                'description' => __trans('Allows the staff member to view security checks and perform IP lookups.'),
            ],
            'run_checks' => [
                'type' => 'bool',
                'display_name' => __trans('Run security checks'),
                'description' => __trans('Allows the staff member to run security checks on the FOSSBilling installation.'),
            ],
            'manage_rate_limits' => [
                'type' => 'bool',
                'display_name' => __trans('Manage rate limits'),
                'description' => __trans('Allows the staff member to review and reset IP-based rate limiter counters.'),
            ],
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
        $finder = new Finder();

        $finder->files()->in(Path::join(__DIR__, 'Checks'))->name('*.php');
        foreach ($finder as $file) {
            $checkID = $file->getFilenameWithoutExtension();
            $className = "Box\Mod\Security\Checks\\{$checkID}";
            if (!class_exists($className)) {
                continue;
            }

            $newCheck = new $className();
            if (method_exists($newCheck, 'setDi')) {
                $newCheck->setDi($this->di);
            }
            if ($newCheck instanceof SecurityCheckInterface) {
                $checks[$checkID] = $newCheck;
            } else {
                error_log("{$className} does not implement the SecurityCheckInterface interface.");
            }
        }

        return $checks;
    }

    /**
     * Runs all available security checks.
     */
    public function runAllChecks(): array
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('security', 'run_checks');

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
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('security', 'run_checks');

        $class = "Box\Mod\Security\Checks\\$checkID";
        if (!class_exists($class)) {
            throw new InformationException('The check :checkName: does not exist.', [':checkName:' => $checkID]);
        }

        $check = new $class();
        if (method_exists($check, 'setDi')) {
            $check->setDi($this->di);
        }
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
    public function lookupIP(string $ip): array
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException('The provided input was not a valid IP address.');
        }

        try {
            $countryInfo = $this->di['geoip']->country($ip);
        } catch (IncompleteRecord) {
            $countryInfo = [];
        }

        try {
            $asnPath = Reader::getAsnDatabase();
            $asnReader = new Reader($asnPath);
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

    public function getRateLimitStatus(): array
    {
        return [
            'enabled' => $this->di['rate_limiter']->isEnabled(),
        ];
    }

    public function getRateLimitList(?string $ip = null, ?string $search = null): array
    {
        $counters = $this->di['rate_limiter']->listIpCounters($ip);

        if ($search === null || $search === '') {
            return $counters;
        }

        $search = strtolower($search);

        return array_values(array_filter(
            $counters,
            static fn (array $counter): bool => str_contains(strtolower((string) $counter['ip']), $search) || str_contains(strtolower((string) $counter['policy']), $search),
        ));
    }

    public function resetRateLimitIp(string $ip, ?string $policy = null): array
    {
        $removed = $this->di['rate_limiter']->resetIp($ip, $policy);

        $this->di['logger']->setChannel('security')->info('Rate limiter counters reset for IP %s%s', $ip, $policy ? " and policy {$policy}" : '');

        return [
            'ip' => $ip,
            'policy' => $policy,
            'removed' => $removed,
        ];
    }

    public function resetAllRateLimits(): array
    {
        $cleared = $this->di['rate_limiter']->resetAll();

        $this->di['logger']->setChannel('security')->warning('All rate limiter counters were reset');

        return [
            'cleared' => $cleared,
        ];
    }
}
