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


class Model_ActivityAdminHistoryTable implements \Box\InjectionAwareInterface
{
    /**
     * @var \Box_Di
     */
    protected $di;

    /**
     * @param Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return Box_Di
     */
    public function getDi()
    {
        return $this->di;
    }

    /**
     * @param array $data
     */
    public function logEvent($data)
    {
        if(!isset($data['admin_id']) || !isset($data['ip'])) {
            return ;
        }

        $entry = $this->di['db']->dispense('ActivityAdminHistory');
        $entry->admin_id        = $data['admin_id'];
        $entry->ip              = $data['ip'];
        $entry->created_at      = date('c');
        $entry->updated_at      = date('c');
        $this->di['db']->store($entry);
    }
}