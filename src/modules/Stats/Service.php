<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Stats;

use Box\Mod\Invoice\Entity\Invoice;
use FOSSBilling\Core\Container\InjectionAwareInterface;
use FOSSBilling\Core\Doctrine\SqlExpr;

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

    public function getModulePermissions(): array
    {
        return [
            'view' => [
                'type' => 'bool',
                'display_name' => __trans('View statistics'),
                'description' => __trans('Allows the staff member to view system statistics and charts.'),
            ],
        ];
    }

    /**
     * Day/month range boundaries for "today"/"yesterday"/"this month"/"last month" stats,
     * expressed as portable `column >= :start AND column < :end` params rather than the
     * MySQL-only DATE_FORMAT()/CURDATE()/DATE_SUB() functions this replaced. Computed once and
     * reused across every table's queries in a call.
     *
     * @return array<string, string>
     */
    private function getDayBoundaries(): array
    {
        $todayStart = new \DateTimeImmutable('today');
        $monthStart = $todayStart->modify('first day of this month');

        $format = static fn (\DateTimeImmutable $date): string => $date->format('Y-m-d H:i:s');

        return [
            'yesterday_start' => $format($todayStart->modify('-1 day')),
            'today_start' => $format($todayStart),
            'tomorrow_start' => $format($todayStart->modify('+1 day')),
            'last_month_start' => $format($monthStart->modify('-1 month')),
            'month_start' => $format($monthStart),
            'next_month_start' => $format($monthStart->modify('+1 month')),
        ];
    }

    /**
     * Conditions use plain date comparisons so the same aggregates work on every driver.
     * The column names are internal constants, never request input.
     *
     * @return array<string, string>
     */
    private function getPeriodConditions(string $column): array
    {
        return [
            'today' => "{$column} >= :today_start AND {$column} < :tomorrow_start",
            'yesterday' => "{$column} >= :yesterday_start AND {$column} < :today_start",
            'this_month' => "{$column} >= :month_start AND {$column} < :next_month_start",
            'last_month' => "{$column} >= :last_month_start AND {$column} < :month_start",
        ];
    }

    public function getSummary(): array
    {
        $stats = [];
        $boundaries = $this->getDayBoundaries();
        $columns = ['COUNT(1) AS total'];
        foreach ($this->getPeriodConditions('created_at') as $period => $condition) {
            $columns[] = "COUNT(CASE WHEN {$condition} THEN 1 END) AS {$period}";
        }
        $select = implode(', ', $columns);

        foreach (['clients' => 'client', 'orders' => 'client_order', 'invoices' => 'invoice', 'tickets' => 'support_ticket'] as $prefix => $table) {
            $row = $this->di['dbal']->executeQuery("SELECT {$select} FROM {$table}", $boundaries)->fetchAssociative() ?: [];
            foreach ($row as $period => $count) {
                $stats["{$prefix}_{$period}"] = $count;
            }
        }

        return $stats;
    }

    public function getSummaryIncome(): array
    {
        $columns = ['COALESCE(SUM(base_income), 0) - COALESCE(SUM(base_refund), 0) AS total'];
        foreach ($this->getPeriodConditions('paid_at') as $period => $condition) {
            // Aggregate each amount separately to retain the original NULL and refund semantics.
            $columns[] = "COALESCE(SUM(CASE WHEN {$condition} THEN base_income END), 0)"
                . " - COALESCE(SUM(CASE WHEN {$condition} THEN base_refund END), 0) AS {$period}";
        }
        $select = implode(', ', $columns);

        return $this->di['dbal']->executeQuery(
            "SELECT {$select} FROM invoice WHERE issued = true AND (status = 'paid' OR status = 'refunded')",
            $this->getDayBoundaries(),
        )->fetchAssociative() ?: [];
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
        $dbal = $this->di['dbal'];

        $query = "SELECT p.id, p.title, COUNT(o.id) as orders
                FROM client_order o
                RIGHT JOIN product p ON(p.id = o.product_id)
                WHERE o.status = 'active'
                GROUP BY o.product_id
                ORDER BY orders DESC
                ";

        $result = $dbal->executeQuery($query);

        return $result->fetchAllAssociative();
    }

    public function getProductSales($data)
    {
        [$time_from, $time_to] = $this->_getDateInterval($data);

        $dbal = $this->di['dbal'];

        $query = 'SELECT title, COUNT(product_id) as sales
                FROM client_order
                WHERE status = :status
                AND created_at BETWEEN :date_from AND :date_to
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

        $query = 'SELECT COALESCE(SUM(base_refund), 0) AS refund, COALESCE(SUM(base_income), 0) AS income
                FROM invoice
                WHERE issued = true
                AND (status = :status1 OR status = :status2)
                ';

        $result = $dbal->executeQuery($query, [
            'status1' => Invoice::STATUS_PAID,
            'status2' => Invoice::STATUS_REFUNDED,
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

        // Truncates the 'Y-m-d H:i:s' column down to its 'Y-m-d' prefix for grouping - a portable
        // stand-in for MySQL's DATE_FORMAT(), which PostgreSQL and SQLite don't have.
        $date = SqlExpr::dateOnly($dbal, 'created_at');
        $query = "SELECT {$date} AS date, COALESCE(SUM(base_refund), 0) AS refund
                FROM invoice
                WHERE created_at BETWEEN :date_from AND :date_to
                AND issued = true
                AND (status = :refunded OR (status = :paid AND base_refund > 0))
                GROUP BY date";

        $result = $dbal->executeQuery($query, [
            'refunded' => Invoice::STATUS_REFUNDED,
            'paid' => Invoice::STATUS_PAID,
            'date_from' => date('Y-m-d', $time_from),
            'date_to' => date('Y-m-d', $time_to),
        ]);

        $results = $result->fetchAllKeyValue();

        return $this->_genFlotArrayFloat($results, $time_from, $time_to);
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

        // Truncates the 'Y-m-d H:i:s' column down to its 'Y-m-d' prefix for grouping - a portable
        // stand-in for MySQL's DATE_FORMAT(), which PostgreSQL and SQLite don't have.
        $date = SqlExpr::dateOnly($dbal, 'paid_at');
        $query = "SELECT {$date} AS date, (COALESCE(SUM(base_income), 0) - COALESCE(SUM(base_refund), 0)) AS income
                FROM invoice
                WHERE paid_at BETWEEN :date_from AND :date_to
                AND issued = true
                AND (status = :paid OR status = :refunded)
                GROUP BY date";

        $result = $dbal->executeQuery($query, [
            'paid' => Invoice::STATUS_PAID,
            'refunded' => Invoice::STATUS_REFUNDED,
            'date_from' => date('Y-m-d', $time_from),
            'date_to' => date('Y-m-d', $time_to),
        ]);

        $results = $result->fetchAllKeyValue();

        return $this->_genFlotArrayFloat($results, $time_from, $time_to);
    }

    public function getClientCountries($data)
    {
        $limit = (int) ($data['limit'] ?? 10);
        $q = "
            SELECT country, COUNT(id) as clients
            FROM client
            GROUP BY country
            ORDER BY clients DESC
            LIMIT $limit
        ";
        $dbal = $this->di['dbal'];
        $result = $dbal->executeQuery($q);

        return $result->fetchAllKeyValue();
    }

    public function getSalesByCountry($data)
    {
        $limit = (int) ($data['limit'] ?? 10);
        $q = "
            SELECT buyer_country, COUNT(id) as sales
            FROM invoice
            WHERE status = 'paid'
            GROUP BY buyer_country
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
        $allowedTables = ['client_order', 'client', 'invoice', 'support_ticket'];
        if (!in_array($table, $allowedTables, true)) {
            throw new \FOSSBilling\Core\Exception\BaseException('Invalid table name: :table', [':table' => $table]);
        }
        $time_from = strtotime('-1 month');
        $time_to = strtotime('+1 day');

        if (isset($data['date_from']) && !empty($data['date_from'])) {
            $time_from = strtotime((string) $data['date_from']);
        }

        if (isset($data['date_to']) && !empty($data['date_to'])) {
            $time_to = strtotime((string) $data['date_to']);
        }

        $dbal = $this->di['dbal'];

        // Truncates the 'Y-m-d H:i:s' column down to its 'Y-m-d' prefix for grouping - a portable
        // stand-in for MySQL's DATE_FORMAT(), which PostgreSQL and SQLite don't have. $table is
        // safe to interpolate: it was checked against $allowedTables above.
        $date = SqlExpr::dateOnly($dbal, 'created_at');
        $query = "SELECT {$date} AS date, COUNT(1) AS count
                FROM $table
                WHERE created_at BETWEEN :date_from AND :date_to
                GROUP BY date";

        $result = $dbal->executeQuery($query, [
            'date_from' => date('Y-m-d', $time_from),
            'date_to' => date('Y-m-d', $time_to),
        ]);

        $results = $result->fetchAllKeyValue();

        return $this->_genFlotArrayInt($results, $time_from, $time_to);
    }

    /**
     * @return array<int, array{0:int, 1:int}>
     */
    private function _genFlotArrayInt(array $results, int $time_from, int $time_to): array
    {
        $data = [];
        do {
            $time_from = strtotime('+1 day', $time_from);
            $dom = date('Y-m-d', $time_from);
            $data[] = [$time_from * 1000, (int) ($results[$dom] ?? 0)];
        } while ($time_to > $time_from);
        array_pop($data);

        return $data;
    }

    /**
     * @return array<int, array{0:int, 1:float}>
     */
    private function _genFlotArrayFloat(array $results, int $time_from, int $time_to): array
    {
        $data = [];
        do {
            $time_from = strtotime('+1 day', $time_from);
            $dom = date('Y-m-d', $time_from);
            $data[] = [$time_from * 1000, round((float) ($results[$dom] ?? 0), 2)];
        } while ($time_to > $time_from);
        array_pop($data);

        return $data;
    }
}
