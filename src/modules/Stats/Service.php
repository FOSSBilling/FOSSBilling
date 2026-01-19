<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Stats;

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

    public function getSummary(): array
    {
        $stats = [];

        $dbal = $this->di['dbal'];

        $total_query = 'SELECT COUNT(1) FROM :table';
        $yeste_query = "SELECT COUNT(1) FROM :table WHERE DATE_FORMAT(`created_at`, '%Y-%m-%d') = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        $today_query = "SELECT COUNT(1) FROM :table WHERE DATE_FORMAT(`created_at`, '%Y-%m-%d') = CURDATE()";
        $month_query = "SELECT COUNT(1) FROM :table WHERE DATE_FORMAT(`created_at`, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')";
        $last_month_query = "SELECT COUNT(1) FROM :table WHERE created_at BETWEEN DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m-01') AND DATE_FORMAT(NOW() ,'%Y-%m-01')";

        // client stats
        $table = 'client';
        $result = $dbal->executeQuery(str_replace(':table', $table, $total_query));
        $stats['clients_total'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $today_query));
        $stats['clients_today'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $yeste_query));
        $stats['clients_yesterday'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $month_query));
        $stats['clients_this_month'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $last_month_query));
        $stats['clients_last_month'] = $result->fetchOne();

        // orders stats
        $table = 'client_order';
        $result = $dbal->executeQuery(str_replace(':table', $table, $total_query));
        $stats['orders_total'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $today_query));
        $stats['orders_today'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $yeste_query));
        $stats['orders_yesterday'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $month_query));
        $stats['orders_this_month'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $last_month_query));
        $stats['orders_last_month'] = $result->fetchOne();

        // invoice stats
        $table = 'invoice';
        $result = $dbal->executeQuery(str_replace(':table', $table, $total_query));
        $stats['invoices_total'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $today_query));
        $stats['invoices_today'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $yeste_query));
        $stats['invoices_yesterday'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $month_query));
        $stats['invoices_this_month'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $last_month_query));
        $stats['invoices_last_month'] = $result->fetchOne();

        // ticket stats
        $table = 'support_ticket';
        $result = $dbal->executeQuery(str_replace(':table', $table, $total_query));
        $stats['tickets_total'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $today_query));
        $stats['tickets_today'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $yeste_query));
        $stats['tickets_yesterday'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $month_query));
        $stats['tickets_this_month'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $last_month_query));
        $stats['tickets_last_month'] = $result->fetchOne();

        return $stats;
    }

    public function getSummaryIncome(): array
    {
        $stats = [];

        $dbal = $this->di['dbal'];

        $total_query = "SELECT (COALESCE(SUM(base_income), 0) - COALESCE(SUM(base_refund), 0)) AS income FROM invoice WHERE approved = 1 AND (status = 'paid' OR status = 'refunded')";
        $yeste_query = "SELECT (COALESCE(SUM(base_income), 0) - COALESCE(SUM(base_refund), 0)) AS income FROM invoice WHERE approved = 1 AND (status = 'paid' OR status = 'refunded') AND DATE_FORMAT(`paid_at`, '%Y-%m-%d') = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        $today_query = "SELECT (COALESCE(SUM(base_income), 0) - COALESCE(SUM(base_refund), 0)) AS income FROM invoice WHERE approved = 1 AND (status = 'paid' OR status = 'refunded') AND DATE_FORMAT(`paid_at`, '%Y-%m-%d') = CURDATE()";
        $month_query = "SELECT (COALESCE(SUM(base_income), 0) - COALESCE(SUM(base_refund), 0)) AS income FROM invoice WHERE approved = 1 AND (status = 'paid' OR status = 'refunded') AND DATE_FORMAT(`paid_at`, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')";
        $last_month_query = "SELECT (COALESCE(SUM(base_income), 0) - COALESCE(SUM(base_refund), 0)) AS income FROM invoice WHERE approved = 1 AND (status = 'paid' OR status = 'refunded') AND paid_at BETWEEN DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m-01') AND DATE_FORMAT(NOW() ,'%Y-%m-01')";

        $result = $dbal->executeQuery($total_query);
        $stats['total'] = $result->fetchOne();

        $result = $dbal->executeQuery($today_query);
        $stats['today'] = $result->fetchOne();

        $result = $dbal->executeQuery($yeste_query);
        $stats['yesterday'] = $result->fetchOne();

        $result = $dbal->executeQuery($month_query);
        $stats['this_month'] = $result->fetchOne();

        $result = $dbal->executeQuery($last_month_query);
        $stats['last_month'] = $result->fetchOne();

        return $stats;
    }

    public function getOrdersStatuses($data)
    {
        $orderService = $this->di['mod_service']('order');
        $c = $orderService->counter();
        unset($c['total']);

        return $c;
    }

    public function getProductSummary($data)
    {
        $query = "SELECT p.id, p.title, COUNT(o.id) as orders
                FROM `client_order` o
                RIGHT JOIN `product` p ON(p.id = o.product_id)
                WHERE o.status = 'active'
                GROUP BY o.product_id
                ORDER BY orders DESC
                ";

        return $this->di['db']->getAll($query);
    }

    public function getProductSales($data)
    {
        [$time_from, $time_to] = $this->_getDateInterval($data);

        $dbal = $this->di['dbal'];

        $query = 'SELECT title, COUNT(product_id) as sales
                FROM `client_order`
                WHERE status = :status
                AND `created_at` BETWEEN :date_from AND :date_to
                GROUP BY product_id
                ';

        $result = $dbal->executeQuery($query, [
            'status' => 'active',
            'date_from' => date('Y-m-d', $time_from),
            'date_to' => date('Y-m-d', $time_to),
        ]);

        return $result->fetchAllKeyValue();
    }

    public function incomeAndRefundStats($data)
    {
        $dbal = $this->di['dbal'];

        $query = 'SELECT COALESCE(SUM(base_refund), 0) AS `refund`, COALESCE(SUM(base_income), 0) AS `income`
                FROM `invoice`
                WHERE approved = 1
                AND (status = :status1 OR status = :status2)
                ';

        $result = $dbal->executeQuery($query, [
            'status1' => \Model_Invoice::STATUS_PAID,
            'status2' => \Model_Invoice::STATUS_REFUNDED,
        ]);

        $results = $result->fetchAllAssociative();

        return $results[0];
    }

    public function getRefunds($data)
    {
        $time_from = strtotime('-1 month');
        $time_to = strtotime('+1 day');

        if (isset($data['date_from']) && !empty($data['date_from'])) {
            $time_from = strtotime((string) $data['date_from']);
        }

        if (isset($data['date_to']) && !empty($data['date_to'])) {
            $time_to = strtotime((string) $data['date_to']);
        }

        $dbal = $this->di['dbal'];

        $query = "SELECT DATE_FORMAT(`created_at`, '%Y-%m-%d') AS `date`, COALESCE(SUM(base_refund), 0) AS `refund`
                FROM `invoice`
                WHERE `created_at` BETWEEN :date_from AND :date_to
                AND approved = 1
                AND status = :status
                GROUP BY `date`";

        $result = $dbal->executeQuery($query, [
            'status' => \Model_Invoice::STATUS_REFUNDED,
            'date_from' => date('Y-m-d', $time_from),
            'date_to' => date('Y-m-d', $time_to),
        ]);

        $results = $result->fetchAllKeyValue();

        return $this->_genFlotArray($results, $time_from, $time_to);
    }

    public function getIncome($data)
    {
        $time_from = strtotime('-1 month');
        $time_to = strtotime('+1 day');

        if (isset($data['date_from']) && !empty($data['date_from'])) {
            $time_from = strtotime((string) $data['date_from']);
        }

        if (isset($data['date_to']) && !empty($data['date_to'])) {
            $time_to = strtotime((string) $data['date_to']);
        }

        $dbal = $this->di['dbal'];

        $query = "SELECT DATE_FORMAT(`paid_at`, '%Y-%m-%d') AS `date`, (COALESCE(SUM(base_income), 0) - COALESCE(SUM(base_refund), 0)) AS `income`
                FROM `invoice`
                WHERE `paid_at` BETWEEN :date_from AND :date_to
                AND approved = 1
                AND status = :status
                GROUP BY `date`";

        $result = $dbal->executeQuery($query, [
            'status' => \Model_Invoice::STATUS_PAID,
            'date_from' => date('Y-m-d', $time_from),
            'date_to' => date('Y-m-d', $time_to),
        ]);

        $results = $result->fetchAllKeyValue();

        return $this->_genFlotArray($results, $time_from, $time_to);
    }

    public function getClientCountries($data)
    {
        $limit = (int) $data['limit'] ?? 10;
        $q = "
            SELECT country, COUNT(id) as clients
            FROM `client`
            GROUP BY `country`
            ORDER BY clients DESC
            LIMIT $limit
        ";
        $dbal = $this->di['dbal'];
        $result = $dbal->executeQuery($q);

        return $result->fetchAllKeyValue();
    }

    public function getSalesByCountry($data)
    {
        $limit = (int) $data['limit'] ?? 10;
        $q = "
            SELECT buyer_country, COUNT(id) as sales
            FROM `invoice`
            WHERE status = 'paid'
            GROUP BY `buyer_country`
            ORDER BY sales DESC
            LIMIT $limit
        ";
        $dbal = $this->di['dbal'];
        $result = $dbal->executeQuery($q);

        return $result->fetchAllKeyValue();
    }

    private function _getDateInterval($data): array
    {
        $time_from = strtotime('-1 month');
        $time_to = strtotime('+1 day');

        if (isset($data['date_from']) && !empty($data['date_from'])) {
            $time_from = strtotime((string) $data['date_from']);
        }

        if (isset($data['date_to']) && !empty($data['date_to'])) {
            $time_to = strtotime((string) $data['date_to']);
        }

        return [$time_from, $time_to];
    }

    public function getTableStats($table, $data = [])
    {
        $time_from = strtotime('-1 month');
        $time_to = strtotime('+1 day');

        if (isset($data['date_from']) && !empty($data['date_from'])) {
            $time_from = strtotime((string) $data['date_from']);
        }

        if (isset($data['date_to']) && !empty($data['date_to'])) {
            $time_to = strtotime((string) $data['date_to']);
        }

        $dbal = $this->di['dbal'];

        $query = "SELECT DATE_FORMAT(`created_at`, '%Y-%m-%d') AS `date`, COUNT(1) AS `count`
                FROM `$table`
                WHERE `created_at` BETWEEN :date_from AND :date_to
                GROUP BY `date`";

        $result = $dbal->executeQuery($query, [
            'date_from' => date('Y-m-d', $time_from),
            'date_to' => date('Y-m-d', $time_to),
        ]);

        $results = $result->fetchAllKeyValue();

        return $this->_genFlotArray($results, $time_from, $time_to);
    }

    /**
     * @param int $time_from
     * @param int $time_to
     *
     * @return int[][]
     */
    private function _genFlotArray($results, $time_from, $time_to): array
    {
        $data = [];
        // Loop between timestamps, 1 day at a time
        do {
            $time_from = strtotime('+1 day', $time_from);
            $dom = date('Y-m-d', $time_from);
            $c = $results[$dom] ?? 0;
            $data[] = [$time_from * 1000, (int) $c];
        } while ($time_to > $time_from);
        array_pop($data);

        return $data;
    }
}
