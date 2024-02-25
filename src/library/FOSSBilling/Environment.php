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

class Environment
{
    final public const ENV_KEY = 'APP_ENV';
    final public const PRODUCTION = 'prod';
    final public const DEVELOPMENT = 'dev';
    final public const TESTING = 'test';

    final public const POSSIBLE = [self::PRODUCTION, self::DEVELOPMENT, self::TESTING];
    final public const DEFAULT = self::PRODUCTION;

    /**
     * Get the current environment of the application.
     * The environment variable set in the operating system will have priority over the environment variable set in the .env file.
     */
    public static function getCurrentEnvironment(): string
    {
        return in_array(getenv(self::ENV_KEY), self::POSSIBLE) ? getenv(self::ENV_KEY) : self::DEFAULT;
    }

    /**
     * Check if the current environment is a production environment.
     */
    public static function isProduction(): bool
    {
        return self::getCurrentEnvironment() === self::PRODUCTION;
    }

    /**
     * Check if the current environment is a development environment.
     */
    public static function isDevelopment(): bool
    {
        return self::getCurrentEnvironment() === self::DEVELOPMENT;
    }

    /**
     * Check if the current environment is a testing environment.
     */
    public static function isTesting(): bool
    {
        return self::getCurrentEnvironment() === self::TESTING;
    }

    /**
     * Check if the current environment is a CLI environment.
     */
    public static function isCLI(): bool
    {
        return PHP_SAPI === 'cli' || defined('STDIN') || !http_response_code();
    }
}
