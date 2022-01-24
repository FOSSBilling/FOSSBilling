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


namespace Box\Mod\Notification;

use Box\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    protected $di;

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
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

        return array($q, array());
    }

    public function toApiArray($row)
    {
        return $this->di['db']->toArray($row);
    }

    public function create($message)
    {
        $meta             = $this->di['db']->dispense('extension_meta');
        $meta->extension  = 'mod_notification';
        $meta->rel_type   = 'staff';
        $meta->rel_id     = 1;
        $meta->meta_key   = 'message';
        $meta->meta_value = $message;
        $meta->created_at = date('Y-m-d H:i:s');
        $meta->updated_at = date('Y-m-d H:i:s');
        $id               = $this->di['db']->store($meta);

        $this->di['events_manager']->fire(array('event' => 'onAfterAdminNotificationAdd', 'params' => array('id' => $id)));

        return $id;
    }
}