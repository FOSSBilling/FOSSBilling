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
