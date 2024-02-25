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

use AntCMS\AntLoader;

class AutoLoader
{
    public AntLoader $AntLoader;

    public function getAntLoader(): AntLoader
    {
        return $this->AntLoader;
    }

    /**
     * Creates a new instance of AntLoader and then loads the classmap.
     * The instance is configured for filesystem caching within the FOSSBilling cache directory.
     *
     * @param bool $registerFOSSBillingDefaults (optional) Set to true if you want the autoloader to be shipped with the default paths and namespaces for FOSSBilling. Defaults to true.
     */
    public function __construct(bool $registerFOSSBillingDefaults = true)
    {
        $this->AntLoader = new AntLoader([
            'mode' => 'filesystem',
            'path' => PATH_CACHE . DIRECTORY_SEPARATOR . 'classMap.php',
        ]);

        if ($registerFOSSBillingDefaults) {
            $this->AntLoader->addNamespace('', PATH_LIBRARY, 'psr0');
            $this->AntLoader->addNamespace('Box\\Mod\\', PATH_MODS);
        }

        $this->AntLoader->checkClassMap();
    }

    /**
     * Registers the autoloader with PHP.
     *
     * @param bool $prepend (optional) Set to true to have this autoloader be placed before others currently registered, meaning it will be checked first. Defaults to true.
     */
    public function register(bool $prepend = true): void
    {
        $this->AntLoader->register($prepend);
    }
}
