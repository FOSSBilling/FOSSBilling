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
}