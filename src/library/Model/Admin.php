<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Model_Admin extends RedBeanPHP\SimpleModel
{
    final public const string SYSTEM_CRON = 'cron';

    final public const string STATUS_ACTIVE = 'active';
    final public const string STATUS_INACTIVE = 'inactive';

    public function getFullName()
    {
        return $this->name;
    }

    public function getStatus($status = ''): string
    {
        $statusArray = [
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE,
        ];
        if (in_array($status, $statusArray)) {
            return strtolower((string) $status);
        }

        return self::STATUS_INACTIVE;
    }

    public function isCron(): bool
    {
        return $this->system_name === self::SYSTEM_CRON;
    }
}
