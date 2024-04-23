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

use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

class Config
{
    final public const MAX_RECURSION_LEVEL = 25;

    public static function getConfig(): array
    {
        return include PATH_CONFIG;
    }

    /**
     * Fetches a property from the config file using dot notation.
     *
     * @param string $property the property to pull from the database. Example: `debug_and_monitoring.report_errors`
     * @param mixed  $default  sets a default value to return if this property doesn't exist
     */
    public static function getProperty(string $property, mixed $default = null): mixed
    {
        $result = self::getConfig();
        foreach (explode('.', $property) as $segment) {
            if (array_key_exists($segment, $result)) {
                $result = $result[$segment];
            } else {
                // Key not found, handle the error or set a default value
                return $default;
            }
        }

        return $result;
    }

    /**
     * Updates or adds a new property to the config file using dot notation.
     *
     * @param string $property   the property to update. Example: `debug_and_monitoring.report_errors`
     * @param mixed  $newValue   the new value to set the property to
     * @param bool   $clearCache if the function should clear the FOSSBilling cache after updating the config file
     *
     * @throws Exception
     */
    public static function setProperty(string $property, mixed $newValue, bool $clearCache = true): void
    {
        $config = self::getConfig();

        $temp = &$config;
        $segments = explode('.', $property);

        foreach ($segments as $segment) {
            if (!array_key_exists($segment, $temp) || !is_array($temp[$segment])) {
                $temp[$segment] = [];
            }
            $temp = &$temp[$segment];
        }

        $temp = $newValue;

        self::setConfig($config, $clearCache);
    }

    /**
     * Updates the existing FOSSBilling configuration.
     * Will automatically create a backup of the existing config file and will pretty print the new one for easier legibility.
     *
     * @throws Exception
     */
    public static function setConfig(array $newConfig, bool $clearCache = true): void
    {
        $filesystem = new Filesystem();

        try {
            $filesystem->copy(PATH_CONFIG, substr(PATH_CONFIG, 0, -4) . '.old.php');
        } catch (FileNotFoundException|IOException) {
            throw new Exception('An error occurred when creating a backup of the configuration file.');
        }

        try {
            $filesystem->dumpFile(PATH_CONFIG, self::prettyPrintArrayToPHP($newConfig));
        } catch (IOException) {
            throw new Exception('An error occurred when writing the updated configuration file.');
        }

        if ($clearCache) {
            // If opcache is installed and enabled, invalidate the cache for the config file
            if (function_exists('opcache_invalidate') && function_exists('opcache_compile_file')) {
                @$filesystem->touch(PATH_CACHE);
                @opcache_invalidate(PATH_CONFIG, true);
                @opcache_compile_file(PATH_CONFIG);
            }

            try {
                $filesystem->remove(PATH_CACHE);
                $filesystem->mkdir(PATH_CACHE, 0755);
            } catch (\Exception) {
                // We shouldn't need to halt execution if there was an error when clearing the cache
            }
        }
    }

    /**
     * Pretty prints an array (our config file) to a PHP file.
     * This function automatically handles indentation & formats arrays with the cleaner `[]` representation rather than `array()`.
     *
     * @param array $array the array to save
     *
     * @return string the formatted code that can be written to a PHP file and then read again to fetch the array
     *
     * @throws Exception if the number of recursive iterations passes this class's MAX_RECURSION_LEVEL
     */
    private static function prettyPrintArrayToPHP(array $array): string
    {
        $output = '<?php' . PHP_EOL . 'return [';
        foreach ($array as $key => $value) {
            // Extra spacing between each "primary" key for slightly improved readability
            $output .= PHP_EOL . "    '" . $key . "'" . self::recursivelyIdentAndFormat($value);
        }

        return $output . '];';
    }

    /**
     * Handles the recursive looping and formatting over each array key.
     *
     * @throws Exception if the number of recursive iterations passes this class's MAX_RECURSION_LEVEL
     */
    private static function recursivelyIdentAndFormat(array|string|bool|float|int $value, $level = 1): string
    {
        if ($level > self::MAX_RECURSION_LEVEL) {
            throw new Exception('Too many iterations were performed while formatting the config file');
        }

        // Handle strings (Outputs `=> 'strict',`)
        if (is_string($value)) {
            return " => '" . $value . "'," . PHP_EOL;
        }

        // Handle numbers (Outputs `=> 7200,`)
        if (is_numeric($value)) {
            return ' => ' . $value . ',' . PHP_EOL;
        }

        // Handle bools (Outputs `=> true,`)
        if (is_bool($value)) {
            $boolAsWord = $value ? 'true' : 'false';

            return ' => ' . $boolAsWord . ',' . PHP_EOL;
        }

        // Generate an indentation equal to 4 spaces per level of recursion
        $indent = str_repeat(' ', $level * 4);
        $additionalIndent = str_repeat(' ', ($level + 1) * 4);

        // Handle arrays. Loop through each one & indent
        $result = ' => [' . PHP_EOL;
        foreach ($value as $key => $value) {
            // Special case for empty arrays to ensure they are printed on a single line
            if (is_array($value) && !$value) {
                $result .= $additionalIndent . "'" . $key . "'=> []," . PHP_EOL;

                continue;
            }
            $result .= $additionalIndent . "'" . $key . "'" . self::recursivelyIdentAndFormat($value, $level + 1);
        }

        return $result . ($indent . '],' . PHP_EOL);
    }
}
