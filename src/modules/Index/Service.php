<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Index;

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

    public function getDashboardData(\Model_Client $client): array
    {
        $data['client_id'] = $client->id;

        return [
            'profile' => $this->getProfile($client),
            'tickets' => $this->getTicketsData($data),
            'invoices' => $this->getInvoicesData($data),
            'orders' => $this->getOrdersData($data),
            'recent_orders' => $this->getRecentOrders($data),
            'recent_tickets' => $this->getRecentTickets($data),
        ];
    }

    private function getProfile(\Model_Client $client): array
    {
        $clientService = $this->di['mod_service']('client');

        return $clientService->toApiArray($client, false);
    }

    private function getTicketsData(array $data): array
    {
        $sql = 'SELECT status, COUNT(*) as total
                 FROM support_ticket
                 WHERE client_id = :client_id
                 GROUP BY status';

        $results = $this->di['db']->getAll($sql, $data);

        $counts = [
            'total' => 0,
            'open' => 0,
            'on_hold' => 0,
            'closed' => 0,
        ];

        foreach ($results as $row) {
            $counts['total'] += (int) $row['total'];

            $status = (string) $row['status'];
            if (array_key_exists($status, $counts) && $status !== 'total') {
                $counts[$status] = (int) $row['total'];
            }
        }

        return $counts;
    }

    private function getRecentTickets(array $data): array
    {
        $sql = 'SELECT st.*
                 FROM support_ticket st
                 WHERE st.client_id = :client_id
                 ORDER BY st.updated_at DESC
                 LIMIT 5';

        $results = $this->di['db']->getAll($sql, $data);

        $supportService = $this->di['mod_service']('support');
        $tickets = [];

        foreach ($results as $row) {
            $ticket = $this->di['db']->getExistingModelById('SupportTicket', $row['id'], 'Ticket not found');
            $tickets[] = $supportService->toApiArray($ticket, false, $this->di['loggedin_client'], false);
        }

        return $tickets;
    }

    private function getInvoicesData(array $data): array
    {
        $sql = 'SELECT status, COUNT(*) as total
                 FROM invoice
                 WHERE client_id = :client_id
                 AND approved = 1
                 GROUP BY status';

        $results = $this->di['db']->getAll($sql, $data);

        $counts = [
            'total' => 0,
            'paid' => 0,
            'unpaid' => 0,
        ];

        foreach ($results as $row) {
            $counts['total'] += (int) $row['total'];

            $status = (string) $row['status'];
            if (in_array($status, ['paid', 'unpaid'], true)) {
                $counts[$status] = (int) $row['total'];
            }
        }

        return $counts;
    }

    private function getOrdersData(array $data): array
    {
        $sql = 'SELECT status, COUNT(*) as total
                 FROM client_order
                 WHERE client_id = :client_id
                 AND group_master = 1
                 GROUP BY status';

        $results = $this->di['db']->getAll($sql, $data);

        $counts = [
            'total' => 0,
            'active' => 0,
            'expiring' => 0,
        ];

        foreach ($results as $row) {
            // Sum total orders across all statuses
            $counts['total'] += (int) $row['total'];

            // Only track counts for expected statuses to avoid dynamic keys
            if ($row['status'] === 'active') {
                $counts['active'] = (int) $row['total'];
            }
        }

        $systemService = $this->di['mod_service']('system');
        $daysUntilExpiration = $systemService->getParamValue('invoice_issue_days_before_expire', 14);

        $expiringSql = "SELECT COUNT(*) as total
                        FROM client_order
                        WHERE client_id = :client_id
                        AND group_master = 1
                        AND status = 'active'
                        AND invoice_option = 'issue-invoice'
                        AND period IS NOT NULL
                        AND expires_at IS NOT NULL
                        AND unpaid_invoice_id IS NULL
                        AND DATEDIFF(expires_at, NOW()) <= :days";

        $expiringResult = $this->di['db']->getCell($expiringSql, [
            'client_id' => $data['client_id'],
            'days' => $daysUntilExpiration,
        ]);

        $counts['expiring'] = (int) $expiringResult;

        return $counts;
    }

    private function getRecentOrders(array $data): array
    {
        $sql = 'SELECT co.*
                 FROM client_order co
                 WHERE co.client_id = :client_id
                 AND co.group_master = 1
                 ORDER BY co.updated_at DESC
                 LIMIT 5';

        $results = $this->di['db']->getAll($sql, $data);

        $orderService = $this->di['mod_service']('order');
        $orders = [];

        foreach ($results as $row) {
            $order = $this->di['db']->getExistingModelById('ClientOrder', $row['id'], 'Order not found');
            $orders[] = $orderService->toApiArray($order, false, $this->di['loggedin_client']);
        }

        return $orders;
    }
}
