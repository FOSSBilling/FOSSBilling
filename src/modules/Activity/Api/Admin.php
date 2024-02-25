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
 * System activity messages management.
 */

namespace Box\Mod\Activity\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get a list of activity messages.
     *
     * @param array $data Search parameters
     *
     * @return array An array containing the list of activity messages and the pager information
     */
    public function log_get_list($data)
    {
        $data['no_debug'] = true;
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
        [$sql, $params] = $this->getService()->getSearchQuery($data);
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);

        foreach ($pager['list'] as $key => $item) {
            if (isset($item['staff_id'])) {
                $pager['list'][$key]['staff']['id'] = $item['staff_id'];
                $pager['list'][$key]['staff']['name'] = $item['staff_name'];
                $pager['list'][$key]['staff']['email'] = $item['staff_email'];
            }
            if (isset($item['client_id'])) {
                $pager['list'][$key]['client']['id'] = $item['client_id'];
                $pager['list'][$key]['client']['name'] = $item['client_name'];
                $pager['list'][$key]['client']['email'] = $item['client_email'];
            }
        }

        return $pager;
    }

    /**
     * Add a message to the log.
     *
     * @param array $data Message data
     *
     * @return bool
     */
    public function log($data)
    {
        if (!isset($data['m'])) {
            return false;
        }

        $priority = $data['priority'] ?? 6;

        $entry = $this->di['db']->dispense('ActivitySystem');
        $entry->client_id = $data['client_id'] ?? null;
        $entry->admin_id = $data['admin_id'] ?? null;
        $entry->priority = $priority;
        $entry->message = $data['m'];
        $entry->created_at = date('Y-m-d H:i:s');
        $entry->updated_at = date('Y-m-d H:i:s');
        $entry->ip = $this->di['request']->getClientAddress();
        $this->di['db']->store($entry);

        return true;
    }

    /**
     * Add an email to the log.
     *
     * @param array $data Email data
     *
     * @return bool
     */
    public function log_email($data)
    {
        if (!isset($data['subject'])) {
            error_log('Email was not logged. Subject not passed');

            return false;
        }

        $client_id = $data['client_id'] ?? null;
        $sender = $data['sender'] ?? null;
        $recipients = $data['recipients'] ?? null;
        $subject = $data['subject'];
        $content_html = $data['content_html'] ?? null;
        $content_text = $data['content_text'] ?? null;

        return $this->getService()->logEmail($subject, $client_id, $sender, $recipients, $content_html, $content_text);
    }

    /**
     * Remove an activity message from the log.
     *
     * @param array $data Message data
     *
     * @return bool True if the message was deleted, false otherwise
     */
    public function log_delete($data)
    {
        $required = [
            'id' => 'ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ActivitySystem', $data['id'], 'Event not found');

        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('activity', 'delete_activity');

        $this->di['db']->trash($model);

        return true;
    }

    /**
     * Delete multiple activity messages from the log.
     *
     * @param array $data Deletion data
     *
     * @return bool True if the messages were deleted, false otherwise
     */
    public function batch_delete($data)
    {
        $required = [
            'ids' => 'IDs not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        foreach ($data['ids'] as $id) {
            $this->log_delete(['id' => $id]);
        }

        return true;
    }
}
