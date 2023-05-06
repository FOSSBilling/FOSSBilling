<?php declare(strict_types=1);
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc. 
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

final class Version
{
    const VERSION = '0.0.1';

    /**
     * Compare the specified FOSSBilling version string $version
     * with the current \FOSSBilling\Version::VERSION of FOSSBilling.
     *
     * @param   string  $version  A version string (e.g. "0.7.1").
     * @return  integer           -1 if the $version is older,
     *                            0 if they are the same,
     *                            and +1 if $version is newer.
     *
     */
    public static function compareVersion(string $version): int
    {
        return version_compare(strtolower($version), strtolower(self::VERSION));
    }
}
