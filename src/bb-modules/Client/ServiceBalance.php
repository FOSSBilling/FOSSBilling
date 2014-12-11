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


namespace Box\Mod\Client;

use Box\InjectionAwareInterface;

class ServiceBalance implements InjectionAwareInterface
{
    protected $di = null;

    /**
     * @param Box_Di|null $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return Box_Di|null
     */
    public function getDi()
    {
        return $this->di;
    }

    public function getClientBalance(\Model_Client $c)
    {
        return (float)$this->clientTotal($c);
    }

    public function clientTotal(\Model_Client $c)
    {
        $sql="
        SELECT SUM(amount) as client_total
        FROM client_balance
        WHERE client_id = ?
        GROUP BY client_id
        ORDER BY id DESC
        ";
        return $this->di['db']->getCell($sql, array($c->id));
    }

    public function rmByClient(\Model_Client $client)
    {
        $clientBalances = $this->di['db']->find('ClientBalance', 'client_id', array($client->id));
        foreach ($clientBalances as $balanceModel){
            $this->di['db']->trash($balanceModel);
        }
    }

    public function rm(\Model_ClientBalance $model)
    {
        $this->di['db']->trash($model);
    }

    public function toApiArray(\Model_ClientBalance $model)
    {
        $client = $this->di['db']->getExistingModelById('Client', $model->client_id, 'Client not found');
        return array(
            'id'            =>  $model->id,
            'description'   =>  $model->description,
            'amount'        =>  $model->amount,
            'currency'      =>  $client->currency,
            'created_at'    =>  $model->created_at,
        );
    }

    public function getSearchQuery($data)
    {
        $q = 'SELECT m.*, c.currency  as currency
              FROM client_balance as m
                LEFT JOIN client as c on c.id = m.client_id';

        $id         = isset($data['id']) ? $data['id'] : NULL;
        $client_id  = isset($data['client_id']) ? $data['client_id'] : NULL;
        $date_from  = isset($data['date_from']) ? $data['date_from'] : NULL;
        $date_to    = isset($data['date_to']) ? $data['date_to'] : NULL;

        $where = array();
        $params = array();

        if(NULL !== $id) {
            $where[] = 'm.id = :id';
            $params[':id'] = $id;
        }

        if(NULL !== $client_id) {
            $where[] = 'm.client_id = :client_id';
            $params[':client_id'] = $client_id;
        }

        if(NULL !== $date_from) {
            $where[] = 'm.created_at >= :date_from';
            $params[':date_from'] = strtotime($date_from);
        }

        if(NULL !== $date_to) {
            $where[] = 'm.created_at <= :date_to';
            $params[':date_to'] = strtotime($date_to);
        }

        if (!empty($where)){
            $q .= ' WHERE '.implode(' AND ', $where);
        }
        $q .= ' ORDER by m.id DESC';

        return array($q, $params);
    }
}