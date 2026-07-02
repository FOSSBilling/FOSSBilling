<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Enums;

/**
 * Canonical client lifecycle status.
 *
 * The underlying string values must match the existing RedBean `client.status`
 * column values and the `Model_Client::ACTIVE/SUSPENDED/CANCELED` constants
 * so the enum is interchangeable with the legacy code paths.
 */
enum ClientStatusEnum: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case CANCELED = 'canceled';
}
