<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Order;

use Box\Mod\Client\Entity\Client as ClientEntity;
use Box\Mod\Currency\Entity\Currency;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Entity\OrderMeta;
use Box\Mod\Order\Entity\OrderStatus;
use Box\Mod\Order\Repository\OrderMetaRepository;
use Box\Mod\Order\Repository\OrderRepository;
use Box\Mod\Order\Repository\OrderStatusRepository;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Staff\Entity\Admin;
use FOSSBilling\InformationException;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\Validation\PriceValidator;
use Symfony\Component\HttpFoundation\Response;

class Service implements InjectionAwareInterface
{
    public const META_CANCEL_AT_PERIOD_END = 'cancel_at_period_end';

    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    private ?OrderRepository $orderRepository = null;
    private ?OrderMetaRepository $orderMetaRepository = null;
    private ?OrderStatusRepository $orderStatusRepository = null;

    public function getOrderRepository(): OrderRepository
    {
        if ($this->orderRepository === null) {
            $this->orderRepository = $this->di['em']->getRepository(Order::class);
        }

        return $this->orderRepository;
    }

    public function getOrderMetaRepository(): OrderMetaRepository
    {
        if ($this->orderMetaRepository === null) {
            $this->orderMetaRepository = $this->di['em']->getRepository(OrderMeta::class);
        }

        return $this->orderMetaRepository;
    }

    public function getOrderStatusRepository(): OrderStatusRepository
    {
        if ($this->orderStatusRepository === null) {
            $this->orderStatusRepository = $this->di['em']->getRepository(OrderStatus::class);
        }

        return $this->orderStatusRepository;
    }

    public function getModulePermissions(): array
    {
        return [
            'view' => [
                'type' => 'bool',
                'display_name' => __trans('View orders'),
                'description' => __trans('Allows the staff member to view orders and order details.'),
            ],
            'manage' => [
                'type' => 'bool',
                'display_name' => __trans('Manage orders'),
                'description' => __trans('Allows the staff member to create, update, delete, and change order statuses.'),
            ],
            'export' => [
                'type' => 'bool',
                'display_name' => __trans('Export orders'),
                'description' => __trans('Allows the staff member to export order data as CSV.'),
            ],
            'manage_settings' => [],
        ];
    }

    public function counter(): array
    {
        $sql = '
        SELECT status, COUNT(id) as counter
        FROM client_order
        WHERE group_master = 1
        GROUP BY status
        ';

        $data = $this->di['em']->getConnection()->fetchAllKeyValue($sql);

        return [
            'total' => array_sum($data),
            Order::STATUS_PENDING_SETUP => $data[Order::STATUS_PENDING_SETUP] ?? 0,
            Order::STATUS_FAILED_SETUP => $data[Order::STATUS_FAILED_SETUP] ?? 0,
            Order::STATUS_FAILED_RENEW => $data[Order::STATUS_FAILED_RENEW] ?? 0,
            Order::STATUS_ACTIVE => $data[Order::STATUS_ACTIVE] ?? 0,
            Order::STATUS_SUSPENDED => $data[Order::STATUS_SUSPENDED] ?? 0,
            Order::STATUS_CANCELED => $data[Order::STATUS_CANCELED] ?? 0,
        ];
    }

    public static function onAfterAdminOrderActivate(\Box_Event $event): void
    {
        $params = $event->getParameters();
        $order_id = $params['id'];
        $di = $event->getDi();
        $service = $di['mod_service']('order');

        try {
            $order = $di['em']->getRepository(Order::class)->find($order_id);
            if (!$order instanceof Order) {
                throw new \FOSSBilling\Exception('Order not found');
            }
            $s = $service->getOrderServiceData($order);
            $orderArr = $service->toApiArray($order, true);

            $email = $params;
            $email['to_client'] = $order->getClientId();
            $email['code'] = sprintf('mod_service%s_activated', $orderArr['service_type']);
            $email['service'] = $s;
            $email['order'] = $orderArr;

            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            $di['logger']->setChannel('email')->error('Failed to send order activation email', ['exception' => $exc->getMessage(), 'order_id' => $order_id]);
        }
    }

    public static function onAfterAdminOrderRenew(\Box_Event $event): void
    {
        $params = $event->getParameters();
        $order_id = $params['id'];
        $di = $event->getDi();
        $orderService = $di['mod_service']('order');

        try {
            $order = $di['em']->getRepository(Order::class)->find($order_id);
            if (!$order instanceof Order) {
                throw new \FOSSBilling\Exception('Order not found');
            }
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
            $di['logger']->setChannel('email')->error('Failed to send order renewal email', ['exception' => $exc->getMessage()]);
        }
    }

    public static function onAfterAdminOrderSuspend(\Box_Event $event): void
    {
        $params = $event->getParameters();
        $order_id = $params['id'];
        $di = $event->getDi();
        $service = $di['mod_service']('order');

        try {
            $order = $di['em']->getRepository(Order::class)->find($order_id);
            if (!$order instanceof Order) {
                throw new \FOSSBilling\Exception('Order not found');
            }
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
            $di['logger']->setChannel('email')->error('Failed to send order suspension email', ['exception' => $exc->getMessage()]);
        }
    }

    public static function onAfterAdminOrderUnsuspend(\Box_Event $event): void
    {
        $params = $event->getParameters();
        $order_id = $params['id'];
        $di = $event->getDi();
        $service = $di['mod_service']('order');

        try {
            $order = $di['em']->getRepository(Order::class)->find($order_id);
            if (!$order instanceof Order) {
                throw new \FOSSBilling\Exception('Order not found');
            }
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
            $di['logger']->setChannel('email')->error('Failed to send order unsuspension email', ['exception' => $exc->getMessage()]);
        }
    }

    public static function onAfterAdminOrderCancel(\Box_Event $event): void
    {
        $params = $event->getParameters();
        $order_id = $params['id'];
        $di = $event->getDi();
        $service = $di['mod_service']('order');

        try {
            $order = $di['em']->getRepository(Order::class)->find($order_id);
            if (!$order instanceof Order) {
                throw new \FOSSBilling\Exception('Order not found');
            }
            $identity = $di['loggedin_admin'];
            $orderArr = $service->toApiArray($order, true, $identity);

            $email = [];
            $email['to_client'] = $orderArr['client']['id'];
            $email['code'] = sprintf('mod_service%s_canceled', $orderArr['service_type']);
            $email['order'] = $orderArr;

            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            $di['logger']->setChannel('email')->error('Failed to send order cancellation email', ['exception' => $exc->getMessage()]);
        }
    }

    public static function onAfterAdminOrderUncancel(\Box_Event $event): void
    {
        $params = $event->getParameters();
        $order_id = $params['id'];
        $di = $event->getDi();
        $service = $di['mod_service']('order');

        try {
            $order = $di['em']->getRepository(Order::class)->find($order_id);
            if (!$order instanceof Order) {
                throw new \FOSSBilling\Exception('Order not found');
            }
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
            $di['logger']->setChannel('email')->error('Failed to send order uncancel email', ['exception' => $exc->getMessage()]);
        }
    }

    public function getOrderService(Order $order)
    {
        $serviceId = $order instanceof Order ? $order->getServiceId() : $order->getServiceId();
        $serviceType = $order instanceof Order ? $order->getServiceType() : $order->getServiceType();

        if ($serviceId !== null) {
            $builtInServiceTypes = [
                \Box\Mod\Product\Service::CUSTOM,
                \Box\Mod\Product\Service::LICENSE,
                \Box\Mod\Product\Service::DOWNLOADABLE,
                \Box\Mod\Product\Service::HOSTING,
                \Box\Mod\Product\Service::DOMAIN,
            ];
            if (in_array($serviceType, $builtInServiceTypes, true)) {
                $entityClass = $this->_getServiceEntityClass($order);
                if ($entityClass !== null) {
                    return $this->di['em']->getRepository($entityClass)->find($serviceId);
                }

                return null;
            }

            return $this->di['em']->getConnection()->fetchAssociative(
                'SELECT * FROM service_' . $serviceType . ' WHERE id = :id',
                ['id' => $serviceId]
            );
        }

        return null;
    }

    protected function _getServiceClassName(Order $order): string
    {
        $serviceType = $order instanceof Order ? $order->getServiceType() : $order->getServiceType();
        $s = $this->di['tools']->to_camel_case($serviceType, true);

        return 'Service' . ucfirst((string) $s);
    }

