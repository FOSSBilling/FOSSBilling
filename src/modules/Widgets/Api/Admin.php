<?php
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Widgets\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get a paginated list of widgets.
     *
     * @return array
     */
    public function get_list($data)
    {
        $service = $this->getService();
        [$sql, $params] = $service->getSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getDefaultPerPage();

        return $this->di['pager']->getPaginatedResultSet($sql, $params, $per_page);
    }

    public function batch_connect($data)
    {
        $mod = $data['mod'] ?? null;
        $service = $this->getService();

        return $service->batchConnect($mod);
    }
}
