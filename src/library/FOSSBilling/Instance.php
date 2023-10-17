<?php

declare(strict_types=1);
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use Ramsey\Uuid\Uuid;

class Instance
{
    /**
     * Returns a RFC4122 version 5 UUID for the current FOSSBilling instance.
     * Must be called after the config is loaded and `BB_URL` is defined.
     * 
     * @return string 
     */
    public static function getInstanceID(): string
    {
        $uuid = Uuid::uuid5(Uuid::NAMESPACE_URL, BB_URL);
        return $uuid->toString();
    }
}
