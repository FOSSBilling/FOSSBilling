<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Notification;

use FOSSBilling\InjectionAwareInterface;

class Service implements InjectionAwareInterface
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

    public function getSearchQuery($filter)
    {
        $q = "SELECT *
            FROM extension_meta
            WHERE extension = 'mod_notification'
            AND meta_key = 'message'
            ORDER BY id DESC
        ";

        return [$q, []];
    }

    public function toApiArray($row)
    {
        return $this->di['db']->toArray($row);
    }

    public function create($message)
    {
        $meta = $this->di['db']->dispense('extension_meta');
        $meta->extension = 'mod_notification';
        $meta->rel_type = 'staff';
        $meta->rel_id = 1;
        $meta->meta_key = 'message';
        $meta->meta_value = $message;
        $meta->created_at = date('Y-m-d H:i:s');
        $meta->updated_at = date('Y-m-d H:i:s');
        $id = $this->di['db']->store($meta);

        $this->di['events_manager']->fire(['event' => 'onAfterAdminNotificationAdd', 'params' => ['id' => $id]]);

        return $id;
    }
}
