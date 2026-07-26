<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Model_ClientOrder extends RedBeanPHP\SimpleModel
{
    final public const string STATUS_PENDING_SETUP = \Box\Mod\Order\Entity\Order::STATUS_PENDING_SETUP;
    final public const string STATUS_FAILED_SETUP = \Box\Mod\Order\Entity\Order::STATUS_FAILED_SETUP;
    final public const string STATUS_FAILED_RENEW = \Box\Mod\Order\Entity\Order::STATUS_FAILED_RENEW;
    final public const string STATUS_ACTIVE = \Box\Mod\Order\Entity\Order::STATUS_ACTIVE;
    final public const string STATUS_CANCELED = \Box\Mod\Order\Entity\Order::STATUS_CANCELED;
    final public const string STATUS_SUSPENDED = \Box\Mod\Order\Entity\Order::STATUS_SUSPENDED;

    final public const string ACTION_CREATE = \Box\Mod\Order\Entity\Order::ACTION_CREATE;
    final public const string ACTION_ACTIVATE = \Box\Mod\Order\Entity\Order::ACTION_ACTIVATE;
    final public const string ACTION_RENEW = \Box\Mod\Order\Entity\Order::ACTION_RENEW;
    final public const string ACTION_SUSPEND = \Box\Mod\Order\Entity\Order::ACTION_SUSPEND;
    final public const string ACTION_UNSUSPEND = \Box\Mod\Order\Entity\Order::ACTION_UNSUSPEND;
    final public const string ACTION_CANCEL = \Box\Mod\Order\Entity\Order::ACTION_CANCEL;
    final public const string ACTION_UNCANCEL = \Box\Mod\Order\Entity\Order::ACTION_UNCANCEL;
    final public const string ACTION_DELETE = \Box\Mod\Order\Entity\Order::ACTION_DELETE;

    public static function getValidStatuses(): array
    {
        return \Box\Mod\Order\Entity\Order::getValidStatuses();
    }
}
