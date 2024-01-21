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
    /**
     * FOSSBilling uses databases that are licensed under the public domain (CC0 and PDDL).
     * This is done to ensure that we have good enough data out of the box that's updated regularly and that can be used without a concern of licensing. 
     * @see https://github.com/HostByBelle/IP-Geolocation-DB for the database sources
     */
    public const defaultSource = 'https://github.com/HostByBelle/IP-Geolocation-DB/releases/latest/download/cc0-pddl-country-asn-both-variant-1.mmdb';
    public const defaultDBPath = PATH_LIBRARY . DIRECTORY_SEPARATOR . 'ipDB.mmdb';
    public const customDBDownloadedPath = PATH_LIBRARY . DIRECTORY_SEPARATOR . 'customIpDB.mmdb';

    /**
     * Creates a new instance of the GeoIP2 reader, selecting either our default DB or the custom one as set by the system admin
     */
    public static function getReader(): Reader
    {
        return new Reader(self::getPath());
    }

    /**
     * Updates the currently in use DB
     */
    public static function update()
    {
        if (!empty($custom_path)) {
            return;
        }

        $localDb = self::getPath(true);
        $dbAge = time() - filemtime($localDb);

        if ($dbAge >= 86400) {
            self::performUpdate($localDb, self::getDownloadUrl());
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
        $custom_path = Config::getProperty('ip_database.custom_path', '');
        $custom_url = Config::getProperty('ip_database.custom_url', '');

        if (!empty($custom_path) && !empty($custom_url)) {
            return ['country', 'asn'];
        } else {
            return Config::getProperty('ip_database.included_data', []);
        }
    }

    /**
     * Returns the correct paths for the actively used database.
     * 
     * @param bool $defaults Set to true to only have the default DB paths returned. 
     */
    public static function getPath(bool $default = false, bool $skipUpdate = false): string
    {
        $custom_path = Config::getProperty('ip_database.custom_path', '');
        $custom_url = Config::getProperty('ip_database.custom_url', '');

        if (!empty($custom_url) && empty($custom_path) && !$default) {
            // First, update the remote DB if it doesn't exist
            if (file_exists(self::customDBDownloadedPath) && !$skipUpdate) {
                self::update();
            }
            return self::customDBDownloadedPath;
        }

        if (!empty($custom_path) && !$default) {
            return Path::canonicalize($custom_path);
        } else {
            return self::defaultDBPath;
        }
    }
    public function getDownloadUrl(bool $default = false)
    {
        $custom_url = Config::getProperty('ip_database.custom_url', '');
        if (!$default && !empty($custom_url)) {
            return $custom_url;
        } else {
            return self::defaultSource;
        }
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
            error_log("There was an error while updating the IP address database: " . $e->getMessage());
        }
    }
}
