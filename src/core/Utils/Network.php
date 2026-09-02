<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Utils;

use FOSSBilling\Container\InjectionAwareInterface;
use FOSSBilling\System\Config;

final class Network implements InjectionAwareInterface
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

    public static function isValidHttpInterface(string $interface): bool
    {
        if (filter_var($interface, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        if (ctype_digit($interface)) {
            return false;
        }

        return preg_match('/^[a-zA-Z0-9._-]*[a-zA-Z._-][a-zA-Z0-9._-]*$/', $interface) === 1;
    }

    public static function getDefaultInterface(): string|int
    {
        $customInterface = Config::getProperty('custom_interface_ip', '');
        if (!empty($customInterface) && self::isValidHttpInterface($customInterface)) {
            return $customInterface;
        }

        $interface = Config::getProperty('interface_ip', '0');

        try {
            $knownInterfaces = gethostbynamel(gethostname());
        } catch (\Exception) {
            $knownInterfaces = [];
        }

        if ($interface !== '0' && in_array($interface, $knownInterfaces)) {
            return $interface;
        }

        return 0;
    }

    public function listHttpInterfaces(): array
    {
        $validatedIps = [];

        try {
            $ips = gethostbynamel(gethostname());
        } catch (\Exception) {
            $ips = [];
        }

        if (!$ips) {
            return [];
        }

        foreach ($ips as $ip) {
            try {
                $this->getExternalIP(true, $ip);
                $validatedIps[] = $ip;
            } catch (\Exception) {
            }
        }

        return $validatedIps;
    }

    public function getExternalIP(bool $throw = true, ?string $bind = null): ?string
    {
        $services = ['https://api64.ipify.org', 'https://checkip.global.api.aws', 'https://ifconfig.io/ip'];
        $httpClient = $this->di['http_client'];
        if ($bind !== null) {
            $httpClient = $httpClient->withOptions(['bindto' => $bind]);
        }

        foreach ($services as $service) {
            try {
                $response = $httpClient->request('GET', $service, [
                    'timeout' => 2,
                ]);

                $ip = filter_var($response->getContent(), FILTER_VALIDATE_IP);
                if ($ip) {
                    return $ip;
                }
            } catch (\Exception $e) {
                $this->di['logger']->error(
                    'Error fetching external IP from "{service}" ({exception_class}): {exception_message}',
                    [
                        'service' => $service,
                        'exception_class' => $e::class,
                        'exception_message' => $e->getMessage(),
                        'exception' => $e,
                    ]
                );
            }
        }

        if ($throw) {
            throw new \Exception('Unable to determine external IP address from any service.');
        }

        return null;
    }
}
