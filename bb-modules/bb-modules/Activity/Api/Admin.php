<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * System activity messages management
 */

namespace Box\Mod\Activity\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get list of activity messages
     */
    public function log_get_list($data)
    {
        $data['no_debug'] = true;
        $per_page         = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        list($sql, $params) = $this->getService()->getSearchQuery($data);
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);

        foreach ($pager['list'] as $key => $item) {
            if (isset($item['staff_id'])) {
                $pager['list'][$key]['staff']['id']    = $item['staff_id'];
                $pager['list'][$key]['staff']['name']  = $item['staff_name'];
                $pager['list'][$key]['staff']['email'] = $item['staff_email'];
            }
            if (isset($item['client_id'])) {
                $pager['list'][$key]['client']['id']    = $item['client_id'];
                $pager['list'][$key]['client']['name']  = $item['client_name'];
                $pager['list'][$key]['client']['email'] = $item['client_email'];
            }
        }

        return $pager;
    }

    /**
     * Add message to log
     *
     * @param string $m - Message text
     * @optional int $admin_id - admin id
     * @optional int $client_id - client id
     * @optional string $priority - log priority
     * 
     * @return bool
     */
    public function log($data)
    {
        if(!isset($data['m'])) {
            return false;
        }
        
        $priority = $this->di['array_get']($data, 'priority', 6);

        $entry = $this->di['db']->dispense('ActivitySystem');
        $entry->client_id       = $this->di['array_get']($data, 'client_id', NULL);
        $entry->admin_id        = $this->di['array_get']($data, 'admin_id', NULL);
        $entry->priority        = $priority;
        $entry->message         = $data['m'];
        $entry->created_at      = date('Y-m-d H:i:s');
        $entry->updated_at      = date('Y-m-d H:i:s');
        $entry->ip              = $this->di['request']->getClientAddress();
        $this->di['db']->store($entry);
        
        return true;
    }
    
    /**
     * Add email to log
     *
     * @return bool
     */
    public function log_email($data)
    {
        if (!isset($data['subject'])) {
            error_log('Email was not logged. Subject not passed');
            return false;
        }

        $client_id    = $this->di['array_get']($data, 'client_id', NULL);
        $sender       = $this->di['array_get']($data, 'sender', NULL);
        $recipients   = $this->di['array_get']($data, 'recipients', NULL);
        $subject      = $data['subject'];
        $content_html = $this->di['array_get']($data, 'content_html', NULL);
        $content_text = $this->di['array_get']($data, 'content_text', NULL);

        return $this->getService()->logEmail($subject, $client_id, $sender, $recipients, $content_html, $content_text);
    }

    /**
     * Remove activity message
     *
     * @param int $id - Message ID
     */
    public function log_delete($data)
    {
        $required = array(
            'id' => 'ID is required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ActivitySystem', $data['id'], 'Event not found');

        $this->di['db']->trash($model);
        return true;
    }

    /**
     * Deletes logs with given IDs
     *
     * @param array $ids - IDs for deletion
     *
     * @return bool
     */
    public function batch_delete($data)
    {
        $required = array(
            'ids' => 'IDs not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        foreach ($data['ids'] as $id) {
            $this->log_delete(array('id' => $id));
        }

        return true;
    }
}
