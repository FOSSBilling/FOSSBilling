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

namespace FOSSBilling;

final class Version
{
    public const VERSION = '0.0.1';
    public const PATCH = 0;
    public const MINOR = 1;
    public const MAJOR = 2;
    public const semverRegex = '^(?P<major>0|[1-9]\d*)\.(?P<minor>0|[1-9]\d*)\.(?P<patch>0|[1-9]\d*)(?:-(?P<prerelease>(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+(?P<buildmetadata>[0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$^';

    /**
     * Compare the specified FOSSBilling version string $version
     * with the current \FOSSBilling\Version::VERSION of FOSSBilling.
     *
     * @param string $version A version string (e.g. "0.7.1").
     *
     * @return int -1 if the $version is older,
     *             0 if they are the same,
     *             and +1 if $version is newer
     */
    public static function compareVersion(string $version): int
    {
        return version_compare(strtolower($version), strtolower(self::VERSION));
    }

    /**
     * Used to compare two different FOSSBilling versions to determine if updating between them is considered a major, minor, or a patch update.
     *
     * @param string      $new     The new FOSSBilling version to compare against
     * @param string|null $current (optional) Defaults to the current version, however you can override it if you wanted / needed
     *
     * @return int 0-2 to indicate the type of update
     */
    public static function getUpdateType(string $new, string $current = null): int
    {
        // Report patch as a dummy value as we can't properly compare version numbers when the current version is a preview build
        if (self::isPreviewVersion()) {
            return self::PATCH;
        }

        $current = explode('.', $current ?? self::VERSION);
        $new = explode('.', $new);

        if (intval($new[0]) === 0) {
            // We are still in pre-release status, so handle the version increments differently
            if ($new[1] !== $current[1]) {
                return self::MAJOR;
            } else {
                return self::MINOR;
            }
        } else {
            // We aren't in pre-production anymore, so treat it using normal semver practices
            if ($new[0] !== $current[0]) {
                return self::MAJOR;
            } elseif ($new[1] !== $current[1]) {
                return self::MINOR;
            } else {
                return self::PATCH;
            }
        }
    }

    public static function isPreviewVersion(string $version = Version::VERSION): bool
    {
        return ($version !== '0.0.1' && preg_match(self::semverRegex, $version, $matches) !== 0) ? false : true;
    }
}
