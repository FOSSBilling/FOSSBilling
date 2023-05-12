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

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Dotenv\Exception\PathException;

class Environment
{
    const ENV_KEY = 'APP_ENV';
    const PRODUCTION = 'prod';
    const DEVELOPMENT = 'dev';
    const TESTING = 'test';

    const POSSIBLE = [self::PRODUCTION, self::DEVELOPMENT, self::TESTING];
    const DEFAULT = self::PRODUCTION;

    /**
     * Get the current environment of the application.
     * The environment variable set in the operating system will have priority over the environment variable set in the .env file.
     * 
     * @return string
     */
    public static function getCurrentEnvironment(): string
    {
        return in_array(getenv(self::ENV_KEY), self::POSSIBLE) ? getenv(self::ENV_KEY) : self::DEFAULT;
    }

    /**
     * Check if the current environment is a production environment.
     * @return bool
     */
    public static function isProduction(): bool
    {
        return self::getCurrentEnvironment() === self::PRODUCTION;
    }

    /**
     * Check if the current environment is a development environment.
     * @return bool
     */
    public static function isDevelopment(): bool
    {
        return self::getCurrentEnvironment() === self::DEVELOPMENT;
    }

    /**
     * Check if the current environment is a testing environment.
     * @return bool
     */
    public static function isTesting(): bool
    {
        return self::getCurrentEnvironment() === self::TESTING;
    }

    /**
     * Check if the current environment is a CLI environment.
     * @return bool
     */
    public static function isCLI(): bool
    {
        return php_sapi_name() === 'cli';
    }

    /**
     * Load the .env file and the corresponding .env.local, .env.$env and .env.$env.local files if they exist.
     * The environment variables set in the operating system will have priority over the environment variables set in the .env file.
     * 
     * @see https://symfony.com/components/Dotenv
     * @return void
     */
    public static function loadDotEnv(): void
    {
        $dotenv = new Dotenv();
        $dotenv->usePutenv(true);

        try {
            $dotenv->loadEnv(PATH_ROOT . '/.env', self::ENV_KEY, self::DEFAULT);
        } catch (PathException $e) {
            // Do nothing
        }
    }
}