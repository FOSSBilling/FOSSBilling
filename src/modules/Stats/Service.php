<?php
/**
 * Copyright 2022-2024 FOSSBilling
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

        $pdo = $this->di['pdo'];

        $total_query = 'SELECT COUNT(1) FROM :table';
        $yeste_query = "SELECT COUNT(1) FROM :table WHERE DATE_FORMAT(`created_at`, '%Y-%m-%d') = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        $today_query = "SELECT COUNT(1) FROM :table WHERE DATE_FORMAT(`created_at`, '%Y-%m-%d') = CURDATE()";
        $month_query = "SELECT COUNT(1) FROM :table WHERE DATE_FORMAT(`created_at`, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')";
        $last_month_query = "SELECT COUNT(1) FROM :table WHERE created_at BETWEEN DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m-01') AND DATE_FORMAT(NOW() ,'%Y-%m-01')";

        // client stats
        $table = 'client';
        $stmt = $pdo->prepare(str_replace(':table', $table, $total_query));
        $stmt->execute();
        $stats['clients_total'] = $stmt->fetchColumn();

        $stmt = $pdo->prepare(str_replace(':table', $table, $today_query));
        $stmt->execute();
        $stats['clients_today'] = $stmt->fetchColumn();

        $stmt = $pdo->prepare(str_replace(':table', $table, $yeste_query));
        $stmt->execute();
        $stats['clients_yesterday'] = $stmt->fetchColumn();

        $stmt = $pdo->prepare(str_replace(':table', $table, $month_query));
        $stmt->execute();
        $stats['clients_this_month'] = $stmt->fetchColumn();

        $stmt = $pdo->prepare(str_replace(':table', $table, $last_month_query));
        $stmt->execute();
        $stats['clients_last_month'] = $stmt->fetchColumn();

        // orders stats
        $table = 'client_order';
        $stmt = $pdo->prepare(str_replace(':table', $table, $total_query));
        $stmt->execute();
        $stats['orders_total'] = $stmt->fetchColumn();

        $stmt = $pdo->prepare(str_replace(':table', $table, $today_query));
        $stmt->execute();
        $stats['orders_today'] = $stmt->fetchColumn();

        $stmt = $pdo->prepare(str_replace(':table', $table, $yeste_query));
        $stmt->execute();
        $stats['orders_yesterday'] = $stmt->fetchColumn();

        $stmt = $pdo->prepare(str_replace(':table', $table, $month_query));
        $stmt->execute();
        $stats['orders_this_month'] = $stmt->fetchColumn();

        $stmt = $pdo->prepare(str_replace(':table', $table, $last_month_query));
        $stmt->execute();
        $stats['orders_last_month'] = $stmt->fetchColumn();

        // invoice stats
        $table = 'invoice';
        $stmt = $pdo->prepare(str_replace(':table', $table, $total_query));
        $stmt->execute();
        $stats['invoices_total'] = $stmt->fetchColumn();

        $stmt = $pdo->prepare(str_replace(':table', $table, $today_query));
        $stmt->execute();
        $stats['invoices_today'] = $stmt->fetchColumn();

        $stmt = $pdo->prepare(str_replace(':table', $table, $yeste_query));
        $stmt->execute();
        $stats['invoices_yesterday'] = $stmt->fetchColumn();

        $stmt = $pdo->prepare(str_replace(':table', $table, $month_query));
        $stmt->execute();
        $stats['invoices_this_month'] = $stmt->fetchColumn();

        $stmt = $pdo->prepare(str_replace(':table', $table, $last_month_query));
        $stmt->execute();
        $stats['invoices_last_month'] = $stmt->fetchColumn();

        // ticket stats
        $table = 'support_ticket';
        $stmt = $pdo->prepare(str_replace(':table', $table, $total_query));
        $stmt->execute();
        $stats['tickets_total'] = $stmt->fetchColumn();

        $stmt = $pdo->prepare(str_replace(':table', $table, $today_query));
        $stmt->execute();
        $stats['tickets_today'] = $stmt->fetchColumn();

        $stmt = $pdo->prepare(str_replace(':table', $table, $yeste_query));
        $stmt->execute();
        $stats['tickets_yesterday'] = $stmt->fetchColumn();

        $stmt = $pdo->prepare(str_replace(':table', $table, $month_query));
        $stmt->execute();
        $stats['tickets_this_month'] = $stmt->fetchColumn();

        $stmt = $pdo->prepare(str_replace(':table', $table, $last_month_query));
        $stmt->execute();
        $stats['tickets_last_month'] = $stmt->fetchColumn();

        return $stats;
    }

    public function getSummaryIncome(): array
    {
        $stats = [];

        $pdo = $this->di['pdo'];

        $total_query = "SELECT (COALESCE(SUM(base_income), 0) - COALESCE(SUM(base_refund), 0)) AS income FROM invoice WHERE approved = 1 AND (status = 'paid' OR status = 'refunded')";
        $yeste_query = "SELECT (COALESCE(SUM(base_income), 0) - COALESCE(SUM(base_refund), 0)) AS income FROM invoice WHERE approved = 1 AND (status = 'paid' OR status = 'refunded') AND DATE_FORMAT(`paid_at`, '%Y-%m-%d') = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        $today_query = "SELECT (COALESCE(SUM(base_income), 0) - COALESCE(SUM(base_refund), 0)) AS income FROM invoice WHERE approved = 1 AND (status = 'paid' OR status = 'refunded') AND DATE_FORMAT(`paid_at`, '%Y-%m-%d') = CURDATE()";
        $month_query = "SELECT (COALESCE(SUM(base_income), 0) - COALESCE(SUM(base_refund), 0)) AS income FROM invoice WHERE approved = 1 AND (status = 'paid' OR status = 'refunded') AND DATE_FORMAT(`paid_at`, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')";
        $last_month_query = "SELECT (COALESCE(SUM(base_income), 0) - COALESCE(SUM(base_refund), 0)) AS income FROM invoice WHERE approved = 1 AND (status = 'paid' OR status = 'refunded') AND paid_at BETWEEN DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m-01') AND DATE_FORMAT(NOW() ,'%Y-%m-01')";

        $stmt = $pdo->prepare($total_query);
        $stmt->execute();
        $stats['total'] = $stmt->fetchColumn();

        $stmt = $pdo->prepare($today_query);
        $stmt->execute();
        $stats['today'] = $stmt->fetchColumn();

        $stmt = $pdo->prepare($yeste_query);
        $stmt->execute();
        $stats['yesterday'] = $stmt->fetchColumn();

        $stmt = $pdo->prepare($month_query);
        $stmt->execute();
        $stats['this_month'] = $stmt->fetchColumn();

        $stmt = $pdo->prepare($last_month_query);
        $stmt->execute();
        $stats['last_month'] = $stmt->fetchColumn();

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

        $pdo = $this->di['pdo'];

        $query = 'SELECT title, COUNT(product_id) as sales
                FROM `client_order`
                WHERE status = :status
                AND `created_at` BETWEEN :date_from AND :date_to
                GROUP BY product_id
                ';

        $stmt = $pdo->prepare($query);
        $stmt->execute(
            [
                'status' => 'active',
                'date_from' => date('Y-m-d', $time_from),
                'date_to' => date('Y-m-d', $time_to),
            ]
        );

        return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public function incomeAndRefundStats($data)
    {
        $pdo = $this->di['pdo'];

        $query = 'SELECT COALESCE(SUM(base_refund), 0) AS `refund`, COALESCE(SUM(base_income), 0) AS `income`
                FROM `invoice`
                WHERE approved = 1
                AND (status = :status1 OR status = :status2)
                ';

        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'status1' => \Model_Invoice::STATUS_PAID,
            'status2' => \Model_Invoice::STATUS_REFUNDED,
        ]);

        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $results[0];
    }

    public function getRefunds($data)
    {
        $time_from = strtotime('-1 month');
        $time_to = strtotime('+1 day');

        if (isset($data['date_from']) && !empty($data['date_from'])) {
            $time_from = strtotime($data['date_from']);
        }

        if (isset($data['date_to']) && !empty($data['date_to'])) {
            $time_to = strtotime($data['date_to']);
        }

        $pdo = $this->di['pdo'];

        $query = "SELECT DATE_FORMAT(`created_at`, '%Y-%m-%d') AS `date`, COALESCE(SUM(base_refund), 0) AS `refund`
                FROM `invoice`
                WHERE `created_at` BETWEEN :date_from AND :date_to
                AND approved = 1
                AND status = :status
                GROUP BY `date`";

        $stmt = $pdo->prepare($query);
        $stmt->execute(
            [
                'status' => \Model_Invoice::STATUS_REFUNDED,
                'date_from' => date('Y-m-d', $time_from),
                'date_to' => date('Y-m-d', $time_to),
            ]
        );

        $results = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        return $this->_genFlotArray($results, $time_from, $time_to);
    }

    public function getIncome($data)
    {
        $time_from = strtotime('-1 month');
        $time_to = strtotime('+1 day');

        if (isset($data['date_from']) && !empty($data['date_from'])) {
            $time_from = strtotime($data['date_from']);
        }

        if (isset($data['date_to']) && !empty($data['date_to'])) {
            $time_to = strtotime($data['date_to']);
        }

        $pdo = $this->di['pdo'];

        $query = "SELECT DATE_FORMAT(`paid_at`, '%Y-%m-%d') AS `date`, (COALESCE(SUM(base_income), 0) - COALESCE(SUM(base_refund), 0)) AS `income`
                FROM `invoice`
                WHERE `paid_at` BETWEEN :date_from AND :date_to
                AND approved = 1
                AND status = :status
                GROUP BY `date`";

        $stmt = $pdo->prepare($query);
        $stmt->execute(
            [
                'status' => \Model_Invoice::STATUS_PAID,
                'date_from' => date('Y-m-d', $time_from),
                'date_to' => date('Y-m-d', $time_to),
            ]
        );

        $results = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

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
        $pdo = $this->di['pdo'];
        $stmt = $pdo->prepare($q);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
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
        $pdo = $this->di['pdo'];
        $stmt = $pdo->prepare($q);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    private function _getDateInterval($data)
    {
        $time_from = strtotime('-1 month');
        $time_to = strtotime('+1 day');

        if (isset($data['date_from']) && !empty($data['date_from'])) {
            $time_from = strtotime($data['date_from']);
        }

        if (isset($data['date_to']) && !empty($data['date_to'])) {
            $time_to = strtotime($data['date_to']);
        }

        return [$time_from, $time_to];
    }

    public function getTableStats($table, $data = [])
    {
        $time_from = strtotime('-1 month');
        $time_to = strtotime('+1 day');

        if (isset($data['date_from']) && !empty($data['date_from'])) {
            $time_from = strtotime($data['date_from']);
        }

        if (isset($data['date_to']) && !empty($data['date_to'])) {
            $time_to = strtotime($data['date_to']);
        }

        $pdo = $this->di['pdo'];

        $query = "SELECT DATE_FORMAT(`created_at`, '%Y-%m-%d') AS `date`, COUNT(1) AS `count`
                FROM `$table`
                WHERE `created_at` BETWEEN :date_from AND :date_to
                GROUP BY `date`";

        $stmt = $pdo->prepare($query);
        $stmt->execute(
            [
                'date_from' => date('Y-m-d', $time_from),
                'date_to' => date('Y-m-d', $time_to),
            ]
        );

        $results = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

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