    protected function _getServiceEntityClass(Order $order): ?string
    {
        $serviceType = $order instanceof Order ? $order->getServiceType() : $order->getServiceType();

        return match ($serviceType) {
            \Box\Mod\Product\Service::HOSTING => \Box\Mod\Servicehosting\Entity\ServiceHosting::class,
            \Box\Mod\Product\Service::DOMAIN => \Box\Mod\Servicedomain\Entity\ServiceDomain::class,
            \Box\Mod\Product\Service::LICENSE => \Box\Mod\Servicelicense\Entity\ServiceLicense::class,
            \Box\Mod\Product\Service::DOWNLOADABLE => \Box\Mod\Servicedownloadable\Entity\ServiceDownloadable::class,
            \Box\Mod\Product\Service::CUSTOM => \Box\Mod\Servicecustom\Entity\ServiceCustom::class,
            \Box\Mod\Product\Service::APIKEY => \Box\Mod\Serviceapikey\Entity\ServiceApiKey::class,
            default => null,
        };
    }

    public function getServiceOrder($service)
    {
        $type = $this->di['tools']->from_camel_case($service::class);

        return $this->getOrderRepository()->findOneBy([
            'serviceType' => $type,
            'serviceId' => $service->getId(),
        ]);
    }

    public function getConfig(Order $model)
    {
        return json_decode(($model instanceof Order ? $model->getConfig() : $model->getConfig()) ?? '', true) ?? [];
    }

    public function productHasOrders(Product $product): bool
    {
        $order = $this->getOrderRepository()->findOneByProductId($product->getId());

        return $order instanceof Order;
    }

    public function getLogger(Order $order)
    {
        $orderId = $order instanceof Order ? $order->getId() : $order->getId();
        $orderStatus = $order instanceof Order ? $order->getStatus() : $order->getStatus();

        $log = $this->di['logger'];
        $log->setEventItem('client_order_id', $orderId);
        $log->setEventItem('status', $orderStatus);

        return $log;
    }

    /**
     * @param string $notes
     */
    public function saveStatusChange(Order $order, $notes = null): void
    {
        $orderId = $order instanceof Order ? $order->getId() : $order->getId();
        $orderStatus = $order instanceof Order ? $order->getStatus() : $order->getStatus();

        $os = new OrderStatus();
        $os->setClientOrderId($orderId);
        $os->setStatus($orderStatus);
        $os->setNotes($notes);
        $this->di['em']->persist($os);
        $this->di['em']->flush();
    }

    public function getSoonExpiringActiveOrders()
    {
        [$query, $bindings] = $this->getSoonExpiringActiveOrdersQuery();

        return $this->di['em']->getConnection()->fetchAllAssociative($query, $bindings);
    }

    public function getSoonExpiringActiveOrdersQuery($data = []): array
    {
        $systemService = $this->di['mod_service']('system');
        $days_until_expiration = $systemService->getParamValue('invoice_issue_days_before_expire', 14);

        $client_id = $data['client_id'] ?? null;

        $query = 'SELECT co.*
                FROM client_order co
                LEFT JOIN invoice i ON i.id = co.unpaid_invoice_id AND i.status = :unpaid_invoice_status
                WHERE co.status = :status
                AND co.invoice_option = :invoice_option
                AND co.period IS NOT NULL
                AND co.expires_at IS NOT NULL
                AND i.id IS NULL
                /* Pair non-executed renewal items with paid invoices to skip renewals already queued for activation. */
                AND NOT EXISTS (
                    SELECT 1
                    FROM invoice_item pending_item
                    INNER JOIN invoice pending_invoice ON pending_invoice.id = pending_item.invoice_id
                    WHERE pending_item.rel_id = co.id
                    AND pending_item.type = :pending_item_type
                    AND pending_item.task = :pending_item_task
                    AND pending_item.status != :pending_item_status
                    AND pending_invoice.status = :pending_invoice_status
                )';

        $where = [];
        $bindings = [];

        if ($client_id !== null) {
            $where[] = 'co.client_id = :client_id';
            $bindings['client_id'] = $client_id;
        }

        if (!empty($where)) {
            $query = $query . ' AND ' . implode(' AND ', $where);
        }

        $query .= ' HAVING DATEDIFF(co.expires_at, NOW()) <= :days_until_expiration ORDER BY co.client_id DESC';
        $bindings['status'] = Order::STATUS_ACTIVE;
        $bindings['invoice_option'] = 'issue-invoice';
        $bindings['unpaid_invoice_status'] = Invoice::STATUS_UNPAID;
        $bindings['pending_item_type'] = InvoiceItem::TYPE_ORDER;
        $bindings['pending_item_task'] = InvoiceItem::TASK_RENEW;
        $bindings['pending_item_status'] = InvoiceItem::STATUS_EXECUTED;
        $bindings['pending_invoice_status'] = Invoice::STATUS_PAID;
        $bindings['days_until_expiration'] = $days_until_expiration;

        return [$query, $bindings];
    }

