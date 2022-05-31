<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
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
