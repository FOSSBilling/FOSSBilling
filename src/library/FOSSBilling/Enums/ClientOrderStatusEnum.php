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
 * Canonical client order lifecycle status.
 *
 * The underlying string values must match the existing RedBean `client_order.status`
 * column values and the `Model_ClientOrder::STATUS_*` constants so the enum is
 * interchangeable with the legacy code paths.
 */
enum ClientOrderStatusEnum: string
{
    case PENDING_SETUP = 'pending_setup';
    case FAILED_SETUP = 'failed_setup';
    case FAILED_RENEW = 'failed_renew';
    case ACTIVE = 'active';
    case CANCELED = 'canceled';
    case SUSPENDED = 'suspended';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
