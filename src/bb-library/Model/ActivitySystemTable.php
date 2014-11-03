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


class Model_ActivitySystemTable implements \Box\InjectionAwareInterface
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
        if(!isset($data['message']) || empty ($data['message'])) {
            return ;
        }
        
        foreach($data as $val) {
            if($val instanceof Model_Admin) {
                $data['admin_id'] = $val->id;
            }
            if($val instanceof Model_Client) {
                $data['client_id'] = $val->id;
            }
        }

        $entry = $this->di['db']->dispense('ActivitySystem');
        $entry->client_id       = isset($data['client_id']) ? $data['client_id'] : NULL;
        $entry->admin_id        = isset($data['admin_id']) ? $data['admin_id'] : NULL;
        $entry->priority        = $data['priority'];
        $entry->message         = $data['message'];
        $entry->created_at      = $data['timestamp'];
        $entry->updated_at      = $data['timestamp'];
        $entry->ip              = isset($data['ip']) ? $data['ip'] : NULL;
        $this->di['db']->store($entry);
    }
    
    public function rmByClient(Model_Client $client)
    {
        $models = $this->di['db']->find('ActivitySystem', 'client_id = ?', $client->id);
        foreach($models as $model){
            $this->di['db']->trash($model);
        }
    }
}