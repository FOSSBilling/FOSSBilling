<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Widgets\Api;

class Admin extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Get all registered widgets grouped by slots.
     *
     * @return array list of slots with their widgets
     */
    public function list(): array
    {
        $this->getDi()['mod_service']('Staff')->checkPermissionsAndThrowException('widgets', 'view');

        return $this->getService()->getWidgetList();
    }

    /**
     * Force rebuild of the widget registry cache.
     * Useful for debugging or after manual changes.
     */
    public function rebuild(): bool
    {
        $this->getDi()['mod_service']('Staff')->checkPermissionsAndThrowException('widgets', 'manage');

        $service = $this->getService();
        $service->invalidateCache();
        $service->getRegistry(); // Trigger rebuild

        return true;
    }
}
