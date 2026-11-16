<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Order;

use Box\Mod\Client\Entity\Client as ClientEntity;
use Box\Mod\Currency\Entity\Currency;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Entity\OrderMeta;
use Box\Mod\Order\Entity\OrderStatus;
use Box\Mod\Order\Event\AfterAdminBatchCancelSuspendedOrdersEvent;
use Box\Mod\Order\Event\AfterAdminBatchCancelUnpaidOrdersEvent;
use Box\Mod\Order\Event\AfterAdminBatchSendSuspensionWarningsEvent;
use Box\Mod\Order\Event\AfterAdminBatchSuspendOrdersEvent;
use Box\Mod\Order\Event\AfterAdminOrderActivateEvent;
use Box\Mod\Order\Event\AfterAdminOrderCancelEvent;
use Box\Mod\Order\Event\AfterAdminOrderCreateEvent;
use Box\Mod\Order\Event\AfterAdminOrderDeleteEvent;
use Box\Mod\Order\Event\AfterAdminOrderRenewEvent;
use Box\Mod\Order\Event\AfterAdminOrderSuspendEvent;
use Box\Mod\Order\Event\AfterAdminOrderUncancelEvent;
use Box\Mod\Order\Event\AfterAdminOrderUnsuspendEvent;
use Box\Mod\Order\Event\AfterAdminOrderUpdateEvent;
use Box\Mod\Order\Event\BeforeAdminBatchCancelSuspendedOrdersEvent;
use Box\Mod\Order\Event\BeforeAdminBatchCancelUnpaidOrdersEvent;
use Box\Mod\Order\Event\BeforeAdminBatchSendSuspensionWarningsEvent;
use Box\Mod\Order\Event\BeforeAdminBatchSuspendOrdersEvent;
use Box\Mod\Order\Event\BeforeAdminOrderActivateEvent;
use Box\Mod\Order\Event\BeforeAdminOrderCancelEvent;
use Box\Mod\Order\Event\BeforeAdminOrderCreateEvent;
use Box\Mod\Order\Event\BeforeAdminOrderDeleteEvent;
use Box\Mod\Order\Event\BeforeAdminOrderRenewEvent;
use Box\Mod\Order\Event\BeforeAdminOrderSuspendEvent;
use Box\Mod\Order\Event\BeforeAdminOrderUncancelEvent;
use Box\Mod\Order\Event\BeforeAdminOrderUnsuspendEvent;
use Box\Mod\Order\Event\BeforeAdminOrderUpdateEvent;
use Box\Mod\Order\Repository\OrderMetaRepository;
use Box\Mod\Order\Repository\OrderRepository;
use Box\Mod\Order\Repository\OrderStatusRepository;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Staff\Entity\Admin;
use Doctrine\DBAL\LockMode;
use FOSSBilling\Core\Container\InjectionAwareInterface;
use FOSSBilling\Core\Doctrine\NamedLock;
use FOSSBilling\Core\Doctrine\RowLock;
use FOSSBilling\Core\Exception\InformationException;
use FOSSBilling\Core\Logging\Logger;
use FOSSBilling\Core\SortOptions;
use FOSSBilling\Core\Validation\NonNegativeIntegerValidator;
use FOSSBilling\Core\Validation\PriceValidator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;

class Service implements InjectionAwareInterface
{
    /**
     * Columns on the `client_order` table permitted in CSV exports.
     * The `config` column (service provisioning credentials) is excluded.
     */
    private const array EXPORTABLE_COLUMNS = [
        'id', 'client_id', 'product_id', 'form_id', 'promo_id', 'promo_recurring',
        'promo_used', 'group_id', 'group_master', 'invoice_option', 'title',
        'currency', 'unpaid_invoice_id', 'service_id', 'service_type', 'period',
        'quantity', 'unit', 'price', 'discount', 'status', 'reason', 'notes',
        'suspension_grace_days', 'referred_by', 'expires_at', 'activated_at',
        'suspended_at', 'unsuspended_at', 'canceled_at', 'created_at', 'updated_at',
    ];

    /** Subset of EXPORTABLE_COLUMNS used when the caller passes no headers. */
    private const array DEFAULT_EXPORT_COLUMNS = [
        'id', 'client_id', 'product_id', 'title', 'currency', 'service_type',
        'period', 'quantity', 'price', 'discount', 'status', 'reason', 'notes',
    ];

    /** Fields safe to expose in a typed create event. Provisioning config and payment details are excluded. */
    private const array CREATE_EVENT_INPUT_FIELDS = [
        'quantity', 'price', 'currency', 'period', 'group_id', 'title', 'activate',
        'invoice_option', 'mark_invoice_paid', 'created_at', 'updated_at', 'promo_id', 'notes',
    ];

    /** Fields safe to expose in a typed update event. Metadata may contain secrets and is excluded. */
    private const array UPDATE_EVENT_INPUT_FIELDS = [
        'period', 'created_at', 'activated_at', 'expires_at', 'invoice_option', 'title',
        'price', 'status', 'notes', 'reason', 'suspension_grace_days',
    ];

    public const string META_PAID_RENEWAL_OUTCOME_PREFIX = 'paid_renewal_outcome_';
    public const string META_FREE_RENEWAL_CLAIM_PREFIX = 'free_domain_renewal_claim_';
    public const string META_FREE_RENEWAL_RETRY_PREFIX = 'free_domain_renewal_retry_';
    public const string RENEWAL_OUTCOME_PENDING = 'pending';
    public const string RENEWAL_OUTCOME_COMPLETED = 'completed';
    public const string RENEWAL_OUTCOME_RETRY = 'retry';

    public const META_CANCEL_AT_PERIOD_END = 'cancel_at_period_end';
    public const META_SCHEDULED_CANCEL_CLAIM = 'scheduled_cancel_claim';
    public const META_SCHEDULED_CANCEL_PHASE = 'scheduled_cancel_phase';
    public const META_SCHEDULED_CANCEL_OPTIONS = 'scheduled_cancel_options';

    public const META_DELETION_STATE = DeletionRecovery::META_NAME;

    /** @var array<string, true> Locks shared by service instances using the same connection. */
    private static array $lifecycleLocks = [];
    private const string META_SUSPENSION_WARNING_FOR = 'suspension_warning_for';

    public const META_MERGE_RENEWALS = 'merge_renewals';

    public const META_STOCK_RESERVED_QTY = 'stock_reserved_qty';

    private const array BUILT_IN_SERVICE_TYPES = [
        \Box\Mod\Product\Service::CUSTOM,
        \Box\Mod\Product\Service::LICENSE,
        \Box\Mod\Product\Service::DOWNLOADABLE,
        \Box\Mod\Product\Service::HOSTING,
        \Box\Mod\Product\Service::DOMAIN,
        \Box\Mod\Product\Service::APIKEY,
    ];

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
        $this->orderRepository ??= $this->di['em']->getRepository(Order::class);

