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

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Order\Entity\Order;
use FOSSBilling\Core\Exception\InformationException;
use Pimple\Container;

class Upgrades
{
    public const string TABLE = 'client_order_upgrade';
    public const string RULES = 'client_order_upgrade_rule';
    public const string EVENT = 'onAfterClientOrderUpgradePaid';

    public const string STATUS_PENDING = 'pending';
    public const string STATUS_APPLYING = 'applying';
    public const string STATUS_APPLIED = 'applied';
    public const string STATUS_FAILED = 'failed';
    public const string STATUS_CANCELLED = 'cancelled';

    /**
     * Ensures required database tables exist.
     */
    public static function ensureTables(Container $di): void
    {
        $db = $di['em']->getConnection();
        $schemaManager = method_exists($db, 'createSchemaManager') ? $db->createSchemaManager() : $db->getSchemaManager();

        if (!$schemaManager->tablesExist([self::TABLE])) {
            $db->executeStatement("
                CREATE TABLE IF NOT EXISTS `" . self::TABLE . "` (
                    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    `order_id` bigint(20) NOT NULL,
                    `client_id` bigint(20) NOT NULL,
                    `kind` varchar(20) NOT NULL,
                    `from_product_id` bigint(20) NOT NULL,
                    `from_title` varchar(255) NOT NULL DEFAULT '',
                    `from_period` varchar(10) NOT NULL,
                    `from_price` decimal(18,2) NOT NULL,
                    `from_expires_at` datetime NOT NULL,
                    `to_product_id` bigint(20) NOT NULL,
                    `to_title` varchar(255) NOT NULL DEFAULT '',
                    `to_period` varchar(10) NOT NULL,
                    `to_price` decimal(18,2) NOT NULL,
                    `to_expires_at` datetime NOT NULL,
                    `days_left` int(11) NOT NULL,
                    `days_total` int(11) NOT NULL,
                    `credit` decimal(18,2) NOT NULL,
                    `charge` decimal(18,2) NOT NULL,
                    `amount` decimal(18,2) NOT NULL,
                    `currency` varchar(10) NOT NULL DEFAULT '',
                    `invoice_id` bigint(20) DEFAULT NULL,
                    `status` varchar(20) NOT NULL DEFAULT 'pending',
                    `error` text,
                    `created_at` datetime NOT NULL,
                    `claimed_at` datetime DEFAULT NULL,
                    `applied_at` datetime DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `order_status` (`order_id`, `status`),
                    KEY `status` (`status`),
                    KEY `invoice` (`invoice_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        }

        if (!$schemaManager->tablesExist([self::RULES])) {
            $db->executeStatement("
                CREATE TABLE IF NOT EXISTS `" . self::RULES . "` (
                    `scope` varchar(10) NOT NULL,
                    `ref_id` bigint(20) NOT NULL,
                    `upgrade` tinyint(1) DEFAULT NULL,
                    `downgrade` tinyint(1) DEFAULT NULL,
                    `updated_at` datetime NOT NULL,
                    PRIMARY KEY (`scope`, `ref_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        }
    }

    /**
     * Retrieves any pending upgrade waiting for payment on this order.
     */
    public static function waiting(Container $di, int $orderId): ?array
    {
        self::ensureTables($di);
        $db = $di['em']->getConnection();

        $row = $db->fetchAssociative(
            'SELECT u.*, i.hash as invoice_hash, i.status as invoice_status
             FROM ' . self::TABLE . ' u
             LEFT JOIN invoice i ON i.id = u.invoice_id
             WHERE u.order_id = ? AND u.status = ?
             ORDER BY u.id DESC LIMIT 1',
            [$orderId, self::STATUS_PENDING]
        );

        if (!$row) {
            return null;
        }

        if ($row['invoice_status'] === Invoice::STATUS_CANCELED) {
            $db->executeStatement('UPDATE ' . self::TABLE . ' SET status = ? WHERE id = ?', [self::STATUS_CANCELLED, $row['id']]);

            return null;
        }

        return $row;
    }

    /**
     * Calculates available upgrade and downgrade options for an active order.
     */
    public static function options(Container $di, object $order, ?string $now = null): array
    {
        self::ensureTables($di);
        $now ??= date('Y-m-d H:i:s');

        $orderId = (int) ($order->id ?? $order->getId());
        $status = (string) ($order->status ?? $order->getStatus());
        $orderPrice = (float) ($order->price ?? $order->getPrice());
        $orderPeriod = (string) ($order->period ?? $order->getPeriod());
        $orderExpiresAt = (string) ($order->expires_at ?? $order->getExpiresAt());
        $productId = (int) ($order->product_id ?? $order->getProductId());
        $serviceType = (string) ($order->service_type ?? $order->getServiceType());
        $currency = (string) ($order->currency ?? $order->getCurrency());
        $orderTitle = (string) ($order->title ?? $order->getTitle());

        $current = [
            'product_id' => $productId,
            'title' => $orderTitle,
            'period' => $orderPeriod,
            'price' => $orderPrice,
            'expires_at' => $orderExpiresAt,
            'currency' => $currency,
        ];

        $waiting = self::waiting($di, $orderId);
        if ($waiting !== null) {
            return [
                'available' => false,
                'reason' => __trans('An upgrade of this service is waiting for payment.'),
                'current' => $current,
                'options' => [],
                'waiting' => $waiting,
            ];
        }

        if ($status !== Order::STATUS_ACTIVE && $status !== 'active') {
            return [
                'available' => false,
                'reason' => __trans('Only active services can be upgraded or downgraded.'),
                'current' => $current,
                'options' => [],
                'waiting' => null,
            ];
        }

        if (empty($orderPeriod) || empty($orderExpiresAt)) {
            return [
                'available' => false,
                'reason' => __trans('This service does not have a recurring billing cycle.'),
                'current' => $current,
                'options' => [],
                'waiting' => null,
            ];
        }

        $productService = $di['mod_service']('product');
        $currentProduct = $productService->findProductById($productId);
        if (!$currentProduct) {
            return [
                'available' => false,
                'reason' => __trans('Current product not found.'),
                'current' => $current,
                'options' => [],
                'waiting' => null,
            ];
        }

        $upgradablePairs = $productService->getUpgradablePairs($currentProduct);
        $upgradablePairs[$currentProduct->getId()] = $currentProduct->getTitle();

        $currentConfig = json_decode($currentProduct->getConfig() ?? '', true) ?? [];
        $options = [];

        foreach ($upgradablePairs as $targetId => $targetTitle) {
            $targetProduct = $productService->findProductById((int) $targetId);
            if (!$targetProduct || $targetProduct->getType() !== $serviceType) {
                continue;
            }

            $targetConfig = json_decode($targetProduct->getConfig() ?? '', true) ?? [];

            if ($serviceType === 'hosting') {
                $currentServerId = (int) ($currentConfig['server_id'] ?? 0);
                $targetServerId = (int) ($targetConfig['server_id'] ?? 0);
                $targetHpId = (int) ($targetConfig['hosting_plan_id'] ?? 0);

                if ($targetHpId <= 0 || ($currentServerId > 0 && $targetServerId > 0 && $currentServerId !== $targetServerId)) {
                    continue;
                }
            }

            $pricing = $productService->getProductPricingArray($targetProduct);
            $recurrent = $pricing['recurrent'] ?? [];

            foreach ($recurrent as $periodCode => $info) {
                if (empty($info['enabled'])) {
                    continue;
                }

                $newPrice = (float) ($info['price'] ?? 0);
                if ($newPrice <= 0) {
                    continue;
                }

                if ($targetId === $productId && strtoupper(trim($periodCode)) === strtoupper(trim($orderPeriod))) {
                    continue;
                }

                try {
                    $quote = Proration::quote($orderPrice, $orderPeriod, $orderExpiresAt, $newPrice, $periodCode, $now);
                } catch (\Throwable) {
                    continue;
                }

                $options[] = [
                    'product_id' => (int) $targetId,
                    'title' => (string) $targetTitle,
                    'period' => $periodCode,
                    'price' => $newPrice,
                    'kind' => $quote['kind'],
                    'same_cycle' => $quote['same_cycle'],
                    'days_left' => $quote['days_left'],
                    'days_total' => $quote['days_total'],
                    'credit' => $quote['credit'],
                    'charge' => $quote['charge'],
                    'amount' => $quote['amount'],
                    'new_expires_at' => $quote['new_expires_at'],
                ];
            }
        }

        return [
            'available' => count($options) > 0,
            'reason' => count($options) > 0 ? '' : __trans('No upgrade options are currently available for this service.'),
            'current' => $current,
            'options' => $options,
            'waiting' => null,
        ];
    }

    /**
     * Requests an upgrade or downgrade for a service.
     */
    public static function request(Container $di, object $order, int $productId, string $period, ?string $now = null): array
    {
        self::ensureTables($di);
        $now ??= date('Y-m-d H:i:s');
        $db = $di['em']->getConnection();

        $orderId = (int) ($order->id ?? $order->getId());
        $clientId = (int) ($order->client_id ?? $order->getClientId());
        $orderPeriod = (string) ($order->period ?? $order->getPeriod());
        $orderPrice = (float) ($order->price ?? $order->getPrice());
        $orderExpiresAt = (string) ($order->expires_at ?? $order->getExpiresAt());
        $orderTitle = (string) ($order->title ?? $order->getTitle());
        $currency = (string) ($order->currency ?? $order->getCurrency());

        $options = self::options($di, $order, $now);
        if (!$options['available']) {
            throw new InformationException($options['reason']);
        }

        $period = strtoupper(trim($period));
        $chosen = null;
        foreach ($options['options'] as $opt) {
            if ($opt['product_id'] === $productId && strtoupper(trim($opt['period'])) === $period) {
                $chosen = $opt;
                break;
            }
        }

        if ($chosen === null) {
            throw new InformationException('This change is not available for this service.');
        }

        $db->insert(self::TABLE, [
            'order_id' => $orderId,
            'client_id' => $clientId,
            'kind' => $chosen['kind'],
            'from_product_id' => (int) ($order->product_id ?? $order->getProductId()),
            'from_title' => mb_substr($orderTitle, 0, 255),
            'from_period' => $orderPeriod,
            'from_price' => $orderPrice,
            'from_expires_at' => $orderExpiresAt,
            'to_product_id' => $productId,
            'to_title' => mb_substr((string) $chosen['title'], 0, 255),
            'to_period' => $chosen['period'],
            'to_price' => $chosen['price'],
            'to_expires_at' => $chosen['new_expires_at'],
            'days_left' => $chosen['days_left'],
            'days_total' => $chosen['days_total'],
            'credit' => $chosen['credit'],
            'charge' => $chosen['charge'],
            'amount' => $chosen['amount'],
            'currency' => $currency,
            'status' => self::STATUS_PENDING,
            'created_at' => $now,
        ]);
        $upgradeId = (int) $db->lastInsertId();

        // Positive amount: create invoice for difference
        if ($chosen['amount'] > 0) {
            $client = $di['db']->getExistingModelById('Client', $clientId, 'Client not found');
            $invoiceService = $di['mod_service']('invoice');
            $clientService = $di['mod_service']('client');

            $invoice = $invoiceService->prepareInvoice($client, [
                'items' => [[
                    'type' => InvoiceItem::TYPE_ORDER,
                    'task' => InvoiceItem::TASK_UPGRADE,
                    'rel_id' => (string) $upgradeId,
                    'title' => sprintf('Upgrade: %s (%s) to %s (%s)', $orderTitle, $orderPeriod, $chosen['title'], $chosen['period']),
                    'price' => $chosen['amount'],
                    'quantity' => 1,
                    'taxed' => (bool) $clientService->isClientTaxable($client),
                ]],
                'approve' => true,
            ]);

            $db->update(self::TABLE, ['invoice_id' => (int) $invoice->id], ['id' => $upgradeId]);

            return [
                'id' => $upgradeId,
                'status' => self::STATUS_PENDING,
                'amount' => $chosen['amount'],
                'invoice_hash' => (string) $invoice->hash,
            ];
        }

        // Amount <= 0 (Downgrade or zero-difference change):
        if ($chosen['amount'] < 0) {
            $credit = abs($chosen['amount']);
            $client = $di['db']->getExistingModelById('Client', $clientId, 'Client not found');
            $clientService = $di['mod_service']('client');
            $clientService->addFunds($client, $credit, sprintf('Downgrade credit for order #%d', $orderId));
        }

        self::apply($di, $upgradeId, $now);
        $finalStatus = (string) $db->fetchOne('SELECT status FROM ' . self::TABLE . ' WHERE id = ?', [$upgradeId]);

        return [
            'id' => $upgradeId,
            'status' => $finalStatus,
            'amount' => $chosen['amount'],
            'invoice_hash' => null,
        ];
    }

    /**
     * Callback triggered when the upgrade invoice is marked as paid.
     */
    public static function onPaid(Container $di, int $upgradeId, ?string $now = null): void
    {
        self::ensureTables($di);
        $db = $di['em']->getConnection();

        $row = $db->fetchAssociative(
            'SELECT u.status, i.status AS invoice_status
             FROM ' . self::TABLE . ' u
             LEFT JOIN invoice i ON i.id = u.invoice_id
             WHERE u.id = ?',
            [$upgradeId]
        );

        if (!$row || $row['status'] !== self::STATUS_PENDING || $row['invoice_status'] !== Invoice::STATUS_PAID) {
            return;
        }

        self::apply($di, $upgradeId, $now);
    }

    /**
     * Applies an upgrade or downgrade to the service and order.
     */
    public static function apply(Container $di, int $upgradeId, ?string $now = null): bool
    {
        self::ensureTables($di);
        $now ??= date('Y-m-d H:i:s');
        $db = $di['em']->getConnection();

        $claimed = $db->executeStatement(
            'UPDATE ' . self::TABLE . ' SET status = ?, claimed_at = ? WHERE id = ? AND status IN (?, ?)',
            [self::STATUS_APPLYING, $now, $upgradeId, self::STATUS_PENDING, self::STATUS_FAILED]
        );

        if ($claimed !== 1) {
            return false;
        }

        $up = $db->fetchAssociative('SELECT * FROM ' . self::TABLE . ' WHERE id = ?', [$upgradeId]);
        if (!$up) {
            return false;
        }

        $orderId = (int) $up['order_id'];

        try {
            $order = $di['db']->load('ClientOrder', $orderId);
            if (!$order) {
                throw new InformationException('The service order was not found.');
            }

            $productService = $di['mod_service']('product');
            $targetProduct = $productService->findProductById((int) $up['to_product_id']);
            if (!$targetProduct) {
                throw new InformationException('The target product was not found.');
            }

            $targetConfig = json_decode($targetProduct->getConfig() ?? '', true) ?? [];

            // 1. Move hosting account plan if hosting service
            if ($order->service_type === 'hosting') {
                $hpId = (int) ($targetConfig['hosting_plan_id'] ?? 0);
                if ($hpId > 0) {
                    $hp = $di['db']->getExistingModelById('ServiceHostingHp', $hpId, 'Hosting plan not found');
                    $service = $di['mod_service']('order')->getOrderService($order);
                    if ($service instanceof \Model_ServiceHosting) {
                        $di['mod_service']('servicehosting')->changeAccountPlan($order, $service, $hp);
                    }
                }
            }

            // 2. Cancel any pending unpaid renewal invoice issued for old cycle/price
            if ($order->unpaid_invoice_id) {
                $unpaidInvoiceId = (int) $order->unpaid_invoice_id;
                $unpaidInvoice = $di['db']->load('Invoice', $unpaidInvoiceId);
                if ($unpaidInvoice && $unpaidInvoice->status === Invoice::STATUS_UNPAID) {
                    $unpaidInvoice->status = Invoice::STATUS_CANCELED;
                    $unpaidInvoice->updated_at = $now;
                    $di['db']->store($unpaidInvoice);
                }
                $order->unpaid_invoice_id = null;
            }

            // 3. Update order config
            $orderConfig = json_decode((string) $order->config, true) ?: [];
            if ($order->service_type === 'hosting' && isset($targetConfig['hosting_plan_id'])) {
                $orderConfig['hosting_plan_id'] = $targetConfig['hosting_plan_id'];
                if (isset($targetConfig['server_id'])) {
                    $orderConfig['server_id'] = $targetConfig['server_id'];
                }
            }

            // 4. Update order details
            $order->product_id = (int) $up['to_product_id'];
            $order->title = (string) $up['to_title'];
            $order->price = (float) $up['to_price'];
            $order->period = (string) $up['to_period'];
            $order->expires_at = (string) $up['to_expires_at'];
            $order->config = json_encode($orderConfig);
            $order->updated_at = $now;

            $di['db']->store($order);

            // 5. Mark upgrade as applied
            $db->update(
                self::TABLE,
                ['status' => self::STATUS_APPLIED, 'applied_at' => $now, 'error' => null],
                ['id' => $upgradeId]
            );

            $di['logger']->info(sprintf('Order #%d upgraded to product #%d (%s)', $orderId, (int) $up['to_product_id'], (string) $up['to_period']));

            return true;
        } catch (\Throwable $e) {
            $db->update(
                self::TABLE,
                ['status' => self::STATUS_FAILED, 'error' => mb_substr($e->getMessage(), 0, 2000)],
                ['id' => $upgradeId]
            );

            $di['logger']->error(sprintf('Failed to apply upgrade #%d for order #%d: %s', $upgradeId, $orderId, $e->getMessage()));

            return false;
        }
    }

    /**
     * History of upgrades for an order.
     */
    public static function history(Container $di, int $orderId): array
    {
        self::ensureTables($di);

        return $di['em']->getConnection()->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE order_id = ? ORDER BY id DESC',
            [$orderId]
        );
    }
}
