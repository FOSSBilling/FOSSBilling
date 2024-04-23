<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Invoice;

use FOSSBilling\InjectionAwareInterface;

class ServiceSubscription implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function create(\Model_Client $client, \Model_PayGateway $pg, array $data)
    {
        $model = $this->di['db']->dispense('Subscription');
        $model->client_id = $data['client_id'];
        $model->pay_gateway_id = $data['gateway_id'];

        $model->sid = $data['sid'] ?? null;
        $model->status = $data['status'] ?? null;
        $model->period = $data['period'] ?? null;
        $model->amount = $data['amount'] ?? null;
        $model->currency = $data['currency'] ?? null;
        $model->rel_id = $data['rel_id'] ?? null;
        $model->rel_type = $data['rel_type'] ?? null;
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $newId = $this->di['db']->store($model);

        $this->di['events_manager']->fire(['event' => 'onAfterAdminSubscriptionCreate', 'params' => ['id' => $newId]]);

        $this->di['logger']->info('Created subscription %s', $newId);

        return $newId;
    }

    public function update(\Model_Subscription $model, array $data)
    {
        $model->status = $data['status'] ?? $model->status;
        $model->sid = $data['sid'] ?? $model->sid;
        $model->period = $data['period'] ?? $model->period;
        $model->amount = $data['amount'] ?? $model->amount;
        $model->currency = $data['currency'] ?? $model->currency;
        $model->updated_at = date('Y-m-d H:i:s');
        $newId = $this->di['db']->store($model);

        $this->di['logger']->info('Updated subscription %s', $newId);

        return true;
    }

    public function toApiArray(\Model_Subscription $model, $deep = false, $identity = null): array
    {
        $result = [
            'id' => $model->id,
            'sid' => $model->sid,
            'period' => $model->period,
            'amount' => $model->amount,
            'currency' => $model->currency,
            'status' => $model->status,
            'created_at' => $model->created_at,
            'updated_at' => $model->updated_at,
        ];
        $client = $this->di['db']->load('Client', $model->client_id);
        if ($client instanceof \Model_Client) {
            $clientService = $this->di['mod_service']('Client');
            $result['client'] = $clientService->toApiArray($client, false, $identity);
        } else {
            $result['client'] = [];
        }

        $gtw = $this->di['db']->load('PayGateway', $model->pay_gateway_id);
        if ($gtw instanceof \Model_PayGateway) {
            $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
            $result['gateway'] = $payGatewayService->toApiArray($gtw, false, $identity);
        } else {
            $result['gateway'] = [];
        }

        return $result;
    }

    public function delete(\Model_Subscription $model)
    {
        $id = $model->id;
        $this->di['db']->trash($model);

        $this->di['events_manager']->fire(['event' => 'onAfterAdminSubscriptionDelete', 'params' => ['id' => $id]]);

        $this->di['logger']->info('Removed subscription %s', $id);

        return true;
    }

    public function getSearchQuery(array $data)
    {
        $sql = 'SELECT *
            FROM subscription
            WHERE 1 ';

        $id = $data['id'] ?? null;
        $sid = $data['sid'] ?? null;
        $search = $data['search'] ?? null;
        $invoice_id = $data['invoice_id'] ?? null;
        $gateway_id = $data['gateway_id'] ?? null;
        $client_id = $data['client_id'] ?? null;
        $status = $data['status'] ?? null;
        $currency = $data['currency'] ?? null;

        $date_from = $data['date_from'] ?? null;
        $date_to = $data['date_to'] ?? null;
        $params = [];
        if ($status) {
            $sql .= ' AND status = :status';
            $params[':status'] = $status;
        }

        if ($invoice_id) {
            $sql .= ' AND invoice_id = :invoice_id';
            $params['invoice_id'] = $invoice_id;
        }

        if ($gateway_id) {
            $sql .= ' AND gateway_id = :gateway_id';
            $params[':gateway_id'] = $gateway_id;
        }

        if ($client_id) {
            $sql .= ' AND client_id  = :client_id';
            $params[':client_id'] = $client_id;
        }

        if ($currency) {
            $sql .= ' AND currency =  :currency ';
            $params[':currency'] = $currency;
        }

        if ($date_from) {
            $sql .= ' AND UNIX_TIMESTAMP(created_at) >= :date_from';
            $params[':date_from'] = $date_from;
        }

        if ($date_to) {
            $sql .= ' AND UNIX_TIMESTAMP(created_at) <= :date_to';
            $params[':date_to'] = $date_to;
        }

        if ($search) {
            $sql .= ' AND sid = :sid OR id = :mid ';
            $params[':sid'] = $search;
            $params[':mid'] = $search;
        }

        if ($id) {
            $sql .= ' AND id = :id';
            $params[':id'] = $id;
        }

        if ($sid) {
            $sql .= ' AND sid = :sid';
            $params[':sid'] = $sid;
        }

        $sql .= ' ORDER BY id DESC';

        return [$sql, $params];
    }

    public function isSubscribable($invoice_id)
    {
        $query = 'SELECT COUNT(id) as cc
            FROM invoice_item
            WHERE invoice_id = :id
            GROUP BY invoice_id
           ';
        $count = $this->di['db']->getCell($query, ['id' => $invoice_id]);
        if ($count > 1) {
            return false;
        }

        // check if first invoice line has denied period
        $query = 'SELECT id, period
            FROM invoice_item
            WHERE invoice_id = :id
            LIMIT 1
           ';
        $list = $this->di['db']->getAll($query, [':id' => $invoice_id]);

        if (
            isset($list[0])
            && isset($list[0]['period'])
            && !empty($list[0]['period'])
        ) {
            return true;
        } else {
            return false;
        }
    }

    public function getSubscriptionPeriod(\Model_Invoice $invoice)
    {
        if (!$this->isSubscribable($invoice->id)) {
            return null;
        }

        $query = 'SELECT period
            FROM invoice_item
            WHERE invoice_id = :id
            LIMIT 1
           ';

        return $this->di['db']->getCell($query, ['id' => $invoice->id]);
    }

    public function unsubscribe(\Model_Subscription $model)
    {
        $model->status = 'canceled';
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);
    }
}
