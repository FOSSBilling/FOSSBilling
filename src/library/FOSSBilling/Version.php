<?php declare(strict_types=1);
/**
 * FOSSBilling.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * Copyright FOSSBilling 2022
 * This software may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE.
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
