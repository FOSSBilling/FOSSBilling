<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Notification\Controller;

class Admin implements \FOSSBilling\Core\Container\InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function register(\FOSSBilling\Core\Http\App &$app): void
    {
        $app->get('/notification', 'get_index', [], static::class);
    }

    public function get_index(\FOSSBilling\Core\Http\App $app): string
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_notification_index');
    }
}
