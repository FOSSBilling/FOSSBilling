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
 * Hooks management module.
 */

namespace Box\Mod\Hook\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get paginated list of hooks.
     *
     * @return array
     */
    public function get_list($data)
    {
        $service = $this->getService();
        [$sql, $params] = $service->getSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();

        return $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
    }

    /**
     * Invoke hook with params.
     *
     * @optional array $params - what params are passed to event method $event->getParams()
     *
     * @return mixed - event return value
     */
    public function call($data)
    {
        if (!isset($data['event']) || empty($data['event'])) {
            error_log('Invoked event call without providing event name');

            return false;
        }

        $event = $data['event'];
        $params = $data['params'] ?? null;
        if (DEBUG) {
            try {
                $this->di['logger']->info($event . ': ' . var_export($params, 1));
            } catch (\Exception $e) {
                error_log($e->getMessage());
            }
        }

        return $this->di['events_manager']->fire($data);
    }

    /**
     * Reinstall and activate all existing hooks from module or all
     * activated modules. Does not connect already connected event.
     *
     * @optional string $mod - module name to connect hooks
     *
     * @return bool
     */
    public function batch_connect($data)
    {
        $mod = $data['mod'] ?? null;
        $service = $this->getService();

        return $service->batchConnect($mod);
    }
}
