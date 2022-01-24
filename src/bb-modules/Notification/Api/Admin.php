<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * Notifications center management.
 *
 * Notifications are important messages for staff messages to get informed
 * about important events on BoxBilling.
 *
 * For example cron job can inform staff members
 */

namespace Box\Mod\Notification\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get paginated list of notifications
     *
     * @return array
     */
    public function get_list($data)
    {
        list($sql, $params) = $this->getService()->getSearchQuery($data);
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        return $resultSet = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
    }

    /**
     * Get notification message
     *
     * @param int $id - message id
     * @return array
     * @throws Box_Exception
     */
    public function get($data)
    {
        $required = array(
            'id' => 'Notification ID is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $meta = $this->di['db']->load('extension_meta', $data['id']);
        if ($meta->extension != 'mod_notification' || $meta->meta_key != 'message') {
            throw new \Box_Exception('Notification message was not found');
        }

        return $this->getService()->toApiArray($meta);
    }

    /**
     * Add new notification message
     *
     * @param string $message - message text
     * @return int|false - new message id
     */
    public function add($data)
    {
        if (!isset($data['message'])) {
            return false;
        }

        $message = htmlspecialchars($data['message'], ENT_QUOTES, 'UTF-8');

        return $this->getService()->create($message);
    }

    /**
     * Remove notification message
     *
     * @param int $id - message id
     * @return boolean
     * @throws Box_Exception
     */
    public function delete($data)
    {
        $required = array(
            'id' => 'Notification ID is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $meta = $this->di['db']->load('extension_meta', $data['id']);
        if ($meta->extension != 'mod_notification' || $meta->meta_key != 'message') {
            throw new \Box_Exception('Notification message was not found');
        }
        $this->di['db']->trash($meta);

        return true;
    }

    /**
     * Remove all notification messages
     *
     * @return boolean
     * @throws Box_Exception
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