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
 * Notifications center management.
 *
 * Notifications are important messages for staff messages to get informed
 * about important events on FOSSBilling.
 *
 * For example cron job can inform staff members
 */

namespace Box\Mod\Notification\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get paginated list of notifications.
     *
     * @return array
     */
    public function get_list($data)
    {
        [$sql, $params] = $this->getService()->getSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();

        return $resultSet = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
    }

    /**
     * Get notification message.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    public function get($data)
    {
        $required = [
            'id' => 'Notification ID is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $meta = $this->di['db']->load('extension_meta', $data['id']);
        if ($meta->extension != 'mod_notification' || $meta->meta_key != 'message') {
            throw new \FOSSBilling\Exception('Notification message was not found');
        }

        return $this->getService()->toApiArray($meta);
    }

    /**
     * Add new notification message.
     *
     * @return int|false - new message id
     */
    public function add($data): int|false
    {
        if (!isset($data['message'])) {
            return false;
        }

        $message = htmlspecialchars($data['message'], ENT_QUOTES, 'UTF-8');

        return $this->getService()->create($message);
    }

    /**
     * Remove notification message.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function delete($data)
    {
        $required = [
            'id' => 'Notification ID is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $meta = $this->di['db']->load('extension_meta', $data['id']);
        if ($meta->extension != 'mod_notification' || $meta->meta_key != 'message') {
            throw new \FOSSBilling\Exception('Notification message was not found');
        }
        $this->di['db']->trash($meta);

        return true;
    }

    /**
     * Remove all notification messages.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function delete_all()
    {
        $sql = "DELETE
            FROM extension_meta 
            WHERE extension = 'mod_notification'
            AND meta_key = 'message';";
        $this->di['db']->exec($sql);

        return true;
    }
}
