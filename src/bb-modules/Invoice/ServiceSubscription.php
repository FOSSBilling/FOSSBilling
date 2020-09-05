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
namespace Box\Mod\Invoice;
use Box\InjectionAwareInterface;

class ServiceSubscription implements InjectionAwareInterface
{
    /**
     * @var \Box_Di
     */
    protected $di = null;

    /**
     * @param \Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return \Box_Di
     */
    public function getDi()
    {
        return $this->di;
    }

    public function create(\Model_Client $cient, \Model_PayGateway $pg, array $data)
    {
        $model                 = $this->di['db']->dispense('Subscription');
        $model->client_id      = $data['client_id'];
        $model->pay_gateway_id = $data['gateway_id'];

        $model->sid        = $this->di['array_get']($data, 'sid', NULL);
        $model->status     = $this->di['array_get']($data, 'status', NULL);
        $model->period     = $this->di['array_get']($data, 'period', NULL);
        $model->amount     = $this->di['array_get']($data, 'amount', NULL);
        $model->currency   = $this->di['array_get']($data, 'currency', NULL);
        $model->rel_id     = $this->di['array_get']($data, 'rel_id', NULL);
        $model->rel_type   = $this->di['array_get']($data, 'rel_type', NULL);
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $newId             = $this->di['db']->store($model);

        $this->di['events_manager']->fire(array('event' => 'onAfterAdminSubscriptionCreate', 'params' => array('id' => $newId)));

        $this->di['logger']->info('Created subscription %s', $newId);

        return $newId;
    }

    public function update(\Model_Subscription $model, array $data)
    {
        $model->status     = $this->di['array_get']($data, 'status', $model->status);
        $model->sid        = $this->di['array_get']($data, 'sid', $model->sid);
        $model->period     = $this->di['array_get']($data, 'period', $model->period);
        $model->amount     = $this->di['array_get']($data, 'amount', $model->amount);
        $model->currency   = $this->di['array_get']($data, 'currency', $model->currency);
        $model->updated_at = date('Y-m-d H:i:s');
        $newId             = $this->di['db']->store($model);

        $this->di['logger']->info('Updated subscription %s', $newId);
        return true;
    }


    public function toApiArray(\Model_Subscription $model, $deep = false, $identity = null)
    {
        $result = array(
            'id'         => $model->id,
            'sid'        => $model->sid,
            'period'     => $model->period,
            'amount'     => $model->amount,
            'currency'   => $model->currency,
            'status'     => $model->status,
            'created_at' => $model->created_at,
            'updated_at' => $model->updated_at,
        );
        $client = $this->di['db']->load('Client', $model->client_id);
        if ($client instanceof \Model_Client) {
            $clientService    = $this->di['mod_service']('Client');
            $result['client'] = $clientService->toApiArray($client, false, $identity);
        } else {
            $result['client'] = array();
        }

        $gtw = $this->di['db']->load('PayGateway', $model->pay_gateway_id);
        if ($gtw instanceof \Model_PayGateway) {
            $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
            $result['gateway'] = $payGatewayService->toApiArray($gtw, false, $identity);
        } else {
            $result['gateway'] = array();
        }

        return $result;
    }

    public function delete(\Model_Subscription $model)
    {
        $id = $model->id;
        $this->di['db']->trash($model);

        $this->di['events_manager']->fire(array('event'=>'onAfterAdminSubscriptionDelete', 'params'=>array('id'=>$id)));

        $this->di['logger']->info('Removed subscription %s', $id);
        return true;
    }

    public function getSearchQuery(array $data)
    {
        $sql = 'SELECT *
            FROM subscription
            WHERE 1 ';


        $id         = $this->di['array_get']($data, 'id', NULL);
        $sid        = $this->di['array_get']($data, 'sid', NULL);
        $search     = $this->di['array_get']($data, 'search', NULL);
        $invoice_id = $this->di['array_get']($data, 'invoice_id', NULL);
        $gateway_id = $this->di['array_get']($data, 'gateway_id', NULL);
        $client_id  = $this->di['array_get']($data, 'client_id', NULL);
        $status     = $this->di['array_get']($data, 'status', NULL);
        $currency   = $this->di['array_get']($data, 'currency', NULL);

        $date_from = $this->di['array_get']($data, 'date_from', NULL);
        $date_to   = $this->di['array_get']($data, 'date_to', NULL);
        $params    = array();
        if ($status) {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        if ($invoice_id) {
            $sql .= ' AND invoice_id = :invoice_id';
            $params['invoice_id'] = $invoice_id;
        }

        if ($gateway_id) {
            $sql .= ' AND gateway_id = :gateway_id';
            $params['gateway_id'] = $gateway_id;
        }

        if ($client_id) {
            $sql .= ' AMD client_id  = :client_id';
            $params['client_id'] = $client_id;
        }

        if ($currency) {
            $sql .= ' AND currency =  :currency ';
            $params['currency'] = $currency;
        }

        if ($date_from) {
            $sql .= ' AND UNIX_TIMESTAMP(m.created_at) >= :date_from';
            $params['date_from'] = $date_from;

        }

        if ($date_to) {
            $sql .= ' AND UNIX_TIMESTAMP(m.created_at) <= :date_to';
            $params['date_to'] = $date_to;
        }

        if ($search) {
            $sql .= ' AND sid = :sid OR m.id = :mid ';
            $params['sid'] = $search;
            $params['mid'] = $search;
        }

        if ($id) {
            $sql .= ' AND id = :id';
            $params['id'] = $id;
        }

        if ($sid) {
            $sql .= ' AND sid = :sid';
            $params['sid'] = $sid;
        }


        $sql .= ' ORDER BY id DESC';

        return array($sql, $params);
    }

    public function isSubscribable($invoice_id)
    {
        $query = "SELECT COUNT(id) as cc
            FROM invoice_item
            WHERE invoice_id = :id
            GROUP BY invoice_id
           ";
        $count = $this->di['db']->getCell($query, array('id'=>$invoice_id));
        if($count > 1) {
            return false;
        }

        // check if first invoice line has deined period
        $query = "SELECT id, period
            FROM invoice_item
            WHERE invoice_id = :id
            LIMIT 1
           ";
        $list = $this->di['db']->getAll($query, array(':id' => $invoice_id));

        if(isset($list[0])
            && isset($list[0]['period'])
            && !empty($list[0]['period'])) {
            return true;
        } else {
            return false;
        }
    }

    public function getSubscriptionPeriod(\Model_Invoice $invoice)
    {
        if(!$this->isSubscribable($invoice->id)) {
            return NULL;
        }

        $query = "SELECT period
            FROM invoice_item
            WHERE invoice_id = :id
            LIMIT 1
           ";
        return $this->di['db']->getCell($query, array('id'=>$invoice->id));
    }

    public function unsubscribe(\Model_Subscription $model)
    {
        $model->status = 'canceled';
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);
    }
}