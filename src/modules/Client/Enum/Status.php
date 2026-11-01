<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Client\Enum;

/**
 * Canonical client lifecycle status.
 *
 * The underlying string values must match the existing `client.status` column
 * values and the `Client::ACTIVE/SUSPENDED/CANCELED` entity constants so the enum is
 * interchangeable with the entity code paths.
 */
enum Status: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case CANCELED = 'canceled';
}
