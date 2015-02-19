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


class Model_ActivityClientHistoryTable implements \Box\InjectionAwareInterface
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
        if(!isset($data['client_id']) || !isset($data['ip'])) {
            return ;
        }

        $entry = $this->di['db']->dispense('ActivityClientHistory');
        $entry->client_id       = $data['client_id'];
        $entry->ip              = $data['ip'];
        $entry->created_at      = date('Y-m-d H:i:s');
        $entry->updated_at      = date('Y-m-d H:i:s');
        $this->di['db']->store($entry);
    }

    public function rmByClient(Model_Client $client)
    {
        $models = $this->di['db']->find('ActivityClientHistory', 'client_id = ?', array($client->id));
        foreach($models as $model){
            $this->di['db']->trash($model);
        }
    }

}