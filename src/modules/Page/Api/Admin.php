<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * Static Pages management.
 */

namespace Box\Mod\Page\Api;

class Admin extends \Api_Abstract
{
    /**
     * Return page pairs. Includes module and currently selected client area
     * pages.
     *
     * @return array
     */
    public function get_pairs()
    {
        $service = $this->getService();

        return $service->getPairs();
    }
}
