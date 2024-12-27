<?php

declare(strict_types=1);
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\GeoIP;

use FOSSBilling\i18n;
use FOSSBilling\StandardsHelper;
use MaxMind\Db\Reader as MaxMindReader;
use MaxMind\Db\Reader\InvalidDatabaseException;
use PrinsFrank\Standards\Language\LanguageAlpha2;

class Reader
{
    private readonly MaxMindReader $reader;
    private readonly LanguageAlpha2 $language;

    /**
     * Constructs a new GeoIP reader instance.
     *
     * @param string $database a path to the database to load
     * @param string $locale   (Optional) the locale to use for country names. Defaults to the locale being used by FOSSBilling.
     *
     * @throws \InvalidArgumentException for invalid database path or unknown arguments
     * @throws InvalidDatabaseException  if the database is invalid or there is an error reading from it
     */
    public function __construct(string $database, ?string $locale = null)
    {
        $this->reader = new MaxMindReader($database);

        if ($locale === null) {
            $locale = i18n::getActiveLocale();
        }

        $this->language = StandardsHelper::getLanguageObject(false, $locale);
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
}
