<?php

declare(strict_types=1);
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use GeoIp2\Database\Reader;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpClient\HttpClient;

final class IPDatabase
{
    public const IPv4 = 4;
    public const IPv6 = 6;
    public const IPvUnknown = 0;

    /**
     * FOSSBilling uses databases that are licenced under the public domain (CC0 and PDDL).
     * This is done to ensure that we have good enough data out of the box that's updated regularly and that can be used without a concern of licencing. 
     * Below are the download URLs for both the IPv4 and IPv6 DBs we utilize.
     * Both include ASN and Country information. 
     */
    public const DefaultIPv4DB = 'https://github.com/HostByBelle/IP-Geolocation-DB/releases/latest/download/cc0-pddl-country-asn-v4-variant-1.mmdb';
    public const DefaultIPv6DB = 'https://github.com/HostByBelle/IP-Geolocation-DB/releases/latest/download/cc0-pddl-country-asn-v6-variant-1.mmdb';

    /**
     * Creates a new instance of the GeoIP2 reader for either a fixed address type or optionally will autodetect the correct type 
     * 
     * @param int $type The IP address type. Represented as `4`, `6`, or `0`. A zero should be passed to have the type automatically detected by providing the IP address used.
     * @param null|string $ip (optional) The IP address to detect the type of.
     * @throws Exception If the IP address or type given is invalid.
     */
    public static function getReader(int $type = self::IPvUnknown, ?string $ip = null): Reader
    {
        if ($type === self::IPvUnknown) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $type = self::IPv4;
            } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $type = self::IPv6;
            } else {
                throw new Exception("The provided IP address is invalid");
            }
        }

        [$IPv4, $IPv6] = self::getPaths();
        error_log("$IPv4, $IPv6");

        switch ($type) {
            case self::IPv4:
                return new Reader($IPv4);
                break;
            case self::IPv6:
                return new Reader($IPv6);
                break;
            default:
                throw new Exception("An invalid IP address type was given");
        }
    }

    /**
     * Updates the default databases that are included inside of FOSSBilling.
     * If one of the databases have been replaced with an alternative, it won't be updated.
     * 
     * This function will only ever update one DB at a time as both an effort to spread out server load and to lessen any hangs that the fallback cron method may cause.
     */
    public static function update()
    {
        [$IPv4, $IPv6] = self::getPaths(true);
        $IPv4Age = time() - filemtime($IPv4);
        $IPv6Age = time() - filemtime($IPv6);

        if (!defined('IPv4DB') && $IPv4Age >= 86400) {
            self::performUpdate($IPv4, self::DefaultIPv4DB);
            return;
        }

        if (!defined('IPv6DB') && $IPv6Age >= 86400) {
            self::performUpdate($IPv6, self::DefaultIPv6DB);
            return;
        }
    }

    /**
     * Returns an array containing what type of data is included in a given IP DB.
     * Relies on the user to correctly set this information for DBs they provide on their own.
     * 
     * @return array 
     */
    public static function whatIsIncluded(): array
    {
        if (defined('includedIPv4')) {
            $IPv4 = includedIPv4;
        } else {
            $IPv4 = ['country', 'asn'];
        }

        if (defined('includedIPv6')) {
            $IPv6 = includedIPv6;
        } else {
            $IPv6 = ['country', 'asn'];
        }

        return [$IPv4, $IPv6];
    }

    /**
     * Returns the correct paths for the actively used database.
     * 
     * @param bool $defaults Set to true to only have the default DB paths returned. 
     */
    public static function getPaths(bool $defaults = false): array
    {
        if (defined('IPv4DB') && !$defaults) {
            $IPv4 = Path::canonicalize(IPv4DB);
        } else {
            //$IPv4 = Path::normalize(PATH_LIBRARY . '/IPv4.mmdb');
            $IPv4 = Path::normalize(PATH_LIBRARY . '/GeoLite2-Country.mmdb');
        }

        if (defined('IPv6DB')  && !$defaults) {
            $IPv6 = Path::canonicalize(IPv6DB);;
        } else {
            $IPv6 = Path::normalize(PATH_LIBRARY . '/IPv6.mmdb');
        }

        return [$IPv4, $IPv6];
    }

    private static function performUpdate(string $path, string $url)
    {
        try {
            $httpClient = HttpClient::create();
            $response = $httpClient->request('GET', $url);
            if ($response->getStatusCode() === 200) {
                file_put_contents($path, $response->getContent());
            } else {
                error_log("Got a " . $response->getStatusCode() . ' status code when attempting to download ' . $url);
            }
        } catch (\Exception $e) {
            error_log("There was an error while updating one of the IP address databases: " . $e->getMessage());
        }
    }
}
