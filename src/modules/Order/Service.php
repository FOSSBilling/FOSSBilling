<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Order;

use FOSSBilling\InformationException;
use FOSSBilling\InjectionAwareInterface;

class Service implements InjectionAwareInterface
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

    public function counter()
    {
        $sql = '
        SELECT status, COUNT(id) as counter
        FROM client_order
        WHERE group_master = 1
        GROUP BY status
        ';

        $data = $this->di['db']->getAssoc($sql);

        return [
            'total' => array_sum($data),
            \Model_ClientOrder::STATUS_PENDING_SETUP => $data[\Model_ClientOrder::STATUS_PENDING_SETUP] ?? 0,
            \Model_ClientOrder::STATUS_FAILED_SETUP => $data[\Model_ClientOrder::STATUS_FAILED_SETUP] ?? 0,
            \Model_ClientOrder::STATUS_FAILED_RENEW => $data[\Model_ClientOrder::STATUS_FAILED_RENEW] ?? 0,
            \Model_ClientOrder::STATUS_ACTIVE => $data[\Model_ClientOrder::STATUS_ACTIVE] ?? 0,
            \Model_ClientOrder::STATUS_SUSPENDED => $data[\Model_ClientOrder::STATUS_SUSPENDED] ?? 0,
            \Model_ClientOrder::STATUS_CANCELED => $data[\Model_ClientOrder::STATUS_CANCELED] ?? 0,
        ];
    }

    public static function onAfterAdminOrderActivate(\Box_Event $event)
    {
        $params = $event->getParameters();
        $order_id = $params['id'];
        $di = $event->getDi();
        $service = $di['mod_service']('order');

        try {
            $order = $di['db']->getExistingModelById('ClientOrder', $order_id, 'Order not found');
            $s = $service->getOrderServiceData($order);
            $orderArr = $service->toApiArray($order, true);

            $email = $params;
            $email['to_client'] = $order->client_id;
            $email['code'] = sprintf('mod_service%s_activated', $orderArr['service_type']);
            $email['service'] = $s;
            $email['order'] = $orderArr;

            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public static function onAfterAdminOrderRenew(\Box_Event $event)
    {
        $params = $event->getParameters();
        $order_id = $params['id'];
        $di = $event->getDi();
        $orderService = $di['mod_service']('order');

        try {
            $order = $di['db']->getExistingModelById('ClientOrder', $order_id, 'Order not found');
            $identity = $di['loggedin_admin'];
            $service = $orderService->getOrderServiceData($order, $identity);
            $orderArr = $orderService->toApiArray($order, true, $identity);

            $email = [];
            $email['to_client'] = $orderArr['client']['id'];
            $email['code'] = sprintf('mod_service%s_renewed', $orderArr['service_type']);
            $email['service'] = $service;
            $email['order'] = $orderArr;

            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public static function onAfterAdminOrderSuspend(\Box_Event $event)
    {
        $params = $event->getParameters();
        $order_id = $params['id'];
        $di = $event->getDi();
        $service = $di['mod_service']('order');

        try {
            $order = $di['db']->getExistingModelById('ClientOrder', $order_id, 'Order not found');
            $identity = $di['loggedin_admin'];
            $s = $service->getOrderServiceData($order, $identity);
            $orderArr = $service->toApiArray($order, true, $identity);

            $email = [];
            $email['to_client'] = $orderArr['client']['id'];
            $email['code'] = sprintf('mod_service%s_suspended', $orderArr['service_type']);
            $email['service'] = $s;
            $email['order'] = $orderArr;

            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public static function onAfterAdminOrderUnsuspend(\Box_Event $event)
    {
        $params = $event->getParameters();
        $order_id = $params['id'];
        $di = $event->getDi();
        $service = $di['mod_service']('order');

        try {
            $order = $di['db']->getExistingModelById('ClientOrder', $order_id, 'Order not found');
            $identity = $di['loggedin_admin'];
            $s = $service->getOrderServiceData($order, $identity);
            $orderArr = $service->toApiArray($order, true, $identity);

            $email = [];
            $email['to_client'] = $orderArr['client']['id'];
            $email['code'] = sprintf('mod_service%s_unsuspended', $orderArr['service_type']);
            $email['service'] = $s;
            $email['order'] = $orderArr;

            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public static function onAfterAdminOrderCancel(\Box_Event $event)
    {
        $params = $event->getParameters();
        $order_id = $params['id'];
        $di = $event->getDi();
        $service = $di['mod_service']('order');

        try {
            $order = $di['db']->getExistingModelById('ClientOrder', $order_id, 'Order not found');
            $identity = $di['loggedin_admin'];
            $orderArr = $service->toApiArray($order, true, $identity);

            $email = [];
            $email['to_client'] = $orderArr['client']['id'];
            $email['code'] = sprintf('mod_service%s_canceled', $orderArr['service_type']);
            $email['order'] = $orderArr;

            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public static function onAfterAdminOrderUncancel(\Box_Event $event)
    {
        $params = $event->getParameters();
        $order_id = $params['id'];
        $di = $event->getDi();
        $service = $di['mod_service']('order');

        try {
            $order = $di['db']->getExistingModelById('ClientOrder', $order_id, 'Order not found');
            $identity = $di['loggedin_admin'];
            $s = $service->getOrderServiceData($order, $identity);
            $orderArr = $service->toApiArray($order, true, $identity);

            $email = [];
            $email['to_client'] = $orderArr['client']['id'];
            $email['code'] = sprintf('mod_service%s_renewed', $orderArr['service_type']);
            $email['order'] = $orderArr;
            $email['service'] = $s;

            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public function getOrderService(\Model_ClientOrder $order)
    {
        if ($order->service_id !== null) {
            // @deprecated
            // @todo remove this when doctrine is removed
            $core_services = [
                \Model_ProductTable::CUSTOM,
                \Model_ProductTable::LICENSE,
                \Model_ProductTable::DOWNLOADABLE,
                \Model_ProductTable::HOSTING,
                \Model_ProductTable::MEMBERSHIP,
                \Model_ProductTable::DOMAIN,
            ];
            if (in_array($order->service_type, $core_services)) {
                $repo_class = $this->_getServiceClassName($order);

                return $this->di['db']->load($repo_class, $order->service_id);
            } else {
                return $this->di['db']->findOne(
                    'service_' . $order->service_type,
                    'id = :id',
                    [':id' => $order->service_id]
                );
            }
        }

        return null;
    }

    protected function _getServiceClassName(\Model_ClientOrder $order)
    {
        $s = $this->di['tools']->to_camel_case($order->service_type, true);

        return 'Service' . ucfirst($s);
    }

    public function getServiceOrder($service)
    {
        $type = $this->di['tools']->from_camel_case(str_replace('Model_Service', '', $service::class));

        $bindings = [
            'service_type' => $type,
            ':service_id' => $service->id,
        ];

        return $this->di['db']->findOne('ClientOrder', 'service_type  = :service_type AND service_id = :service_id', $bindings);
    }

    public function getConfig(\Model_ClientOrder $model)
    {
        if (is_string($model->config) && json_validate($model->config)) {
            return json_decode($model->config, true);
        }

        return [];
    }

    public function productHasOrders(\Model_Product $product)
    {
        $order = $this->di['db']->findOne('ClientOrder', 'product_id = :product_id', [':product_id' => $product->id]);

        return $order instanceof \Model_ClientOrder;
    }

    public function getLogger(\Model_ClientOrder $order)
    {
        $log = $this->di['logger'];
        $log->addWriter(new \Box_LogDb('Model_ClientOrderStatus'));
        $log->setEventItem('client_order_id', $order->id);
        $log->setEventItem('status', $order->status);

        return $log;
    }

    /**
     * @param string $notes
     */
    public function saveStatusChange(\Model_ClientOrder $order, $notes = null)
    {
        $os = $this->di['db']->dispense('ClientOrderStatus');
        $os->client_order_id = $order->id;
        $os->status = $order->status;
        $os->notes = $notes;
        $os->created_at = date('Y-m-d H:i:s');
        $os->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($os);
    }

    public function getSoonExpiringActiveOrders()
    {
        [$query, $bindings] = $this->getSoonExpiringActiveOrdersQuery();

        return $this->di['db']->getAll($query, $bindings);
    }

    public function getSoonExpiringActiveOrdersQuery($data = [])
    {
        $systemService = $this->di['mod_service']('system');
        $days_until_expiration = $systemService->getParamValue('invoice_issue_days_before_expire', 14);

        $client_id = $data['client_id'] ?? null;

        $query = 'SELECT *
                FROM client_order
                WHERE status = :status
                AND invoice_option = :invoice_option
                AND period IS NOT NULL
                AND expires_at IS NOT NULL
                AND unpaid_invoice_id IS NULL';

        $where = [];
        $bindings = [];

        if ($client_id !== null) {
            $where[] = 'client_id = :client_id';
            $bindings[':client_id'] = $client_id;
        }

        if (!empty($where)) {
            $query = $query . ' AND ' . implode(' AND ', $where);
        }

        $query .= ' HAVING DATEDIFF(expires_at, NOW()) <= :days_until_expiration ORDER BY client_id DESC';
        $bindings[':status'] = \Model_ClientOrder::STATUS_ACTIVE;
        $bindings[':invoice_option'] = 'issue-invoice';
        $bindings[':days_until_expiration'] = $days_until_expiration;

        return [$query, $bindings];
    }

    public function toApiArray(\Model_ClientOrder $model, $deep = true, $identity = null)
    {
        $clientService = $this->di['mod_service']('client');
        $supportService = $this->di['mod_service']('support');

        $data = $this->di['db']->toArray($model);
        if (!empty($model->config) && json_validate($model->config)) {
            $data['config'] = json_decode($model->config, true);
        } else {
            $data['config'] = [];
        }
        $data['total'] = $this->getTotal($model);
        $data['title'] = $model->title;
        $data['meta'] = $this->di['db']->getAssoc('SELECT name, value FROM client_order_meta WHERE client_order_id = :id', [':id' => $model->id]);
        $data['active_tickets'] = $supportService->getActiveTicketsCountForOrder($model);
        $client = $this->di['db']->getExistingModelById('Client', $model->client_id, 'Client not found');
        $data['client'] = $clientService->toApiArray($client, false);

        if ($identity instanceof \Model_Admin) {
            $data['config'] = $this->getConfig($model);
            $productModel = $this->di['db']->load('Product', $model->product_id);
            if ($productModel instanceof \Model_Product) {
                $data['plugin'] = $productModel->plugin;
            } else {
                $data['plugin'] = null;
            }
        }

        $client = $this->di['db']->getExistingModelById('Client', $model->client_id, 'Client not found');
        $data['client'] = $clientService->toApiArray($client, false);

        return $data;
    }

    public function getSearchQuery($data)
    {
        $query = 'SELECT co.* from client_order co
                LEFT JOIN client c ON c.id = co.client_id
                LEFT JOIN client_order_meta meta ON meta.client_order_id = co.id';

        $search = $data['search'] ?? false;
        $hide_addons = $data['hide_addons'] ?? null;
        $show_action_required = $data['show_action_required'] ?? null;
        $id = $data['id'] ?? null;
        $product_id = $data['product_id'] ?? null;
        $status = $data['status'] ?? null;
        $title = $data['title'] ?? null;
        $period = $data['period'] ?? null;
        $type = $data['type'] ?? null;
        $created_at = $data['created_at'] ?? null;
        $date_from = $data['date_from'] ?? null;
        $date_to = $data['date_to'] ?? null;
        $ids = (isset($data['ids']) && is_array($data['ids'])) ? $data['ids'] : null;
        $meta = (isset($data['meta']) && is_array($data['meta'])) ? $data['meta'] : null;

        $client_id = $data['client_id'] ?? null;
        $invoice_option = $data['invoice_option'] ?? null;

        $where = [];
        $bindings = [];

        if ($client_id) {
            $where[] = 'co.client_id = :client_id';
            $bindings[':client_id'] = $client_id;
        }

        if ($invoice_option) {
            $where[] = 'co.invoice_option = :invoice_option';
            $bindings[':invoice_option'] = $invoice_option;
        }

        if ($id) {
            $where[] = 'co.id = :id';
            $bindings[':id'] = $id;
        }

        if ($show_action_required) {
            $where[] = 'co.status = \'pending_setup\' OR co.status = \'failed_setup\' OR co.status =\'failed_renew\'';
        }

        if ($status) {
            $where[] = 'co.status = :status';
            $bindings[':status'] = $status;
        }

        if ($product_id) {
            $where[] = 'co.product_id = :product_id';
            $bindings[':product_id'] = $product_id;
        }

        if ($type) {
            $where[] = 'co.service_type = :service_type';
            $bindings[':service_type'] = $type;
        }

        if ($title) {
            $where[] = 'co.title LIKE :title';
            $bindings[':title'] = '%' . $title . '%';
        }

        if ($period) {
            $where[] = 'co.period = :period';
            $bindings[':period'] = $period;
        }

        if ($hide_addons) {
            $where[] = 'co.group_master = 1';
        }

        if ($created_at) {
            $where[] = "DATE_FORMAT(co.created_at, '%Y-%m-%d') = :created_at";
            $bindings[':created_at'] = date('Y-m-d', strtotime($created_at));
        }

        if ($date_from) {
            $where[] = 'UNIX_TIMESTAMP(co.created_at) >= :date_from';
            $bindings[':date_from'] = strtotime($date_from);
        }

        if ($date_to) {
            $where[] = 'UNIX_TIMESTAMP(co.created_at) <= :date_to';
            $bindings[':date_to'] = strtotime($date_to);
        }

        // smartSearch
        if ($search) {
            if (is_numeric($search)) {
                $where[] = 'co.id = :search';
                $bindings[':search'] = $search;
            } else {
                $where[] = '(c.first_name LIKE :first_name OR c.last_name LIKE :last_name OR co.title LIKE :title)';
                $bindings[':first_name'] = "%$search%";
                $bindings[':last_name'] = "%$search%";
                $bindings[':title'] = "%$search%";
            }
        }

        if ($ids) {
            $where[] = 'co.id IN (:ids)';
            $bindings[':ids'] = implode(', ', $ids);
        }
        if ($meta) {
            $i = 1;
            foreach ($meta as $k => $v) {
                $where[] = "(meta.name = :meta_name$i AND meta.value LIKE :meta_value$i)";
                $bindings[':meta_name' . $i] = $k;
                $bindings[':meta_value' . $i] = $v . '%';
                ++$i;
            }
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }
        $query .= ' ORDER BY co.id DESC';

        return [$query, $bindings];
    }

    public function createOrder(\Model_Client $client, \Model_Product $product, array $data)
    {
        $currencyService = $this->di['mod_service']('currency');
        if (isset($data['currency']) && !empty($data['currency'])) {
            $currency = $currencyService->getByCode($data['currency']);
        } elseif ($client->currency) {
            $currency = $currencyService->getByCode($client->currency);
        } else {
            $currency = $currencyService->getDefault();
        }
        if (!$currency instanceof \Model_Currency) {
            throw new \FOSSBilling\Exception('Currency could not be determined for order');
        }

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminOrderCreate', 'params' => $data, 'subject' => $product->type]);

        $period = (isset($data['period']) && !empty($data['period'])) ? $data['period'] : null;
        $qty = $data['quantity'] ?? 1;
        $config = (isset($data['config']) && is_array($data['config'])) ? $data['config'] : [];
        $group_id = $data['group_id'] ?? null;
        $activate = (bool) ($data['activate'] ?? false);
        $invoiceOption = $data['invoice_option'] ?? 'no-invoice';
        $skipValidation = (bool) ($data['skip_validation'] ?? false);

        $cartService = $this->di['mod_service']('cart');
        // check stock
        if (!$cartService->isStockAvailable($product, $qty)) {
            throw new InformationException('Product :id is out of stock.', [':id' => $product->id], 831);
        }

        // Addons must have defined master order
        $parent_order = false;
        if ($product->is_addon && empty($group_id)) {
            throw new \FOSSBilling\Exception('Group ID parameter is missing for addon product order', null, 832);
        }

        if (!empty($group_id)) {
            $parent_order = $this->getMasterOrderForClient($client, $group_id);
            if (!$parent_order instanceof \Model_ClientOrder) {
                throw new \FOSSBilling\Exception('Parent order :group_id was not found', [':group_id' => $group_id]);
            }
        }

        // validate order config data
        if ($period) {
            $config['period'] = $period;
        }
        $se = $this->di['mod_service']('service' . $product->type);
        // @deprecated logic
        if (method_exists($se, 'prependOrderConfig')) {
            $config = $se->prependOrderConfig($product, $config);
        }

        // @migration script
        $se = $this->di['mod_service']('service' . $product->type);
        if (method_exists($se, 'attachOrderConfig')) {
            $config = $se->attachOrderConfig($product, $config);
        }

        if (method_exists($se, 'validateOrderData')) {
            if (!$skipValidation) {
                $se->validateOrderData($config);
            }
        }

        if (method_exists($se, 'generateOrderTitle')) {
            $generatedOrderTitle = $se->generateOrderTitle($config);
        } else {
            $generatedOrderTitle = null;
        }

        $order = $this->di['db']->dispense('ClientOrder');
        $order->client_id = $client->id;
        $order->product_id = $product->id;
        $order->group_id = ($parent_order) ? $parent_order->group_id : uniqid();
        $order->group_master = ($parent_order) ? 0 : 1;
        $order->title = $generatedOrderTitle ?? $data['title'] ?? $product->title;
        $order->currency = $currency->code;
        $order->quantity = $qty;
        $order->service_type = $product->type;
        $order->unit = $product->unit;
        $order->status = \Model_ClientOrder::STATUS_PENDING_SETUP;
        $order->config = json_encode($config);
        $order->invoice_option = $invoiceOption;

        if ($period) {
            $bp = $this->di['period']($data['period']);
            $order->period = $bp->getCode();
        }

        if (isset($data['price'])) {
            $order->price = $data['price'];
        } else {
            $repo = $product->getTable();
            $rate = $currencyService->getRateByCode($currency->code);
            $order->price = $repo->getProductPrice($product, $config) * $rate;
        }

        $order->notes = $data['notes'] ?? $order->notes;
        if (isset($data['created_at'])) {
            $order->created_at = date('Y-m-d H:i:s', strtotime($data['created_at']));
        } else {
            $order->created_at = date('Y-m-d H:i:s');
        }

        if (isset($data['updated_at'])) {
            $order->updated_at = date('Y-m-d H:i:s', strtotime($data['updated_at']));
        } else {
            $order->updated_at = date('Y-m-d H:i:s');
        }

        $id = $this->di['db']->store($order);

        if (isset($data['meta']) && is_array($data['meta'])) {
            foreach ($data['meta'] as $k => $v) {
                $mm = $this->di['db']->findOne('client_order_meta', 'client_order_id = :id AND name = :n', [':id' => $order->id, ':n' => $k]);
                if (!$mm) {
                    $mm = $this->di['db']->dispense('ClientOrderMeta');
                    $mm->client_order_id = $id;
                    $mm->name = $k;
                    $mm->created_at = date('Y-m-d H:i:s');
                }
                $mm->value = $v;
                $mm->updated_at = date('Y-m-d H:i:s');
                $this->di['db']->store($mm);
            }
        }

        $this->di['events_manager']->fire(['event' => 'onAfterAdminOrderCreate', 'params' => ['id' => $order->id], 'subject' => $product->type]);

        $this->di['logger']->info('Created order #%s', $id);

        // invoice options
        if ($invoiceOption == 'issue-invoice' && $order->price > 0) {
            $invoiceService = $this->di['mod_service']('invoice');
            $invoice = $invoiceService->generateForOrder($order);

            $invoiceService->approveInvoice($invoice, ['id' => $invoice->id, 'use_credits' => true]);
        }

        // activate immediately if say so
        if ($activate) {
            try {
                $this->activateOrder($order);
            } catch (\Exception $e) {
                error_log($e->getMessage());
            }
        }

        return $id;
    }

    public function getMasterOrderForClient(\Model_Client $client, $group_id)
    {
        $bindings = [
            ':group_id' => $group_id,
            ':client_id' => $client->id,
        ];

        return $this->di['db']->findOne('ClientOrder', 'group_id = :group_id AND group_master = 1 AND client_id = :client_id', $bindings);
    }

    /**
     * activate all addons on initial activation.
     *
     * @see https://github.com/boxbilling/boxbilling/issues/54
     */
    public function activateOrderAddons(\Model_ClientOrder $order)
    {
        if (!$order->group_master) {
            return false;
        }

        $list = $this->getOrderAddonsList($order);
        foreach ($list as $addon) {
            try {
                $this->di['events_manager']->fire(['event' => 'onBeforeAdminOrderActivate', 'params' => ['id' => $addon->id]]);
                $this->createFromOrder($addon);
                $this->di['events_manager']->fire(['event' => 'onAfterAdminOrderActivate', 'params' => ['id' => $addon->id]]);
            } catch (\Exception $e) {
                error_log($e->getMessage());
            }
        }

        return true;
    }

    public function activateOrder(\Model_ClientOrder $order, $data = [])
    {
        $statues = [
            \Model_ClientOrder::STATUS_PENDING_SETUP,
            \Model_ClientOrder::STATUS_FAILED_SETUP,
        ];
        if (!in_array($order->status, $statues)) {
            if (!isset($data['force']) || !$data['force']) {
                throw new \FOSSBilling\Exception('Only pending setup or failed orders can be activated');
            }
        }

        $event_params = ['id' => $order->id];
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminOrderActivate', 'params' => $event_params]);
        $result = $this->createFromOrder($order);
        if (is_array($result)) {
            $event_params = [...$event_params, ...$result];
        }
        $this->di['events_manager']->fire(['event' => 'onAfterAdminOrderActivate', 'params' => $event_params]);

        $this->activateOrderAddons($order);

        $this->di['logger']->info('Activated order #%s', $order->id);

        return true;
    }

    public function createFromOrder(\Model_ClientOrder $order)
    {
        $service = $this->getOrderService($order);
        if (!is_object($service)) {
            $mod = $this->di['mod']('service' . $order->service_type);
            $s = $mod->getService();
            if (method_exists($s, 'create') || method_exists($s, 'action_create')) {
                $service = $this->_callOnService($order, \Model_ClientOrder::ACTION_CREATE);
                if (!is_object($service)) {
                    throw new \FOSSBilling\Exception('Error creating ' . $order->service_type . ' service for order ' . $order->id);
                }

                $order->service_id = $service->id;
                $order->updated_at = date('Y-m-d H:i:s');
                $this->di['db']->store($order);
            }
        }

        try {
            $result = $this->_callOnService($order, \Model_ClientOrder::ACTION_ACTIVATE);
        } catch (\Exception $e) {
            $order->status = \Model_ClientOrder::STATUS_FAILED_SETUP;
            $this->di['db']->store($order);

            $this->saveStatusChange($order, $e->getMessage());

            throw $e;
        }

        // set automatic order expiration
        if (!empty($order->period)) {
            $from_time = ($order->expires_at === null) ? time() : strtotime($order->expires_at);

            $period = $this->di['period']($order->period);
            $order->expires_at = date('Y-m-d H:i:s', $period->getExpirationTime($from_time));
        }

        $order->status = \Model_ClientOrder::STATUS_ACTIVE;
        $order->activated_at = date('Y-m-d H:i:s');
        $order->suspended_at = null;
        $order->canceled_at = null;
        $order->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($order);

        $productModel = $this->di['db']->load('Product', $order->product_id);
        if ($productModel instanceof \Model_Product) {
            $this->stockSale($productModel, $order->quantity);
        } else {
            error_log(sprintf('Order without product ID detected Order #%s', $order->id));
        }

        $this->saveStatusChange($order, 'Order activated');

        return $result;
    }

    public function getOrderAddonsList(\Model_ClientOrder $order)
    {
        return $this->di['db']->find('ClientOrder', 'group_id = :group_id AND client_id = :client_id and (group_master = 0 OR group_master IS NULL)', [':group_id' => $order->group_id, ':client_id' => $order->client_id]);
    }

    protected function _callOnService(\Model_ClientOrder $order, $action)
    {
        $repo = $this->di['mod_service']('service' . $order->service_type);
        // @deprecated
        // @todo remove this when doctrine is removed
        $core_services = [
            \Model_ProductTable::CUSTOM,
            \Model_ProductTable::LICENSE,
            \Model_ProductTable::DOWNLOADABLE,
            \Model_ProductTable::HOSTING,
            \Model_ProductTable::MEMBERSHIP,
            \Model_ProductTable::DOMAIN,
        ];

        if (in_array($order->service_type, $core_services)) {
            $m = 'action_' . $action;
            if (!method_exists($repo, $m) || !is_callable([$repo, $m])) {
                throw new \FOSSBilling\Exception('Service ' . $order->service_type . ' do not support ' . $m);
            }

            return $repo->$m($order);
        } else {
            // @new logic for services
            $o = $this->di['db']->findOne(
                'client_order',
                'id = :id',
                [':id' => $order->id]
            );
            $service = null;
            $sdbname = 'service_' . $order->service_type;
            if ($order->service_id) {
                $service = $this->di['db']->load($sdbname, $order->service_id);
            }
            if (method_exists($repo, $action) && is_callable([$repo, $action])) {
                return $repo->$action($o, $service);
            }
        }
        error_log(sprintf('Service %s does not support action %s', $order->service_type, $action));

        return null;
    }

    public function stockSale(\Model_Product $product, $qty)
    {
        if ($product->stock_control) {
            $product->quantity_in_stock -= $qty;
            $product->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($product);
        }

        return true;
    }

    /**
     * @return int
     */
    public function updatePeriod(\Model_ClientOrder $order, $period)
    {
        if (!empty($period)) {
            $period = $this->di['period']($period);
            $order->period = $period->getCode();

            return 1;
        }

        if (!is_null($period)) {
            $order->period = null;

            return 2;
        }

        return 0;
    }

    /**
     * @return int
     */
    public function updateOrderMeta(\Model_ClientOrder $order, $meta)
    {
        if (!is_array($meta)) {
            return 0;
        }

        if (empty($meta)) {
            $this->di['db']->exec('DELETE FROM client_order_meta WHERE client_order_id = :id;', [':id' => $order->id]);

            return 1;
        }
        foreach ($meta as $k => $v) {
            $mm = $this->di['db']->findOne('ClientOrderMeta', 'client_order_id = :id AND name = :n', [':id' => $order->id, ':n' => $k]);
            if (!$mm) {
                $mm = $this->di['db']->dispense('ClientOrderMeta');
                $mm->client_order_id = $order->id;
                $mm->name = $k;
                $mm->created_at = date('Y-m-d H:i:s');
            }
            $mm->value = $v;
            $mm->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($mm);
        }

        return 2;
    }

    public function updateOrder(\Model_ClientOrder $order, array $data)
    {
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminOrderUpdate', 'params' => $data]);
        $this->updatePeriod($order, $data['period'] ?? null);

        $created_at = $data['created_at'] ?? '';
        if (!empty($created_at)) {
            $order->created_at = date('Y-m-d H:i:s', strtotime($created_at));
        }

        $activated_at = $data['activated_at'] ?? null;
        if (!empty($activated_at)) {
            $order->activated_at = date('Y-m-d H:i:s', strtotime($activated_at));
        }

        $expires_at = $data['expires_at'] ?? null;
        if (!empty($expires_at)) {
            $order->expires_at = date('Y-m-d H:i:s', strtotime($expires_at));
        }
        if (empty($expires_at) && !is_null($expires_at)) {
            $order->expires_at = null;
        }

        $order->invoice_option = $data['invoice_option'] ?? $order->invoice_option;
        $order->title = $data['title'] ?? $order->title;
        $order->price = $data['price'] ?? $order->price;
        $order->status = $data['status'] ?? $order->status;
        $order->notes = $data['notes'] ?? $order->notes;
        $order->reason = $data['reason'] ?? $order->reason;

        $this->updateOrderMeta($order, $data['meta'] ?? null);

        $order->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($order);

        $this->di['events_manager']->fire(['event' => 'onAfterAdminOrderUpdate', 'params' => ['id' => $order->id]]);

        $this->di['logger']->info('Update order #%s', $order->id);

        return true;
    }

    public function renewOrder(\Model_ClientOrder $order)
    {
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminOrderRenew', 'params' => ['id' => $order->id]]);

        $this->renewFromOrder($order);

        // activate all addons on initial activation
        // @see https://github.com/boxbilling/boxbilling/issues/54
        if ($order->group_master && $order->status == \Model_ClientOrder::STATUS_PENDING_SETUP) {
            $list = $this->getOrderAddonsList($order);
            foreach ($list as $addon) {
                try {
                    $this->renewFromOrder($addon);
                } catch (\Exception $e) {
                    error_log($e->getMessage());
                }
            }
        }

        $this->di['events_manager']->fire(['event' => 'onAfterAdminOrderRenew', 'params' => ['id' => $order->id]]);
        $this->di['logger']->info('Renewed order #%s', $order->id);

        return true;
    }

    public function renewFromOrder(\Model_ClientOrder $order)
    {
        // renew order, update order history if failure on renewal
        try {
            $result = $this->_callOnService($order, \Model_ClientOrder::ACTION_RENEW);
        } catch (\Exception $e) {
            $order->status = \Model_ClientOrder::STATUS_FAILED_RENEW;
            $this->di['db']->store($order);

            $this->saveStatusChange($order, $e->getMessage());

            throw $e;
        }

        // set automatic order expiration
        if (!empty($order->period)) {
            $from_time = ($order->expires_at === null) ? time() : strtotime($order->expires_at); // from expiration date

            $config = $this->di['mod_config']('order');
            $logic = $config['order_renewal_logic'] ?? '';

            if ($logic == 'from_today') {
                $from_time = time(); // renew order from the date renewal occurred
            } elseif ($logic == 'from_greater') {
                if (strtotime($order->expires_at) > time()) {
                    $from_time = strtotime($order->expires_at);
                } else {
                    $from_time = time();
                }
            }
            $period = $this->di['period']($order->period);
            $order->expires_at = date('Y-m-d H:i:s', $period->getExpirationTime($from_time));
        }

        $order->status = \Model_ClientOrder::STATUS_ACTIVE;
        $order->suspended_at = null;
        $order->unsuspended_at = null;
        $order->canceled_at = null;
        $order->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($order);

        $this->saveStatusChange($order, 'Order renewed');
    }

    public function suspendFromOrder(\Model_ClientOrder $order, $reason = null, $skipEvent = false)
    {
        if (!$skipEvent) {
            $this->di['events_manager']->fire(['event' => 'onBeforeAdminOrderSuspend', 'params' => ['id' => $order->id]]);
        }

        if ($order->status != \Model_ClientOrder::STATUS_ACTIVE) {
            throw new InformationException('Only active orders can be suspended');
        }

        $this->_callOnService($order, \Model_ClientOrder::ACTION_SUSPEND);

        $order->status = \Model_ClientOrder::STATUS_SUSPENDED;
        $order->reason = $reason;
        $order->suspended_at = date('Y-m-d H:i:s');
        $order->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($order);

        $note = ($reason === null) ? 'Order suspended' : 'Order suspended for ' . $reason;
        $this->saveStatusChange($order, $note);

        if (!$skipEvent) {
            $this->di['events_manager']->fire(['event' => 'onAfterAdminOrderSuspend', 'params' => ['id' => $order->id]]);
        }

        $this->di['logger']->info('Suspended order #%s', $order->id);

        return true;
    }

    public function unsuspendFromOrder(\Model_ClientOrder $order)
    {
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminOrderUnsuspend', 'params' => ['id' => $order->id]]);

        $this->_callOnService($order, \Model_ClientOrder::ACTION_UNSUSPEND);

        $order->status = \Model_ClientOrder::STATUS_ACTIVE;
        $order->reason = null;
        $order->suspended_at = null;
        $order->unsuspended_at = date('Y-m-d H:i:s');
        $order->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($order);

        $this->saveStatusChange($order, 'Order unsuspended');

        $this->di['events_manager']->fire(['event' => 'onAfterAdminOrderUnsuspend', 'params' => ['id' => $order->id]]);

        $this->di['logger']->info('Unsuspended order #%s', $order->id);

        return true;
    }

    public function cancelFromOrder(\Model_ClientOrder $order, $reason = null, $skipEvent = false)
    {
        if (!$skipEvent) {
            $this->di['events_manager']->fire(['event' => 'onBeforeAdminOrderCancel', 'params' => ['id' => $order->id]]);
        }

        if (in_array($order->status, [\Model_ClientOrder::STATUS_CANCELED, \Model_ClientOrder::STATUS_PENDING_SETUP, \Model_ClientOrder::STATUS_FAILED_SETUP])) {
            throw new \FOSSBilling\Exception('Cannot cancel ' . $order->status . ' order');
        }

        $this->_callOnService($order, \Model_ClientOrder::ACTION_CANCEL);

        $order->status = \Model_ClientOrder::STATUS_CANCELED;
        $order->reason = $reason;
        $order->canceled_at = date('Y-m-d H:i:s');
        $order->expires_at = null;
        $order->suspended_at = null;
        $order->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($order);

        $note = ($reason === null) ? 'Order canceled' : 'Canceled order for ' . $reason;
        $this->saveStatusChange($order, $note);

        if (!$skipEvent) {
            $this->di['events_manager']->fire(['event' => 'onAfterAdminOrderCancel', 'params' => ['id' => $order->id]]);
        }

        $this->di['logger']->info('Canceled order #%s', $order->id);

        return true;
    }

    public function uncancelFromOrder(\Model_ClientOrder $order)
    {
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminOrderUncancel', 'params' => ['id' => $order->id]]);

        $this->_callOnService($order, \Model_ClientOrder::ACTION_UNCANCEL);

        $expires_at = null;
        if ($order->period) {
            $period = $this->di['period']($order->period);
            $expires_at = $period->getExpirationTime(time());
            $expires_at = date('Y-m-d H:i:s', $expires_at);
        }

        $order->status = \Model_ClientOrder::STATUS_ACTIVE;
        $order->reason = null;
        $order->activated_at = date('Y-m-d H:i:s');
        $order->expires_at = $expires_at;
        $order->suspended_at = null;
        $order->canceled_at = null;
        $order->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($order);

        $this->saveStatusChange($order, 'Activated canceled order');

        $this->di['events_manager']->fire(['event' => 'onAfterAdminOrderUncancel', 'params' => ['id' => $order->id]]);

        $this->di['logger']->info('Uncanceled order #%s', $order->id);

        return true;
    }

    public function rmInvoiceItemByOrder(\Model_ClientOrder $order)
    {
        $bindings = [
            ':rel_id' => $order->id,
            ':status' => \Model_InvoiceItem::STATUS_PENDING_PAYMENT,
        ];

        $items = $this->di['db']->find('InvoiceItem', 'rel_id = :rel_id AND status = :status', $bindings);
        foreach ($items as $item) {
            if ($item instanceof \Model_InvoiceItem) {
                $this->di['db']->trash($item);
            }
        }
    }

    public function rmClientOrderStatusByOrder(\Model_ClientOrder $order)
    {
        $this->di['db']->exec('Delete FROM client_order_status WHERE client_order_id = :client_order_id', [':client_order_id' => $order->id]);
    }

    public function rmOrder(\Model_ClientOrder $model)
    {
        if ($model->group_master) {
            // set addons as separate orders
            $list = $this->getOrderAddonsList($model);
            foreach ($list as $addon) {
                $addon->group_master = 1;
                $addon->group_id = 0;
                $this->di['db']->store($addon);
            }
        }
        $this->di['db']->trash($model);
    }

    public function deleteFromOrder(\Model_ClientOrder $order, bool $forceDelete = false)
    {
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminOrderDelete', 'params' => ['id' => $order->id]]);

        if ($order->status == \Model_ClientOrder::STATUS_PENDING_SETUP) {
            $this->rmInvoiceItemByOrder($order);
        }

        try {
            $this->_callOnService($order, \Model_ClientOrder::ACTION_DELETE);
        } catch (\Exception $e) {
            if (!$forceDelete) {
                throw $e;
            } else {
                error_log($e->getMessage() . 'in ' . $e->getFile() . ':' . $e->getFile());
            }
        }

        $id = $order->id;
        $this->rmClientOrderStatusByOrder($order);
        $this->rmOrder($order);

        $this->di['events_manager']->fire(['event' => 'onAfterAdminOrderDelete', 'params' => ['id' => $id]]);
        $this->di['logger']->info('Deleted order #%s', $id);

        return true;
    }

    public function getExpiredOrders()
    {
        $bindings = [
            ':status' => \Model_ClientOrder::STATUS_ACTIVE,
        ];

        return $this->di['db']->find('ClientOrder', 'status = :status AND expires_at IS NOT NULL AND DATEDIFF(NOW(), expires_at) >= 1 ORDER BY id', $bindings);
    }

    public function batchSuspendExpired()
    {
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminBatchSuspendOrders']);

        $mod = $this->di['mod']('order');
        $c = $mod->getConfig();

        $reason = $c['batch_suspend_reason'] ?? null;

        $list = $this->getExpiredOrders();

        foreach ($list as $order) {
            try {
                $this->suspendFromOrder($order, $reason);
            } catch (\Exception $e) {
                error_log($e->getMessage());
            }
        }

        $this->di['events_manager']->fire(['event' => 'onAfterAdminBatchSuspendOrders']);

        $this->di['logger']->info('Executed action to suspend expired orders');

        return true;
    }

    public function batchCancelSuspended()
    {
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminBatchCancelSuspendedOrders']);

        $mod = $this->di['mod']('order');
        $config = $mod->getConfig();
        if (!isset($config['batch_cancel_suspended']) || !$config['batch_cancel_suspended']) {
            return false;
        }

        $reason = $config['batch_cancel_suspended_reason'] ?? null;
        $days = isset($config['batch_cancel_suspended_after_days']) ? (int) $config['batch_cancel_suspended_after_days'] : 7;

        if ($days < 0) {
            $days = 7;
        }

        $sql = "
            SELECT id, suspended_at, DATEDIFF(NOW(), suspended_at) as days_passed_since_suspension
            FROM client_order
            WHERE status = 'suspended'
            AND DATEDIFF(NOW(), suspended_at) > :days
            ORDER BY id DESC
        ";

        $orders = $this->di['db']->getAll($sql, [':days' => $days]);

        foreach ($orders as $orderArr) {
            try {
                $order = $this->di['db']->getExistingModelById('ClientOrder', $orderArr['id'], 'Order not found');
                $this->cancelFromOrder($order, $reason);
            } catch (\Exception $e) {
                error_log($e);
            }
        }

        $this->di['events_manager']->fire(['event' => 'onAfterAdminBatchCancelSuspendedOrders']);

        $this->di['logger']->info('Executed action to cancel suspended orders');

        return true;
    }

    public function updateOrderConfig(\Model_ClientOrder $order, array $config)
    {
        $oldConfig = $order->config;

        $order->config = json_encode($config);
        $order->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($order);

        $this->di['logger']->info(sprintf("Order #%s config changes:\n%s\n%s", $order->id, $oldConfig, $order->config));

        return true;
    }

    public function getOrderStatusSearchQuery($data)
    {
        $query = 'SELECT * FROM client_order_status';

        $oid = $data['client_order_id'] ?? null;

        $where = [];
        $bindings = [];

        if ($oid !== null) {
            $where[] = 'client_order_id = :client_order_id';

            $bindings[':client_order_id'] = $oid;
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }

        $query .= ' ORDER BY id DESC';

        return [$query, $bindings];
    }

    public function orderStatusAdd(\Model_ClientOrder $order, $status, $notes = null)
    {
        $bean = $this->di['db']->dispense('ClientOrderStatus');
        $bean->client_order_id = $order->id;
        $bean->status = $status;
        $bean->notes = $notes;
        $bean->created_at = date('Y-m-d H:i:s');
        $bean->updated_at = date('Y-m-d H:i:s');
        $id = $this->di['db']->store($bean);

        $this->di['logger']->info('Added order status history message to order #%s', $id);

        return true;
    }

    public function orderStatusRm($id)
    {
        $orderStatus = $this->di['db']->getExistingModelById('ClientOrderStatus', $id, 'Order history line not found');

        $this->di['db']->trash($orderStatus);

        $this->di['logger']->info('Order history line removed');

        return true;
    }

    public function findForClientById(\Model_Client $client, $id)
    {
        $bindings = [
            ':id' => $id,
            ':client_id' => $client->id,
        ];

        return $this->di['db']->findOne('ClientOrder', 'id = :id AND client_id = :client_id', $bindings);
    }

    public function getOrderServiceData(\Model_ClientOrder $order, $identity = null)
    {
        $orderId = $order->id;
        $service = $this->getOrderService($order);
        if (!is_object($service)) {
            error_log(sprintf('Order #%s has no active service', $orderId));

            return null;
        }
        $srepo = $this->di['mod_service']('service' . $order->service_type);
        if (!method_exists($srepo, 'toApiArray')) {
            error_log(sprintf('service #%s method toApiArray is missing', $order->service_type));

            return null;
        }

        return $srepo->toApiArray($service, true, $identity);
    }

    public function getTotal(\Model_ClientOrder $model)
    {
        return $model->price * $model->quantity;
    }

    public function setUnpaidInvoice(\Model_ClientOrder $order, \Model_Invoice $proforma)
    {
        $order->unpaid_invoice_id = $proforma->id;
        $order->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($order);
    }

    public function unsetUnpaidInvoice(\Model_ClientOrder $order)
    {
        $order->unpaid_invoice_id = null;
        $order->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($order);
    }

    public function getRelatedOrderIdByType(\Model_ClientOrder $order, $type)
    {
        $bindings = [
            ':group_id' => $order->group_id,
            ':service_type' => $type,
        ];

        $o = $this->di['db']->findOne('ClientOrder', 'group_id = :group_id AND service_type = :service_type', $bindings);

        if ($o instanceof \Model_ClientOrder) {
            return $o->id;
        }

        return null;
    }

    public function rmByClient(\Model_Client $client)
    {
        $sql = 'DELETE FROM client_order WHERE  client_id = :id';

        $pdo = $this->di['pdo'];
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $client->id]);
    }

    public function exportCSV(array $headers)
    {
        if (!$headers) {
            $headers = ['id', 'client_id', 'product_id', 'title', 'currency', 'service_type', 'period', 'quantity', 'price', 'discount', 'status', 'reason', 'notes'];
        }

        return $this->di['table_export_csv']('client_order', 'orders.csv', $headers);
    }
}
