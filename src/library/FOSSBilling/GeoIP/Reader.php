<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\GeoIP;

use FOSSBilling\Config;
use MaxMind\Db\Reader as MaxMindReader;
use MaxMind\Db\Reader\InvalidDatabaseException;
use Pimple\Container;
use PrinsFrank\Standards\Language\LanguageAlpha2;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class Reader
{
    protected ?Container $di = null;

    private readonly MaxMindReader $reader;
    private readonly LanguageAlpha2 $language;

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
    {
        return $this->di;
    }

    /**
     * Returns the path to the system's `country` database.
     */
    public static function getCountryDatabase(): string
    {
        return Path::join(PATH_LIBRARY, 'FOSSBilling', 'GeoIP', 'Databases', 'CC0-Country.mmdb');
    }

    /**
     * Returns the path to the system's `asn` database.
     */
    public static function getAsnDatabase(): string
    {
        return Path::join(PATH_LIBRARY, 'FOSSBilling', 'GeoIP', 'Databases', 'PDDL-ASN.mmdb');
    }

    /**
     * Handles updating the built-in, default databases.
     * The databases will only be updated if the files do not exist, or if they are over 7 days old.
     * This will only update 1 database per call as it's intended to be run in the background and have the work spread out VS all at once.
     *
     * @throws IOException
     */
    public function updateDefaultDatabases(): bool
    {
        $databases = [
            self::getCountryDatabase() => 'https://github.com/HostByBelle/IP-Geolocation-DB/releases/latest/download/cc0-both-country.mmdb',
            self::getAsnDatabase() => 'https://github.com/HostByBelle/IP-Geolocation-DB/releases/latest/download/pddl-asn-both.mmdb',
        ];

        foreach ($databases as $path => $url) {
            if (self::shouldUpdate($path)) {
                try {
                    $this->downloadDb($path, $url);

                    return true;
                } catch (\Exception $e) {
                    error_log("There was an error while updating the IP address database: {$e->getMessage()}.");
                }
            }
        }

        return false;
    }

    /**
     * Constructs a new GeoIP reader instance.
     *
     * @param string|null $database (Optional) a path to the database to load> Will default to the system's country database
     * @param string|null $locale   (Optional) the locale to use for country names. Defaults to the configured default locale.
     *
     * @throws \InvalidArgumentException for invalid database path or unknown arguments
     * @throws InvalidDatabaseException  if the database is invalid or there is an error reading from it
     */
    public function __construct(?string $database = null, ?string $locale = null)
    {
        $database ??= self::getCountryDatabase();

        $this->reader = new MaxMindReader($database);

        if ($locale === null) {
            $locale = Config::getProperty('i18n.locale', 'en_US');
        }

        $original = $locale;
        if (str_contains((string) $locale, '_')) {
            $locale = explode('_', (string) $locale)[0];
        }
        $this->language = strlen((string) $locale) === 2 ? LanguageAlpha2::from($locale) : throw new \ValueError("No matching Alpha2 language found for {$original}");
    }

    /**
     * Retrieves the record for the IP address.
     *
     * @param string $ipAddress the IP address to look up
     *
     * @return array the record for the IP address
     *
     * @throws \InvalidArgumentException if something other than a single IP address is passed to the method
     * @throws InvalidDatabaseException  if the database is invalid or there is an error reading from it
     */
    public function get(string $ipAddress)
    {
        return $this->reader->get($ipAddress) ?? [];
    }

    /**
     * Retrieves the country for an IP address.
     *
     * @param string $ipAddress the IP address to look up
     *
     * @return Country A country object for the associated record
     *
     * @throws IncompleteRecord          if the record for the given IP address does not contain the needed information
     * @throws \InvalidArgumentException if something other than a single IP address is passed to the method
     * @throws InvalidDatabaseException  if the database is invalid or there is an error reading from it
     */
    public function country(string $ipAddress): Country
    {
        $record = $this->get($ipAddress);

        return new Country($record['country'] ?? [], $this->language);
    }

    /**
     * Returns the ASN information for an IP address.
     *
     * @param string $ipAddress the IP address to look up
     *
     * @return ASN the ASN object for the associated record
     *
     * @throws IncompleteRecord          if the record for the given IP address does not contain the needed information
     * @throws \InvalidArgumentException if something other than a single IP address is passed to the method
     * @throws InvalidDatabaseException  if the database is invalid or there is an error reading from it
     */
    public function asn(string $ipAddress): ASN
    {
        $record = $this->get($ipAddress);

        return new ASN($record);
    }

    /**
     * Checks if a database should be updated.
     *
     * @param string $path   The path to the database
     * @param int    $maxAge The maximum age in seconds. The default is 7 days.
     *
     * @throws IOException
     */
    private static function shouldUpdate(string $path, int $maxAge = 604800): bool
    {
        $filesystem = new Filesystem();
        if (!$filesystem->exists($path)) {
            return true;
        }

        $dbAge = time() - filemtime($path);

        return $dbAge >= $maxAge;
    }

    /**
     * Downloads a database file and saves it to the provided location.
     *
     * @throws TransportExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws ServerExceptionInterface
     */
    private function downloadDb(string $path, string $url): void
    {
        $httpClient = $this->di['http_client'];
        $response = $httpClient->request('GET', $url);

        if ($response->getStatusCode() === 200) {
            $this->di['filesystem']->dumpFile($path, $response->getContent());
        } else {
            throw new \Exception("Got a {$response->getStatusCode()} status code when attempting to download {$url}.");
        }
    }
}
