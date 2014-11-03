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
        $per_page         = isset($data['per_page']) ? $data['per_page'] : $this->di['pager']->getPer_page();
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
        
        $priority = isset($data['priority']) ? (int)$data['priority'] : 6;

        $entry = $this->di['db']->dispense('ActivitySystem');
        $entry->client_id       = isset($data['client_id']) ? $data['client_id'] : NULL;
        $entry->admin_id        = isset($data['admin_id']) ? $data['admin_id'] : NULL;
        $entry->priority        = $priority;
        $entry->message         = $data['m'];
        $entry->created_at      = date('c');
        $entry->updated_at      = date('c');
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

        $client_id    = isset($data['client_id']) ? $data['client_id'] : NULL;
        $sender       = isset($data['sender']) ? $data['sender'] : NULL;
        $recipients   = isset($data['recipients']) ? $data['recipients'] : NULL;
        $subject      = isset($data['subject']) ? $data['subject'] : NULL;
        $content_html = isset($data['content_html']) ? $data['content_html'] : NULL;
        $content_text = isset($data['content_text']) ? $data['content_text'] : NULL;

        return $this->getService()->logEmail($subject, $client_id, $sender, $recipients, $subject, $content_html, $content_text);
    }

    /**
     * Remove activity message
     *
     * @param int $id - Message ID
     */
    public function log_delete($data)
    {
        if(!isset($data['id'])) {
            throw new \Box_Exception('ID is required');
        }

        $database = $this->di['db'];
        $model = $database->load('ActivitySystem', $data['id']);
        if(!$model instanceof \Model_ActivitySystem) {
            throw new \Box_Exception('Event not found');
        }

        $database->trash($model);
        return true;
    }
}
