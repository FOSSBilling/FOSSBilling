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


class Model_ClientOrderStatusTable implements \Box\InjectionAwareInterface
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
        // do not log debug messages if debug is disabled
        if(isset($data['priority']) && $data['priority'] > 6 && !BB_DEBUG) {
            return ;
        }

        if(!isset($data['message']) || empty ($data['message'])) {
            return ;
        }
        
        if(!isset($data['client_order_id'])) {
            return ;
        }

        $entry = $this->di['db']->dispense('ClientOrderStatus');
        $entry->client_order_id = $data['client_order_id'];
        $entry->status          = isset($data['status']) ? $data['status'] : Model_ClientOrder::STATUS_PENDING_SETUP;
        $entry->notes           = $data['message'];
        $entry->created_at      = date('c');
        $entry->updated_at      = date('c');
        $this->di['db']->store($entry);
    }

    public function getSearchQuery($data)
    {
        $sql = 'SELECT *
                FROM client_order_status
                WHERE 1';
        $params = array();

        $oid = isset($data['client_order_id']) ? $data['client_order_id'] : NULL;

        if(NULL !== $oid) {
            $params[':client_order_id'] = $oid;
            $sql .= ' AND client_order_id = :client_order_id';
        }

        $sql .= ' ORDER BY id DESC';
        return array($sql, $params);
    }
    
    public function toApiArray(\Model_ClientOrderStatus $model)
    {
        return array(
            'id'  =>  $model->id,
            'order_id'  =>  $model->client_order_id,
            'status'  =>  $model->status,
            'notes'  =>  $model->notes,
            'created_at'  =>  $model->created_at,
        );
    }
}