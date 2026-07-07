<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * Hooks management module.
 */

namespace Box\Mod\Hook\Api;

use FOSSBilling\PaginationOptions;

class Admin extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Get paginated list of hooks.
     *
     * @return array
     */
    public function get_list($data)
    {
        $this->checkPermissions('hook', 'view');

        $service = $this->getService();
        [$sql, $params] = $service->getSearchQuery($data);

        return $this->getDi()['pager']->getPaginatedResultSet($sql, $params, PaginationOptions::fromArray($data));
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
        $this->checkPermissions('hook', 'trigger_hooks');

        if (!isset($data['event']) || empty($data['event'])) {
            $this->getDi()['logger']->warning('Invoked event call without providing event name');

            return false;
        }

        $event = $data['event'];
        $params = $data['params'] ?? null;
        // @phpstan-ignore if.alwaysFalse
        if (DEBUG) {
            try {
                $this->getDi()['logger']->info($event . ': ' . var_export($params, true));
            } catch (\Exception $e) {
                error_log($e->getMessage());
            }
        }

        return $this->getDi()['events_manager']->fire($data);
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
        $this->checkPermissions('hook', 'manage_hooks');

        $mod = $data['mod'] ?? null;
        $service = $this->getService();

        return $service->batchConnect($mod);
    }
}
