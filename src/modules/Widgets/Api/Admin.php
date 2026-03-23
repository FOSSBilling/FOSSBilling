<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Widgets\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get all registered widgets grouped by slots.
     *
     * @return array list of slots with their widgets
     */
    public function list(): array
    {
        return $this->getService()->getWidgetList();
    }

    /**
     * Force rebuild of the widget registry cache.
     * Useful for debugging or after manual changes.
     */
    public function rebuild(): bool
    {
        $service = $this->getService();
        $service->invalidateCache();
        $service->getRegistry(); // Trigger rebuild

        return true;
    }
}
