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

    public function getSummary(): array
    {
        $stats = [];

        $dbal = $this->di['em']->getConnection();
        $boundaries = $this->getDayBoundaries();

        $total_query = 'SELECT COUNT(1) FROM :table';
        $yeste_query = 'SELECT COUNT(1) FROM :table WHERE created_at >= :yesterday_start AND created_at < :today_start';
        $today_query = 'SELECT COUNT(1) FROM :table WHERE created_at >= :today_start AND created_at < :tomorrow_start';
        $month_query = 'SELECT COUNT(1) FROM :table WHERE created_at >= :month_start AND created_at < :next_month_start';
        $last_month_query = 'SELECT COUNT(1) FROM :table WHERE created_at >= :last_month_start AND created_at < :month_start';

        // client stats
        $table = 'client';
        $result = $dbal->executeQuery(str_replace(':table', $table, $total_query));
        $stats['clients_total'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $today_query), $boundaries);
        $stats['clients_today'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $yeste_query), $boundaries);
        $stats['clients_yesterday'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $month_query), $boundaries);
        $stats['clients_this_month'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $last_month_query), $boundaries);
        $stats['clients_last_month'] = $result->fetchOne();

        // orders stats
        $table = 'client_order';
        $result = $dbal->executeQuery(str_replace(':table', $table, $total_query));
        $stats['orders_total'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $today_query), $boundaries);
        $stats['orders_today'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $yeste_query), $boundaries);
        $stats['orders_yesterday'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $month_query), $boundaries);
        $stats['orders_this_month'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $last_month_query), $boundaries);
        $stats['orders_last_month'] = $result->fetchOne();

        // invoice stats
        $table = 'invoice';
        $result = $dbal->executeQuery(str_replace(':table', $table, $total_query));
        $stats['invoices_total'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $today_query), $boundaries);
        $stats['invoices_today'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $yeste_query), $boundaries);
        $stats['invoices_yesterday'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $month_query), $boundaries);
        $stats['invoices_this_month'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $last_month_query), $boundaries);
        $stats['invoices_last_month'] = $result->fetchOne();

        // ticket stats
        $table = 'support_ticket';
        $result = $dbal->executeQuery(str_replace(':table', $table, $total_query));
        $stats['tickets_total'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $today_query), $boundaries);
        $stats['tickets_today'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $yeste_query), $boundaries);
        $stats['tickets_yesterday'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $month_query), $boundaries);
        $stats['tickets_this_month'] = $result->fetchOne();

        $result = $dbal->executeQuery(str_replace(':table', $table, $last_month_query), $boundaries);
        $stats['tickets_last_month'] = $result->fetchOne();

        return $stats;
    }

    /**
     * Deposit (Add funds) top-ups are balance liability, not revenue: the
     * same external cash must not count both when the deposit is paid and
     * again when the balance it created pays a service invoice.
     *
     * Pure deposit invoices contribute nothing. Mixed invoices (a draft
     * deposit proforma that gained service lines before issuance) contribute
     * only their service portion. Deposit lines are never taxed and every
     * conversion is a flat rate, so the service share is exactly the stored
     * base amount times the service item share of the invoice subtotal.
     * Deriving the share from the stored base itself (instead of re-applying
     * a rate) keeps income and refunds on the same basis through every
     * recalculation, even after the currency rate moves. Refunds split
     * through the credit-note documents, whose mirrored lines keep the
     * refunded lines' types (synthetic custom lines carry no attribution and
     * are skipped): the linked ratio applies only when the linked notes cover
     * the whole refund, pure deposits contribute nothing, a linked-less
     * refund that covers the whole invoice is split by the original lines,
     * and anything else stays explicitly unresolved (NULL) rather than
     * guessing. Every allocation is rounded to cents, matching income.
     */
    private function hasDepositLinesClause(): string
    {
        return "EXISTS (SELECT 1 FROM invoice_item di WHERE di.invoice_id = invoice.id AND di.type = 'deposit')";
    }

    private function hasServiceLinesClause(): string
    {
        return "EXISTS (SELECT 1 FROM invoice_item si WHERE si.invoice_id = invoice.id AND (si.type <> 'deposit' OR si.type IS NULL))";
    }

    /**
     * SUM(price × quantity) over an invoice's own lines, in invoice currency.
     */
    private function ownLinesTotalExpr(bool $serviceOnly): string
    {
        $filter = $serviceOnly ? "AND (ii.type <> 'deposit' OR ii.type IS NULL)" : '';

        return "(SELECT SUM(ii.price * COALESCE(ii.quantity, 1)) FROM invoice_item ii WHERE ii.invoice_id = invoice.id {$filter})";
    }

    /**
     * SUM(price × quantity) over an invoice's credit-note lines, in invoice
     * currency. Credit lines mirror the refunded lines' types with negated
     * prices, so deposit refunds are directly attributable. Synthetic custom
     * lines carry no type attribution, so both totals skip them: a custom
     * refund falls back to the fully-backed reconstruction, if any.
     */
    private function creditLinesTotalExpr(bool $serviceOnly): string
    {
        $filter = $serviceOnly
            ? "AND (ci.type IS NULL OR (ci.type <> 'deposit' AND ci.type <> 'custom'))"
            : "AND ci.type <> 'custom'";

        return "(SELECT SUM(ci.price * COALESCE(ci.quantity, 1)) FROM invoice cn JOIN invoice_item ci ON ci.invoice_id = cn.id WHERE cn.credit_note_for_invoice_id = invoice.id {$filter})";
    }

    private function serviceIncomeExpr(): string
    {
        $dep = $this->hasDepositLinesClause();
        $svc = $this->hasServiceLinesClause();
        $svcSub = $this->ownLinesTotalExpr(true);
        $totSub = $this->ownLinesTotalExpr(false);

        return "(CASE WHEN NOT ({$dep}) THEN COALESCE(invoice.base_income, 0) WHEN ({$svc}) THEN ROUND(COALESCE(invoice.base_income, 0) * COALESCE(({$svcSub}), 0) / NULLIF(({$totSub}), 0), 2) ELSE 0 END)";
    }

    private function serviceRefundExpr(bool $taxAware): string
    {
        $dep = $this->hasDepositLinesClause();
        $svc = $this->hasServiceLinesClause();
        $svcSub = $this->ownLinesTotalExpr(true);
        $totSub = $this->ownLinesTotalExpr(false);
        $cnSvcSub = $this->creditLinesTotalExpr(true);
        $cnTotSub = $this->creditLinesTotalExpr(false);
        $base = $taxAware
            ? 'CASE WHEN invoice.refund IS NOT NULL AND invoice.base_refund_tax IS NULL THEN NULL ELSE invoice.base_refund END'
            : 'invoice.base_refund';
        // Coverage gate: the linked ratio only applies when the linked notes
        // account for the whole refund (compared in invoice currency via the
        // income-implied rate, so no stored rate is needed). Anything less is
        // an incomplete history: the remainder stays unresolved (NULL).
        $coverage = "COALESCE(({$cnTotSub}), 0) <> 0 AND ABS(ABS(({$cnTotSub})) - ABS(({$base}) * ({$totSub}) / NULLIF(invoice.base_income, 0))) <= 0.02";

        return "(CASE WHEN NOT ({$dep}) THEN ROUND(COALESCE({$base}, 0), 2)"
            . " WHEN ({$dep}) AND NOT ({$svc}) THEN 0"
            . " WHEN ({$coverage}) THEN ROUND(({$base}) * COALESCE(({$cnSvcSub}), 0) / NULLIF(({$cnTotSub}), 0), 2)"
            . " WHEN ({$base}) IS NOT NULL AND ABS(COALESCE(({$base}), 0) - COALESCE(invoice.base_income, 0)) <= 0.02 THEN ROUND(({$base}) * COALESCE(({$svcSub}), 0) / NULLIF(({$totSub}), 0), 2)"
            . ' ELSE NULL END)';
    }

    public function getSummaryIncome(): array
    {
        $stats = [];

        $dbal = $this->di['em']->getConnection();
        $boundaries = $this->getDayBoundaries();

        $income = $this->serviceIncomeExpr();
        $refund = $this->serviceRefundExpr(false);
        $base = "SELECT (COALESCE(SUM({$income}), 0) - COALESCE(SUM({$refund}), 0)) AS income FROM invoice WHERE issued = true AND (status = 'paid' OR status = 'refunded')";
        $total_query = $base;
        $yeste_query = $base . ' AND paid_at >= :yesterday_start AND paid_at < :today_start';
        $today_query = $base . ' AND paid_at >= :today_start AND paid_at < :tomorrow_start';
        $month_query = $base . ' AND paid_at >= :month_start AND paid_at < :next_month_start';
        $last_month_query = $base . ' AND paid_at >= :last_month_start AND paid_at < :month_start';

        $result = $dbal->executeQuery($total_query);
        $stats['total'] = $result->fetchOne();

        $result = $dbal->executeQuery($today_query, $boundaries);
        $stats['today'] = $result->fetchOne();

        $result = $dbal->executeQuery($yeste_query, $boundaries);
        $stats['yesterday'] = $result->fetchOne();

        $result = $dbal->executeQuery($month_query, $boundaries);
        $stats['this_month'] = $result->fetchOne();

        $result = $dbal->executeQuery($last_month_query, $boundaries);
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
        $dbal = $this->di['em']->getConnection();

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

        $dbal = $this->di['em']->getConnection();

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
        $dbal = $this->di['em']->getConnection();

        // `refund` stays net of tax and covers classified rows only: countIncome()
        // always stores base_refund_tax alongside base_refund, so a refund with a
        // NULL tax is a legacy row the backfill has not classified yet. Those are
        // excluded here (their stored base still mixes the old gross basis in) and
        // counted in refund_tax_unresolved instead. `income` is the raw
        // SUM(base_income) over all rows - base_income never mixed bases, so it
        // needs no such filter. Gross cash of classified refunds is
        // refund + refund_tax.
        $income = $this->serviceIncomeExpr();
        $refund = $this->serviceRefundExpr(true);
        $query = "SELECT COALESCE(SUM({$refund}), 0) AS refund, COALESCE(SUM({$income}), 0) AS income, COALESCE(SUM(base_refund_tax), 0) AS refund_tax, (COALESCE(SUM({$refund}), 0) + COALESCE(SUM(base_refund_tax), 0)) AS refund_gross, COALESCE(SUM(CASE WHEN refund IS NOT NULL AND base_refund_tax IS NULL THEN 1 ELSE 0 END), 0) AS refund_tax_unresolved
                FROM invoice
                WHERE issued = true
                AND (status = :status1 OR status = :status2)
                ";

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

        $dbal = $this->di['em']->getConnection();

        // Truncates the 'Y-m-d H:i:s' column down to its 'Y-m-d' prefix for grouping - a portable
        // stand-in for MySQL's DATE_FORMAT(), which PostgreSQL and SQLite don't have.
        $date = SqlExpr::dateOnly($dbal, 'created_at');
        $refund = $this->serviceRefundExpr(false);
        $query = "SELECT {$date} AS date, COALESCE(SUM({$refund}), 0) AS refund
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

        $dbal = $this->di['em']->getConnection();

        // Truncates the 'Y-m-d H:i:s' column down to its 'Y-m-d' prefix for grouping - a portable
        // stand-in for MySQL's DATE_FORMAT(), which PostgreSQL and SQLite don't have.
        $date = SqlExpr::dateOnly($dbal, 'paid_at');
        $income = $this->serviceIncomeExpr();
        $refund = $this->serviceRefundExpr(false);
        $query = "SELECT {$date} AS date, (COALESCE(SUM({$income}), 0) - COALESCE(SUM({$refund}), 0)) AS income
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
        $dbal = $this->di['em']->getConnection();
        $result = $dbal->executeQuery($q);

        return $result->fetchAllKeyValue();
    }

    public function getSalesByCountry($data)
    {
        $limit = (int) ($data['limit'] ?? 10);
        $dep = $this->hasDepositLinesClause();
        $svc = $this->hasServiceLinesClause();
        // Mixed invoices hold a real sale, so only pure deposit top-ups are
        // excluded from the sales count.
        $q = "
            SELECT buyer_country, COUNT(id) as sales
            FROM invoice
            WHERE status = 'paid'
            AND NOT (({$dep}) AND NOT ({$svc}))
            GROUP BY buyer_country
            ORDER BY sales DESC
            LIMIT $limit
        ";
        $dbal = $this->di['em']->getConnection();
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

        $dbal = $this->di['em']->getConnection();

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
