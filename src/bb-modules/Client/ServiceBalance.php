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


namespace Box\Mod\Client;

use Box\InjectionAwareInterface;
use Box_Exception;
use Model_Client;
use Model_ClientBalance;

class ServiceBalance implements InjectionAwareInterface
{
    protected $di = null;

    /**
     * @param \Box_Di|null $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return \Box_Di|null
     */
    public function getDi()
    {
        return $this->di;
    }

    public function getClientBalance(Model_Client $c)
    {
        return (float)$this->clientTotal($c);
    }

    public function clientTotal(Model_Client $c)
    {
        $sql="
        SELECT SUM(amount) as client_total
        FROM client_balance
        WHERE client_id = ?
        GROUP BY client_id
        ";
        return $this->di['db']->getCell($sql, array($c->id));
    }

    public function rmByClient(Model_Client $client)
    {
        $clientBalances = $this->di['db']->find('ClientBalance', 'client_id = ?', array($client->id));
        foreach ($clientBalances as $balanceModel){
            $this->di['db']->trash($balanceModel);
        }
    }

    public function rm(Model_ClientBalance $model)
    {
        $this->di['db']->trash($model);
    }

    public function toApiArray(Model_ClientBalance $model)
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

        $id         = $this->di['array_get']($data, 'id', NULL);
        $client_id  = $this->di['array_get']($data, 'client_id', NULL);
        $date_from  = $this->di['array_get']($data, 'date_from', NULL);
        $date_to    = $this->di['array_get']($data, 'date_to', NULL);

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

    /**
     * @param Model_Client $client
     * @param double        $amount
     * @param string        $description
     * @param array         $data
     * @return Model_ClientBalance
     * @throws Box_Exception
     */
    public function deductFunds(Model_Client $client, $amount, $description, array $data = null)
    {
        if(!is_numeric($amount)) {
            throw new Box_Exception('Funds amount is not valid');
        }

        if(strlen(trim($description)) == 0) {
            throw new Box_Exception('Funds description is not valid');
        }

        $credit = $this->di['db']->dispense('ClientBalance');
        $credit->client_id = $client->id;
        $credit->type = $this->di['array_get']($data, 'type', 'default');
        $credit->rel_id = $this->di['array_get']($data, 'rel_id', null);
        $credit->description = $description;
        $credit->amount = -$amount;
        $credit->created_at = date('Y-m-d H:i:s');
        $credit->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($credit);

        return $credit;
    }
}