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


namespace Box\Mod\Order;

use Box\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    protected $di;

    /**
     * @param mixed $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return mixed
     */
    public function getDi()
    {
        return $this->di;
    }

    public function counter()
    {
        $sql = "
        SELECT status, COUNT(id) as counter
        FROM client_order
        WHERE group_master = 1
        GROUP BY status
        ";

        $data = $this->di['db']->getAssoc($sql);

        return array(
            'total'                                  => array_sum($data),
            \Model_ClientOrder::STATUS_PENDING_SETUP => $this->di['array_get']($data, \Model_ClientOrder::STATUS_PENDING_SETUP, 0),
            \Model_ClientOrder::STATUS_FAILED_SETUP  => $this->di['array_get']($data, \Model_ClientOrder::STATUS_FAILED_SETUP, 0),
            \Model_ClientOrder::STATUS_ACTIVE        => $this->di['array_get']($data, \Model_ClientOrder::STATUS_ACTIVE, 0),
            \Model_ClientOrder::STATUS_SUSPENDED     => $this->di['array_get']($data, \Model_ClientOrder::STATUS_SUSPENDED, 0),
            \Model_ClientOrder::STATUS_CANCELED      => $this->di['array_get']($data, \Model_ClientOrder::STATUS_CANCELED, 0),
        );
    }

    public static function onAfterAdminOrderActivate(\Box_Event $event)
    {
        $params   = $event->getParameters();
        $order_id = $params['id'];
        $di = $event->getDi();
        $service = $di['mod_service']('order');

        try {
            $order = $di['db']->getExistingModelById('ClientOrder', $order_id, 'Order not found');;
            $s  = $service->getOrderServiceData($order);
            $orderArr = $service->toApiArray($order, true);

            $email              = $params;
            $email['to_client'] = $order->client_id;
            $email['code']      = sprintf('mod_service%s_activated', $orderArr['service_type']);
            $email['service']   = $s;
            $email['order']     = $orderArr;

            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public static function onAfterAdminOrderRenew(\Box_Event $event)
    {
        $params   = $event->getParameters();
        $order_id = $params['id'];
        $di = $event->getDi();
        $orderService = $di['mod_service']('order');

        try {
            $order    = $di['db']->getExistingModelById('ClientOrder', $order_id, 'Order not found');
            $identity = $di['loggedin_admin'];
            $service  = $orderService->getOrderServiceData($order, $identity);
            $orderArr = $orderService->toApiArray($order, true, $identity);

            $email              = array();
            $email['to_client'] = $orderArr['client']['id'];
            $email['code']      = sprintf('mod_service%s_renewed', $orderArr['service_type']);
            $email['service']   = $service;
            $email['order']     = $orderArr;

            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public static function onAfterAdminOrderSuspend(\Box_Event $event)
    {
        $params   = $event->getParameters();
        $order_id = $params['id'];
        $di = $event->getDi();
        $service = $di['mod_service']('order');

        try {
            $order    = $di['db']->getExistingModelById('ClientOrder', $order_id, 'Order not found');
            $identity = $di['loggedin_admin'];
            $s  = $service->getOrderServiceData($order, $identity);
            $orderArr = $service->toApiArray($order, true, $identity);

            $email              = array();
            $email['to_client'] = $orderArr['client']['id'];
            $email['code']      = sprintf('mod_service%s_suspended', $orderArr['service_type']);
            $email['service']   = $s;
            $email['order']     = $orderArr;

            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public static function onAfterAdminOrderUnsuspend(\Box_Event $event)
    {
        $params   = $event->getParameters();
        $order_id = $params['id'];
        $di = $event->getDi();
        $service = $di['mod_service']('order');

        try {
            $order    = $di['db']->getExistingModelById('ClientOrder', $order_id, 'Order not found');
            $identity = $di['loggedin_admin'];
            $s  = $service->getOrderServiceData($order, $identity);
            $orderArr = $service->toApiArray($order, true, $identity);

            $email              = array();
            $email['to_client'] = $orderArr['client']['id'];
            $email['code']      = sprintf('mod_service%s_unsuspended', $orderArr['service_type']);
            $email['service']   = $s;
            $email['order']     = $orderArr;

            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public static function onAfterAdminOrderCancel(\Box_Event $event)
    {
        $params   = $event->getParameters();
        $order_id = $params['id'];
        $di = $event->getDi();
        $service = $di['mod_service']('order');

        try {
            $order    = $di['db']->getExistingModelById('ClientOrder', $order_id, 'Order not found');
            $identity = $di['loggedin_admin'];
            $orderArr = $service->toApiArray($order, true, $identity);

            $email              = array();
            $email['to_client'] = $orderArr['client']['id'];
            $email['code']      = sprintf('mod_service%s_canceled', $orderArr['service_type']);
            $email['order']     = $orderArr;

            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public static function onAfterAdminOrderUncancel(\Box_Event $event)
    {
        $params   = $event->getParameters();
        $order_id = $params['id'];
        $di = $event->getDi();
        $service = $di['mod_service']('order');

        try {
            $order    = $di['db']->getExistingModelById('ClientOrder', $order_id, 'Order not found');
            $identity = $di['loggedin_admin'];
            $s          = $service->getOrderServiceData($order, $identity);
            $orderArr   = $service->toApiArray($order, true, $identity);

            $email              = array();
            $email['to_client'] = $orderArr['client']['id'];
            $email['code']      = sprintf('mod_service%s_renewed', $orderArr['service_type']);
            $email['order']     = $orderArr;
            $email['service']   = $s;

            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public function getOrderService(\Model_ClientOrder $order)
    {
        if (NULL !== $order->service_id) {
            //@deprecated
            //@todo remove this when doctrine is removed
            $core_services = array(
                \Model_ProductTable::CUSTOM,
                \Model_ProductTable::LICENSE,
                \Model_ProductTable::DOWNLOADABLE,
                \Model_ProductTable::HOSTING,
                \Model_ProductTable::MEMBERSHIP,
                \Model_ProductTable::DOMAIN,
            );
            if (in_array($order->service_type, $core_services)) {
                $repo_class = $this->_getServiceClassName($order);

                return $this->di['db']->load($repo_class, $order->service_id);
            } else {
                $service = $this->di['db']->findOne('service_' . $order->service_type,
                    'id = :id',
                    array(':id' => $order->service_id));

                return $service;
            }
        }

        return NULL;
    }

    protected function _getServiceClassName(\Model_ClientOrder $order)
    {
        $s = $this->di['tools']->to_camel_case($order->service_type, true);

        return 'Service' . ucfirst($s);
    }

    public function getServiceOrder($service)
    {
        $type = $this->di['tools']->from_camel_case(str_replace('Model_Service', '', get_class($service)));

        $bindings = array(
            'service_type' => $type,
            ':service_id'  => $service->id
        );

        return $this->di['db']->findOne('ClientOrder', 'service_type  = :service_type AND service_id = :service_id', $bindings);
    }

    public function getConfig(\Model_ClientOrder $model)
    {
        return $this->di['tools']->decodeJ($model->config);
    }

    public function productHasOrders(\Model_Product $product)
    {
        $order = $this->di['db']->findOne('ClientOrder', 'product_id = :product_id', array(':product_id' => $product->id));

        return ($order instanceof \Model_ClientOrder);
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
    public function saveStatusChange(\Model_ClientOrder $order, $notes = NULL)
    {
        $os                  = $this->di['db']->dispense('ClientOrderStatus');
        $os->client_order_id = $order->id;
        $os->status          = $order->status;
        $os->notes           = $notes;
        $os->created_at      = date('Y-m-d H:i:s');
        $os->updated_at      = date('Y-m-d H:i:s');
        $this->di['db']->store($os);
    }

    public function getSoonExpiringActiveOrders()
    {
        list($query, $bindings) = $this->getSoonExpiringActiveOrdersQuery();

        return $this->di['db']->getAll($query, $bindings);
    }

    public function getSoonExpiringActiveOrdersQuery($data = array())
    {
        $systemService        = $this->di['mod_service']('system');
        $days_until_expiration = $systemService->getParamValue('invoice_issue_days_before_expire', 14);

        $client_id = $this->di['array_get']($data, 'client_id', NULL); 

        $query = "SELECT *
                FROM client_order
                WHERE status = :status
                AND invoice_option = :invoice_option
                AND period IS NOT NULL
                AND expires_at IS NOT NULL
                AND unpaid_invoice_id IS NULL";

        $where    = array();
        $bindings = array();

        if (NULL !== $client_id) {
            $where[]                = 'client_id = :client_id';
            $bindings[':client_id'] = $client_id;
        }

        if (!empty($where)) {
            $query = $query . ' AND '. implode(' AND ', $where);
        }

        $query .= " HAVING DATEDIFF(expires_at, NOW()) <= :days_until_expiration ORDER BY client_id DESC";
        $bindings[':status']                = \Model_ClientOrder::STATUS_ACTIVE;
        $bindings[':invoice_option']        = 'issue-invoice';
        $bindings[':days_until_expiration'] = $days_until_expiration;

        return array($query, $bindings);
    }

    public function toApiArray(\Model_ClientOrder $model, $deep = true, $identity = null)
    {
        $clientService  = $this->di['mod_service']('client');
        $supportService = $this->di['mod_service']('support');

        $data                   = $this->di['db']->toArray($model);
        $data['config']         = json_decode($model->config, 1);
        $data['total']          = $this->getTotal($model);
        $data['title']          = $model->title;
        $data['meta']           = $this->di['db']->getAssoc('SELECT name, value FROM client_order_meta WHERE client_order_id = :id', array(':id' => $model->id));
        $data['active_tickets'] = $supportService->getActiveTicketsCountForOrder($model);
        $client = $this->di['db']->getExistingModelById('Client', $model->client_id, 'Client not found');
        $data['client'] = $clientService->toApiArray($client, false);

        if ($identity instanceof \Model_Admin) {
            $data['config'] = $this->getConfig($model);
            $productModel = $this->di['db']->load('Product', $model->product_id);
            if ($productModel instanceof \Model_Product){
                $data['plugin'] = $productModel->plugin;
            }else{
                $data['plugin'] = null;
            }
        }

        $client = $this->di['db']->getExistingModelById('Client', $model->client_id, 'Client not found');
        $data['client'] = $clientService->toApiArray($client, false);

        return $data;
    }

    public function getSearchQuery($data)
    {
        $query = "SELECT co.* from client_order co
                LEFT JOIN client c ON c.id = co.client_id
                LEFT JOIN client_order_meta meta ON meta.client_order_id = co.id";

        $search      = $this->di['array_get']($data, 'search', FALSE); 
        $hide_addons = $this->di['array_get']($data, 'hide_addons', NULL); 
        $id          = $this->di['array_get']($data, 'id', NULL); 
        $product_id  = $this->di['array_get']($data, 'product_id', NULL); 
        $status      = $this->di['array_get']($data, 'status', NULL); 
        $title       = $this->di['array_get']($data, 'title', NULL); 
        $period      = $this->di['array_get']($data, 'period', NULL); 
        $type        = $this->di['array_get']($data, 'type', NULL); 
        $created_at  = $this->di['array_get']($data, 'created_at', NULL); 
        $date_from   = $this->di['array_get']($data, 'date_from', NULL); 
        $date_to     = $this->di['array_get']($data, 'date_to', NULL); 
        $ids         = (isset($data['ids']) && is_array($data['ids'])) ? $data['ids'] : null;
        $meta        = (isset($data['meta']) && is_array($data['meta'])) ? $data['meta'] : null;

        $client_id      = $this->di['array_get']($data, 'client_id', NULL); 
        $invoice_option = $this->di['array_get']($data, 'invoice_option', NULL); 

        $where    = array();
        $bindings = array();

        if ($client_id) {
            $where[]                = "co.client_id = :client_id";
            $bindings[':client_id'] = $client_id;
        }

        if ($invoice_option) {
            $where[]                     = "co.invoice_option = :invoice_option";
            $bindings[':invoice_option'] = $invoice_option;
        }

        if ($id) {
            $where[]         = "co.id = :id";
            $bindings[':id'] = $id;
        }

        if ($status) {
            $where[]             = "co.status = :status";
            $bindings[':status'] = $status;
        }

        if ($product_id) {
            $where[]                 = "co.product_id = :product_id";
            $bindings[':product_id'] = $product_id;
        }

        if ($type) {
            $where[]                   = "co.service_type = :service_type";
            $bindings[':service_type'] = $type;
        }

        if ($title) {
            $where[]            = "co.title LIKE :title";
            $bindings[':title'] = '%' . $title . '%';
        }

        if ($period) {
            $where[]             = "co.period = :period";
            $bindings[':period'] = $period;
        }

        if ($hide_addons) {
            $where[] = "co.group_master = 1";
        }

        if ($created_at) {
            $where[]                 = "DATE_FORMAT(co.created_at, '%Y-%m-%d') = :created_at";
            $bindings[':created_at'] = date('Y-m-d', strtotime($created_at));
        }

        if ($date_from) {
            $where[]                = "UNIX_TIMESTAMP(co.created_at) >= :date_from";
            $bindings[':date_from'] = strtotime($date_from);
        }

        if ($date_to) {
            $where[]              = "UNIX_TIMESTAMP(co.created_at) <= :date_to";
            $bindings[':date_to'] = strtotime($date_to);
        }

        //smartSearch
        if ($search) {
            if (is_numeric($search)) {
                $where[]             = "co.id = :search";
                $bindings[':search'] = $search;
            } else {
                $where[]                 = "(c.first_name LIKE :first_name OR c.last_name LIKE :last_name OR co.title LIKE :title)";
                $bindings[':first_name'] = "%$search%";
                $bindings[':last_name']  = "%$search%";
                $bindings[':title']      = "%$search%";
            }
        }

        if ($ids) {
            $where[]          = "co.id IN (:ids)";
            $bindings[':ids'] = implode(', ',$ids);
        }
        if ($meta) {
            $i = 1;
            foreach ($meta as $k => $v) {
                $where[]                      = "(meta.name = :meta_name$i AND meta.value LIKE :meta_value$i)";
                $bindings[':meta_name' . $i]  = $k;
                $bindings[':meta_value' . $i] = $v . '%';
                $i++;
            }
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }
        $query .= " ORDER BY co.id DESC";

        return array($query, $bindings);
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
            throw new \Box_Exception('Currency could not be determined for order');
        }

        $this->di['events_manager']->fire(array('event' => 'onBeforeAdminOrderCreate', 'params' => $data, 'subject' => $product->type));

        $period         = (isset($data['period']) && !empty($data['period'])) ? $data['period'] : NULL;
        $qty            = $this->di['array_get']($data, 'quantity', 1);
        $config         = (isset($data['config']) && is_array($data['config'])) ? $data['config'] : array();
        $group_id       = $this->di['array_get']($data, 'group_id');
        $activate       = (bool) $this->di['array_get']($data, 'activate', false);
        $invoiceOption  = $this->di['array_get']($data, 'invoice_option', 'no-invoice');
        $skipValidation = (bool) $this->di['array_get']($data, 'skip_validation', false);

        $cartService = $this->di['mod_service']('cart');
        // check stock
        if (!$cartService->isStockAvailable($product, $qty)) {
            throw new \Box_Exception("Product :id is out of stock.", array(':id' => $product->id), 831);
        }

        // Addons must have defined master order
        $parent_order = FALSE;
        if ($product->is_addon && empty($group_id)) {
            throw new \Box_Exception('Group ID parameter is missing for addon product order', null, 832);
        }

        if (!empty($group_id)) {
            $parent_order = $this->getMasterOrderForClient($client, $group_id);
            if (!$parent_order instanceof \Model_ClientOrder) {
                throw new \Box_Exception('Parent order :group_id was not found', array(':group_id' => $group_id));
            }
        }

        //validate order config data
        if ($period) {
            $config['period'] = $period;
        }
        $se = $this->di['mod_service']('service' . $product->type);
        //@deprecated logic
        if (method_exists($se, 'prependOrderConfig')) {
            $config = $se->prependOrderConfig($product, $config);
        }

        //@migration script
        $se = $this->di['mod_service']('service' . $product->type);
        if (method_exists($se, 'attachOrderConfig')) {
            $config = $se->attachOrderConfig($product, $config);
        }

        if (method_exists($se, 'validateOrderData')) {
            if (!$skipValidation) $se->validateOrderData($config);
        }

        $order                 = $this->di['db']->dispense('ClientOrder');
        $order->client_id      = $client->id;
        $order->product_id     = $product->id;
        $order->group_id       = ($parent_order) ? $parent_order->group_id : uniqid();
        $order->group_master   = ($parent_order) ? 0 : 1;
        $order->title          = isset($data['title']) ? $data['title'] : $product->title;
        $order->currency       = $currency->code;
        $order->quantity       = $qty;
        $order->service_type   = $product->type;
        $order->unit           = $product->unit;
        $order->status         = \Model_ClientOrder::STATUS_PENDING_SETUP;
        $order->config         = json_encode($config);
        $order->invoice_option = $invoiceOption;

        if ($period) {
            $bp            = $this->di['period']($data['period']);
            $order->period = $bp->getCode();
        }

        if (isset($data['price'])) {
            $order->price = $data['price'];
        } else {
            $repo = $product->getTable($product->type);
            $rate         = $currencyService->getRateByCode($currency->code);
            $order->price = $repo->getProductPrice($product, $config) * $rate;
        }

        $order->notes = $this->di['array_get']($data, 'notes', $order->notes);
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
                $mm = $this->di['db']->findOne('client_order_meta', 'client_order_id = :id AND name = :n', array(':id' => $order->id, ':n' => $k));
                if (!$mm) {
                    $mm                  = $this->di['db']->dispense('ClientOrderMeta');
                    $mm->client_order_id = $id;
                    $mm->name            = $k;
                    $mm->created_at      = date('Y-m-d H:i:s');
                }
                $mm->value      = $v;
                $mm->updated_at = date('Y-m-d H:i:s');
                $this->di['db']->store($mm);
            }
        }

        $this->di['events_manager']->fire(array('event' => 'onAfterAdminOrderCreate', 'params' => array('id' => $order->id), 'subject' => $product->type));

        $this->di['logger']->info('Created order #%s', $id);

        // invoice options
        if ($invoiceOption == 'issue-invoice' && $order->price > 0) {
            $invoiceService = $this->di['mod_service']('invoice');
            $invoice        = $invoiceService->generateForOrder($order);

            $invoiceService->approveInvoice($invoice, array('id' => $invoice->id, 'use_credits' => true));
        }

        // activate imediately if say so
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
        $bindings = array(
            ':group_id'  => $group_id,
            ':client_id' => $client->id
        );

        return $this->di['db']->findOne('ClientOrder', 'group_id = :group_id AND group_master = 1 AND client_id = :client_id', $bindings);
    }

    /**
     * activate all addons on initial activation
     * @see https://github.com/boxbilling/BoxBilling/issues/54
     */
    public function activateOrderAddons(\Model_ClientOrder $order)
    {
        if (!$order->group_master) {
            return false;
        }

        $list = $this->getOrderAddonsList($order);
        foreach ($list as $addon) {
            try {
                $this->di['events_manager']->fire(array('event' => 'onBeforeAdminOrderActivate', 'params' => array('id' => $addon->id)));
                $this->createFromOrder($addon);
                $this->di['events_manager']->fire(array('event' => 'onAfterAdminOrderActivate', 'params' => array('id' => $addon->id)));
            } catch (\Exception $e) {
                error_log($e->getMessage());
            }
        }
        return true;
    }

    public function activateOrder(\Model_ClientOrder $order, $data = array())
    {
        $statues = array(
            \Model_ClientOrder::STATUS_PENDING_SETUP,
            \Model_ClientOrder::STATUS_FAILED_SETUP,
        );
        if (!in_array($order->status, $statues)){
            if (!isset($data['force']) || !$data['force']) {
                throw new \Box_Exception('Only pending setup or failed orders can be activated');
            }
        }

        $event_params = array('id' => $order->id);
        $this->di['events_manager']->fire(array('event' => 'onBeforeAdminOrderActivate', 'params' => $event_params));
        $result = $this->createFromOrder($order);
        if (is_array($result)) {
            $event_params = array_merge($event_params, $result);
        }
        $this->di['events_manager']->fire(array('event' => 'onAfterAdminOrderActivate', 'params' => $event_params));

        $this->activateOrderAddons($order);

        $this->di['logger']->info('Activated order #%s', $order->id);

        return TRUE;
    }

    public function createFromOrder(\Model_ClientOrder $order)
    {
        $service = $this->getOrderService($order);
        if (!is_object($service)) {
            $mod = $this->di['mod']('service' . $order->service_type);
            $s   = $mod->getService();
            if (method_exists($s, 'create') || method_exists($s, 'action_create')) {
                $service = $this->_callOnService($order, \Model_ClientOrder::ACTION_CREATE);
                if (!is_object($service)) {
                    throw new \Box_Exception('Error creating ' . $order->service_type . ' service for order ' . $order->id);
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
            $from_time = (NULL === $order->expires_at) ? time() : strtotime($order->expires_at);

            $period            = $this->di['period']($order->period);
            $order->expires_at = date('Y-m-d H:i:s', $period->getExpirationTime($from_time));
        }

        $order->status       = \Model_ClientOrder::STATUS_ACTIVE;
        $order->activated_at = date('Y-m-d H:i:s');
        $order->suspended_at = NULL;
        $order->canceled_at  = NULL;
        $order->updated_at   = date('Y-m-d H:i:s');
        $this->di['db']->store($order);

        $productModel = $this->di['db']->load('Product', $order->product_id);
        if($productModel instanceof \Model_Product) {
            $this->stockSale($productModel, $order->quantity);
        } else {
            error_log(sprintf('Order without product ID detected Order #%s', $order->id));
        }

        $this->saveStatusChange($order, 'Order activated');

        return $result;
    }

    public function getOrderAddonsList(\Model_ClientOrder $order)
    {
        return $this->di['db']->find('ClientOrder', 'group_id = :group_id AND client_id = :client_id and (group_master = 0 OR group_master IS NULL)', array(':group_id' => $order->group_id, ':client_id' => $order->client_id));
    }

    protected function _callOnService(\Model_ClientOrder $order, $action)
    {
        $repo = $this->di['mod_service']('service' . $order->service_type);
        //@deprecated
        //@todo remove this when doctrine is removed
        $core_services = array(
            \Model_ProductTable::CUSTOM,
            \Model_ProductTable::LICENSE,
            \Model_ProductTable::DOWNLOADABLE,
            \Model_ProductTable::HOSTING,
            \Model_ProductTable::MEMBERSHIP,
            \Model_ProductTable::DOMAIN,
        );

        if (in_array($order->service_type, $core_services)) {
            $m = 'action_' . $action;
            if (!method_exists($repo, $m) || !is_callable(array($repo, $m))) {
                throw new \Box_Exception('Service ' . $order->service_type . ' do not support ' . $m);
            }

            return $repo->$m($order);
        } else {
            //@new logic for services
            $o       = $this->di['db']->findOne('client_order',
                'id = :id',
                array(':id' => $order->id));
            $service = NULL;
            $sdbname = 'service_' . $order->service_type;
            if ($order->service_id) {
                $service = $this->di['db']->load($sdbname, $order->service_id);
            }
            if (method_exists($repo, $action) && is_callable(array($repo, $action))) {
                return $repo->$action($o, $service);
            }
        }
        error_log(sprintf('Service %s does not support action %s', $order->service_type, $action));

        return null;
    }

    public function stockSale(\Model_Product $product, $qty)
    {
        if ($product->stock_control) {
            $product->quantity_in_stock = $product->quantity_in_stock - $qty;
            $product->updated_at        = date('Y-m-d H:i:s');
            $this->di['db']->store($product);
        }

        return TRUE;
    }

    /**
     * @param \Model_ClientOrder $order
     * @return int
     */
    public function updatePeriod(\Model_ClientOrder $order, $period)
    {
        if (!empty($period)) {
            $period        = $this->di['period']($period);
            $order->period = $period->getCode();
            return 1;
        }

        if (!is_null($period)) {
            $order->period = NULL;
            return 2;
        }
        return 0;
    }

    /**
     * @param \Model_ClientOrder $order
     * @return int
     */
    public function updateOrderMeta(\Model_ClientOrder $order, $meta)
    {
        if (!is_array($meta)) {
            return 0;
        }

        if (empty($meta)) {
            $this->di['db']->exec('DELETE FROM client_order_meta WHERE client_order_id = :id;', array(':id' => $order->id));
            return 1;
        }
        foreach ($meta as $k => $v) {
            $mm = $this->di['db']->findOne('ClientOrderMeta', 'client_order_id = :id AND name = :n', array(':id' => $order->id, ':n' => $k));
            if (!$mm) {
                $mm                  = $this->di['db']->dispense('ClientOrderMeta');
                $mm->client_order_id = $order->id;
                $mm->name            = $k;
                $mm->created_at      = date('Y-m-d H:i:s');
            }
            $mm->value      = $v;
            $mm->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($mm);
        }
        return 2;
    }

    public function updateOrder(\Model_ClientOrder $order, array $data)
    {
        $this->di['events_manager']->fire(array('event' => 'onBeforeAdminOrderUpdate', 'params' => $data));
        $this->updatePeriod($order, $this->di['array_get']($data, 'period', null));

        $created_at = $this->di['array_get']($data, 'created_at', '');
        if (!empty($created_at)) {
            $order->created_at = date('Y-m-d H:i:s', strtotime($created_at));
        }

        $activated_at = $this->di['array_get']($data, 'activated_at');
        if (!empty($activated_at)) {
            $order->activated_at = date('Y-m-d H:i:s', strtotime($activated_at));
        }

        $expires_at = $this->di['array_get']($data, 'expires_at');
        if (!empty($expires_at)) {
            $order->expires_at = date('Y-m-d H:i:s', strtotime($expires_at));
        }
        if (empty($expires_at) && !is_null($expires_at)) {
            $order->expires_at = NULL;
        }

        $order->invoice_option = $this->di['array_get']($data, 'invoice_option', $order->invoice_option);
        $order->title          = $this->di['array_get']($data, 'title', $order->title);
        $order->price          = $this->di['array_get']($data, 'price', $order->price);
        $order->status         = $this->di['array_get']($data, 'status', $order->status);
        $order->notes          = $this->di['array_get']($data, 'notes', $order->notes);
        $order->reason         = $this->di['array_get']($data, 'reason', $order->reason);

        $this->updateOrderMeta($order, $this->di['array_get']($data, 'meta', null));

        $order->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($order);

        $this->di['events_manager']->fire(array('event' => 'onAfterAdminOrderUpdate', 'params' => array('id' => $order->id)));

        $this->di['logger']->info('Update order #%s', $order->id);

        return TRUE;
    }

    public function renewOrder(\Model_ClientOrder $order)
    {
        $this->di['events_manager']->fire(array('event' => 'onBeforeAdminOrderRenew', 'params' => array('id' => $order->id)));

        $this->renewFromOrder($order);

        // activate all addons on initial activation
        // @see https://github.com/boxbilling/BoxBilling/issues/54
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

        $this->di['events_manager']->fire(array('event' => 'onAfterAdminOrderRenew', 'params' => array('id' => $order->id)));
        $this->di['logger']->info('Renewed order #%s', $order->id);

        return TRUE;
    }

    public function renewFromOrder(\Model_ClientOrder $order)
    {
        $this->_callOnService($order, \Model_ClientOrder::ACTION_RENEW);

        // set automatic order expiration
        if (!empty($order->period)) {
            $from_time = (NULL === $order->expires_at) ? time() : strtotime($order->expires_at); // from expiration date

            $config = $this->di['mod_config']('order');
            $logic  = $this->di['array_get']($config, 'order_renewal_logic', ''); 

            if ($logic == 'from_today') {
                $from_time = time(); //renew order from the date renewal occured
            } elseif ($logic == 'from_greater') {
                if (strtotime($order->expires_at) > time()) {
                    $from_time = strtotime($order->expires_at);
                } else {
                    $from_time = time();
                }
            }
            $period            = $this->di['period']($order->period);
            $order->expires_at = date('Y-m-d H:i:s', $period->getExpirationTime($from_time));
        }

        $order->status         = \Model_ClientOrder::STATUS_ACTIVE;
        $order->suspended_at   = NULL;
        $order->unsuspended_at = NULL;
        $order->canceled_at    = NULL;
        $order->updated_at     = date('Y-m-d H:i:s');
        $this->di['db']->store($order);

        $this->saveStatusChange($order, 'Order renewed');
    }

    public function suspendFromOrder(\Model_ClientOrder $order, $reason = null, $skipEvent = false)
    {
        if (!$skipEvent) $this->di['events_manager']->fire(array('event' => 'onBeforeAdminOrderSuspend', 'params' => array('id' => $order->id)));

        if ($order->status != \Model_ClientOrder::STATUS_ACTIVE) {
            throw new \Box_Exception('Only active orders can be suspended');
        }

        $this->_callOnService($order, \Model_ClientOrder::ACTION_SUSPEND);

        $order->status       = \Model_ClientOrder::STATUS_SUSPENDED;
        $order->reason       = $reason;
        $order->suspended_at = date('Y-m-d H:i:s');
        $order->updated_at   = date('Y-m-d H:i:s');
        $this->di['db']->store($order);

        $note = (NULL === $reason) ? "Order suspeded" : 'Order suspended for ' . $reason;
        $this->saveStatusChange($order, $note);

        if (!$skipEvent) $this->di['events_manager']->fire(array('event' => 'onAfterAdminOrderSuspend', 'params' => array('id' => $order->id)));

        $this->di['logger']->info('Suspended order #%s', $order->id);

        return TRUE;
    }

    public function unsuspendFromOrder(\Model_ClientOrder $order)
    {
        $this->di['events_manager']->fire(array('event' => 'onBeforeAdminOrderUnsuspend', 'params' => array('id' => $order->id)));

        $this->_callOnService($order, \Model_ClientOrder::ACTION_UNSUSPEND);

        $order->status         = \Model_ClientOrder::STATUS_ACTIVE;
        $order->reason         = NULL;
        $order->suspended_at   = NULL;
        $order->unsuspended_at = date('Y-m-d H:i:s');
        $order->updated_at     = date('Y-m-d H:i:s');
        $this->di['db']->store($order);

        $this->saveStatusChange($order, 'Order unsuspended');

        $this->di['events_manager']->fire(array('event' => 'onAfterAdminOrderUnsuspend', 'params' => array('id' => $order->id)));

        $this->di['logger']->info('Unsuspended order #%s', $order->id);

        return TRUE;
    }

    public function cancelFromOrder(\Model_ClientOrder $order, $reason = null, $skipEvent = false)
    {
        if (!$skipEvent) $this->di['events_manager']->fire(array('event' => 'onBeforeAdminOrderCancel', 'params' => array('id' => $order->id)));

        if (in_array($order->status, array(\Model_ClientOrder::STATUS_CANCELED, \Model_ClientOrder::STATUS_PENDING_SETUP, \Model_ClientOrder::STATUS_FAILED_SETUP))) {
            throw new \Box_Exception('Can not cancel ' . $order->status . ' order');
        }

        $this->_callOnService($order, \Model_ClientOrder::ACTION_CANCEL);

        $order->status       = \Model_ClientOrder::STATUS_CANCELED;
        $order->reason       = $reason;
        $order->canceled_at  = date('Y-m-d H:i:s');
        $order->expires_at   = NULL;
        $order->suspended_at = NULL;
        $order->updated_at   = date('Y-m-d H:i:s');
        $this->di['db']->store($order);

        $note = (NULL === $reason) ? "Order canceled" : 'Canceled order for ' . $reason;
        $this->saveStatusChange($order, $note);

        if (!$skipEvent) $this->di['events_manager']->fire(array('event' => 'onAfterAdminOrderCancel', 'params' => array('id' => $order->id)));

        $this->di['logger']->info('Canceled order #%s', $order->id);

        return TRUE;
    }

    public function uncancelFromOrder(\Model_ClientOrder $order)
    {
        $this->di['events_manager']->fire(array('event' => 'onBeforeAdminOrderUncancel', 'params' => array('id' => $order->id)));

        $this->_callOnService($order, \Model_ClientOrder::ACTION_UNCANCEL);

        $expires_at = NULL;
        if ($order->period) {
            $period     = $this->di['period']($order->period);
            $expires_at = $period->getExpirationTime(time());
            $expires_at = date('Y-m-d H:i:s', $expires_at);
        }

        $order->status       = \Model_ClientOrder::STATUS_ACTIVE;
        $order->reason       = NULL;
        $order->activated_at = date('Y-m-d H:i:s');
        $order->expires_at   = $expires_at;
        $order->suspended_at = NULL;
        $order->canceled_at  = NULL;
        $order->updated_at   = date('Y-m-d H:i:s');
        $this->di['db']->store($order);

        $this->saveStatusChange($order, 'Activated canceled order');

        $this->di['events_manager']->fire(array('event' => 'onAfterAdminOrderUncancel', 'params' => array('id' => $order->id)));

        $this->di['logger']->info('Uncanceled order #%s', $order->id);

        return TRUE;
    }

    public function rmInvoiceItemByOrder(\Model_ClientOrder $order)
    {
        $bindings = array(
            ':rel_id' => $order->id,
            ':status' => \Model_InvoiceItem::STATUS_PENDING_PAYMENT
        );

        $items = $this->di['db']->find('InvoiceItem', 'rel_id = :rel_id AND status = :status', $bindings);
        foreach($items as $item){
        if ($item instanceof \Model_InvoiceItem) {
            $this->di['db']->trash($item);
            }
        }
    }


    public function rmClientOrderStatusByOrder(\Model_ClientOrder $order)
    {
        $this->di['db']->exec('Delete FROM client_order_status WHERE client_order_id = :client_order_id', array(':client_order_id' => $order->id));
    }

    public function rmOrder(\Model_ClientOrder $model)
    {
        if ($model->group_master) {
            // set addons as separate orders
            $list = $this->getOrderAddonsList($model);
            foreach ($list as $addon) {
                $addon->group_master = 1;
                $addon->group_id     = 0;
                $this->di['db']->store($addon);
            }
        }
        $this->di['db']->trash($model);
    }

    public function deleteFromOrder(\Model_ClientOrder $order)
    {
        $this->di['events_manager']->fire(array('event' => 'onBeforeAdminOrderDelete', 'params' => array('id' => $order->id)));

        if ($order->status == \Model_ClientOrder::STATUS_PENDING_SETUP) {
            $this->rmInvoiceItemByOrder($order);
        }

        $this->_callOnService($order, \Model_ClientOrder::ACTION_DELETE);
        $id = $order->id;
        $this->rmClientOrderStatusByOrder($order);
        $this->rmOrder($order);

        $this->di['events_manager']->fire(array('event' => 'onAfterAdminOrderDelete', 'params' => array('id' => $id)));
        $this->di['logger']->info('Deleted order #%s', $id);

        return TRUE;
    }

    public function getExpiredOrders()
    {
        $bindings = array(
            ':status' => \Model_ClientOrder::STATUS_ACTIVE,
        );

        return $this->di['db']->find('ClientOrder', 'status = :status AND expires_at IS NOT NULL AND DATEDIFF(NOW(), expires_at) >= 1 ORDER BY id', $bindings);
    }

    public function batchSuspendExpired()
    {
        $this->di['events_manager']->fire(array('event' => 'onBeforeAdminBatchSuspendOrders'));

        $mod = $this->di['mod']('order');
        $c   = $mod->getConfig();

        $reason = $this->di['array_get']($c, 'batch_suspend_reason', null); 

        $list = $this->getExpiredOrders();

        foreach ($list as $order) {
            try {
                $this->suspendFromOrder($order, $reason);
            } catch (\Exception $e) {
                error_log($e->getMessage());
            }
        }

        $this->di['events_manager']->fire(array('event' => 'onAfterAdminBatchSuspendOrders'));

        $this->di['logger']->info('Executed action to suspend expired orders');

        return TRUE;
    }

    public function batchCancelSuspended()
    {
        $this->di['events_manager']->fire(array('event' => 'onBeforeAdminBatchCancelSuspendedOrders'));

        $mod    = $this->di['mod']('order');
        $config = $mod->getConfig();
        if (!isset($config['batch_cancel_suspended']) || !$config['batch_cancel_suspended']) {
            return false;
        }

        $reason = $this->di['array_get']($config, 'batch_cancel_suspended_reason', null); 
        $days   = isset($config['batch_cancel_suspended_after_days']) ? (int)$config['batch_cancel_suspended_after_days'] : 7;

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

        $orders = $this->di['db']->getAll($sql, array(':days' => $days));

        foreach ($orders as $orderArr) {
            try {
                $order = $this->di['db']->getExistingModelById('ClientOrder', $orderArr['id'], 'Order not found');
                $this->cancelFromOrder($order, $reason);
            } catch (\Exception $e) {
                error_log($e);
            }
        }

        $this->di['events_manager']->fire(array('event' => 'onAfterAdminBatchCancelSuspendedOrders'));

        $this->di['logger']->info('Executed action to cancel suspended orders');

        return TRUE;
    }

    public function updateOrderConfig(\Model_ClientOrder $order, array $config)
    {
        $oldConfig = $order->config;

        $order->config     = json_encode($config);
        $order->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($order);

        $this->di['logger']->info(sprintf("Order #%s config changes:\n%s\n%s", $order->id, $oldConfig, $order->config));

        return TRUE;
    }

    public function getOrderStatusSearchQuery($data)
    {
        $query = "SELECT * FROM client_order_status";

        $oid = $this->di['array_get']($data, 'client_order_id', NULL); 

        $where    = array();
        $bindings = array();

        if (NULL !== $oid) {
            $where[] = "client_order_id = :client_order_id";

            $bindings[':client_order_id'] = $oid;
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }

        $query .= " ORDER BY id DESC";

        return array($query, $bindings);
    }

    public function orderStatusAdd(\Model_ClientOrder $order, $status, $notes = null)
    {
        $bean                  = $this->di['db']->dispense('ClientOrderStatus');
        $bean->client_order_id = $order->id;
        $bean->status          = $status;
        $bean->notes           = $notes;
        $bean->created_at      = date('Y-m-d H:i:s');
        $bean->updated_at      = date('Y-m-d H:i:s');
        $id                    = $this->di['db']->store($bean);

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
        $bindings = array(
            ':id'        => $id,
            ':client_id' => $client->id
        );
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
        return ($model->price * $model->quantity);
    }

    public function setUnpaidInvoice(\Model_ClientOrder $order, \Model_Invoice $proforma)
    {
        $order->unpaid_invoice_id = $proforma->id;
        $order->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($order);
    }

    public function unsetUnpaidInvoice(\Model_ClientOrder $order)
    {
        $order->unpaid_invoice_id = NULL;
        $order->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($order);
    }

    public function getRelatedOrderIdByType(\Model_ClientOrder $order, $type)
    {
        $bindings = array(
            ':group_id'     => $order->group_id,
            ':service_type' => $type
        );

        $o = $this->di['db']->findOne('ClientOrder', 'group_id = :group_id AND service_type = :service_type', $bindings);

        if ($o instanceof \Model_ClientOrder) {
            return $o->id;
        }

        return NULL;
    }

    public function rmByClient(\Model_Client $client)
    {
        $sql = 'DELETE FROM client_order WHERE  client_id = :id';

        $pdo = $this->di['pdo'];
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array('id'=>$client->id));
    }

}
