<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */


final class Box_Version
{
    const VERSION = '0.0.1';

    /**
     * Compare the specified BoxBilling version string $version
     * with the current Box_Version::VERSION of BoxBilling.
     *
     * @param  string  $version  A version string (e.g. "0.7.1").
     * @return integer           -1 if the $version is older,
     *                           0 if they are the same,
     *                           and +1 if $version is newer.
     *
     */
    public static function compareVersion($version)
    {
        return version_compare(strtolower($version), strtolower(self::VERSION));
    }
}