    public function toApiArray(Order $model, $deep = true, $identity = null)
    {
        $clientService = $this->di['mod_service']('client');
        $supportService = $this->di['mod_service']('support');

        $data = [
                'id' => $model->getId(),
                'client_id' => $model->getClientId(),
                'product_id' => $model->getProductId(),
                'form_id' => $model->getFormId(),
                'promo_id' => $model->getPromoId(),
                'promo_recurring' => $model->isPromoRecurring(),
                'promo_used' => $model->getPromoUsed(),
                'group_id' => $model->getGroupId(),
                'group_master' => $model->isGroupMaster(),
                'invoice_option' => $model->getInvoiceOption(),
                'title' => $model->getTitle(),
                'currency' => $model->getCurrency(),
                'unpaid_invoice_id' => $model->getUnpaidInvoiceId(),
                'service_id' => $model->getServiceId(),
                'service_type' => $model->getServiceType(),
                'period' => $model->getPeriod(),
                'quantity' => $model->getQuantity(),
                'unit' => $model->getUnit(),
                'price' => $model->getPrice(),
                'discount' => $model->getDiscount(),
                'status' => $model->getStatus(),
                'reason' => $model->getReason(),
                'notes' => $model->getNotes(),
                'config' => $model->getConfig(),
                'referred_by' => $model->getReferredBy(),
                'expires_at' => $model->getExpiresAt()?->format('Y-m-d H:i:s'),
                'activated_at' => $model->getActivatedAt()?->format('Y-m-d H:i:s'),
                'suspended_at' => $model->getSuspendedAt()?->format('Y-m-d H:i:s'),
                'unsuspended_at' => $model->getUnsuspendedAt()?->format('Y-m-d H:i:s'),
                'canceled_at' => $model->getCanceledAt()?->format('Y-m-d H:i:s'),
                'created_at' => $model->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updated_at' => $model->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ];
            $modelId = $model->getId();
            $modelConfig = $model->getConfig();
            $modelTitle = $model->getTitle();
            $modelClientId = $model->getClientId();

        $data['config'] = json_decode($modelConfig ?? '', true) ?? [];
        $data['total'] = $this->getTotal($model);
        $data['discount'] ??= 0;
        $data['title'] = $modelTitle;
        $data['meta'] = $this->getOrderMetaRepository()->getPairsForOrder($modelId);
        $data['active_tickets'] = $supportService->getSupportTicketRepository()->countActiveTicketsForOrder((int) $modelId);
        $client = $this->di['em']->getRepository(ClientEntity::class)->find($modelClientId)
            ?? throw new InformationException('Client not found');
        $data['client'] = $clientService->toApiArray($client, false);

        if ($identity instanceof Admin) {
            $data['config'] = $this->getConfig($model);
            $productService = $this->di['mod_service']('product');
            $productId = $model instanceof Order ? $model->getProductId() : $model->getProductId();
            $data['plugin'] = $productService->getProductPluginById((int) $productId);
        }

        return $data;
    }

    /**
     * Get multiple orders in a batch for API response.
     *
     * @param array                           $ids      Array of order IDs to fetch
     * @param Admin|ClientEntity|null $identity The requesting identity
     *
     * @return array Array of order API arrays. Missing IDs are silently skipped.
     */
    public function getBatchForApi(array $ids, $identity = null): array
    {
        $ids = $this->normalizeIds($ids);
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $orders = $this->di['em']->getConnection()->fetchAllAssociative("SELECT * FROM client_order WHERE id IN ($placeholders)", $ids);
        if (empty($orders)) {
            return [];
        }
        $orders = $this->orderRowsByIds($orders, $ids);

        $orderIds = array_column($orders, 'id');
        $clientIds = $this->normalizeIds(array_column($orders, 'client_id'));
        $productIds = $this->normalizeIds(array_column($orders, 'product_id'));

        $clients = [];
        if (!empty($clientIds)) {
            $clientModels = $this->di['em']->getRepository(ClientEntity::class)->findBy(['id' => $clientIds]);
            $clientService = $this->di['mod_service']('client');
            foreach ($clientModels as $client) {
                $clients[$client instanceof ClientEntity ? $client->getId() : $client->getId()] = $clientService->toApiArray($client, false, $identity);
            }
        }

        $meta = [];
        if (!empty($orderIds)) {
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $metaRows = $this->di['em']->getConnection()->fetchAllAssociative(
                "SELECT client_order_id, name, value FROM client_order_meta WHERE client_order_id IN ($placeholders)",
                $orderIds
            );
            foreach ($metaRows as $row) {
                $meta[$row['client_order_id']][$row['name']] = $row['value'];
            }
        }

        $activeTickets = [];
        if (!empty($orderIds)) {
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $rows = $this->di['em']->getConnection()->fetchAllAssociative(
                "SELECT rel_id, COUNT(id) as counter FROM support_ticket
                WHERE rel_type = 'order'
                AND rel_id IN ($placeholders)
                AND (status = 'open' OR status = 'on_hold')
                GROUP BY rel_id",
                $orderIds
            );
            foreach ($rows as $row) {
                $activeTickets[$row['rel_id']] = (int) $row['counter'];
            }
        }

        $plugins = [];
        if ($identity instanceof Admin && !empty($productIds)) {
            $productService = $this->di['mod_service']('product');
            $plugins = $productService->getProductPluginMap($productIds);
        }

        $result = [];
        foreach ($orders as $order) {
            $clientId = $order['client_id'];
            $data = $order;
            $data['config'] = json_decode($order['config'] ?? '', true) ?? [];
            $data['total'] = $this->calculateTotal($order['price'], $order['quantity']);
            $data['title'] = $order['title'];
            $data['meta'] = $meta[$order['id']] ?? [];
            $data['active_tickets'] = $activeTickets[$order['id']] ?? 0;
            if (!isset($clients[$clientId])) {
                $this->di['logger']->error('Missing client for order ' . $order['id']);
                $data['client'] = [];
            } else {
                $data['client'] = $clients[$clientId];
            }

            if ($identity instanceof Admin) {
                $data['plugin'] = $plugins[$order['product_id']] ?? null;
            }

            $result[] = $data;
        }

        return $result;
    }

    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_map(intval(...), array_filter($ids, is_numeric(...)))));
    }

    private function orderRowsByIds(array $rows, array $ids): array
    {
        $rowsById = [];
        foreach ($rows as $row) {
            $rowsById[(int) $row['id']] = $row;
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($rowsById[$id])) {
                $ordered[] = $rowsById[$id];
            }
        }

        return $ordered;
    }

    public function getSearchQuery($data): array
    {
        $query = 'SELECT co.* from client_order co
                LEFT JOIN client c ON c.id = co.client_id
                LEFT JOIN client_order_meta meta ON meta.client_order_id = co.id';

        $search = $data['search'] ?? false;
        $hide_addons = $data['hide_addons'] ?? null;
        $show_action_required = $data['show_action_required'] ?? null;
        $id = $data['id'] ?? null;
        $product_id = $data['product_id'] ?? null;
        $promo_id = $data['promo_id'] ?? null;
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
            $bindings['client_id'] = $client_id;
        }

        if ($invoice_option) {
            $where[] = 'co.invoice_option = :invoice_option';
            $bindings['invoice_option'] = $invoice_option;
        }

        if ($id) {
            $where[] = 'co.id = :id';
            $bindings['id'] = $id;
        }

        if ($show_action_required) {
            $where[] = '(co.status = \'pending_setup\' OR co.status = \'failed_setup\' OR co.status =\'failed_renew\')';
        }

        if ($status) {
            $where[] = 'co.status = :status';
            $bindings['status'] = $status;
        }

        if ($product_id) {
            $where[] = 'co.product_id = :product_id';
            $bindings['product_id'] = $product_id;
        }

        if ($promo_id) {
            $where[] = 'co.promo_id = :promo_id';
            $bindings['promo_id'] = $promo_id;
        }

        if ($type) {
            $where[] = 'co.service_type = :service_type';
            $bindings['service_type'] = $type;
        }

        if ($title) {
            $where[] = 'co.title LIKE :title';
            $bindings['title'] = '%' . $title . '%';
        }

        if ($period) {
            $where[] = 'co.period = :period';
            $bindings['period'] = $period;
        }

        if ($hide_addons) {
            $where[] = 'co.group_master = 1';
        }

        if ($created_at) {
            $where[] = "DATE_FORMAT(co.created_at, '%Y-%m-%d') = :created_at";
            $bindings['created_at'] = date('Y-m-d', strtotime((string) $created_at));
        }

        if ($date_from) {
            $where[] = 'UNIX_TIMESTAMP(co.created_at) >= :date_from';
            $bindings['date_from'] = strtotime((string) $date_from);
        }

        if ($date_to) {
            $where[] = 'UNIX_TIMESTAMP(co.created_at) <= :date_to';
            $bindings['date_to'] = strtotime((string) $date_to);
        }

        // smartSearch
        if ($search) {
            if (is_numeric($search)) {
                $where[] = 'co.id = :search';
                $bindings['search'] = $search;
            } else {
                $where[] = '(c.first_name LIKE :first_name OR c.last_name LIKE :last_name OR co.title LIKE :title)';
                $bindings['first_name'] = "%$search%";
                $bindings['last_name'] = "%$search%";
                $bindings['title'] = "%$search%";
            }
        }

        if ($ids) {
            $where[] = 'co.id IN (:ids)';
            $bindings['ids'] = implode(', ', $ids);
        }
        if ($meta) {
            $i = 1;
            foreach ($meta as $k => $v) {
                $where[] = "(meta.name = :meta_name$i AND meta.value LIKE :meta_value$i)";
                $bindings['meta_name' . $i] = $k;
                $bindings['meta_value' . $i] = $v . '%';
                ++$i;
            }
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }
        $query .= ' ORDER BY co.id DESC';

        return [$query, $bindings];
    }

    public function createOrder(ClientEntity $client, Product $product, array $data)
    {
        $quantity = PriceValidator::validateQuantity($data['quantity'] ?? 1);
        $price = isset($data['price']) ? PriceValidator::validateAmount($data['price']) : null;

        $currencyService = $this->di['mod_service']('currency');
        /** @var \Box\Mod\Currency\Repository\CurrencyRepository $currencyRepository */
        $currencyRepository = $currencyService->getCurrencyRepository();

        if (isset($data['currency']) && !empty($data['currency'])) {
            $currency = $currencyRepository->findOneByCode($data['currency']);
        } elseif ($client instanceof ClientEntity ? $client->getCurrency() : $client->getCurrency()) {
            $currency = $currencyRepository->findOneByCode($client instanceof ClientEntity ? $client->getCurrency() : $client->getCurrency());
        } else {
            $currency = $currencyRepository->findDefault();
        }
        if (!$currency instanceof Currency) {
            throw new \FOSSBilling\Exception('Currency could not be determined for order');
        }

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminOrderCreate', 'params' => $data, 'subject' => $this->getProductType($product)]);

        $period = (isset($data['period']) && !empty($data['period'])) ? $data['period'] : null;
        $config = (isset($data['config']) && is_array($data['config'])) ? $data['config'] : [];
        $group_id = $data['group_id'] ?? null;
        $activate = (bool) ($data['activate'] ?? false);
        $invoiceOption = $data['invoice_option'] ?? 'no-invoice';
        $skipValidation = (bool) ($data['skip_validation'] ?? false);

        $cartService = $this->di['mod_service']('cart');
        // check stock
        if (!$cartService->isStockAvailable($product, $quantity)) {
            throw new InformationException('Product :id is out of stock.', [':id' => $this->getProductId($product)], 831);
        }

        // Addons must have defined master order
        $parent_order = false;
        if ($this->isAddonProduct($product) && empty($group_id)) {
            throw new \FOSSBilling\Exception('Group ID parameter is missing for addon product order', null, 832);
        }

        if (!empty($group_id)) {
            $parent_order = $this->getMasterOrderForClient($client, $group_id);
            if (!$parent_order instanceof Order) {
                throw new \FOSSBilling\Exception('Parent order :group_id was not found', [':group_id' => $group_id]);
            }
        }

        // validate order config data
        if ($period) {
            $config['period'] = $period;
        }
        $se = $this->di['mod_service']('service' . $this->getProductType($product));
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

        $invoice = null;
        $markInvoicePaid = \FOSSBilling\Tools::normalizeBoolean($data['mark_invoice_paid'] ?? false);

        $id = $this->di['em']->wrapInTransaction(function () use (
            $client,
            $config,
            $currency,
            $currencyRepository,
            $data,
            $generatedOrderTitle,
            $invoiceOption,
            $parent_order,
            $period,
            $price,
            $product,
            $quantity,
            &$invoice
        ) {
            $order = new Order();
            $order->setClientId($client instanceof ClientEntity ? $client->getId() : $client->getId());
            $order->setProductId($this->getProductId($product));
            $order->setFormId($this->getProductFormId($product));
            $parentGroupId = ($parent_order) ? ($parent_order instanceof Order ? $parent_order->getGroupId() : $parent_order->getGroupId()) : null;
            $order->setGroupId($parentGroupId ?? uniqid());
            $order->setGroupMaster(!$parent_order);
            $order->setTitle($generatedOrderTitle ?? $data['title'] ?? $this->getProductTitle($product));
            $order->setCurrency($currency->getCode());
            $order->setServiceType($this->getProductType($product));
            $order->setUnit($this->getProductUnit($product));
            $order->setStatus(Order::STATUS_PENDING_SETUP);
            $order->setConfig(json_encode($config));
            $order->setInvoiceOption($invoiceOption);

            if ($period) {
                $bp = $this->di['period']($data['period']);
                $order->setPeriod($bp->getCode());
            }

            $line = null;
            if (!isset($data['price']) || $this->getProductType($product) === \Box\Mod\Product\Service::DOMAIN) {
                $productService = $this->di['mod_service']('Product');
                $line = $productService->getProductOrderLineConfig($product, array_merge($config, ['quantity' => $quantity]));
                $order->setQuantity($line['quantity']);
            } else {
                $order->setQuantity($quantity);
            }

            if ($price !== null) {
                $order->setPrice($price);
            } else {
                $rate = $currencyRepository->getRateByCode($currency->getCode());
                if ($rate === null) {
                    throw new \FOSSBilling\Exception("Currency rate for '{$currency->getCode()}' is not configured");
                }
                $order->setPrice($line['price'] * $rate);
            }

            $order->setNotes($data['notes'] ?? $order->getNotes());
            if (isset($data['created_at'])) {
                $order->setCreatedAt(new \DateTime(date('Y-m-d H:i:s', strtotime((string) $data['created_at']))));
            } else {
                $order->setCreatedAt(new \DateTime());
            }

            if (isset($data['updated_at'])) {
                $order->setUpdatedAt(new \DateTime(date('Y-m-d H:i:s', strtotime((string) $data['updated_at']))));
            } else {
                $order->setUpdatedAt(new \DateTime());
            }

            $this->di['em']->persist($order);
            $this->di['em']->flush();
            $orderId = $order->getId();

            if (isset($data['meta']) && is_array($data['meta'])) {
                foreach ($data['meta'] as $k => $v) {
                    $mm = $this->getOrderMetaRepository()->findOneByOrderIdAndName($orderId, $k);
                    if (!$mm instanceof OrderMeta) {
                        $mm = new OrderMeta();
                        $mm->setClientOrderId($orderId);
                        $mm->setName($k);
                        $mm->setCreatedAt(new \DateTime());
                    }
                    $mm->setValue($v);
                    $mm->setUpdatedAt(new \DateTime());
                    $this->di['em']->persist($mm);
                }
                $this->di['em']->flush();
            }

            if ($invoiceOption == 'issue-invoice') {
                $invoiceService = $this->di['mod_service']('invoice');

                try {
                    $invoice = $invoiceService->generateForOrder($order);
                } catch (InformationException $e) {
                    $this->di['logger']->warning($e->getMessage());
                }
            }

            return $orderId;
        });

        if ($invoice instanceof Invoice) {
            $invoiceService = $this->di['mod_service']('invoice');

            try {
                $invoiceService->approveInvoice($invoice, ['id' => $invoice->getId(), 'use_credits' => true]);

                if ($markInvoicePaid) {
                    $invoiceService->markAsPaidByAdmin($invoice, $data);
                }
            } catch (\Exception $e) {
                $this->di['logger']->info($e->getMessage());

                try {
                    $invoiceService->addNote($invoice, 'Order was created, but invoice follow-up failed: ' . $e->getMessage());
                } catch (\Exception $noteException) {
                    $this->di['logger']->info($noteException->getMessage());
                }
            }
        }

        $order = $this->getOrderRepository()->find($id);
        if (!$order instanceof Order) {
            throw new \FOSSBilling\Exception('Order not found');
        }

        $this->di['events_manager']->fire(['event' => 'onAfterAdminOrderCreate', 'params' => ['id' => $order->getId()], 'subject' => $this->getProductType($product)]);

        $this->di['logger']->info('Created order #%s', $id);

        // activate immediately on creation
        if ($activate) {
            try {
                $this->activateOrder($order);
            } catch (\Exception $e) {
                $this->di['logger']->info($e->getMessage());
            }
        }

        return $id;
    }

    public function getMasterOrderForClient(ClientEntity $client, $group_id)
    {
        return $this->getOrderRepository()->findOneBy([
            'groupId' => $group_id,
            'groupMaster' => true,
            'clientId' => $client instanceof ClientEntity ? $client->getId() : $client->getId(),
        ]);
    }

    /**
     * activate all addons on initial activation.
     *
     * @see https://github.com/boxbilling/boxbilling/issues/54
     */
    public function activateOrderAddons(Order $order): bool
    {
        $isGroupMaster = $order instanceof Order ? $order->isGroupMaster() : $order->isGroupMaster();
        if (!$isGroupMaster) {
            return false;
        }

        $list = $this->getOrderAddonsList($order);
        foreach ($list as $addon) {
            $addonId = $addon instanceof Order ? $addon->getId() : $addon->getId();

            try {
                $this->di['events_manager']->fire(['event' => 'onBeforeAdminOrderActivate', 'params' => ['id' => $addonId]]);
                $this->createFromOrder($addon);
                $this->di['events_manager']->fire(['event' => 'onAfterAdminOrderActivate', 'params' => ['id' => $addonId]]);
            } catch (\Exception $e) {
                $this->di['logger']->info($e->getMessage());
            }
        }

        return true;
    }

    public function activateOrder(Order $order, $data = []): bool
    {
        $orderId = $order instanceof Order ? $order->getId() : $order->getId();
        $order = $this->getOrderRepository()->find($orderId);
        if (!$order instanceof Order) {
            throw new \FOSSBilling\Exception('Order :id not found', [':id' => $orderId]);
        }
        $force = !empty($data['force']);

        $orderStatus = $order instanceof Order ? $order->getStatus() : $order->getStatus();
        if ($orderStatus === Order::STATUS_ACTIVE && !$force) {
            return true;
        }

        $statues = [
            Order::STATUS_PENDING_SETUP,
            Order::STATUS_FAILED_SETUP,
        ];
        if (!in_array($orderStatus, $statues) && !$force) {
            throw new \FOSSBilling\Exception('Only pending setup or failed orders can be activated');
        }

        $event_params = ['id' => $orderId];
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminOrderActivate', 'params' => $event_params]);
        $result = $this->createFromOrder($order);
        if (is_array($result)) {
            $event_params = [...$event_params, ...$result];
        }
        $this->di['events_manager']->fire(['event' => 'onAfterAdminOrderActivate', 'params' => $event_params]);

        $this->activateOrderAddons($order);

        $this->di['logger']->info('Activated order #%s', $orderId);

        return true;
    }

    public function createFromOrder(Order $order)
    {
        $orderId = $order instanceof Order ? $order->getId() : $order->getId();
        $serviceType = $order instanceof Order ? $order->getServiceType() : $order->getServiceType();

        $service = $this->getOrderService($order);
        if (!is_object($service)) {
            $mod = $this->di['mod']('service' . $serviceType);
            $s = $mod->getService();
            if (method_exists($s, 'create') || method_exists($s, 'action_create')) {
                $service = $this->_callOnService($order, Order::ACTION_CREATE);
                if (!is_object($service)) {
                    throw new \FOSSBilling\Exception('Error creating ' . $serviceType . ' service for order ' . $orderId);
                }

                if ($order instanceof Order) {
                    $order->setServiceId($service->getId());
                    $order->setUpdatedAt(new \DateTime());
                    $this->di['em']->persist($order);
                    $this->di['em']->flush();
                } else {
                    $order->service_id = $service->getId();
                    $order->updated_at = date('Y-m-d H:i:s');
                    $this->di['em']->persist($order);
                }
            }
        }

        try {
            $result = $this->_callOnService($order, Order::ACTION_ACTIVATE);
        } catch (\Exception $e) {
            if ($order instanceof Order) {
                $order->setStatus(Order::STATUS_FAILED_SETUP);
                $this->di['em']->persist($order);
                $this->di['em']->flush();
            } else {
                $order->status = Order::STATUS_FAILED_SETUP;
                $this->di['em']->persist($order);
            }

            $this->saveStatusChange($order, $e->getMessage());

            throw $e;
        }

        $period = $order instanceof Order ? $order->getPeriod() : $order->getPeriod();
        $expiresAt = $order instanceof Order ? $order->getExpiresAt() : $order->getExpiresAt();
        if (!empty($period)) {
            $from_time = ($expiresAt === null) ? time() : ($order instanceof Order ? ($expiresAt?->getTimestamp() ?? time()) : strtotime($expiresAt));

            $periodObj = $this->di['period']($period);
            $newExpires = date('Y-m-d H:i:s', $periodObj->getExpirationTime($from_time));
            if ($order instanceof Order) {
                $order->setExpiresAt(new \DateTime($newExpires));
            } else {
                $order->expires_at = $newExpires;
            }
        }

        if ($order instanceof Order) {
            $order->setStatus(Order::STATUS_ACTIVE);
            $order->setActivatedAt(new \DateTime());
            $order->setSuspendedAt(null);
            $order->setCanceledAt(null);
            $order->setUpdatedAt(new \DateTime());
            $this->di['em']->persist($order);
            $this->di['em']->flush();
        } else {
            $order->status = Order::STATUS_ACTIVE;
            $order->activated_at = date('Y-m-d H:i:s');
            $order->suspended_at = null;
            $order->canceled_at = null;
            $order->updated_at = date('Y-m-d H:i:s');
            $this->di['em']->persist($order);
        }

        if ($order instanceof Order ? $order->getProductId() : $order->getProductId()) {
            $productService = $this->di['mod_service']('product');
            $productService->reduceStock($order instanceof Order ? (int) $order->getProductId() : (int) $order->getProductId(), $order instanceof Order ? $order->getQuantity() : $order->getQuantity());
        } else {
            $this->di['logger']->info("Order without product ID detected Order #{$orderId}.");
        }

        $this->saveStatusChange($order, 'Order activated');

        return $result;
    }

    public function getOrderAddonsList(Order $order)
    {
        $groupId = $order instanceof Order ? $order->getGroupId() : $order->getGroupId();
        $clientId = $order instanceof Order ? $order->getClientId() : $order->getClientId();

        return $this->getOrderRepository()->findBy([
            'groupId' => $groupId,
            'clientId' => $clientId,
        ]);
    }

    protected function _callOnService(Order $order, $action)
    {
        $serviceType = $order instanceof Order ? $order->getServiceType() : $order->getServiceType();
        $serviceId = $order instanceof Order ? $order->getServiceId() : $order->getServiceId();
        $orderId = $order instanceof Order ? $order->getId() : $order->getId();

        $repo = $this->di['mod_service']('service' . $serviceType);
        $builtInServiceTypes = [
            \Box\Mod\Product\Service::CUSTOM,
            \Box\Mod\Product\Service::LICENSE,
            \Box\Mod\Product\Service::DOWNLOADABLE,
            \Box\Mod\Product\Service::HOSTING,
            \Box\Mod\Product\Service::DOMAIN,
        ];

        if (in_array($serviceType, $builtInServiceTypes, true)) {
            $m = 'action_' . $action;
            if (!method_exists($repo, $m) || !is_callable([$repo, $m])) {
                throw new \FOSSBilling\Exception('Service ' . $serviceType . ' do not support ' . $m);
            }

            return $repo->$m($order);
        }

        $o = $this->getOrderRepository()->find($orderId);
        $service = null;
        $sdbname = 'service_' . $serviceType;
        if ($serviceId) {
            $service = $this->di['em']->getConnection()->fetchAssociative(
                'SELECT * FROM ' . $sdbname . ' WHERE id = :id',
                ['id' => $serviceId]
            );
        }
        if (method_exists($repo, $action) && is_callable([$repo, $action])) {
            return $repo->$action($o, $service);
        }

        $this->di['logger']->info("Service {$serviceType} does not support action {$action}.");

        return null;
    }

    public function stockSale(Product|int $product, $qty): bool
    {
        $productService = $this->di['mod_service']('product');

        return $productService->reduceStock($product, $qty);
    }

    public function updatePeriod(Order $order, $period): int
    {
        if (!empty($period)) {
            $periodObj = $this->di['period']($period);
            if ($order instanceof Order) {
                $order->setPeriod($periodObj->getCode());
            } else {
                $order->period = $periodObj->getCode();
            }

            return 1;
        }

        if (!is_null($period)) {
            if ($order instanceof Order) {
                $order->setPeriod(null);
            } else {
                $order->period = null;
            }

            return 2;
        }

        return 0;
    }

    public function updateOrderMeta(Order $order, $meta): int
    {
        if (!is_array($meta)) {
            return 0;
        }

        $orderId = $order instanceof Order ? $order->getId() : $order->getId();

        if (empty($meta)) {
            $this->getOrderMetaRepository()->deleteByOrderId($orderId);

            return 1;
        }
        foreach ($meta as $k => $v) {
            $mm = $this->getOrderMetaRepository()->findOneByOrderIdAndName($orderId, $k);
            if (!$mm instanceof OrderMeta) {
                $mm = new OrderMeta();
                $mm->setClientOrderId($orderId);
                $mm->setName($k);
                $mm->setCreatedAt(new \DateTime());
            }
            $mm->setValue($v);
            $mm->setUpdatedAt(new \DateTime());
            $this->di['em']->persist($mm);
        }
        $this->di['em']->flush();

        return 2;
    }

    public function updateOrder(Order $order, array $data): bool
    {
        $orderId = $order instanceof Order ? $order->getId() : $order->getId();
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminOrderUpdate', 'params' => $data]);
        $this->updatePeriod($order, $data['period'] ?? null);

        $created_at = $data['created_at'] ?? '';
        if (!empty($created_at)) {
            $createdAtDate = date('Y-m-d H:i:s', strtotime((string) $created_at));
            if ($order instanceof Order) {
                $order->setCreatedAt(new \DateTime($createdAtDate));
            } else {
                $order->created_at = $createdAtDate;
            }
        }

        $activated_at = $data['activated_at'] ?? null;
        if (!empty($activated_at)) {
            $activatedAtDate = date('Y-m-d H:i:s', strtotime((string) $activated_at));
            if ($order instanceof Order) {
                $order->setActivatedAt(new \DateTime($activatedAtDate));
            } else {
                $order->activated_at = $activatedAtDate;
            }
        }

        $expires_at = $data['expires_at'] ?? null;
        if (!empty($expires_at)) {
            $expiresAtDate = date('Y-m-d H:i:s', strtotime((string) $expires_at));
            if ($order instanceof Order) {
                $order->setExpiresAt(new \DateTime($expiresAtDate));
            } else {
                $order->expires_at = $expiresAtDate;
            }
        }
        if (empty($expires_at) && !is_null($expires_at)) {
            if ($order instanceof Order) {
                $order->setExpiresAt(null);
            } else {
                $order->expires_at = null;
            }
        }

        $invoiceOption = $data['invoice_option'] ?? ($order instanceof Order ? $order->getInvoiceOption() : $order->getInvoiceOption());
        if ($order instanceof Order) {
            $order->setInvoiceOption($invoiceOption);
            $order->setTitle($data['title'] ?? $order->getTitle());
        } else {
            $order->invoice_option = $invoiceOption;
            $order->title = $data['title'] ?? $order->getTitle();
        }

        if (isset($data['price'])) {
            $price = PriceValidator::validateAmount($data['price']);
            if ($order instanceof Order) {
                $order->setPrice($price);
            } else {
                $order->price = $price;
            }
        }

        $currentStatus = $order instanceof Order ? $order->getStatus() : $order->getStatus();
        if (isset($data['status']) && $data['status'] !== $currentStatus) {
            if (!in_array($data['status'], Order::getValidStatuses(), true)) {
                throw new InformationException('Invalid order status: :status', [':status:' => $data['status']]);
            }
            if ($order instanceof Order) {
                $order->setStatus($data['status']);
            } else {
                $order->status = $data['status'];
            }
        }

        $notes = $data['notes'] ?? ($order instanceof Order ? $order->getNotes() : $order->getNotes());
        $reason = $data['reason'] ?? ($order instanceof Order ? $order->getReason() : $order->getReason());
        if ($order instanceof Order) {
            $order->setNotes($notes);
            $order->setReason($reason);
        } else {
            $order->notes = $notes;
            $order->reason = $reason;
        }

        $this->updateOrderMeta($order, $data['meta'] ?? null);

        if ($order instanceof Order) {
            $order->setUpdatedAt(new \DateTime());
            $this->di['em']->persist($order);
            $this->di['em']->flush();
        } else {
            $order->updated_at = date('Y-m-d H:i:s');
            $this->di['em']->persist($order);
        }

        $this->di['events_manager']->fire(['event' => 'onAfterAdminOrderUpdate', 'params' => ['id' => $orderId]]);

        $this->di['logger']->info('Update order #%s', $orderId);

        return true;
    }

    public function renewOrder(Order $order): bool
    {
        $orderId = $order instanceof Order ? $order->getId() : $order->getId();
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminOrderRenew', 'params' => ['id' => $orderId]]);

        $this->renewFromOrder($order);

        $isGroupMaster = $order instanceof Order ? $order->isGroupMaster() : $order->isGroupMaster();
        $orderStatus = $order instanceof Order ? $order->getStatus() : $order->getStatus();
        if ($isGroupMaster && $orderStatus == Order::STATUS_PENDING_SETUP) {
            $list = $this->getOrderAddonsList($order);
            foreach ($list as $addon) {
                try {
                    $this->renewFromOrder($addon);
                } catch (\Exception $e) {
                    $this->di['logger']->info($e->getMessage());
                }
            }
        }

        $this->di['events_manager']->fire(['event' => 'onAfterAdminOrderRenew', 'params' => ['id' => $orderId]]);
        $this->di['logger']->info('Renewed order #%s', $orderId);

        return true;
    }

    public function renewFromOrder(Order $order): void
    {
        $orderId = $order instanceof Order ? $order->getId() : $order->getId();

        try {
            $result = $this->_callOnService($order, Order::ACTION_RENEW);
        } catch (\Exception $e) {
            if ($order instanceof Order) {
                $order->setStatus(Order::STATUS_FAILED_RENEW);
                $this->di['em']->persist($order);
                $this->di['em']->flush();
            } else {
                $order->status = Order::STATUS_FAILED_RENEW;
                $this->di['em']->persist($order);
            }

            $this->saveStatusChange($order, $e->getMessage());

            throw $e;
        }

        $period = $order instanceof Order ? $order->getPeriod() : $order->getPeriod();
        $expiresAt = $order instanceof Order ? $order->getExpiresAt() : $order->getExpiresAt();
        if (!empty($period)) {
            $from_time = ($expiresAt === null) ? time() : ($order instanceof Order ? ($expiresAt?->getTimestamp() ?? time()) : strtotime($expiresAt));

            $config = $this->di['mod_config']('order');
            $logic = $config['order_renewal_logic'] ?? '';

            if ($logic == 'from_today') {
                $from_time = time();
            } elseif ($logic == 'from_greater') {
                $expiresTimestamp = $order instanceof Order ? ($expiresAt?->getTimestamp() ?? 0) : strtotime($expiresAt);
                if ($expiresTimestamp > time()) {
                    $from_time = $expiresTimestamp;
                } else {
                    $from_time = time();
                }
            }
            $periodObj = $this->di['period']($period);
            $newExpires = date('Y-m-d H:i:s', $periodObj->getExpirationTime($from_time));
            if ($order instanceof Order) {
                $order->setExpiresAt(new \DateTime($newExpires));
            } else {
                $order->expires_at = $newExpires;
            }
        }

        if ($order instanceof Order) {
            $order->setStatus(Order::STATUS_ACTIVE);
            $order->setSuspendedAt(null);
            $order->setUnsuspendedAt(null);
            $order->setCanceledAt(null);
            $order->setUpdatedAt(new \DateTime());
            $this->di['em']->persist($order);
            $this->di['em']->flush();
        } else {
            $order->status = Order::STATUS_ACTIVE;
            $order->suspended_at = null;
            $order->unsuspended_at = null;
            $order->canceled_at = null;
            $order->updated_at = date('Y-m-d H:i:s');
            $this->di['em']->persist($order);
        }

        $this->saveStatusChange($order, 'Order renewed');
    }

    public function suspendFromOrder(Order $order, $reason = null, $skipEvent = false): bool
    {
        $orderId = $order instanceof Order ? $order->getId() : $order->getId();
        $orderStatus = $order instanceof Order ? $order->getStatus() : $order->getStatus();

        if (!$skipEvent) {
            $this->di['events_manager']->fire(['event' => 'onBeforeAdminOrderSuspend', 'params' => ['id' => $orderId]]);
        }

        if ($orderStatus != Order::STATUS_ACTIVE) {
            throw new InformationException('Only active orders can be suspended');
        }

        $this->_callOnService($order, Order::ACTION_SUSPEND);

        if ($order instanceof Order) {
            $order->setStatus(Order::STATUS_SUSPENDED);
            $order->setReason($reason);
            $order->setSuspendedAt(new \DateTime());
            $order->setUpdatedAt(new \DateTime());
            $this->di['em']->persist($order);
            $this->di['em']->flush();
        } else {
            $order->status = Order::STATUS_SUSPENDED;
            $order->reason = $reason;
            $order->suspended_at = date('Y-m-d H:i:s');
            $order->updated_at = date('Y-m-d H:i:s');
            $this->di['em']->persist($order);
        }

        $note = ($reason === null) ? 'Order suspended' : 'Order suspended for ' . $reason;
        $this->saveStatusChange($order, $note);

        if (!$skipEvent) {
            $this->di['events_manager']->fire(['event' => 'onAfterAdminOrderSuspend', 'params' => ['id' => $orderId]]);
        }

        $this->di['logger']->info('Suspended order #%s', $orderId);

        return true;
    }

    public function unsuspendFromOrder(Order $order): bool
    {
        $orderId = $order instanceof Order ? $order->getId() : $order->getId();
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminOrderUnsuspend', 'params' => ['id' => $orderId]]);

        $this->_callOnService($order, Order::ACTION_UNSUSPEND);

        if ($order instanceof Order) {
            $order->setStatus(Order::STATUS_ACTIVE);
            $order->setReason(null);
            $order->setSuspendedAt(null);
            $order->setUnsuspendedAt(new \DateTime());
            $order->setUpdatedAt(new \DateTime());
            $this->di['em']->persist($order);
            $this->di['em']->flush();
        } else {
            $order->status = Order::STATUS_ACTIVE;
            $order->reason = null;
            $order->suspended_at = null;
            $order->unsuspended_at = date('Y-m-d H:i:s');
            $order->updated_at = date('Y-m-d H:i:s');
            $this->di['em']->persist($order);
        }

        $this->saveStatusChange($order, 'Order unsuspended');

        $this->di['events_manager']->fire(['event' => 'onAfterAdminOrderUnsuspend', 'params' => ['id' => $orderId]]);

        $this->di['logger']->info('Unsuspended order #%s', $orderId);

        return true;
    }

    public function cancelFromOrder(Order $order, $reason = null, $skipEvent = false): bool
    {
        $orderId = $order instanceof Order ? $order->getId() : $order->getId();
        $this->assertOrderCanBeCanceled($order);
        $this->beginCancellation($order, $skipEvent);

        $subscriptionService = $this->di['mod_service']('Invoice', 'Subscription');
        $subscriptionService->cancelForOrder($order);

        $this->completeCancellation($order, $reason, $skipEvent);

        $productService = $this->di['mod_service']('Product');
        $productService->releaseReservedPromoRedemptionsForOrder($order, 'order_canceled');

        $note = ($reason === null) ? 'Order canceled' : 'Canceled order for ' . $reason;
        $this->saveStatusChange($order, $note);

        if (!$skipEvent) {
            $this->di['events_manager']->fire(['event' => 'onAfterAdminOrderCancel', 'params' => ['id' => $orderId]]);
        }

        $this->di['logger']->info('Canceled order #%s', $orderId);

        return true;
    }

    public function scheduleCancellationFromOrder(Order $order, $reason = null): bool
    {
        $orderId = $order instanceof Order ? $order->getId() : $order->getId();
        $this->assertOrderCanBeCanceled($order);

        $subscriptionService = $this->di['mod_service']('Invoice', 'Subscription');
        if (!$subscriptionService->canCancelAtPeriodEndForOrder($order)) {
            throw new InformationException('No active gateway subscription that supports cancellation at period end is linked to this order.');
        }

        if ($subscriptionService->scheduleCancellationForOrder($order) === 0) {
            throw new InformationException('No active gateway subscription is linked to this order.');
        }
        $this->updateOrderMeta($order, [self::META_CANCEL_AT_PERIOD_END => '1']);

        if ($order instanceof Order) {
            $order->setReason($reason);
            $order->setUpdatedAt(new \DateTime());
            $this->di['em']->persist($order);
            $this->di['em']->flush();
        } else {
            $order->reason = $reason;
            $order->updated_at = date('Y-m-d H:i:s');
            $this->di['em']->persist($order);
        }
        $this->saveStatusChange($order, 'Cancellation scheduled at the end of the current billing period');
        $this->di['logger']->info('Scheduled cancellation for order #%s at the end of the current billing period', $orderId);

        return true;
    }

    public function finalizeCancellationFromGateway(Order $order, $reason = null): bool
    {
        $this->assertOrderCanBeCanceled($order);
        $this->beginCancellation($order, false);

        $this->completeCancellation($order, $reason, false);

        return true;
    }

    private function assertOrderCanBeCanceled(Order $order): void
    {
        $status = $order instanceof Order ? $order->getStatus() : $order->getStatus();
        if (!in_array($status, [Order::STATUS_CANCELED, Order::STATUS_PENDING_SETUP, Order::STATUS_FAILED_SETUP], true)) {
            return;
        }

        throw new \FOSSBilling\Exception('Cannot cancel ' . $status . ' order');
    }

    private function beginCancellation(Order $order, bool $skipEvent): void
    {
        $orderId = $order instanceof Order ? $order->getId() : $order->getId();
        if (!$skipEvent) {
            $this->di['events_manager']->fire(['event' => 'onBeforeAdminOrderCancel', 'params' => ['id' => $orderId]]);
        }

        $this->_callOnService($order, Order::ACTION_CANCEL);
    }

    private function completeCancellation(Order $order, $reason, bool $skipEvent): void
    {
        $orderId = $order instanceof Order ? $order->getId() : $order->getId();

        if ($order instanceof Order) {
            $order->setStatus(Order::STATUS_CANCELED);
            $order->setReason($reason);
            $order->setCanceledAt(new \DateTime());
            $order->setExpiresAt(null);
            $order->setSuspendedAt(null);
            $order->setUpdatedAt(new \DateTime());
            $this->di['em']->persist($order);
            $this->di['em']->flush();
        } else {
            $order->status = Order::STATUS_CANCELED;
            $order->reason = $reason;
            $order->canceled_at = date('Y-m-d H:i:s');
            $order->expires_at = null;
            $order->suspended_at = null;
            $order->updated_at = date('Y-m-d H:i:s');
            $this->di['em']->persist($order);
        }
        $this->di['em']->getConnection()->executeStatement(
            'DELETE FROM client_order_meta WHERE client_order_id = :order_id AND name = :name',
            ['order_id' => $orderId, 'name' => self::META_CANCEL_AT_PERIOD_END],
        );
    }

    public function uncancelFromOrder(Order $order): bool
    {
        $orderId = $order instanceof Order ? $order->getId() : $order->getId();
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminOrderUncancel', 'params' => ['id' => $orderId]]);

        $this->_callOnService($order, Order::ACTION_UNCANCEL);

        $expiresAt = null;
        $period = $order instanceof Order ? $order->getPeriod() : $order->getPeriod();
        if ($period) {
            $periodObj = $this->di['period']($period);
            $expiresAt = $periodObj->getExpirationTime(time());
            $expiresAt = date('Y-m-d H:i:s', $expiresAt);
        }

        if ($order instanceof Order) {
            $order->setStatus(Order::STATUS_ACTIVE);
            $order->setReason(null);
            $order->setActivatedAt(new \DateTime());
            $order->setExpiresAt($expiresAt ? new \DateTime($expiresAt) : null);
            $order->setSuspendedAt(null);
            $order->setCanceledAt(null);
            $order->setUpdatedAt(new \DateTime());
            $this->di['em']->persist($order);
            $this->di['em']->flush();
        } else {
            $order->status = Order::STATUS_ACTIVE;
            $order->reason = null;
            $order->activated_at = date('Y-m-d H:i:s');
            $order->expires_at = $expiresAt;
            $order->suspended_at = null;
            $order->canceled_at = null;
            $order->updated_at = date('Y-m-d H:i:s');
            $this->di['em']->persist($order);
        }

        $this->saveStatusChange($order, 'Activated canceled order');

        $this->di['events_manager']->fire(['event' => 'onAfterAdminOrderUncancel', 'params' => ['id' => $orderId]]);

        $this->di['logger']->info('Uncanceled order #%s', $orderId);

        return true;
    }

    public function rmInvoiceItemByOrder(Order $order): void
    {
        $orderId = $order instanceof Order ? $order->getId() : $order->getId();

        $items = $this->di['em']->getRepository(InvoiceItem::class)->findBy([
            'relId' => (string) $orderId,
            'status' => InvoiceItem::STATUS_PENDING_PAYMENT,
        ]);
        foreach ($items as $item) {
            if ($item instanceof InvoiceItem) {
                $this->di['em']->remove($item);
            } elseif ($item instanceof InvoiceItem) {
                $this->di['em']->remove($item);
            }
        }
        if (!empty($items)) {
            $this->di['em']->flush();
        }
    }

    public function rmClientOrderStatusByOrder(Order $order): void
    {
        $orderId = $order instanceof Order ? $order->getId() : $order->getId();
        $this->getOrderStatusRepository()->rmByOrderId($orderId);
    }

    public function rmOrder(Order $model): void
    {
        $isGroupMaster = $model instanceof Order ? $model->isGroupMaster() : $model->isGroupMaster();

        if ($isGroupMaster) {
            $orderId = $model instanceof Order ? $model->getId() : $model->getId();
            $list = $this->getOrderAddonsList($model);
            foreach ($list as $addon) {
                if ($addon instanceof Order) {
                    $addon->setGroupMaster(true);
                    $addon->setGroupId('0');
                    $this->di['em']->persist($addon);
                } else {
                    $addon->group_master = 1;
                    $addon->group_id = 0;
                    $this->di['em']->persist($addon);
                }
            }
            if ($addon instanceof Order) {
                $this->di['em']->flush();
            }
        }
        if ($model instanceof Order) {
            $this->di['em']->remove($model);
            $this->di['em']->flush();
        } else {
            $this->di['em']->remove($model);
        }
    }

    public function deleteFromOrder(Order $order, bool $forceDelete = false): bool
    {
        $orderId = $order instanceof Order ? $order->getId() : $order->getId();
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminOrderDelete', 'params' => ['id' => $orderId]]);

        $orderStatus = $order instanceof Order ? $order->getStatus() : $order->getStatus();
        if ($orderStatus == Order::STATUS_PENDING_SETUP) {
            $this->rmInvoiceItemByOrder($order);
        }

        try {
            $this->_callOnService($order, Order::ACTION_DELETE);
        } catch (\Exception $e) {
            if (!$forceDelete) {
                throw $e;
            }
            $this->di['logger']->info("{$e->getMessage()} in {$e->getFile()} : {$e->getFile()}");
        }

        $productService = $this->di['mod_service']('Product');
        $productService->releaseReservedPromoRedemptionsForOrder($order, 'order_deleted');
        $this->rmClientOrderStatusByOrder($order);
        $this->rmOrder($order);

        $this->di['events_manager']->fire(['event' => 'onAfterAdminOrderDelete', 'params' => ['id' => $orderId]]);
        $this->di['logger']->info('Deleted order #%s', $orderId);

        return true;
    }

    public function getExpiredOrders()
    {
        return $this->getOrderRepository()->getExpired();
    }

    public function batchSuspendExpired(): bool
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
                $this->di['logger']->info($e->getMessage());
            }
        }

        $this->di['events_manager']->fire(['event' => 'onAfterAdminBatchSuspendOrders']);

        $this->di['logger']->info('Executed action to suspend expired orders');

        return true;
    }

    public function batchCancelSuspended(): bool
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

        $orders = $this->di['em']->getConnection()->fetchAllAssociative($sql, ['days' => $days]);

        foreach ($orders as $orderArr) {
            try {
                $order = $this->getOrderRepository()->find((int) $orderArr['id']);
                if (!$order instanceof Order) {
                    throw new \FOSSBilling\Exception('Order not found');
                }
                $this->cancelFromOrder($order, $reason);
            } catch (\Exception $e) {
                $this->di['logger']->info($e->getMessage());
            }
        }

        $this->di['events_manager']->fire(['event' => 'onAfterAdminBatchCancelSuspendedOrders']);

        $this->di['logger']->info('Executed action to cancel suspended orders');

        return true;
    }

    public function updateOrderConfig(Order $order, array $config): bool
    {
        $formId = $order instanceof Order ? $order->getFormId() : $order->getFormId();
        $orderId = $order instanceof Order ? $order->getId() : $order->getId();

        if ($formId) {
            $formbuilderService = $this->di['mod_service']('formbuilder');
            $form = $formbuilderService->getForm((int) $formId);
            $this->validateConfigAgainstForm($config, $form);
        }

        $oldConfig = $order instanceof Order ? $order->getConfig() : $order->getConfig();

        if ($order instanceof Order) {
            $order->setConfig(json_encode($config));
            $order->setUpdatedAt(new \DateTime());
            $this->di['em']->persist($order);
            $this->di['em']->flush();
        } else {
            $order->config = json_encode($config);
            $order->updated_at = date('Y-m-d H:i:s');
            $this->di['em']->persist($order);
        }

        $this->di['logger']->info(sprintf("Order #%s config changes:\n%s\n%s", $orderId, $oldConfig, $order instanceof Order ? $order->getConfig() : $order->getConfig()));

        return true;
    }

    private function validateConfigAgainstForm(array $config, array $form): void
    {
        foreach ($form['fields'] as $field) {
            $name = $field['name'];
            $value = $config[$name] ?? null;

            if (!empty($field['required']) && ($value === null || $value === '' || (is_array($value) && count($value) === 0))) {
                throw new \FOSSBilling\Exception('Field ":field" is required', [':field' => $field['label']], 4892);
            }

            $options = $field['options'] ?? [];
            if (!empty($options)) {
                if ($field['type'] === 'select' || $field['type'] === 'radio') {
                    if ($value === null || $value === '') {
                        continue;
                    }

                    if (!is_scalar($value)) {
                        throw new \FOSSBilling\Exception('Invalid value for field ":field"', [':field' => $field['label']], 4893);
                    }

                    if (!array_key_exists($value, $options) && !in_array($value, $options, true)) {
                        throw new \FOSSBilling\Exception('Invalid value for field ":field"', [':field' => $field['label']], 4893);
                    }
                } elseif ($field['type'] === 'checkbox') {
                    if (is_array($value)) {
                        foreach ($value as $v) {
                            if (!in_array($v, $options, true)) {
                                throw new \FOSSBilling\Exception('Invalid value for field ":field"', [':field' => $field['label']], 4894);
                            }
                        }
                    }
                }
            }
        }
    }

    public function getOrderStatusSearchQuery($data): array
    {
        $query = 'SELECT * FROM client_order_status';

        $oid = $data['client_order_id'] ?? null;

        $where = [];
        $bindings = [];

        if ($oid !== null) {
            $where[] = 'client_order_id = :client_order_id';

            $bindings['client_order_id'] = $oid;
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }

        $query .= ' ORDER BY id DESC';

        return [$query, $bindings];
    }

    public function orderStatusAdd(Order $order, $status, $notes = null): bool
    {
        if (!in_array($status, Order::getValidStatuses(), true)) {
            throw new InformationException('Invalid order status: :status', [':status:' => $status]);
        }

        $orderId = $order instanceof Order ? $order->getId() : $order->getId();

        $bean = new OrderStatus();
        $bean->setClientOrderId($orderId);
        $bean->setStatus($status);
        $bean->setNotes($notes);
        $this->di['em']->persist($bean);
        $this->di['em']->flush();

        $this->di['logger']->info('Added order status history message to order #%s', $bean->getId());

        return true;
    }

    public function orderStatusRm($id): bool
    {
        $orderStatus = $this->getOrderStatusRepository()->find($id);
        if (!$orderStatus instanceof OrderStatus) {
            throw new \FOSSBilling\Exception('Order history line not found');
        }

        $this->di['em']->remove($orderStatus);
        $this->di['em']->flush();

        $this->di['logger']->info('Order history line removed');

        return true;
    }

    public function findForClientById(ClientEntity $client, $id)
    {
        return $this->getOrderRepository()->findForClientById($client->getId(), (int) $id);
    }

    public function findByClientIdAndOrderId(int $clientId, int $orderId): ?Order
    {
        return $this->getOrderRepository()->findForClientById($clientId, $orderId);
    }

    public function getOrderServiceData(Order $order, $identity = null)
    {
        $orderId = $order instanceof Order ? $order->getId() : $order->getId();
        $serviceType = $order instanceof Order ? $order->getServiceType() : $order->getServiceType();
        $service = $this->getOrderService($order);
        if (!is_object($service)) {
            $this->di['logger']->info("Order #{$orderId} has no active service.");

            return null;
        }
        $srepo = $this->di['mod_service']('service' . $serviceType);
        if (!method_exists($srepo, 'toApiArray')) {
            $this->di['logger']->info("Service #{$serviceType} method toApiArray is missing.");

            return null;
        }

        return $srepo->toApiArray($service, true, $identity);
    }

    public function getTotal(Order $model): float
    {
        if ($model instanceof Order) {
            return $this->calculateTotal($model->getPrice() ?? 0, $model->getQuantity() ?? 1);
        }

        return $this->calculateTotal($model->getPrice() ?? 0, $model->getQuantity() ?? 1);
    }

    private function calculateTotal($price, $quantity): float
    {
        return (float) $price * (int) $quantity;
    }

    public function setUnpaidInvoice(Order $order, Invoice $proforma): void
    {
        $orderId = $order instanceof Order ? $order->getId() : $order->getId();
        if ($order instanceof Order) {
            $order->setUnpaidInvoiceId($proforma->getId());
            $order->setUpdatedAt(new \DateTime());
            $this->di['em']->persist($order);
            $this->di['em']->flush();
        } else {
            $order->unpaid_invoice_id = $proforma->getId();
            $order->updated_at = date('Y-m-d H:i:s');
            $this->di['em']->persist($order);
        }
    }

    public function unsetUnpaidInvoice(Order $order): void
    {
        $orderId = $order instanceof Order ? $order->getId() : $order->getId();
        if ($order instanceof Order) {
            $order->setUnpaidInvoiceId(null);
            $order->setUpdatedAt(new \DateTime());
            $this->di['em']->persist($order);
            $this->di['em']->flush();
        } else {
            $order->unpaid_invoice_id = null;
            $order->updated_at = date('Y-m-d H:i:s');
            $this->di['em']->persist($order);
        }
    }

    public function getRelatedOrderIdByType(Order $order, $type)
    {
        $groupId = $order instanceof Order ? $order->getGroupId() : $order->getGroupId();
        $serviceType = $order instanceof Order ? $order->getServiceType() : $order->getServiceType();

        $o = $this->getOrderRepository()->findOneBy(['groupId' => $groupId, 'serviceType' => $type]);

        if ($o instanceof Order) {
            return $o->getId();
        }

        return null;
    }

    public function rmByClient(ClientEntity $client): void
    {
        $productService = $this->di['mod_service']('Product');
        $orders = $this->getOrderRepository()->findByClientId($client->getId());
        foreach ($orders as $order) {
            $productService->releaseReservedPromoRedemptionsForOrder($order, 'client_deleted');
        }

        $query = $this->di['dbal']->createQueryBuilder();
        $query
            ->delete('client_order')
            ->where('client_id = :id')
            ->setParameter('id', $client->getId())
            ->executeStatement();
    }

    public function exportCSV(array $headers): Response
    {
        if (!$headers) {
            $headers = ['id', 'client_id', 'product_id', 'title', 'currency', 'service_type', 'period', 'quantity', 'price', 'discount', 'status', 'reason', 'notes'];
        }

        return $this->di['csv_response_factory']->create('client_order', 'orders.csv', $headers);
    }

    private function getProductId(Product $product): int
    {
        return (int) $product->getId();
    }

    private function getProductFormId(Product $product): ?int
    {
        return $product->getFormId();
    }

    private function getProductTitle(Product $product): string
    {
        return (string) $product->getTitle();
    }

    private function getProductType(Product $product): string
    {
        return (string) $product->getType();
    }

    private function getProductUnit(Product $product): string
    {
        return $product->getUnit();
    }

    private function isAddonProduct(Product $product): bool
    {
        return $product->isAddon();
    }
}