        return $this->orderRepository;
    }

    public function getOrderMetaRepository(): OrderMetaRepository
    {
        $this->orderMetaRepository ??= $this->di['em']->getRepository(OrderMeta::class);

        return $this->orderMetaRepository;
    }

    public function getOrderStatusRepository(): OrderStatusRepository
    {
        $this->orderStatusRepository ??= $this->di['em']->getRepository(OrderStatus::class);

        return $this->orderStatusRepository;
    }

    private function orderId(Order $order): int
    {
        return (int) $order->getId();
    }

    /**
     * @param list<string> $allowedFields
     *
     * @return array<string, mixed>
     */
    private function filterOrderEventInput(array $input, array $allowedFields): array
    {
        return array_intersect_key($input, array_flip($allowedFields));
    }

    private function persistOrder(Order $order): void
    {
        $this->di['em']->persist($order);
        $this->di['em']->flush();
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

    #[AsEventListener]
    public function sendOrderActivationEmail(AfterAdminOrderActivateEvent $event): void
    {
        $di = $this->di ?? throw new \LogicException('Order service must be initialized before handling events.');
        $orderId = $event->orderId;

        try {
            $order = $di['em']->getRepository(Order::class)->find($orderId);
            if (!$order instanceof Order) {
                throw new \FOSSBilling\Core\Exception\BaseException('Order not found');
            }
            $service = $this->getOrderServiceData($order);
            $orderArr = $this->toApiArray($order, true);

            $email = ['id' => $orderId, ...$event->resultParameters];
            $email['to_client'] = $order->getClientId();
            $email['code'] = sprintf('mod_service%s_activated', $orderArr['service_type']);
            $email['service'] = $service;
            $email['order'] = $orderArr;

            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            $di['logger']->withChannel('email')->error('Failed to send order activation email', ['exception' => $exc, 'order_id' => $orderId]);
        }
    }

    private function sendOrderLifecycleEmail(int $orderId, string $templateSuffix, string $logAction, bool $includeService = true, bool $retryOnFailure = false): void
    {
        $di = $this->di ?? throw new \LogicException('Order service must be initialized before handling events.');

        try {
            $order = $di['em']->getRepository(Order::class)->find($orderId);
            if (!$order instanceof Order) {
                throw new \FOSSBilling\Core\Exception\BaseException('Order not found');
            }

            $service = $includeService ? $this->getOrderServiceData($order) : null;
            $orderArr = $this->toApiArray($order, true);

            $email = [
                'to_client' => $orderArr['client']['id'],
                'code' => sprintf('mod_service%s_%s', $orderArr['service_type'], $templateSuffix),
            ];
            if ($includeService) {
                $email['service'] = $service;
            }
            $email['order'] = $orderArr;

            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            if ($retryOnFailure) {
                throw $exc;
            }
            $di['logger']->withChannel('email')->error('Failed to send order {action} email', ['action' => $logAction, 'exception' => $exc]);
        }
    }

    #[AsEventListener]
    public function sendOrderRenewalEmail(AfterAdminOrderRenewEvent $event): void
    {
        $this->sendOrderLifecycleEmail($event->orderId, 'renewed', 'renewal');
    }

    #[AsEventListener]
    public function sendOrderSuspensionEmail(AfterAdminOrderSuspendEvent $event): void
    {
        $this->sendOrderLifecycleEmail($event->orderId, 'suspended', 'suspension');
    }

    #[AsEventListener]
    public function sendOrderUnsuspensionEmail(AfterAdminOrderUnsuspendEvent $event): void
    {
        $this->sendOrderLifecycleEmail($event->orderId, 'unsuspended', 'unsuspension');
    }

    #[AsEventListener]
    public function sendOrderCancellationEmail(AfterAdminOrderCancelEvent $event): void
    {
        $this->sendOrderLifecycleEmail($event->orderId, 'canceled', 'cancellation', false, $event->retryOnEmailFailure);
    }

    #[AsEventListener]
    public function sendOrderUncancelEmail(AfterAdminOrderUncancelEvent $event): void
    {
        $this->sendOrderLifecycleEmail($event->orderId, 'renewed', 'uncancel');
    }

    /**
     * Guards against interacting with a service on an expired order.
     *
     * @throws InformationException if the order has an expiry date in the past
     */
    public function assertOrderUsable(Order $order): void
    {
        $expiresAt = $order->getExpiresAt();
        if ($expiresAt === null) {
            return;
        }

        if ($expiresAt->getTimestamp() <= time()) {
            throw new InformationException('Subscription expired');
        }
    }

    /**
     * Returns the service backing an order.
     *
     * Built-in service types return their Doctrine entity (or null when the
     * entity class is unknown). Third-party service types return the raw
     * `service_<type>` row as a DBAL assoc array — or false when the order's
     * service row no longer exists, and null when the order has no service yet.
     * The value is passed to third-party module methods as-is; extension
     * authors must access fields via array keys.
     */
    public function getOrderService(Order $order)
    {
        $serviceId = $order->getServiceId();
        $serviceType = $order->getServiceType();

        if ($serviceId !== null) {
            if (in_array($serviceType, self::BUILT_IN_SERVICE_TYPES, true)) {
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
        $serviceType = $order->getServiceType();
        $s = \FOSSBilling\Core\Utils\Str::toCamelCase($serviceType, true);

        return 'Service' . ucfirst((string) $s);
    }

    protected function _getServiceEntityClass(Order $order): ?string
    {
        $serviceType = $order->getServiceType();

        return match ($serviceType) {
            \Box\Mod\Product\Service::DOWNLOADABLE => \Box\Mod\Servicedownloadable\Entity\ServiceDownloadable::class,
            \Box\Mod\Product\Service::LICENSE => \Box\Mod\Servicelicense\Entity\ServiceLicense::class,
            \Box\Mod\Product\Service::CUSTOM => \Box\Mod\Servicecustom\Entity\ServiceCustom::class,
            \Box\Mod\Product\Service::APIKEY => \Box\Mod\Serviceapikey\Entity\ServiceApiKey::class,
            \Box\Mod\Product\Service::HOSTING => \Box\Mod\Servicehosting\Entity\ServiceHosting::class,
            \Box\Mod\Product\Service::DOMAIN => \Box\Mod\Servicedomain\Entity\ServiceDomain::class,
            default => null,
        };
    }

    private const array SERVICE_ENTITY_TYPE_MAP = [
        \Box\Mod\Servicedownloadable\Entity\ServiceDownloadable::class => \Box\Mod\Product\Service::DOWNLOADABLE,
        \Box\Mod\Servicelicense\Entity\ServiceLicense::class => \Box\Mod\Product\Service::LICENSE,
        \Box\Mod\Servicecustom\Entity\ServiceCustom::class => \Box\Mod\Product\Service::CUSTOM,
        \Box\Mod\Serviceapikey\Entity\ServiceApiKey::class => \Box\Mod\Product\Service::APIKEY,
        \Box\Mod\Servicehosting\Entity\ServiceHosting::class => \Box\Mod\Product\Service::HOSTING,
        \Box\Mod\Servicedomain\Entity\ServiceDomain::class => \Box\Mod\Product\Service::DOMAIN,
    ];

    public function getServiceOrder($service): ?object
    {
        foreach (self::SERVICE_ENTITY_TYPE_MAP as $entityClass => $serviceType) {
            if ($service instanceof $entityClass) {
                return $this->getOrderRepository()->findOneBy([
                    'serviceType' => $serviceType,
                    'serviceId' => $service->getId(),
                ]);
            }
        }

        $className = $service::class;
        $serviceTypeName = str_starts_with($className, 'Model_Service')
            ? substr($className, strlen('Model_Service'))
            : (new \ReflectionClass($service))->getShortName();
        $type = \FOSSBilling\Core\Utils\Str::fromCamelCase($serviceTypeName);
        $serviceId = method_exists($service, 'getId') ? $service->getId() : $service->id;

        return $this->getOrderRepository()->findOneByServiceTypeAndServiceId($type, (int) $serviceId);
    }

    public function getConfig(Order $model): array
    {
        return $model->getConfig() ?? [];
    }

    public function productHasOrders(Product $product): bool
    {
        $order = $this->getOrderRepository()->findOneByProductId($product->getId());

        return $order instanceof Order;
    }

    public function getLogger(Order $order): Logger
    {
        $orderId = $this->orderId($order);

        return $this->di['logger']->withContext([
            'client_order_id' => $orderId,
            'status' => $order->getStatus(),
        ]);
    }

    /**
     * @param string $notes
     */
    public function saveStatusChange(Order $order, $notes = null): void
    {
        $os = new OrderStatus();
        $os->setOrder($order);
        $os->setStatus($order->getStatus());
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
                AND NOT EXISTS (
                    SELECT 1 FROM client_order_meta deletion_meta
                    WHERE deletion_meta.client_order_id = co.id
                    AND deletion_meta.name = :deletion_meta_name
                )
                AND co.period IS NOT NULL
                AND co.expires_at IS NOT NULL
                AND i.id IS NULL
                AND NOT EXISTS (
                    SELECT 1
                    FROM client_order_meta cancellation_meta
                    WHERE cancellation_meta.client_order_id = co.id
                    AND cancellation_meta.name = :cancellation_meta_name
                    AND cancellation_meta.value = :cancellation_meta_value
                )
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
                )
                /* No renewal invoice is made while a cancellation request waits. */
                AND NOT EXISTS (
                    SELECT 1
                    FROM client_order_cancel_request cancel_request
                    WHERE cancel_request.client_order_id = co.id
                    AND cancel_request.status = :cancel_request_status
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

        // co.expires_at < :expires_before is a portable stand-in for MySQL's
        // DATEDIFF(co.expires_at, NOW()) <= :days_until_expiration - DATEDIFF compares calendar
        // dates only (ignoring time-of-day), so "at most N days from today" means expires_at
        // falls on or before N days from now, i.e. before the start of day N+1. This is a plain
        // row filter, not an aggregate condition, so it belongs in WHERE (via AND) - PostgreSQL
        // rejects a HAVING clause referencing an ungrouped, non-aggregate column outright, unlike
        // MySQL/SQLite's more permissive handling of HAVING without GROUP BY.
        $query .= ' AND co.expires_at < :expires_before ORDER BY co.client_id DESC';
        $bindings['status'] = Order::STATUS_ACTIVE;
        $bindings['invoice_option'] = 'issue-invoice';
        $bindings['unpaid_invoice_status'] = Invoice::STATUS_UNPAID;
        $bindings['deletion_meta_name'] = self::META_DELETION_STATE;
        $bindings['cancellation_meta_name'] = self::META_CANCEL_AT_PERIOD_END;
        $bindings['cancellation_meta_value'] = '1';
        $bindings['pending_item_type'] = \Box\Mod\Invoice\Entity\InvoiceItem::TYPE_ORDER;
        $bindings['pending_item_task'] = \Box\Mod\Invoice\Entity\InvoiceItem::TASK_RENEW;
        $bindings['pending_item_status'] = \Box\Mod\Invoice\Entity\InvoiceItem::STATUS_EXECUTED;
        $bindings['pending_invoice_status'] = Invoice::STATUS_PAID;
        $bindings['cancel_request_status'] = Entity\ClientOrderCancelRequest::STATUS_PENDING;
        $bindings['expires_before'] = (new \DateTimeImmutable('today'))
            ->modify('+' . ((int) $days_until_expiration + 1) . ' days')
            ->format('Y-m-d H:i:s');

        return [$query, $bindings];
    }

    public function toApiArray(Order $model, $deep = true, $identity = null): array
    {
        $clientService = $this->di['mod_service']('client');
        $supportService = $this->di['mod_service']('support');
        $modelId = $this->orderId($model);
        $modelClientId = $model->getClientId();

        $data = [
            'id' => $modelId,
            'client_id' => $modelClientId,
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
            'suspension_grace_days' => $model->getSuspensionGraceDays(),
            'referred_by' => $model->getReferredBy(),
            'expires_at' => $model->getExpiresAt()?->format('Y-m-d H:i:s'),
            'activated_at' => $model->getActivatedAt()?->format('Y-m-d H:i:s'),
            'suspended_at' => $model->getSuspendedAt()?->format('Y-m-d H:i:s'),
            'unsuspended_at' => $model->getUnsuspendedAt()?->format('Y-m-d H:i:s'),
            'canceled_at' => $model->getCanceledAt()?->format('Y-m-d H:i:s'),
            'created_at' => $model->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $model->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];

        $data['config'] = $model->getConfig() ?? [];
        $data['total'] = $this->getTotal($model);
        $data['discount'] ??= 0;
        $data['meta'] = $this->getOrderMetaRepository()->getPairsForOrder($modelId);
        $data['active_tickets'] = $supportService->getSupportTicketRepository()->countActiveTicketsForOrder($modelId);
        $client = $this->di['em']->getRepository(ClientEntity::class)->find($modelClientId);
        if (!$client instanceof ClientEntity) {
            throw new InformationException('Client not found');
        }
        $data['client'] = $clientService->toApiArray($client, false);

        // Clients must not see staff notes on their orders.
        if (!$identity instanceof Admin) {
            unset($data['notes']);
        }

        if ($identity instanceof Admin) {
            $data['config'] = $this->getConfig($model);
            $productService = $this->di['mod_service']('product');
            $productId = $model->getProductId();
            $hasProduct = $productId !== null && $productId > 0;
            $data['plugin'] = $hasProduct ? $productService->getProductPluginById($productId) : null;
            $product = $hasProduct ? $productService->getProductRepository()->find($productId) : null;
            $data['product_suspension_grace_days'] = $product instanceof Product ? $product->getSuspensionGraceDays() : null;
        }

        return $data;
    }

    /**
     * Get multiple orders in a batch for API response.
     *
     * @param array                   $ids      Array of order IDs to fetch
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
                $clients[$client->getId()] = $clientService->toApiArray($client, false);
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
            $data['config'] = json_decode($order['config'] ?? 'null', true, 512, JSON_THROW_ON_ERROR) ?? [];
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

            if (!$identity instanceof Admin) {
                unset($data['notes']);
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
            // A day range rather than DATE_FORMAT(...) = :created_at, which MySQL supports but
            // PostgreSQL and SQLite don't.
            $where[] = 'co.created_at >= :created_at_start AND co.created_at < :created_at_end';
            $dayStart = strtotime(date('Y-m-d', strtotime((string) $created_at)));
            $bindings['created_at_start'] = date('Y-m-d H:i:s', $dayStart);
            $bindings['created_at_end'] = date('Y-m-d H:i:s', strtotime('+1 day', $dayStart));
        }

        if ($date_from) {
            // Compares directly against the datetime column rather than UNIX_TIMESTAMP(co.created_at),
            // which MySQL supports but PostgreSQL and SQLite don't.
            $where[] = 'co.created_at >= :date_from';
            $bindings['date_from'] = date('Y-m-d H:i:s', strtotime((string) $date_from));
        }

        if ($date_to) {
            $where[] = 'co.created_at <= :date_to';
            $bindings['date_to'] = date('Y-m-d H:i:s', strtotime((string) $date_to));
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

        $sort = SortOptions::fromArray($data, [
            'id' => 'co.id',
            'status' => 'co.status',
            'title' => 'co.title',
            'created_at' => 'co.created_at',
            'updated_at' => 'co.updated_at',
        ]);
        $orderBy = $sort->toOrderByClause('co.id') ?? 'co.id DESC';
        $query .= " ORDER BY {$orderBy}";

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
        } elseif ($clientCurrency = $client->getCurrency()) {
            $currency = $currencyRepository->findOneByCode($clientCurrency);
        } else {
            $currency = $currencyRepository->findDefault();
        }
        if (!$currency instanceof Currency) {
            throw new \FOSSBilling\Core\Exception\BaseException('Currency could not be determined for order');
        }

        $this->di['event_dispatcher']->dispatch(new BeforeAdminOrderCreateEvent(
            (int) $client->getId(),
            $this->getProductId($product),
            $this->getProductType($product),
            $this->filterOrderEventInput($data, self::CREATE_EVENT_INPUT_FIELDS),
        ));

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
            throw new \FOSSBilling\Core\Exception\BaseException('Group ID parameter is missing for addon product order', null, 832);
        }

        if (!empty($group_id)) {
            $parent_order = $this->getMasterOrderForClient($client, $group_id);
            if (!$parent_order instanceof Order) {
                throw new \FOSSBilling\Core\Exception\BaseException('Parent order :group_id was not found', [':group_id' => $group_id]);
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
        $markInvoicePaid = \FOSSBilling\Core\Utils\Normalizer::normalizeBoolean($data['mark_invoice_paid'] ?? false);

        $productService = $this->di['mod_service']('Product');
        $promo = $productService->resolvePromoReference(
            isset($data['promo_code']) ? (string) $data['promo_code'] : null,
            isset($data['promo_id']) ? (int) $data['promo_id'] : null
        );
        if ($promo instanceof \Box\Mod\Product\Entity\Promo) {
            if (!$productService->promoCanBeApplied($promo)) {
                throw new InformationException('The promo code has expired or does not exist');
            }

            if (!$productService->isPromoAvailableForClientGroup($promo, $client)) {
                throw new InformationException('Promo code cannot be applied to this client');
            }

            if (!$productService->canClientUsePromo($client, $promo)) {
                throw new InformationException('This client has already used this promo code');
            }
        }

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
            $promo,
            $quantity,
            &$invoice
        ) {
            $order = new Order();
            $order->setClientId($client->getId());
            $order->setProductId($this->getProductId($product));
            $order->setFormId($this->getProductFormId($product));
            $parentGroupId = $parent_order ? $parent_order->getGroupId() : null;
            $order->setGroupId($parentGroupId ?? uniqid());
            $order->setGroupMaster(!$parent_order);
            $order->setTitle($generatedOrderTitle ?? $data['title'] ?? $this->getProductTitle($product));
            $order->setCurrency($currency->getCode());
            $order->setServiceType($this->getProductType($product));
            $order->setUnit($this->getProductUnit($product));
            $order->setStatus(Order::STATUS_PENDING_SETUP);
            $order->setConfig($config);
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
                    throw new \FOSSBilling\Core\Exception\BaseException("Currency rate for '{$currency->getCode()}' is not configured");
                }
                $order->setPrice($line['price'] * $rate);
            }

            $promoDiscount = 0.0;
            if ($promo instanceof \Box\Mod\Product\Entity\Promo) {
                $productService = $this->di['mod_service']('Product');
                $promoConfig = array_merge($config, ['quantity' => $quantity]);
                if (!$productService->isPromoApplicableToProduct($promo, $product, $promoConfig)) {
                    throw new InformationException('This promo code does not apply to the selected product or billing period');
                }

                // In-transaction re-check so concurrent admin orders cannot
                // both consume the last once-per-client use.
                if ($productService->clientHasActivePromoApplicationForUpdate($client, $promo)) {
                    throw new InformationException('This client has already used this promo code');
                }

                $rate = $currencyRepository->getRateByCode($currency->getCode());
                if ($rate === null) {
                    throw new \FOSSBilling\Core\Exception\BaseException("Currency rate for '{$currency->getCode()}' is not configured");
                }

                $rawDiscount = (float) $productService->getProductDiscount($product, $promo, $promoConfig);
                $orderTotal = (float) $order->getPrice() * (float) $order->getQuantity();
                $promoDiscount = min($rawDiscount * $rate, $orderTotal);
                if ($promoDiscount > 0) {
                    $productService->usePromo($promo);
                    $order->setPromoId((int) $promo->getId());
                    $order->setPromoRecurring($promo->isRecurring());
                    $order->setPromoUsed(1);
                    $order->setDiscount($promoDiscount);
                }
            }

            $order->setNotes($data['notes'] ?? null);
            if (isset($data['created_at'])) {
                $order->setCreatedAt(new \DateTimeImmutable(date('Y-m-d H:i:s', strtotime((string) $data['created_at']))));
            } else {
                $order->setCreatedAt(new \DateTimeImmutable());
            }

            if (isset($data['updated_at'])) {
                $order->setUpdatedAt(new \DateTimeImmutable(date('Y-m-d H:i:s', strtotime((string) $data['updated_at']))));
            } else {
                $order->setUpdatedAt(new \DateTimeImmutable());
            }

            $this->di['em']->persist($order);
            $this->di['em']->flush();
            $orderId = $order->getId();

            if (isset($data['meta']) && is_array($data['meta'])) {
                foreach ($data['meta'] as $k => $v) {
                    $mm = $this->getOrderMetaRepository()->findOneByOrderIdAndName($orderId, $k);
                    if (!$mm instanceof OrderMeta) {
                        $mm = new OrderMeta();
                        $mm->setOrder($order);
                        $mm->setName($k);
                        $mm->setCreatedAt(new \DateTimeImmutable());
                    }
                    $mm->setValue($v);
                    $mm->setUpdatedAt(new \DateTimeImmutable());
                    $this->di['em']->persist($mm);
                }
                $this->di['em']->flush();
            }

            // Reserve stock now rather than at activation - this path bypasses the cart, but has
            // the same race. Done after the caller-supplied meta above so it can't be overwritten
            // by a caller passing their own "stock_reserved_qty" meta entry.
            $this->di['mod_service']('Product')->reserveStockForOrder($order);

            if ($invoiceOption == 'issue-invoice') {
                $invoiceService = $this->di['mod_service']('invoice');

                try {
                    // Promo lines are added explicitly below so the first
                    // invoice records a checkout redemption, not a renewal one.
                    $invoice = $invoiceService->generateForOrder($order, null, false);
                } catch (InformationException $e) {
                    $this->di['logger']->warning($e->getMessage());
                }

                if ($promo instanceof \Box\Mod\Product\Entity\Promo && $promoDiscount > 0 && $invoice instanceof Invoice) {
                    $clientService = $this->di['mod_service']('client');
                    $invoiceItemService = $this->di['mod_service']('Invoice', 'InvoiceItem');
                    $invoiceItemService->addNew($invoice, [
                        'title' => __trans('Discount: :product', [':product' => $order->getTitle()]),
                        'price' => $promoDiscount * -1,
                        'quantity' => 1,
                        'unit' => 'discount',
                        'rel_id' => (string) $order->getId(),
                        'taxed' => $clientService->isClientTaxable($client),
                    ]);

                    $productService = $this->di['mod_service']('Product');
                    $productService->createPromoRedemption(
                        $promo,
                        $client,
                        $order,
                        $invoice,
                        \Box\Mod\Product\Entity\PromoRedemption::PHASE_CHECKOUT,
                        $promoDiscount,
                        $currency->getCode(),
                        $order->getCreatedAt()?->format('Y-m-d H:i:s'),
                        \Box\Mod\Product\Entity\PromoRedemption::STATUS_RESERVED,
                    );
                }
            }

            if ($promo instanceof \Box\Mod\Product\Entity\Promo && $promoDiscount > 0 && !$invoice instanceof Invoice) {
                $this->di['mod_service']('Product')->createPromoRedemption(
                    $promo,
                    $client,
                    $order,
                    null,
                    \Box\Mod\Product\Entity\PromoRedemption::PHASE_CHECKOUT,
                    $promoDiscount,
                    $currency->getCode(),
                    $order->getCreatedAt()?->format('Y-m-d H:i:s'),
                    \Box\Mod\Product\Entity\PromoRedemption::STATUS_COMMITTED,
                );
            }

            return $orderId;
        });

        if ($invoice instanceof Invoice) {
            $invoiceService = $this->di['mod_service']('invoice');

            try {
                $invoiceService->issueInvoice($invoice, ['id' => $invoice->getId(), 'use_credits' => true]);

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
            throw new \FOSSBilling\Core\Exception\BaseException('Order not found');
        }

        $this->di['event_dispatcher']->dispatch(new AfterAdminOrderCreateEvent(
            $this->orderId($order),
            (int) $client->getId(),
            $this->getProductId($product),
            $this->getProductType($product),
        ));

        $this->di['logger']->info('Created order #{id}', ['id' => $id]);

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

    public function getMasterOrderForClient(ClientEntity $client, $group_id): ?Order
    {
        return $this->getOrderRepository()->findMasterByGroupAndClient((string) $group_id, (int) $client->getId());
    }

    /**
     * activate all addons on initial activation.
     *
     * @see https://github.com/boxbilling/boxbilling/issues/54
     */
    public function activateOrderAddons(Order $order): bool
    {
        $isGroupMaster = $order->isGroupMaster();
        if (!$isGroupMaster) {
            return false;
        }

        $list = $this->getOrderAddonsList($order);
        foreach ($list as $addon) {
            $addonId = $this->orderId($addon);

            try {
                $this->di['event_dispatcher']->dispatch(new BeforeAdminOrderActivateEvent($addonId));
                $this->createFromOrder($addon);
                $this->di['event_dispatcher']->dispatch(new AfterAdminOrderActivateEvent($addonId));
            } catch (\Exception $e) {
                $this->di['logger']->info($e->getMessage());
            }
        }

        return true;
    }

    public function activateOrder(Order $order, $data = []): bool
    {
        if (!empty($data['force']) && $order->getId() !== null) {
            $connection = $this->di['em']->getConnection();
            $name = 'fb:scheduled-cancel:' . $this->orderId($order);
            if (!NamedLock::acquire($connection, $name, 30)) {
                throw new InformationException('Scheduled cancellation is already being processed.');
            }

            try {
                $meta = $this->getOrderMetaRepository()->getPairsForOrder($this->orderId($order));
                if (in_array($meta[self::META_SCHEDULED_CANCEL_CLAIM] ?? null, ['1', '2'], true)
                    || ($meta[self::META_CANCEL_AT_PERIOD_END] ?? null) === '1') {
                    throw new InformationException('Finish or review the scheduled cancellation before reactivating this order.');
                }

                return $this->activateOrderWithLifecycleLock($order, $data);
            } finally {
                NamedLock::release($connection, $name);
            }
        }

        return $this->activateOrderWithLifecycleLock($order, $data);
    }

    private function activateOrderWithLifecycleLock(Order $order, $data): bool
    {
        return $this->withLifecycleLock($order, function () use ($order, $data): bool {
            $this->refreshLockedOrder($order);
            $this->assertOrderNotDeleting($order);

            return $this->activateOrderLocked($order, $data);
        });
    }

    private function activateOrderLocked(Order $order, $data): bool
    {
        $orderId = $this->orderId($order);
        $order = $this->getOrderRepository()->find($orderId);
        if (!$order instanceof Order) {
            throw new \FOSSBilling\Core\Exception\BaseException('Order :id not found', [':id' => $orderId]);
        }
        $force = !empty($data['force']);

        $orderStatus = $order->getStatus();
        if ($orderStatus === Order::STATUS_ACTIVE && !$force) {
            return true;
        }

        $statues = [
            Order::STATUS_PENDING_SETUP,
            Order::STATUS_FAILED_SETUP,
        ];
        if (!in_array($orderStatus, $statues) && !$force) {
            throw new \FOSSBilling\Core\Exception\BaseException('Only pending setup or failed orders can be activated');
        }

        $this->di['event_dispatcher']->dispatch(new BeforeAdminOrderActivateEvent($orderId));
        $result = $this->createFromOrder($order);
        $this->di['event_dispatcher']->dispatch(new AfterAdminOrderActivateEvent($orderId, is_array($result) ? $result : []));

        $this->activateOrderAddons($order);

        $this->di['logger']->info('Activated order #{order_id}', ['order_id' => $orderId]);

        return true;
    }

    public function createFromOrder(Order $order)
    {
        return $this->withLifecycleLock($order, function () use ($order) {
            $this->refreshLockedOrder($order);

            return $this->createServiceFromOrder($order);
        });
    }

    private function refreshLockedOrder(Order $order): void
    {
        $connection = $this->di['em']->getConnection();
        $id = $this->orderId($order);
        $suffix = $connection->isTransactionActive() ? RowLock::suffix($connection) : '';
        if ($connection->fetchOne('SELECT id FROM client_order WHERE id = :id' . $suffix, ['id' => $id]) === false) {
            throw new InformationException('Order :id not found', [':id' => $id]);
        }
        $this->di['em']->refresh($order, $connection->isTransactionActive() ? LockMode::PESSIMISTIC_WRITE : null);
    }

    private function createServiceFromOrder(Order $order)
    {
        $this->assertOrderNotDeleting($order);
        $orderId = $this->orderId($order);
        $serviceType = $order->getServiceType();

        $service = $this->getOrderService($order);
        if (!is_object($service)) {
            $mod = $this->di['mod']('service' . $serviceType);
            $s = $mod->getService();
            if (method_exists($s, 'create') || method_exists($s, 'action_create')) {
                $service = $this->_callOnService($order, Order::ACTION_CREATE);
                if (!is_object($service)) {
                    throw new \FOSSBilling\Core\Exception\BaseException('Error creating ' . $serviceType . ' service for order ' . $orderId);
                }

                $serviceId = method_exists($service, 'getId') ? $service->getId() : $service->id;
                $order->setServiceId((int) $serviceId);
                $order->setUpdatedAt(new \DateTimeImmutable());
                $this->persistOrder($order);
            }
        }

        // The provisioning call and the order status update that records its
        // outcome are treated as one unit: if anything here fails after the
        // service has already been provisioned (e.g. while computing the new
        // expiry date), the order must still end up in failed_setup instead
        // of being left in pending_setup. Otherwise a retry would call
        // _callOnService() again against a service that already exists on
        // the remote server.
        try {
            $result = $this->_callOnService($order, Order::ACTION_ACTIVATE);

            $period = $order->getPeriod();
            $expiresAt = $order->getExpiresAt();
            if (!empty($period)) {
                $from_time = $expiresAt?->getTimestamp() ?? time();

                $periodObj = $this->di['period']($period);
                $newExpires = date('Y-m-d H:i:s', $periodObj->getExpirationTime($from_time));
                $order->setExpiresAt(new \DateTimeImmutable($newExpires));
            }

            $order->setStatus(Order::STATUS_ACTIVE);
            $order->setActivatedAt(new \DateTimeImmutable());
            $order->setSuspendedAt(null);
            $order->setCanceledAt(null);
            $order->setUpdatedAt(new \DateTimeImmutable());

            $this->persistOrder($order);
        } catch (\Throwable $e) {
            // Caught broadly (not just \Exception): an \Error or \TypeError
            // here means the service was already provisioned remotely, so
            // the order must still be recorded as failed_setup rather than
            // left in pending_setup for a retry to re-provision it.
            $order->setStatus(Order::STATUS_FAILED_SETUP);
            $this->persistOrder($order);

            $this->saveStatusChange($order, $e->getMessage());

            throw $e;
        }

        if (!$order->getProductId()) {
            $this->di['logger']->info("Order without product ID detected Order #{$orderId}.");
        }

        // Stock was already reserved atomically at order-creation time (see
        // Product\Service::reserveStockForOrder()), so it must not be decremented again here.
        $this->saveStatusChange($order, 'Order activated');

        return $result;
    }

    public function getOrderAddonsList(Order $order): array
    {
        $groupId = $order->getGroupId();
        $clientId = $order->getClientId();

        return $this->getOrderRepository()->findAddonsExcluding((string) $groupId, (int) $clientId, $this->orderId($order));
    }

    /**
     * Dispatches a lifecycle action to the order's service module.
     *
     * Built-in service types are dispatched to `action_<action>` methods on the
     * module service with the order entity. Third-party service types are
     * dispatched to an un-prefixed `<action>` method with `$order` and the
     * `service_<type>` row as a DBAL assoc array — or false when the order's
     * service row no longer exists, and null when the order has no service yet.
     */
    protected function _callOnService(Order $order, $action, mixed ...$arguments)
    {
        return $this->withLifecycleLock($order, function () use ($order, $action, $arguments) {
            if ($action !== Order::ACTION_DELETE) {
                $this->assertOrderNotDeleting($order, true);
            }

            return $this->dispatchToService($order, $action, ...$arguments);
        });
    }

    private function dispatchToService(Order $order, $action, mixed ...$arguments)
    {
        $serviceType = $order->getServiceType();
        $serviceId = $order->getServiceId();
        $orderId = $this->orderId($order);

        $repo = $this->di['mod_service']('service' . $serviceType);

        if (in_array($serviceType, self::BUILT_IN_SERVICE_TYPES, true)) {
            $m = 'action_' . $action;
            if (!method_exists($repo, $m) || !is_callable([$repo, $m])) {
                throw new \FOSSBilling\Core\Exception\BaseException('Service ' . $serviceType . ' do not support ' . $m);
            }

            return $repo->$m($order, ...$arguments);
        }

        $service = null;
        $sdbname = 'service_' . $serviceType;
        if ($serviceId) {
            $service = $this->di['em']->getConnection()->fetchAssociative(
                'SELECT * FROM ' . $sdbname . ' WHERE id = :id',
                ['id' => $serviceId]
            );
        }
        if (method_exists($repo, $action) && is_callable([$repo, $action])) {
            return $repo->$action($order, $service);
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
            $order->setPeriod($periodObj->getCode());

            return 1;
        }

        if (!is_null($period)) {
            $order->setPeriod(null);

            return 2;
        }

        return 0;
    }

    /**
     * Stores the per-order renewal merge override. Null/empty clears the
     * override so the order inherits again; '1' forces merging, anything
     * else forbids it.
     */
    public function setMergeRenewalsOverride(Order $order, mixed $value): void
    {
        $orderId = $this->orderId($order);

        if ($value === null || $value === '') {
            $this->getOrderMetaRepository()->deleteByOrderIdAndName($orderId, self::META_MERGE_RENEWALS);

            return;
        }

        $this->updateOrderMeta($order, [self::META_MERGE_RENEWALS => \FOSSBilling\Core\Utils\Normalizer::normalizeBoolean($value) ? '1' : '0']);
    }

    public function updateOrderMeta(Order $order, $meta, bool $protectLifecycleCheckpoints = false): int
    {
        if (!is_array($meta)) {
            return 0;
        }

        $orderId = $this->orderId($order);

        if (empty($meta)) {
            if ($protectLifecycleCheckpoints && $this->getOrderMetaRepository()->getLifecycleCheckpointRows($orderId) !== []) {
                throw new InformationException('Lifecycle recovery checkpoints must be resolved through staff review.');
            }
            $this->getOrderMetaRepository()->deleteEditableByOrderId($orderId);

            return 1;
        }
        if (array_key_exists(self::META_DELETION_STATE, $meta)) {
            throw new InformationException('Deletion recovery state cannot be edited. Retry order deletion instead.');
        }
        $this->di['em']->wrapInTransaction(function () use ($order, $orderId, $meta, $protectLifecycleCheckpoints): void {
            $checkpoints = $protectLifecycleCheckpoints ? $this->getOrderMetaRepository()->getLifecycleCheckpointRows($orderId) : null;
            $edited = [];
            foreach ($meta as $k => $v) {
                $mm = $this->getOrderMetaRepository()->findOneByOrderIdAndName($orderId, $k);
                if ($mm instanceof OrderMeta) {
                    $this->di['em']->refresh($mm);
                }
                if (!$mm instanceof OrderMeta) {
                    $mm = new OrderMeta();
                    $mm->setOrder($order);
                    $mm->setName($k);
                    $mm->setCreatedAt(new \DateTimeImmutable());
                }
                $mm->setValue($v);
                $mm->setUpdatedAt(new \DateTimeImmutable());
                $this->di['em']->persist($mm);
                $edited[] = $mm;
            }
            $this->di['em']->flush();
            // Check persisted names using the column's actual collation. This
            // also catches aliases inserted before a checkpoint exists. Roll
            // back the entire batch before any such write can become visible.
            foreach ($edited as $mm) {
                if ($this->getOrderMetaRepository()->isDeletionCheckpoint($mm)) {
                    throw new InformationException('Deletion recovery state cannot be edited. Retry order deletion instead.');
                }
            }
            if ($checkpoints !== null && $checkpoints !== $this->getOrderMetaRepository()->getLifecycleCheckpointRows($orderId)) {
                throw new InformationException('Lifecycle recovery checkpoints must be resolved through staff review.');
            }
        });

        return 2;
    }

    public function updateOrder(Order $order, array $data): bool
    {
        if ($order->getId() === null || (!array_key_exists('expires_at', $data) && !array_key_exists('status', $data) && !array_key_exists('meta', $data))) {
            return $this->updateOrderLocked($order, $data);
        }

        return $this->withCancellationScheduleLock($order, fn (): bool => $this->updateOrderWithRowLock($order, $data), 0);
    }

    private function isRenewalCheckpointKey(string $key): bool
    {
        foreach ([self::META_PAID_RENEWAL_OUTCOME_PREFIX, self::META_FREE_RENEWAL_CLAIM_PREFIX, self::META_FREE_RENEWAL_RETRY_PREFIX] as $prefix) {
            if (stripos($key, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    private function updateOrderWithRowLock(Order $order, array $data): bool
    {
        $connection = $this->di['em']->getConnection();
        $connection->beginTransaction();

        try {
            $connection->fetchOne('SELECT id FROM client_order WHERE id = ?' . RowLock::suffix($connection), [$order->getId()]);
            $this->di['em']->refresh($order);
            $meta = $this->getOrderMetaRepository()->getPairsForOrder($this->orderId($order));
            $metaInput = $data['meta'] ?? null;
            if (is_array($metaInput)) {
                foreach ($metaInput === [] ? array_keys($meta) : array_keys($metaInput) as $key) {
                    if ($this->isRenewalCheckpointKey((string) $key)) {
                        throw new InformationException('Renewal outcome checkpoints must be resolved through renewal review.');
                    }
                }
            }
            if (is_array($metaInput) && (array_key_exists(self::META_SCHEDULED_CANCEL_CLAIM, $metaInput)
                || array_key_exists(self::META_CANCEL_AT_PERIOD_END, $metaInput) || array_key_exists(self::META_SCHEDULED_CANCEL_PHASE, $metaInput)
                || array_key_exists(self::META_SCHEDULED_CANCEL_OPTIONS, $metaInput))) {
                throw new InformationException('Scheduled cancellation metadata cannot be changed through order updates.');
            }
            if (in_array($meta[self::META_SCHEDULED_CANCEL_CLAIM] ?? null, ['1', '2'], true)
                && (array_key_exists('expires_at', $data) || array_key_exists('status', $data) || $metaInput === [])) {
                throw new InformationException('This order is being canceled and its status or expiration cannot be changed.');
            }
            if (($meta[self::META_CANCEL_AT_PERIOD_END] ?? null) === '1'
                && (array_key_exists('expires_at', $data) || array_key_exists('status', $data) || $metaInput === [])) {
                throw new InformationException('This order is being canceled and its status or expiration cannot be changed.');
            }
            $result = $this->updateOrderLocked($order, $data);
            $connection->commit();

            return $result;
        } catch (\Throwable $e) {
            $connection->rollBack();

            throw $e;
        }
    }

    private function updateOrderLocked(Order $order, array $data): bool
    {
        $orderId = $this->orderId($order);
        $this->di['event_dispatcher']->dispatch(new BeforeAdminOrderUpdateEvent(
            $orderId,
            $this->filterOrderEventInput($data, self::UPDATE_EVENT_INPUT_FIELDS),
        ));
        $this->updatePeriod($order, $data['period'] ?? null);

        $created_at = $data['created_at'] ?? '';
        if (!empty($created_at)) {
            $createdAtDate = date('Y-m-d H:i:s', strtotime((string) $created_at));
            $order->setCreatedAt(new \DateTimeImmutable($createdAtDate));
        }

        $activated_at = $data['activated_at'] ?? null;
        if (!empty($activated_at)) {
            $activatedAtDate = date('Y-m-d H:i:s', strtotime((string) $activated_at));
            $order->setActivatedAt(new \DateTimeImmutable($activatedAtDate));
        }

        $expires_at = $data['expires_at'] ?? null;
        if (!empty($expires_at)) {
            $expiresAtDate = date('Y-m-d H:i:s', strtotime((string) $expires_at));
            $order->setExpiresAt(new \DateTimeImmutable($expiresAtDate));
        }
        if (empty($expires_at) && !is_null($expires_at)) {
            $order->setExpiresAt(null);
        }

        $invoiceOption = $data['invoice_option'] ?? $order->getInvoiceOption();
        $order->setInvoiceOption($invoiceOption);
        $order->setTitle($data['title'] ?? $order->getTitle());

        if (isset($data['price'])) {
            $price = PriceValidator::validateAmount($data['price']);
            $order->setPrice($price);
        }

        $currentStatus = $order->getStatus();
        if (isset($data['status']) && $data['status'] !== $currentStatus) {
            if (!in_array($data['status'], Order::getValidStatuses(), true)) {
                throw new InformationException('Invalid order status: :status', [':status' => $data['status']]);
            }
            $order->setStatus($data['status']);
        }

        $notes = $data['notes'] ?? $order->getNotes();
        $reason = $data['reason'] ?? $order->getReason();
        if (array_key_exists('suspension_grace_days', $data)) {
            $graceDays = $data['suspension_grace_days'] === '' || $data['suspension_grace_days'] === null
                ? null
                : NonNegativeIntegerValidator::validate(
                    $data['suspension_grace_days'],
                    'Suspension grace days must be a non-negative integer or empty to inherit the product setting.',
                );
            $order->setSuspensionGraceDays($graceDays);
        }
        $order->setNotes($notes);
        $order->setReason($reason);

        if (array_key_exists('merge_renewals', $data)) {
            $this->setMergeRenewalsOverride($order, $data['merge_renewals']);
        }

        $this->updateOrderMeta($order, $data['meta'] ?? null, true);

        $order->setUpdatedAt(new \DateTimeImmutable());
        $this->persistOrder($order);

        $this->di['event_dispatcher']->dispatch(new AfterAdminOrderUpdateEvent($orderId));

        $this->di['logger']->info('Update order #{order_id}', ['order_id' => $orderId]);

        return true;
    }

    public function renewOrder(Order $order, array $data = []): bool
    {
        return $this->renewOrderWithCheckpoint($order, null);
    }

    public function renewInvoiceItem(Order $order, int $itemId): bool
    {
        return $this->renewOrderWithCheckpoint($order, self::META_PAID_RENEWAL_OUTCOME_PREFIX . $itemId);
    }

    public function renewFreeDomainInvoiceItem(Order $order, int $itemId, ?callable $prepare = null): bool
    {
        $checkpoint = self::META_FREE_RENEWAL_CLAIM_PREFIX . $itemId;
        if ($prepare === null) {
            return $this->renewOrderWithCheckpoint($order, $checkpoint);
        }

        return $this->withCancellationScheduleLock($order, function () use ($order, $checkpoint, $prepare): bool {
            $this->di['em']->refresh($order);
            $meta = $this->getOrderMetaRepository()->getPairsForOrder($this->orderId($order));
            if (($meta[$checkpoint] ?? null) !== self::RENEWAL_OUTCOME_COMPLETED) {
                $this->assertOrderCanRenew($order, $meta, $checkpoint);
            }
            if (!$prepare()) {
                return false;
            }

            return $this->renewOrderWithCheckpoint($order, $checkpoint, true);
        }, 0);
    }

    private function renewOrderWithCheckpoint(Order $order, ?string $checkpoint, bool $alreadyLocked = false): bool
    {
        $orderId = $this->orderId($order);
        $this->di['event_dispatcher']->dispatch(new BeforeAdminOrderRenewEvent($orderId));

        if ($checkpoint === null) {
            $this->renewFromOrder($order);
        } elseif ($alreadyLocked) {
            $this->renewFromOrderWithRowLock($order, $checkpoint);
        } else {
            $this->renewFromOrderWithCheckpoint($order, $checkpoint);
        }

        $isGroupMaster = $order->isGroupMaster();
        $orderStatus = $order->getStatus();
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

        $this->di['event_dispatcher']->dispatch(new AfterAdminOrderRenewEvent($orderId));
        $this->di['logger']->info('Renewed order #{order_id}', ['order_id' => $orderId]);

        return true;
    }

    public function renewFromOrder(Order $order): void
    {
        $this->renewFromOrderWithCheckpoint($order, null);
    }

    private function renewFromOrderWithCheckpoint(Order $order, ?string $checkpoint): void
    {
        if ($order->getId() === null) {
            $this->renewFromOrderLocked($order);

            return;
        }

        $this->withCancellationScheduleLock($order, function () use ($order, $checkpoint): void {
            $this->renewFromOrderWithRowLock($order, $checkpoint);
        }, 0);
    }

    /**
     * A retry approval belongs to its original item; all entry points share these holds.
     *
     * @param array<string, string> $meta
     */
    private function assertOrderCanRenew(Order $order, array $meta, ?string $checkpoint): void
    {
        $this->assertOrderNotDeleting($order);
        $connection = $this->di['em']->getConnection();
        foreach ($meta as $name => $outcome) {
            if (str_starts_with((string) $name, self::META_PAID_RENEWAL_OUTCOME_PREFIX)
                && ($outcome === self::RENEWAL_OUTCOME_PENDING || ($outcome === self::RENEWAL_OUTCOME_RETRY && $name !== $checkpoint))) {
                throw new InformationException('This order has an unresolved renewal outcome. Review it or execute its approved invoice-item retry before starting another renewal.');
            }
            foreach ([self::META_FREE_RENEWAL_CLAIM_PREFIX, self::META_FREE_RENEWAL_RETRY_PREFIX] as $prefix) {
                if (!str_starts_with((string) $name, $prefix) || $outcome !== '1') {
                    continue;
                }
                $itemId = substr((string) $name, strlen($prefix));
                if ($checkpoint === self::META_FREE_RENEWAL_CLAIM_PREFIX . $itemId) {
                    // Only the shared executor supplies this context after
                    // validating eligibility and consuming retry approval.
                    continue;
                }
                // Historical successful free renewals kept claim 1. An
                // executed item is evidence that its local work finished.
                $status = $connection->fetchOne('SELECT status FROM invoice_item WHERE id = ? AND rel_id = ? AND type = ? AND task = ?', [$itemId, (string) $order->getId(), 'order', 'renew']);
                if ($status !== 'executed') {
                    throw new InformationException('This order has an unresolved domain renewal. Review it or execute its approved invoice-item retry before starting another renewal.');
                }
            }
        }
        if (in_array($meta[self::META_SCHEDULED_CANCEL_CLAIM] ?? null, ['1', '2'], true)
            || ($meta[self::META_CANCEL_AT_PERIOD_END] ?? null) === '1'
            || $order->getStatus() === Order::STATUS_CANCELED) {
            throw new InformationException('This order is being canceled and cannot be renewed.');
        }
    }

    private function renewFromOrderWithRowLock(Order $order, ?string $checkpoint): void
    {
        $connection = $this->di['em']->getConnection();
        $connection->beginTransaction();
        $serviceFailed = false;

        try {
            $connection->fetchOne('SELECT id FROM client_order WHERE id = ?' . RowLock::suffix($connection), [$order->getId()]);
            $this->di['em']->refresh($order);
            $meta = $this->getOrderMetaRepository()->getPairsForOrder($this->orderId($order));
            if ($checkpoint !== null && ($meta[$checkpoint] ?? null) === self::RENEWAL_OUTCOME_COMPLETED) {
                $connection->commit();

                return;
            }
            $this->assertOrderCanRenew($order, $meta, $checkpoint);
            if ($checkpoint !== null && str_starts_with($checkpoint, self::META_PAID_RENEWAL_OUTCOME_PREFIX)) {
                // Persist the attempt before provider I/O. If the process stops
                // before the outcome commits, never repeat it without review.
                $this->updateOrderMeta($order, [$checkpoint => self::RENEWAL_OUTCOME_PENDING]);
                $connection->commit();
                $connection->beginTransaction();
                $connection->fetchOne('SELECT id FROM client_order WHERE id = ?' . RowLock::suffix($connection), [$order->getId()]);
                $this->di['em']->refresh($order);
            }
            // Both provider actions must stay under the cancellation lock and
            // follow the refreshed hold check, including for suspended orders.
            if ($order->getStatus() === Order::STATUS_SUSPENDED) {
                $this->unsuspendFromOrderLocked($order);
            }
            $this->renewFromOrderLocked($order, $serviceFailed);
            if ($checkpoint !== null) {
                // The local term and completed outcome commit together before
                // lifecycle listeners run or the invoice item is acknowledged.
                $this->updateOrderMeta($order, [$checkpoint => self::RENEWAL_OUTCOME_COMPLETED]);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            if ($serviceFailed && $this->di['em']->isOpen()) {
                $this->di['em']->refresh($order);
                $order->setStatus(Order::STATUS_FAILED_RENEW);
                $this->persistOrder($order);
                $this->saveStatusChange($order, $e->getMessage());
            }

            throw $e;
        }
    }

    private function renewFromOrderLocked(Order $order, ?bool &$serviceFailed = null): void
    {
        $this->assertOrderNotDeleting($order);

        try {
            $this->_callOnService($order, Order::ACTION_RENEW);
        } catch (\Exception $e) {
            $serviceFailed = true;
            $order->setStatus(Order::STATUS_FAILED_RENEW);
            $this->persistOrder($order);

            $this->saveStatusChange($order, $e->getMessage());

            throw $e;
        }

        $period = $order->getPeriod();
        $expiresAt = $order->getExpiresAt();
        if (!empty($period)) {
            $from_time = $expiresAt?->getTimestamp() ?? time();

            $config = $this->di['mod_config']('order');
            $logic = $config['order_renewal_logic'] ?? '';

            if ($logic == 'from_today') {
                $from_time = time();
            } elseif ($logic == 'from_greater') {
                $expiresTimestamp = $expiresAt?->getTimestamp() ?? time();
                if ($expiresTimestamp > time()) {
                    $from_time = $expiresTimestamp;
                } else {
                    $from_time = time();
                }
            }
            $periodObj = $this->di['period']($period);
            $newExpires = date('Y-m-d H:i:s', $periodObj->getExpirationTime($from_time));
            $order->setExpiresAt(new \DateTimeImmutable($newExpires));
        }

        $order->setStatus(Order::STATUS_ACTIVE);
        $order->setSuspendedAt(null);
        $order->setUnsuspendedAt(null);
        $order->setCanceledAt(null);
        $order->setUpdatedAt(new \DateTimeImmutable());
        $this->persistOrder($order);

        $this->saveStatusChange($order, 'Order renewed');
    }

    public function suspendFromOrder(Order $order, $reason = null, $skipEvent = false): bool
    {
        $orderId = $this->orderId($order);
        $orderStatus = $order->getStatus();

        if (!$skipEvent) {
            $this->di['event_dispatcher']->dispatch(new BeforeAdminOrderSuspendEvent($orderId));
        }

        if ($orderStatus != Order::STATUS_ACTIVE) {
            throw new InformationException('Only active orders can be suspended');
        }

        $arguments = $order->getServiceType() === \Box\Mod\Product\Service::HOSTING ? [$reason] : [];
        $this->_callOnService($order, Order::ACTION_SUSPEND, ...$arguments);

        $order->setStatus(Order::STATUS_SUSPENDED);
        $order->setReason($reason);
        $order->setSuspendedAt(new \DateTimeImmutable());
        $order->setUpdatedAt(new \DateTimeImmutable());
        $this->persistOrder($order);

        $note = ($reason === null) ? 'Order suspended' : 'Order suspended for ' . $reason;
        $this->saveStatusChange($order, $note);

        if (!$skipEvent) {
            $this->di['event_dispatcher']->dispatch(new AfterAdminOrderSuspendEvent($orderId));
        }

        $this->di['logger']->info('Suspended order #{order_id}', ['order_id' => $orderId]);

        return true;
    }

    public function unsuspendFromOrder(Order $order): bool
    {
        if ($order->getId() === null) {
            return $this->unsuspendFromOrderLocked($order);
        }

        return $this->withCancellationScheduleLock($order, function () use ($order): bool {
            $this->di['em']->refresh($order);

            return $this->unsuspendFromOrderLocked($order);
        });
    }

    private function unsuspendFromOrderLocked(Order $order): bool
    {
        $this->assertOrderNotDeleting($order);
        $meta = $this->getOrderMetaRepository()->getPairsForOrder($this->orderId($order));
        if (in_array($meta[self::META_SCHEDULED_CANCEL_CLAIM] ?? null, ['1', '2'], true)) {
            throw new InformationException('Scheduled cancellation needs staff review before this order can be unsuspended.');
        }
        $orderId = $this->orderId($order);
        $this->di['event_dispatcher']->dispatch(new BeforeAdminOrderUnsuspendEvent($orderId));

        $this->_callOnService($order, Order::ACTION_UNSUSPEND);

        $order->setStatus(Order::STATUS_ACTIVE);
        $order->setReason(null);
        $order->setSuspendedAt(null);
        $order->setUnsuspendedAt(new \DateTimeImmutable());
        $order->setUpdatedAt(new \DateTimeImmutable());
        $this->persistOrder($order);

        $this->saveStatusChange($order, 'Order unsuspended');

        $this->di['event_dispatcher']->dispatch(new AfterAdminOrderUnsuspendEvent($orderId));

        $this->di['logger']->info('Unsuspended order #{order_id}', ['order_id' => $orderId]);

        return true;
    }

    public function cancelFromOrder(Order $order, $reason = null, $skipEvent = false): bool
    {
        $this->assertOrderCanBeCanceled($order);
        if ($order->getId() === null) {
            return $this->cancelFromOrderLocked($order, $reason, $skipEvent);
        }
        $connection = $this->di['em']->getConnection();
        $name = 'fb:scheduled-cancel:' . $this->orderId($order);
        if (!NamedLock::acquire($connection, $name, 30)) {
            throw new InformationException('Scheduled cancellation is already being processed.');
        }

        try {
            $this->di['em']->refresh($order);
            $meta = $this->getOrderMetaRepository()->getPairsForOrder($this->orderId($order));
            if (in_array($meta[self::META_SCHEDULED_CANCEL_CLAIM] ?? null, ['1', '2'], true)) {
                throw new InformationException('The service cancellation requires staff review.');
            }

            if (($meta[self::META_CANCEL_AT_PERIOD_END] ?? null) === '1') {
                if ($connection->isTransactionActive()) {
                    throw new InformationException('Scheduled cancellation requires a committed claim before external actions.');
                }

                return $this->cancelWithRecovery($order, $reason, (bool) $skipEvent);
            }

            return $this->cancelFromOrderLocked($order, $reason, $skipEvent);
        } finally {
            NamedLock::release($connection, $name);
        }
    }

    /** Request retries must never repeat an uncertain external cancellation. */
    public function cancelFromRequest(Order $order, string $reason): bool
    {
        return $this->withCancellationScheduleLock($order, function () use ($order, $reason): bool {
            $this->di['em']->refresh($order);
            $this->assertOrderNotDeleting($order);
            $meta = $this->getOrderMetaRepository()->getPairsForOrder($this->orderId($order));
            if (in_array($meta[self::META_SCHEDULED_CANCEL_CLAIM] ?? null, ['1', '2'], true)) {
                throw new InformationException('The service cancellation requires staff review.');
            }

            return $this->cancelWithRecovery($order, $reason, false);
        }, 0);
    }

    private function cancelWithRecovery(Order $order, $reason, bool $skipEvent): bool
    {
        $this->assertOrderCanBeCanceled($order);
        // A shared durable claim also supports staff-reviewed retries and local
        // cleanup recovery through the existing scheduled cancellation protocol.
        $this->updateOrderMeta($order, [
            self::META_CANCEL_AT_PERIOD_END => '1',
            self::META_SCHEDULED_CANCEL_CLAIM => '1',
            self::META_SCHEDULED_CANCEL_PHASE => 'scheduled',
            self::META_SCHEDULED_CANCEL_OPTIONS => json_encode(['mode' => 'immediate', 'reason' => $reason, 'skip_event' => $skipEvent], JSON_THROW_ON_ERROR),
        ]);
        $this->di['mod_service']('Invoice', 'Subscription')->cancelForOrder($order);

        return $this->finalizeCancellationAfterClaim($order, $reason, $skipEvent);
    }

    private function cancelFromOrderLocked(Order $order, $reason = null, $skipEvent = false): bool
    {
        $orderId = $this->orderId($order);
        $this->assertOrderCanBeCanceled($order);
        $this->beginCancellation($order, $skipEvent);

        $subscriptionService = $this->di['mod_service']('Invoice', 'Subscription');
        $subscriptionService->cancelForOrder($order);

        $this->completeCancellation($order, $reason, $skipEvent);

        $productService = $this->di['mod_service']('Product');
        $productService->releaseReservedPromoRedemptionsForOrder($order, 'order_canceled');
        $productService->releaseReservedStockForOrder($order, 'order_canceled');

        $note = ($reason === null) ? 'Order canceled' : 'Canceled order for ' . $reason;
        $this->saveStatusChange($order, $note);

        if (!$skipEvent) {
            $this->di['event_dispatcher']->dispatch(new AfterAdminOrderCancelEvent($orderId));
        }

        $this->di['logger']->info('Canceled order #{order_id}', ['order_id' => $orderId]);

        return true;
    }

    public function scheduleCancellationFromOrder(Order $order, $reason = null, bool $allowWithoutSubscription = false): bool
    {
        return $this->withCancellationScheduleLock($order, function () use ($order, $reason, $allowWithoutSubscription): bool {
            $this->di['em']->refresh($order);

            return $this->scheduleCancellationUnderLock($order, $reason, $allowWithoutSubscription);
        }, $allowWithoutSubscription ? 0 : 30);
    }

    private function scheduleCancellationUnderLock(Order $order, $reason, bool $allowWithoutSubscription): bool
    {
        $orderId = $this->orderId($order);
        $this->assertOrderCanBeCanceled($order);
        $meta = $this->getOrderMetaRepository()->getPairsForOrder($orderId);
        if (in_array($meta[self::META_SCHEDULED_CANCEL_CLAIM] ?? null, ['1', '2'], true)) {
            throw new InformationException('The scheduled cancellation requires staff review before another provider action.');
        }
        if (($meta[self::META_CANCEL_AT_PERIOD_END] ?? null) === '1') {
            return true;
        }

        $subscriptionService = $this->di['mod_service']('Invoice', 'Subscription');
        if ($allowWithoutSubscription && !$subscriptionService->hasActiveSubscriptionForOrder($order)) {
            if ($order->getExpiresAt() === null) {
                throw new InformationException('This service cannot be cancelled at the end of its period.');
            }
            $this->updateOrderMeta($order, [self::META_CANCEL_AT_PERIOD_END => '1']);
        } else {
            if (!$subscriptionService->canCancelAtPeriodEndForOrder($order)) {
                if ($allowWithoutSubscription) {
                    throw new InformationException('Cancel the subscription at the payment provider and mark it canceled here before accepting this request.');
                }

                throw new InformationException('No active gateway subscription that supports cancellation at period end is linked to this order.');
            }

            // Commit a review hold before provider I/O. A crash or ambiguous
            // response must not leave a provider cancellation with no local marker.
            $this->updateOrderMeta($order, [self::META_CANCEL_AT_PERIOD_END => '1', self::META_SCHEDULED_CANCEL_CLAIM => '1', self::META_SCHEDULED_CANCEL_PHASE => 'scheduling']);
            if ($subscriptionService->scheduleCancellationForOrder($order) === 0) {
                throw new InformationException('No active gateway subscription is linked to this order.');
            }
            $this->updateOrderMeta($order, [self::META_SCHEDULED_CANCEL_CLAIM => '0', self::META_SCHEDULED_CANCEL_PHASE => 'scheduled']);
        }

        $order->setReason($reason);
        $order->setUpdatedAt(new \DateTimeImmutable());
        $this->persistOrder($order);
        $this->saveStatusChange($order, 'Cancellation scheduled at the end of the current billing period');
        $this->di['logger']->info('Scheduled cancellation for order #{order_id} at the end of the current billing period', ['order_id' => $orderId]);

        return true;
    }

    /** Serialize provider scheduling with local renewal and expiry edits. */
    private function withCancellationScheduleLock(Order $order, callable $operation, int $timeoutSeconds = 30): mixed
    {
        $connection = $this->di['em']->getConnection();
        if ($connection->isTransactionActive()) {
            throw new InformationException('Order scheduling and renewal require a committed transaction before external actions.');
        }
        $name = 'fb:scheduled-cancel:' . $this->orderId($order);
        if (!NamedLock::acquire($connection, $name, $timeoutSeconds)) {
            throw new InformationException('Scheduled cancellation is already being processed.');
        }

        try {
            return $this->withLifecycleLock($order, fn (): mixed => $operation());
        } finally {
            NamedLock::release($connection, $name);
        }
    }

    public function finalizeCancellationFromGateway(Order $order, $reason = null): bool
    {
        $connection = $this->di['em']->getConnection();
        if ($connection->isTransactionActive()) {
            throw new InformationException('Scheduled cancellation requires a committed claim before external actions.');
        }
        $name = 'fb:scheduled-cancel:' . $this->orderId($order);
        if (!NamedLock::acquire($connection, $name, 30)) {
            throw new InformationException('Scheduled cancellation is already being processed.');
        }

        try {
            $this->di['em']->refresh($order);
            $meta = $this->getOrderMetaRepository()->getPairsForOrder($this->orderId($order));
            if (($meta[self::META_SCHEDULED_CANCEL_CLAIM] ?? null) === '2') {
                if (($meta[self::META_CANCEL_AT_PERIOD_END] ?? null) !== '1') {
                    return $this->clearOrphanedCancellationClaim($order);
                }

                return $this->finishScheduledCancellation($order, $reason);
            }
            if (($meta[self::META_SCHEDULED_CANCEL_CLAIM] ?? null) === '1') {
                throw new InformationException('The service cancellation requires staff review.');
            }
            if (($meta[self::META_CANCEL_AT_PERIOD_END] ?? null) === '1') {
                $connection->beginTransaction();

                try {
                    $connection->fetchOne('SELECT id FROM client_order WHERE id = ?' . RowLock::suffix($connection), [$order->getId()]);
                    $this->di['em']->refresh($order);
                    $meta = $this->getOrderMetaRepository()->getPairsForOrder($this->orderId($order));
                    if (($meta[self::META_CANCEL_AT_PERIOD_END] ?? null) !== '1'
                        || !in_array($order->getStatus(), [Order::STATUS_ACTIVE, Order::STATUS_SUSPENDED, Order::STATUS_FAILED_RENEW], true)
                        || $order->getExpiresAt() === null || $order->getExpiresAt()->getTimestamp() > time()) {
                        $connection->commit();

                        return false;
                    }
                    $this->updateOrderMeta($order, [self::META_SCHEDULED_CANCEL_CLAIM => '1']);
                    $connection->commit();
                } catch (\Throwable $e) {
                    $connection->rollBack();

                    throw $e;
                }
            }

            return $this->finalizeCancellationAfterClaim($order, $reason);
        } finally {
            NamedLock::release($connection, $name);
        }
    }

    protected function finalizeCancellationAfterClaim(Order $order, $reason = null, bool $skipEvent = false): bool
    {
        $orderId = $this->orderId($order);
        $this->assertOrderCanBeCanceled($order);
        $this->beginCancellation($order, $skipEvent);

        $meta = $this->getOrderMetaRepository()->getPairsForOrder($orderId);
        if (($meta[self::META_CANCEL_AT_PERIOD_END] ?? null) === '1') {
            $this->updateOrderMeta($order, [self::META_SCHEDULED_CANCEL_CLAIM => '2']);

            return $this->finishScheduledCancellation($order, $reason, $skipEvent);
        }

        $this->completeCancellation($order, $reason, false);

        $productService = $this->di['mod_service']('Product');
        $productService->releaseReservedPromoRedemptionsForOrder($order, 'order_canceled');
        $productService->releaseReservedStockForOrder($order, 'order_canceled');

        $note = ($reason === null) ? 'Order canceled' : 'Canceled order for ' . $reason;
        $this->saveStatusChange($order, $note);

        $this->di['event_dispatcher']->dispatch(new AfterAdminOrderCancelEvent($orderId));

        return true;
    }

    /** Finish local cancellation and its queued lifecycle notification atomically. */
    protected function finishScheduledCancellation(Order $order, ?string $reason, bool $skipEvent = false): bool
    {
        $meta = $this->getOrderMetaRepository()->getPairsForOrder($this->orderId($order));
        if (isset($meta[self::META_SCHEDULED_CANCEL_OPTIONS])) {
            $options = json_decode($meta[self::META_SCHEDULED_CANCEL_OPTIONS], true, 512, JSON_THROW_ON_ERROR);
            $reason = $options['reason'];
            $skipEvent = $options['skip_event'];
        }
        $connection = $this->di['em']->getConnection();
        $connection->beginTransaction();

        try {
            $this->completeCancellation($order, $reason, false);
            $productService = $this->di['mod_service']('Product');
            $productService->releaseReservedPromoRedemptionsForOrder($order, 'order_canceled');
            $productService->releaseReservedStockForOrder($order, 'order_canceled');
            $this->saveStatusChange($order, $reason === null ? 'Order canceled' : 'Canceled order for ' . $reason);
            if (!$skipEvent) {
                $this->di['event_dispatcher']->dispatch(new AfterAdminOrderCancelEvent($this->orderId($order), true));
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            if ($this->di['em']->isOpen()) {
                $this->di['em']->refresh($order);
                foreach ($this->di['em']->getUnitOfWork()->getIdentityMap()[\Box\Mod\Product\Entity\PromoRedemption::class] ?? [] as $redemption) {
                    if ($redemption->getClientOrderId() === $order->getId()) {
                        $this->di['em']->refresh($redemption);
                    }
                }
            }

            throw $e;
        }

        return true;
    }

    private function assertOrderCanBeCanceled(Order $order): void
    {
        $status = $order->getStatus();
        if (!in_array($status, [Order::STATUS_CANCELED, Order::STATUS_PENDING_SETUP, Order::STATUS_FAILED_SETUP], true)) {
            return;
        }

        throw new \FOSSBilling\Core\Exception\BaseException('Cannot cancel ' . $status . ' order');
    }

    private function beginCancellation(Order $order, bool $skipEvent): void
    {
        $orderId = $this->orderId($order);
        if (!$skipEvent) {
            $this->di['event_dispatcher']->dispatch(new BeforeAdminOrderCancelEvent($orderId));
        }

        $this->_callOnService($order, Order::ACTION_CANCEL);
    }

    private function completeCancellation(Order $order, $reason, bool $skipEvent): void
    {
        $orderId = $this->orderId($order);

        $order->setStatus(Order::STATUS_CANCELED);
        $order->setReason($reason);
        $order->setCanceledAt(new \DateTimeImmutable());
        $order->setExpiresAt(null);
        $order->setSuspendedAt(null);
        $order->setUpdatedAt(new \DateTimeImmutable());
        $this->persistOrder($order);
        // Remove the schedule checkpoints in one statement. If this process stops
        // before it, claim 2 remains discoverable for cleanup recovery.
        $this->di['em']->getConnection()->executeStatement(
            'DELETE FROM client_order_meta WHERE client_order_id = :order_id AND name IN (:schedule_name, :claim_name, :phase_name, :options_name)',
            ['order_id' => $orderId, 'schedule_name' => self::META_CANCEL_AT_PERIOD_END, 'claim_name' => self::META_SCHEDULED_CANCEL_CLAIM, 'phase_name' => self::META_SCHEDULED_CANCEL_PHASE, 'options_name' => self::META_SCHEDULED_CANCEL_OPTIONS],
        );
    }

    public function uncancelFromOrder(Order $order): bool
    {
        if ($order->getId() === null) {
            return $this->uncancelFromOrderLocked($order);
        }
        $connection = $this->di['em']->getConnection();
        $name = 'fb:scheduled-cancel:' . $this->orderId($order);
        if (!NamedLock::acquire($connection, $name, 30)) {
            throw new InformationException('Scheduled cancellation is already being processed.');
        }

        try {
            $this->di['em']->refresh($order);
            $meta = $this->getOrderMetaRepository()->getPairsForOrder($this->orderId($order));
            if (in_array($meta[self::META_SCHEDULED_CANCEL_CLAIM] ?? null, ['1', '2'], true)
                || ($meta[self::META_CANCEL_AT_PERIOD_END] ?? null) === '1') {
                throw new InformationException('Finish or review the scheduled cancellation before reactivating this order.');
            }

            return $this->uncancelFromOrderLocked($order);
        } finally {
            NamedLock::release($connection, $name);
        }
    }

    private function uncancelFromOrderLocked(Order $order): bool
    {
        $this->assertOrderNotDeleting($order);
        $orderId = $this->orderId($order);
        $this->di['event_dispatcher']->dispatch(new BeforeAdminOrderUncancelEvent($orderId));

        $this->_callOnService($order, Order::ACTION_UNCANCEL);

        $expiresAt = null;
        $period = $order->getPeriod();
        if ($period) {
            $periodObj = $this->di['period']($period);
            $expiresAt = $periodObj->getExpirationTime(time());
            $expiresAt = date('Y-m-d H:i:s', $expiresAt);
        }

        $order->setStatus(Order::STATUS_ACTIVE);
        $order->setReason(null);
        $order->setActivatedAt(new \DateTimeImmutable());
        $order->setExpiresAt($expiresAt ? new \DateTimeImmutable($expiresAt) : null);
        $order->setSuspendedAt(null);
        $order->setCanceledAt(null);
        $order->setUpdatedAt(new \DateTimeImmutable());
        $this->persistOrder($order);

        $this->saveStatusChange($order, 'Activated canceled order');

        $this->di['event_dispatcher']->dispatch(new AfterAdminOrderUncancelEvent($orderId));

        $this->di['logger']->info('Uncanceled order #{order_id}', ['order_id' => $orderId]);

        return true;
    }

    public function rmInvoiceItemByOrder(Order $order): void
    {
        $this->di['em']->getConnection()->executeStatement(
            'DELETE FROM invoice_item WHERE rel_id = :rel_id AND status = :status',
            [
                'rel_id' => (string) $this->orderId($order),
                'status' => \Box\Mod\Invoice\Entity\InvoiceItem::STATUS_PENDING_PAYMENT,
            ],
        );
    }

    public function rmClientOrderStatusByOrder(Order $order): void
    {
        $orderId = $this->orderId($order);
        $this->getOrderStatusRepository()->rmByOrderId($orderId);
    }

    public function rmOrder(Order $model): void
    {
        $isGroupMaster = $model->isGroupMaster();

        if ($isGroupMaster) {
            $list = $this->getOrderAddonsList($model);
            foreach ($list as $addon) {
                $addon->setGroupMaster(true);
                $addon->setGroupId('0');
                $this->di['em']->persist($addon);
            }
            if ($list !== []) {
                $this->di['em']->flush();
            }
        }
        $this->di['em']->remove($model);
        $this->di['em']->flush();
    }

    public function deleteFromOrder(Order $order, bool $forceDelete = false): bool
    {
        if ($this->di['em']->getConnection()->isTransactionActive()) {
            throw new InformationException('Order deletion cannot run inside a database transaction.');
        }

        return $this->withLifecycleLock($order, fn (): bool => $this->deleteHeldOrder($order, $forceDelete));
    }

    private function deleteHeldOrder(Order $order, bool $forceDelete): bool
    {
        $this->di['em']->refresh($order);
        $orderId = $this->orderId($order);
        $this->di['event_dispatcher']->dispatch(new BeforeAdminOrderDeleteEvent($orderId));

        $state = DeletionRecovery::getState($this->di['em']->getConnection(), $orderId);
        if ($state !== null && $state !== DeletionRecovery::SERVICE_DELETED && !$forceDelete) {
            throw new InformationException('A previous deletion was interrupted. Staff must review the service before retrying with force_delete.');
        }
        if ($state !== DeletionRecovery::SERVICE_DELETED) {
            DeletionRecovery::setState($this->di['em']->getConnection(), $orderId, DeletionRecovery::STARTED);
            $this->di['em']->refresh($order);

            try {
                $this->_callOnService($order, Order::ACTION_DELETE, $forceDelete);
            } catch (\Exception $e) {
                if (!$forceDelete) {
                    // A timeout can mean the remote deletion succeeded. Keep the
                    // hold until staff review; do not cancel billing on refusal.
                    throw $e;
                }
                $this->di['logger']->info('Backend deletion failed during forced removal: {message}', ['message' => $e->getMessage()]);
            }
            DeletionRecovery::setState($this->di['em']->getConnection(), $orderId, DeletionRecovery::SERVICE_DELETED);
        }

        // Keep subscription links until cancellation succeeds. A failure must
        // leave both the hold and the confirmed backend outcome available.
        $this->di['mod_service']('Invoice', 'Subscription')->cancelForOrder($order, $forceDelete);
        if ($order->getStatus() === Order::STATUS_PENDING_SETUP) {
            $this->rmInvoiceItemByOrder($order);
        }

        // Keep the recovery hold until the order is removed in the same commit.
        $this->di['em']->wrapInTransaction(function () use ($order, $orderId): void {
            $productService = $this->di['mod_service']('Product');
            $productService->releaseReservedPromoRedemptionsForOrder($order, 'order_deleted');
            $productService->releaseReservedStockForOrder($order, 'order_deleted');
            $this->rmClientOrderStatusByOrder($order);
            $this->di['em']->getConnection()->delete('client_order_cancel_request', ['client_order_id' => $orderId]);
            $this->getOrderMetaRepository()->deleteByOrderId($orderId);
            $this->rmOrder($order);
        });

        $this->di['event_dispatcher']->dispatch(new AfterAdminOrderDeleteEvent($orderId));
        $this->di['logger']->info('Deleted order #{order_id}', ['order_id' => $orderId]);

        return true;
    }

    public function assertOrderNotDeleting(Order|int $order, bool $lockRow = false): void
    {
        $orderId = $order instanceof Order ? $this->orderId($order) : $order;
        DeletionRecovery::assertNotHeld($this->di['em']->getConnection(), $orderId, $lockRow);
    }

    public function withLifecycleLock(Order $order, \Closure $operation): mixed
    {
        $id = $this->orderId($order);
        $connection = $this->di['em']->getConnection();
        $key = spl_object_id($connection) . ':' . $id;
        if (isset(self::$lifecycleLocks[$key])) {
            return $operation();
        }
        $name = 'order_lifecycle_' . $id;
        if (!NamedLock::acquire($connection, $name, 10)) {
            throw new InformationException('Another worker is changing this order. Please retry.');
        }
        self::$lifecycleLocks[$key] = true;

        try {
            return $operation();
        } finally {
            unset(self::$lifecycleLocks[$key]);
            NamedLock::release($connection, $name);
        }
    }

    public function getExpiredOrders()
    {
        return $this->getOrderRepository()->getExpired();
    }

    public function batchSendSuspensionWarnings(): bool
    {
        $this->di['event_dispatcher']->dispatch(new BeforeAdminBatchSendSuspensionWarningsEvent());

        $emailService = $this->di['mod_service']('email');
        foreach ($this->getOrderRepository()->getDueSuspensionWarnings() as $candidate) {
            $order = $this->getOrderRepository()->find($candidate['id']);
            if (!$order instanceof Order || !$this->claimSuspensionWarning($order, $candidate['suspension_at'])) {
                continue;
            }

            try {
                $orderData = $this->toApiArray($order, false);
                $orderData['suspension_at'] = $candidate['suspension_at'];
                $emailService->sendTemplate([
                    'to_client' => $order->getClientId(),
                    'code' => 'mod_order_suspension_warning',
                    'order' => $orderData,
                ]);
            } catch (\Throwable $exception) {
                $this->releaseSuspensionWarningClaim($order, $candidate['suspension_at']);
                $this->di['logger']->withChannel('email')->error('Failed to send order suspension warning email', [
                    'order_id' => $order->getId(),
                    'exception' => $exception,
                ]);
            }
        }

        $this->di['event_dispatcher']->dispatch(new AfterAdminBatchSendSuspensionWarningsEvent());
        $this->di['logger']->info('Executed action to send order suspension warnings');

        return true;
    }

    private function claimSuspensionWarning(Order $order, string $suspensionAt): bool
    {
        $connection = $this->di['em']->getConnection();

        return $connection->transactional(function () use ($connection, $order, $suspensionAt): bool {
            $connection->fetchOne('SELECT id FROM client_order WHERE id = :id' . RowLock::suffix($connection), ['id' => $order->getId()]);
            $existing = $connection->fetchAssociative(
                'SELECT id, value FROM client_order_meta WHERE client_order_id = :order_id AND name = :name ORDER BY id LIMIT 1',
                ['order_id' => $order->getId(), 'name' => self::META_SUSPENSION_WARNING_FOR]
            );

            if (is_array($existing) && $existing['value'] === $suspensionAt) {
                return false;
            }

            if (is_array($existing)) {
                $connection->update('client_order_meta', [
                    'value' => $suspensionAt,
                    'updated_at' => date('Y-m-d H:i:s'),
                ], ['id' => $existing['id']]);
            } else {
                $connection->insert('client_order_meta', [
                    'client_order_id' => $order->getId(),
                    'name' => self::META_SUSPENSION_WARNING_FOR,
                    'value' => $suspensionAt,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            return true;
        });
    }

    private function releaseSuspensionWarningClaim(Order $order, string $suspensionAt): void
    {
        $this->di['em']->getConnection()->delete('client_order_meta', [
            'client_order_id' => $order->getId(),
            'name' => self::META_SUSPENSION_WARNING_FOR,
            'value' => $suspensionAt,
        ]);
    }

    /** Process a scheduled cancellation once; an interrupted external action requires staff review. */
    public function processScheduledCancellation(Order $order): bool
    {
        $connection = $this->di['em']->getConnection();
        if ($connection->isTransactionActive()) {
            throw new InformationException('Scheduled cancellation requires a committed claim before external actions.');
        }
        $name = 'fb:scheduled-cancel:' . $this->orderId($order);
        if (!NamedLock::acquire($connection, $name, 30)) {
            throw new InformationException('Scheduled cancellation is already being processed.');
        }

        try {
            $this->di['em']->refresh($order);
            $meta = $this->getOrderMetaRepository()->getPairsForOrder($this->orderId($order));
            if (($meta[self::META_CANCEL_AT_PERIOD_END] ?? null) !== '1') {
                // Older interrupted acknowledgements could remove the schedule
                // marker before the claim. Cleanup had already completed by
                // then, so a canceled order only needs the orphan removed.
                if (($meta[self::META_SCHEDULED_CANCEL_CLAIM] ?? null) === '2') {
                    return $this->clearOrphanedCancellationClaim($order);
                }

                return false;
            }
            if (($meta[self::META_SCHEDULED_CANCEL_CLAIM] ?? null) === '2'
                && in_array($order->getStatus(), [Order::STATUS_ACTIVE, Order::STATUS_SUSPENDED, Order::STATUS_FAILED_RENEW, Order::STATUS_CANCELED], true)) {
                return $this->finishScheduledCancellation($order, 'Cancellation requested at end of period');
            }
            if (!in_array($order->getStatus(), [Order::STATUS_ACTIVE, Order::STATUS_SUSPENDED, Order::STATUS_FAILED_RENEW], true)) {
                return false;
            }
            $expiresAt = $order->getExpiresAt();
            if ($expiresAt === null || $expiresAt->getTimestamp() > time()) {
                return false;
            }
            if (($meta[self::META_SCHEDULED_CANCEL_CLAIM] ?? null) === '1') {
                $this->di['logger']->error('Scheduled cancellation for order #{id} requires staff review before retrying the service action.', ['id' => $order->getId()]);

                return false;
            }
            // Commit the uncertain-action claim before any gateway I/O. A
            // renewal or staff expiry change then sees the claim and cannot
            // invalidate the cancellation while the gateway is responding.
            $connection->beginTransaction();

            try {
                $connection->fetchOne('SELECT id FROM client_order WHERE id = ?' . RowLock::suffix($connection), [$order->getId()]);
                $this->di['em']->refresh($order);
                $meta = $this->getOrderMetaRepository()->getPairsForOrder($this->orderId($order));
                if (($meta[self::META_CANCEL_AT_PERIOD_END] ?? null) !== '1'
                    || !in_array($order->getStatus(), [Order::STATUS_ACTIVE, Order::STATUS_SUSPENDED, Order::STATUS_FAILED_RENEW], true)
                    || $order->getExpiresAt() === null || $order->getExpiresAt()->getTimestamp() > time()) {
                    $connection->commit();

                    return false;
                }
                $this->updateOrderMeta($order, [self::META_SCHEDULED_CANCEL_CLAIM => '1']);
                $connection->commit();
            } catch (\Throwable $e) {
                $connection->rollBack();

                throw $e;
            }
            // If a gateway call fails or the process stops here, staff must
            // verify its outcome before another external attempt.
            $this->di['mod_service']('Invoice', 'Subscription')->cancelForOrder($order);

            return $this->finalizeCancellationAfterClaim($order, 'Cancellation requested at end of period');
        } finally {
            NamedLock::release($connection, $name);
        }
    }

    /** The old split acknowledgement could leave claim 2 after its schedule
     * marker was removed. That removal only happened after local cleanup. */
    private function clearOrphanedCancellationClaim(Order $order): bool
    {
        if ($order->getStatus() !== Order::STATUS_CANCELED) {
            throw new InformationException('The cancellation claim needs staff review before this order can be changed.');
        }
        $this->di['em']->getConnection()->executeStatement(
            'DELETE FROM client_order_meta WHERE client_order_id = :order_id AND ((name = :claim_name AND value = :claim_value) OR name = :options_name)',
            ['order_id' => $order->getId(), 'claim_name' => self::META_SCHEDULED_CANCEL_CLAIM, 'claim_value' => '2', 'options_name' => self::META_SCHEDULED_CANCEL_OPTIONS],
        );

        return true;
    }

    /** Staff must check the gateway and service provider before resolving an interrupted claim. */
    public function reviewScheduledCancellation(Order $order, string $decision): bool
    {
        if (!in_array($decision, ['retry', 'completed'], true)) {
            throw new InformationException('Choose retry or completed after checking the gateway and service provider.');
        }

        return $this->withCancellationScheduleLock($order, function () use ($order, $decision): bool {
            $this->di['em']->refresh($order);
            $meta = $this->getOrderMetaRepository()->getPairsForOrder($this->orderId($order));
            if (($meta[self::META_CANCEL_AT_PERIOD_END] ?? null) !== '1'
                || ($meta[self::META_SCHEDULED_CANCEL_CLAIM] ?? null) !== '1'
                || !in_array($order->getStatus(), [Order::STATUS_ACTIVE, Order::STATUS_SUSPENDED, Order::STATUS_FAILED_RENEW], true)) {
                throw new InformationException('This order has no scheduled cancellation awaiting review.');
            }
            if ($decision === 'retry') {
                if (isset($meta[self::META_SCHEDULED_CANCEL_OPTIONS])) {
                    $options = json_decode($meta[self::META_SCHEDULED_CANCEL_OPTIONS], true, 512, JSON_THROW_ON_ERROR);
                    // Before execution mode was explicit, only immediate
                    // cancellation wrote options. Preserve those held requests.
                    if (($options['mode'] ?? 'immediate') === 'immediate') {
                        // Claim 1 remains committed throughout this explicitly
                        // approved attempt; another failure requires review again.
                        $this->di['mod_service']('Invoice', 'Subscription')->cancelForOrder($order);

                        return $this->finalizeCancellationAfterClaim($order, $options['reason'], $options['skip_event']);
                    }
                }
                $phase = $meta[self::META_SCHEDULED_CANCEL_PHASE] ?? null;
                if ($phase === 'scheduling' || $phase === null) {
                    $subscriptions = $this->di['mod_service']('Invoice', 'Subscription');
                    // Older claims have no phase. An active subscription must
                    // be scheduled again after staff approves the retry.
                    if ($phase === 'scheduling' || $subscriptions->hasActiveSubscriptionForOrder($order)) {
                        if ($subscriptions->scheduleCancellationForOrder($order) === 0 && $subscriptions->hasActiveSubscriptionForOrder($order)) {
                            throw new InformationException('The gateway cancellation has not been scheduled.');
                        }
                    }
                    $this->updateOrderMeta($order, [self::META_SCHEDULED_CANCEL_PHASE => 'scheduled']);
                }
                $this->updateOrderMeta($order, [self::META_SCHEDULED_CANCEL_CLAIM => '0']);

                return true;
            }
            if ($this->di['mod_service']('Invoice', 'Subscription')->hasUncanceledSubscriptionForOrder($order)) {
                throw new InformationException('Cancel or reconcile the gateway subscription before confirming the service cancellation.');
            }
            $this->updateOrderMeta($order, [self::META_SCHEDULED_CANCEL_CLAIM => '2']);

            return $this->finishScheduledCancellation($order, 'Scheduled cancellation confirmed by staff');
        });
    }

    public function batchSuspendExpired(): bool
    {
        $this->di['event_dispatcher']->dispatch(new BeforeAdminBatchSuspendOrdersEvent());

        $mod = $this->di['mod']('order');
        $c = $mod->getConfig();

        $configuredReason = trim((string) ($c['batch_suspend_reason'] ?? ''));
        $reason = $configuredReason !== '' ? $configuredReason : 'Non-payment';

        // Scheduled cancellations take effect at expiry, independently of
        // suspension grace periods and gateway webhook delivery.
        $ids = $this->di['em']->getConnection()->fetchFirstColumn(
            "SELECT DISTINCT o.id FROM client_order o LEFT JOIN client_order_meta m ON m.client_order_id = o.id AND m.name = :name AND m.value = :value WHERE (m.id IS NOT NULL AND ((o.status IN (:active, :suspended, :failed_renew) AND o.expires_at <= :now) OR (o.status IN (:active2, :suspended2, :failed_renew2, :canceled) AND EXISTS (SELECT 1 FROM client_order_meta c WHERE c.client_order_id = o.id AND c.name = :claim_name AND c.value = '2')))) OR (o.status = :orphan_canceled AND EXISTS (SELECT 1 FROM client_order_meta c WHERE c.client_order_id = o.id AND c.name = :claim_name AND c.value = '2'))",
            ['name' => self::META_CANCEL_AT_PERIOD_END, 'value' => '1', 'active' => Order::STATUS_ACTIVE, 'suspended' => Order::STATUS_SUSPENDED, 'failed_renew' => Order::STATUS_FAILED_RENEW, 'failed_renew2' => Order::STATUS_FAILED_RENEW, 'active2' => Order::STATUS_ACTIVE, 'suspended2' => Order::STATUS_SUSPENDED, 'canceled' => Order::STATUS_CANCELED, 'orphan_canceled' => Order::STATUS_CANCELED, 'now' => date('Y-m-d H:i:s'), 'claim_name' => self::META_SCHEDULED_CANCEL_CLAIM]
        );
        foreach ($ids as $id) {
            try {
                $order = $this->getOrderRepository()->find((int) $id);
                if (!$order instanceof Order) {
                    continue;
                }
                $this->processScheduledCancellation($order);
            } catch (\Throwable $e) {
                $this->di['logger']->error('Could not process scheduled cancellation for order #{id}: {message}', ['id' => $id, 'message' => $e->getMessage()]);
            }
        }

        $list = $this->getExpiredOrders();

        foreach ($list as $order) {
            try {
                // Re-check under the current state: the order may have been
                // renewed/paid while earlier orders in this run were processed.
                $this->di['em']->refresh($order);
                if ($order->getStatus() !== Order::STATUS_ACTIVE) {
                    continue;
                }
                $expiresAt = $order->getExpiresAt();
                if (!$expiresAt instanceof \DateTimeInterface || $expiresAt->format('Y-m-d H:i:s') > date('Y-m-d H:i:s')) {
                    continue;
                }
                if (($this->getOrderMetaRepository()->getPairsForOrder((int) $order->getId())[self::META_CANCEL_AT_PERIOD_END] ?? null) === '1') {
                    continue;
                }
                $this->suspendFromOrder($order, $reason);
            } catch (\Exception $e) {
                $this->di['logger']->info($e->getMessage());
            }
        }

        $this->di['event_dispatcher']->dispatch(new AfterAdminBatchSuspendOrdersEvent());

        $this->di['logger']->info('Executed action to suspend expired orders');

        return true;
    }

    public function batchCancelSuspended(): bool
    {
        $this->di['event_dispatcher']->dispatch(new BeforeAdminBatchCancelSuspendedOrdersEvent());

        $mod = $this->di['mod']('order');
        $config = $mod->getConfig();
        if (!isset($config['batch_cancel_suspended']) || !$config['batch_cancel_suspended']) {
            return false;
        }

        $reason = $config['batch_cancel_suspended_reason'] ?? null;
        $days = $this->resolveBatchAfterDays($config['batch_cancel_suspended_after_days'] ?? null);

        // suspended_at < :suspended_before is a portable stand-in for MySQL's
        // DATEDIFF(NOW(), suspended_at) > :days - DATEDIFF compares calendar dates only
        // (ignoring time-of-day), so "more than N days ago" means suspended_at's date is
        // strictly before N days before today.
        $sql = "
            SELECT id, suspended_at
            FROM client_order
            WHERE status = 'suspended'
            AND suspended_at < :suspended_before
            ORDER BY id DESC
        ";
        $suspendedBefore = (new \DateTimeImmutable('today'))->modify("-{$days} days")->format('Y-m-d H:i:s');

        $orders = $this->di['em']->getConnection()->fetchAllAssociative($sql, ['suspended_before' => $suspendedBefore]);

        foreach ($orders as $orderArr) {
            try {
                $order = $this->getOrderRepository()->find((int) $orderArr['id']);
                if (!$order instanceof Order) {
                    throw new \FOSSBilling\Core\Exception\BaseException('Order not found');
                }
                // Re-check: the order may have been unsuspended/paid while
                // earlier orders in this run were processed.
                $this->di['em']->refresh($order);
                $status = $order->getStatus();
                if ($status !== Order::STATUS_SUSPENDED) {
                    continue;
                }
                // Scheduled cancellations, including uncertain attempts, are
                // handled by their dedicated locked recovery path.
                $name = 'fb:scheduled-cancel:' . $this->orderId($order);
                $connection = $this->di['em']->getConnection();
                if (!NamedLock::acquire($connection, $name, 30)) {
                    continue;
                }

                try {
                    $this->di['em']->refresh($order);
                    $meta = $this->getOrderMetaRepository()->getPairsForOrder($this->orderId($order));
                    if ($order->getStatus() === Order::STATUS_SUSPENDED
                        && ($meta[self::META_CANCEL_AT_PERIOD_END] ?? null) !== '1'
                        && !in_array($meta[self::META_SCHEDULED_CANCEL_CLAIM] ?? null, ['1', '2'], true)) {
                        $this->cancelFromOrderLocked($order, $reason);
                    }
                } finally {
                    NamedLock::release($connection, $name);
                }
            } catch (\Exception $e) {
                $this->di['logger']->info($e->getMessage());
            }
        }

        $this->di['event_dispatcher']->dispatch(new AfterAdminBatchCancelSuspendedOrdersEvent());

        $this->di['logger']->info('Executed action to cancel suspended orders');

        return true;
    }

    public function batchCancelUnpaid(): bool
    {
        $this->di['event_dispatcher']->dispatch(new BeforeAdminBatchCancelUnpaidOrdersEvent());

        $mod = $this->di['mod']('order');
        $config = $mod->getConfig();
        if (!isset($config['batch_cancel_unpaid']) || !$config['batch_cancel_unpaid']) {
            return false;
        }

        $days = $this->resolveBatchAfterDays($config['batch_cancel_unpaid_after_days'] ?? null);

        $staleOrders = $this->getOrderRepository()->getStaleUnpaid($days);

        $invoiceService = $this->di['mod_service']('Invoice');

        // A single invoice can cover several orders from one cart checkout
        // (Cart\Service sets the same unpaid_invoice_id on all of them), so
        // they're batch-loaded once up front rather than once per order.
        $invoiceIds = array_values(array_unique(array_filter(
            array_map(static fn (Order $order): ?int => $order->getUnpaidInvoiceId(), $staleOrders),
            static fn (?int $id): bool => $id !== null
        )));
        $invoicesById = [];
        foreach ($invoiceIds === [] ? [] : $invoiceService->getInvoiceRepository()->findBy(['id' => $invoiceIds]) as $invoice) {
            $invoicesById[$invoice->getId()] = $invoice;
        }

        // Whether each invoice's group may proceed to order deletion - set only
        // once the invoice is confirmed gone or removed, so a failed removal
        // leaves it unresolved for a sibling order to retry rather than
        // wrongly treating the group as already handled.
        $invoiceHandled = [];

        // Pending-setup orders were never provisioned, so cancelFromOrder() (which
        // tears down an active service) explicitly rejects them. deleteFromOrder()
        // is the same path an admin uses to manually remove one. The linked unpaid
        // invoice is removed first via deleteInvoiceByAdmin() so it doesn't linger
        // empty and still eligible for reminder emails after the order it belongs
        // to is gone.
        foreach ($staleOrders as $order) {
            try {
                // Re-check the order's current status before touching anything for
                // it: deleteFromOrder() has no status guard of its own, and this
                // order may have been activated or otherwise moved on while earlier
                // orders in this same run were being processed.
                $this->di['em']->refresh($order);
                if ($order->getStatus() !== Order::STATUS_PENDING_SETUP) {
                    continue;
                }

                $invoiceId = $order->getUnpaidInvoiceId();
                if ($invoiceId !== null) {
                    if (!array_key_exists($invoiceId, $invoiceHandled)) {
                        $invoice = $invoicesById[$invoiceId] ?? null;
                        $status = $invoice instanceof Invoice ? $invoice->getStatus() : null;

                        if ($status === Invoice::STATUS_PAID) {
                            // Paid since getStaleUnpaid() ran - leave every order tied
                            // to it alone instead of deleting one out from under that.
                            $invoiceHandled[$invoiceId] = false;
                        } else {
                            if ($status === Invoice::STATUS_UNPAID) {
                                $invoiceService->deleteInvoiceByAdmin($invoice);
                            }
                            // Already gone, or canceled/refunded/some other non-live
                            // status - either way it's no longer a live unpaid invoice,
                            // so the orders that reference it may proceed.
                            $invoiceHandled[$invoiceId] = true;
                        }
                    }

                    if (!$invoiceHandled[$invoiceId]) {
                        continue;
                    }
                }

                $this->deleteFromOrder($order);
            } catch (\Exception $e) {
                $this->di['logger']->info($e->getMessage());
            }
        }

        $this->di['event_dispatcher']->dispatch(new AfterAdminBatchCancelUnpaidOrdersEvent());

        $this->di['logger']->info('Executed action to remove stale unpaid orders');

        return true;
    }

    /**
     * Parses a "cancel/remove after N days" batch-job setting. A blank form field is
     * submitted as '', which isset() treats as present - so this is not just `(int) $value`,
     * which would silently turn a blank field into 0 instead of the intended default.
     */
    private function resolveBatchAfterDays(mixed $configValue, int $default = 7): int
    {
        $days = ($configValue === null || $configValue === '') ? $default : (int) $configValue;

        return $days < 0 ? $default : $days;
    }

    public function updateOrderConfig(Order $order, array $config): bool
    {
        $formId = $order->getFormId();
        $orderId = $this->orderId($order);

        if ($formId) {
            $formbuilderService = $this->di['mod_service']('formbuilder');
            $form = $formbuilderService->getForm((int) $formId);
            $this->validateConfigAgainstForm($config, $form);

            // Preserve service settings while replacing form answers, including unchecked choices.
            $existingConfig = $this->getConfig($order);
            foreach ($form['fields'] as $field) {
                unset($existingConfig[$field['name']]);
            }
            $config = array_replace($existingConfig, $config);
        }

        $oldConfig = $order->getConfig();

        $order->setConfig($config);
        $order->setUpdatedAt(new \DateTimeImmutable());
        $this->di['em']->persist($order);
        $this->di['em']->flush();

        $this->di['logger']->info(
            "Order #{order_id} config changes:\n{old_config}\n{new_config}",
            ['order_id' => $orderId, 'old_config' => $oldConfig, 'new_config' => $order->getConfig()]
        );

        return true;
    }

    private function validateConfigAgainstForm(array $config, array $form): void
    {
        foreach ($form['fields'] as $field) {
            $name = $field['name'];
            $value = $config[$name] ?? null;

            if (!empty($field['required']) && ($value === null || $value === '' || (is_array($value) && count($value) === 0))) {
                throw new \FOSSBilling\Core\Exception\BaseException('Field ":field" is required', [':field' => $field['label']], 4892);
            }

            $options = $field['options'] ?? [];
            if (!empty($options)) {
                if ($field['type'] === 'select' || $field['type'] === 'radio') {
                    if ($value === null || $value === '') {
                        continue;
                    }

                    if (!is_scalar($value)) {
                        throw new \FOSSBilling\Core\Exception\BaseException('Invalid value for field ":field"', [':field' => $field['label']], 4893);
                    }

                    if (!array_key_exists($value, $options) && !in_array($value, $options, true)) {
                        throw new \FOSSBilling\Core\Exception\BaseException('Invalid value for field ":field"', [':field' => $field['label']], 4893);
                    }
                } elseif ($field['type'] === 'checkbox') {
                    if (is_array($value)) {
                        foreach ($value as $v) {
                            if (!in_array($v, $options, true)) {
                                throw new \FOSSBilling\Core\Exception\BaseException('Invalid value for field ":field"', [':field' => $field['label']], 4894);
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

        $sort = SortOptions::fromArray($data, [
            'id' => 'id',
            'status' => 'status',
            'created_at' => 'created_at',
        ]);
        $orderBy = $sort->toOrderByClause('id') ?? 'id DESC';
        $query .= " ORDER BY {$orderBy}";

        return [$query, $bindings];
    }

    public function orderStatusAdd(Order $order, $status, $notes = null): bool
    {
        if (!in_array($status, Order::getValidStatuses(), true)) {
            throw new InformationException('Invalid order status: :status', [':status' => $status]);
        }

        $statusEntry = new OrderStatus();
        $statusEntry->setOrder($order);
        $statusEntry->setStatus($status);
        $statusEntry->setNotes($notes);
        $this->di['em']->persist($statusEntry);
        $this->di['em']->flush();

        $this->di['logger']->info(
            'Added order status history entry {status_id} for order {order_id}',
            ['status_id' => $statusEntry->getId(), 'order_id' => $order->getId()]
        );

        return true;
    }

    public function orderStatusRm($id): bool
    {
        $orderStatus = $this->getOrderStatusRepository()->find($id);
        if (!$orderStatus instanceof OrderStatus) {
            throw new \FOSSBilling\Core\Exception\BaseException('Order history line not found');
        }

        $this->di['em']->remove($orderStatus);
        $this->di['em']->flush();

        $this->di['logger']->info('Order history line removed');

        return true;
    }

    public function findForClientById(ClientEntity $client, $id): ?Order
    {
        return $this->getOrderRepository()->findForClientById((int) $client->getId(), (int) $id);
    }

    public function findByClientIdAndOrderId(int $clientId, int $orderId): ?Order
    {
        return $this->getOrderRepository()->findForClientById($clientId, $orderId);
    }

    /**
     * Returns the API representation of an order's service data.
     *
     * Only entity-backed (built-in) services reach the module's `toApiArray()`;
     * the third-party (DBAL assoc array or false) path fails the `is_object()`
     * guard and returns null (logged as "has no active service"). Extension
     * authors that need third-party service data must read the row via
     * `getOrderService()`.
     */
    public function getOrderServiceData(Order $order, $identity = null)
    {
        $orderId = $this->orderId($order);
        $serviceType = $order->getServiceType();
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
        return $this->calculateTotal($model->getPrice() ?? 0, $model->getQuantity() ?? 1);
    }

    private function calculateTotal($price, $quantity): float
    {
        return (float) $price * (int) $quantity;
    }

    public function setUnpaidInvoice(Order $order, Invoice $proforma): void
    {
        $order->setUnpaidInvoiceId((int) $proforma->getId());
        $order->setUpdatedAt(new \DateTimeImmutable());
        $this->persistOrder($order);
    }

    public function unsetUnpaidInvoice(Order $order): void
    {
        $order->setUnpaidInvoiceId(null);
        $order->setUpdatedAt(new \DateTimeImmutable());
        $this->persistOrder($order);
    }

    public function getRelatedOrderIdByType(Order $order, $type): ?int
    {
        $groupId = $order->getGroupId();

        $o = $this->getOrderRepository()->findOneByGroupIdAndServiceType((string) $groupId, (string) $type);

        return $o instanceof Order ? $o->getId() : null;
    }

    /** Acquire lifecycle locks before the client-removal transaction starts. */
    public function withClientOrderLocks(ClientEntity $client, \Closure $operation): mixed
    {
        if ($this->di['em']->getConnection()->isTransactionActive()) {
            throw new InformationException('Client deletion cannot run inside a database transaction.');
        }
        $orders = $this->getOrderRepository()->findByClientId((int) $client->getId());
        usort($orders, fn (Order $a, Order $b): int => $this->orderId($a) <=> $this->orderId($b));
        $ids = array_map($this->orderId(...), $orders);
        $lockedOperation = fn () => $operation($ids);
        foreach (array_reverse($orders) as $order) {
            $next = $lockedOperation;
            $lockedOperation = fn (): mixed => $this->withLifecycleLock($order, $next);
        }

        return $lockedOperation();
    }

    public function rmByClient(ClientEntity $client, ?array $lockedOrderIds = null): void
    {
        $connection = $this->di['em']->getConnection();
        $connection->transactional(function () use ($client, $connection, $lockedOrderIds): void {
            $clientId = (int) $client->getId();
            $orders = $this->getOrderRepository()->findByClientId($clientId);
            usort($orders, fn (Order $a, Order $b): int => $this->orderId($a) <=> $this->orderId($b));
            if ($lockedOrderIds !== null && array_diff(array_map($this->orderId(...), $orders), $lockedOrderIds) !== []) {
                throw new InformationException('Client orders changed while preparing deletion. Please retry.');
            }
            foreach ($orders as $order) {
                $this->di['em']->refresh($order, LockMode::PESSIMISTIC_WRITE);
                if ($order->getClientId() === $clientId) {
                    $this->assertOrderNotDeleting($order, true);
                }
            }
            $productService = $this->di['mod_service']('Product');
            foreach ($orders as $order) {
                if ($order->getClientId() !== $clientId) {
                    continue;
                }
                $productService->releaseReservedPromoRedemptionsForOrder($order, 'client_deleted');
                $productService->releaseReservedStockForOrder($order, 'client_deleted');
                $this->getOrderMetaRepository()->deleteByOrderId($this->orderId($order));
                $this->getOrderStatusRepository()->rmByOrderId($this->orderId($order));
                $connection->delete('client_order_cancel_request', ['client_order_id' => $this->orderId($order)]);
                $connection->delete('client_order', ['id' => $this->orderId($order), 'client_id' => $clientId]);
            }
            // Also remove orphaned request history from legacy schemas without FKs.
            $connection->delete('client_order_cancel_request', ['client_id' => $clientId]);
        });
    }

    public function exportCSV(array $headers): Response
    {
        if ($headers) {
            $headers = array_values(array_intersect(self::EXPORTABLE_COLUMNS, $headers));
        }

        if (!$headers) {
            $headers = self::DEFAULT_EXPORT_COLUMNS;
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
