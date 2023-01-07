<?php

/**
 * FOSSBilling.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * Copyright FOSSBilling 2022
 * This software may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
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
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
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
     * @param string $data['m'] Message text
     * @param int $data['admin_id'] [optional] Admin ID
     * @param int $data['client_id'] [optional] Client ID
     * @param string $data['priority'] [optional] Log priority
     * 
     * @return bool
    */
    public function log($data)
    {
        if (!isset($data['m'])) {
            return false;
        }

        $priority = $this->di['array_get']($data, 'priority', 6);

        $entry = $this->di['db']->dispense('ActivitySystem');
        $entry->client_id = $this->di['array_get']($data, 'client_id', null);
        $entry->admin_id = $this->di['array_get']($data, 'admin_id', null);
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
     * @param string $data['subject'] Email subject
     * @param int $data['client_id'] [optional] Client ID
     * @param string $data['sender'] [optional] Email sender
     * @param string $data['recipients'] [optional] Email recipients
     * @param string $data['content_html'] [optional] Email content in HTML format
     * @param string $data['content_text'] [optional] Email content in plain text format
     * 
     * @return bool
     */
    public function log_email($data)
    {
        if (!isset($data['subject'])) {
            error_log('Email was not logged. Subject not passed');

            return false;
        }

        $client_id = $this->di['array_get']($data, 'client_id', null);
        $sender = $this->di['array_get']($data, 'sender', null);
        $recipients = $this->di['array_get']($data, 'recipients', null);
        $subject = $data['subject'];
        $content_html = $this->di['array_get']($data, 'content_html', null);
        $content_text = $this->di['array_get']($data, 'content_text', null);

        return $this->getService()->logEmail($subject, $client_id, $sender, $recipients, $content_html, $content_text);
    }

    /**
     * Remove an activity message from the log.
     * 
     * @param array $data Message data
     * 
     * @param int $data['id'] ID of the message to delete
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

        $this->di['db']->trash($model);

        return true;
    }

    /**
     * Delete multiple activity messages from the log.
     * 
     * @param array $data Deletion data
     * 
     * @param int $data['ids'] IDs of the messages to delete
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
