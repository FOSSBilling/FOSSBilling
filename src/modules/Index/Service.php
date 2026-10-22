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

use Box\Mod\Client\Entity\Client;
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

    /**
     * Get dashboard data for a client.
     *
     * This method aggregates all data needed for the client dashboard into a single
     * response. It fetches profile information, ticket statistics, invoice statistics,
     * order statistics, and recent items.
     *
     * @param Client $client The client entity to get dashboard data for
     *
     * @return array Dashboard data containing profile, tickets, invoices, orders, recent_orders, and recent_tickets
     */
    public function getDashboardData(Client $client): array
    {
        $data['client_id'] = (int) $client->getId();

        return [
            'profile' => $this->getProfile($client),
            'tickets' => $this->getTicketsData($data),
            'invoices' => $this->getInvoicesData($data),
            'orders' => $this->getOrdersData($data),
            'recent_orders' => $this->getRecentOrders($data),
            'recent_tickets' => $this->getRecentTickets($data),
        ];
    }

    private function getProfile(Client $client): array
    {
        $clientService = $this->di['mod_service']('client');

        return $clientService->toApiArray($client, true);
    }

    private function getTicketsData(array $data): array
    {
        $sql = 'SELECT status, COUNT(*) as total
                 FROM support_ticket
                 WHERE client_id = :client_id
                 GROUP BY status';

        $results = $this->di['em']->getConnection()->fetchAllAssociative($sql, $data);

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
        $sql = 'SELECT st.id
                 FROM support_ticket st
                 WHERE st.client_id = :client_id
                 ORDER BY st.updated_at DESC
                 LIMIT 5';

        $rows = $this->di['em']->getConnection()->fetchAllAssociative($sql, $data);

        $ids = array_column($rows, 'id');

        if (empty($ids)) {
            return [];
        }

        $supportService = $this->di['mod_service']('support');

        return $supportService->getBatchForApi($ids, false, $this->di['loggedin_client']);
    }

    private function getInvoicesData(array $data): array
    {
        $sql = 'SELECT status, COUNT(*) as total
                 FROM invoice
                 WHERE client_id = :client_id
                 AND issued = true
                 GROUP BY status';

        $results = $this->di['em']->getConnection()->fetchAllAssociative($sql, $data);

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

        $results = $this->di['em']->getConnection()->fetchAllAssociative($sql, $data);

        $counts = [
            'total' => 0,
            'active' => 0,
            'expiring' => 0,
            'needs_review' => 0,
        ];

        foreach ($results as $row) {
            // Sum total orders across all statuses
            $counts['total'] += (int) $row['total'];

            // Only track counts for expected statuses to avoid dynamic keys
            if ($row['status'] === 'active') {
                $counts['active'] = (int) $row['total'];
            }
        }

        $orderService = $this->di['mod_service']('order');
        [$renewalsQuery, $bindings] = $orderService->getSoonExpiringActiveOrdersQuery(
            ['client_id' => $data['client_id'], 'hide_addons' => true],
            sort: false,
        );
        $counts['expiring'] = (int) $this->di['em']->getConnection()->fetchOne('SELECT COUNT(*) FROM (' . $renewalsQuery . ') dashboard_renewals', $bindings);
        [$reviewQuery, $reviewBindings] = $orderService->getReviewOrdersQuery((int) $data['client_id'], sort: false);
        $counts['needs_review'] = (int) $this->di['em']->getConnection()->fetchOne('SELECT COUNT(*) FROM (' . $reviewQuery . ') dashboard_review', $reviewBindings);

        return $counts;
    }

    private function getRecentOrders(array $data): array
    {
        $sql = 'SELECT co.id
                 FROM client_order co
                 WHERE co.client_id = :client_id
                 AND co.group_master = 1
                 ORDER BY co.updated_at DESC
                 LIMIT 5';

        $rows = $this->di['em']->getConnection()->fetchAllAssociative($sql, $data);

        $ids = array_column($rows, 'id');

        if (empty($ids)) {
            return [];
        }

        $orderService = $this->di['mod_service']('order');

        return $orderService->getBatchForApi($ids, $this->di['loggedin_client']);
    }
}
