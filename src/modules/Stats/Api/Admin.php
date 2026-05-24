<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * Statistics retrieval.
 */

namespace Box\Mod\Stats\Api;

class Admin extends \Api_Abstract
{
    /**
     * Return summary of your system.
     *
     * @return array
     */
    public function get_summary()
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('stats', 'view');

        return $this->getService()->getSummary();
    }

    /**
     * Return income statistics.
     *
     * @return array
     */
    public function get_summary_income()
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('stats', 'view');

        return $this->getService()->getSummaryIncome();
    }

    /**
     * Get order statuses.
     *
     * @return array
     */
    public function get_orders_statuses($data)
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('stats', 'view');

        return $this->getService()->getOrdersStatuses($data);
    }

    /**
     * Get active orders stats grouped by products.
     *
     * @return array
     */
    public function get_product_summary($data)
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('stats', 'view');

        return $this->getService()->getProductSummary($data);
    }

    /**
     * Get product sales.
     *
     * @return array
     */
    public function get_product_sales($data)
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('stats', 'view');

        return $this->getService()->getProductSales($data);
    }

    /**
     * Get income and refunds statistics.
     *
     * @return array
     */
    public function get_income_vs_refunds($data)
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('stats', 'view');

        return $this->getService()->incomeAndRefundStats($data);
    }

    /**
     * Return refunds by day. If no timespan is selected method returns
     * previous month statistics.
     *
     * @optional string $date_from - day since refunds are counted
     * @optional string $date_to - day until refunds are counted
     *
     * @return array
     */
    public function get_refunds($data)
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('stats', 'view');

        return $this->getService()->getRefunds($data);
    }

    /**
     * Return income by day. If no timespan is selected method returns
     * previous month statistics.
     *
     * @optional string $date_from - day since income are counted
     * @optional string $date_to - day until income are counted
     *
     * @return array
     */
    public function get_income($data)
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('stats', 'view');

        return $this->getService()->getIncome($data);
    }

    /**
     * Return statistics for orders.
     *
     * @optional string $date_from - day since income are counted
     * @optional string $date_to - day until income are counted
     *
     * @return array
     */
    public function get_orders($data)
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('stats', 'view');

        return $this->getService()->getTableStats('client_order', $data);
    }

    /**
     * Return clients signups by day. If no timespan is selected method returns
     * previous month statistics.
     *
     * @optional string $date_from - day since income are counted
     * @optional string $date_to - day until income are counted
     *
     * @return array
     */
    public function get_clients($data)
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('stats', 'view');

        return $this->getService()->getTableStats('client', $data);
    }

    /**
     * Get number of clients in country.
     *
     * @return array
     */
    public function client_countries($data)
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('stats', 'view');

        return $this->getService()->getClientCountries($data);
    }

    /**
     * Get number of sales by country.
     *
     * @return array
     */
    public function sales_countries($data)
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('stats', 'view');

        return $this->getService()->getSalesByCountry($data);
    }

    /**
     * Return invoices by day. If no timespan is selected method returns
     * previous month statistics.
     *
     * @optional string $date_from - day since income are counted
     * @optional string $date_to - day until income are counted
     *
     * @return array
     */
    public function get_invoices($data)
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('stats', 'view');

        return $this->getService()->getTableStats('invoice', $data);
    }

    /**
     * Return support tickets by day. If no timespan is selected method returns
     * previous month statistics.
     *
     * @optional string $date_from - day since income are counted
     * @optional string $date_to - day until income are counted
     *
     * @return array
     */
    public function get_tickets($data)
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('stats', 'view');

        return $this->getService()->getTableStats('support_ticket', $data);
    }
}
