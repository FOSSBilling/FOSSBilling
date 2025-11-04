<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Client\Repository;

use Box\Mod\Client\Entity\Client;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use FOSSBilling\Exception\Domain\EntityNotFound;

/**
 * Repository for Client entity.
 *
 * Handles all database queries for clients using Doctrine QueryBuilder.
 *
 * @extends EntityRepository<Client>
 */
class ClientRepository extends EntityRepository
{
    /**
     * Build QueryBuilder for searching clients with filters.
     *
     * Supports filtering by status, search term, email, company, etc.
     * Always returns a QueryBuilder for pagination compatibility.
     *
     * @param array<string, mixed> $data Filter parameters (status, search, id, email, etc.)
     */
    public function getSearchQueryBuilder(array $data): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c');

        // Status filter
        if (!empty($data['status'])) {
            $qb->andWhere('c.status = :status')
               ->setParameter('status', $data['status']);
        }

        // Search filter (searches across multiple fields)
        if (!empty($data['search'])) {
            $search = '%' . $data['search'] . '%';
            $qb->andWhere(
                $qb->expr()->orX(
                    'c.first_name LIKE :search',
                    'c.last_name LIKE :search',
                    'c.email LIKE :search',
                    'c.company LIKE :search',
                    'c.id LIKE :searchExact'
                )
            )
            ->setParameter('search', $search)
            ->setParameter('searchExact', $data['search']); // For exact ID match
        }

        // ID filter
        if (!empty($data['id'])) {
            $qb->andWhere('c.id = :id')
               ->setParameter('id', (int) $data['id']);
        }

        // Email filter
        if (!empty($data['email'])) {
            $qb->andWhere('c.email = :email')
               ->setParameter('email', $data['email']);
        }

        // Company filter
        if (!empty($data['company'])) {
            $qb->andWhere('c.company LIKE :company')
               ->setParameter('company', '%' . $data['company'] . '%');
        }

        // First name filter
        if (!empty($data['first_name'])) {
            $qb->andWhere('c.first_name LIKE :first_name')
               ->setParameter('first_name', '%' . $data['first_name'] . '%');
        }

        // Last name filter
        if (!empty($data['last_name'])) {
            $qb->andWhere('c.last_name LIKE :last_name')
               ->setParameter('last_name', '%' . $data['last_name'] . '%');
        }

        // Group filter
        if (!empty($data['group_id'])) {
            $qb->andWhere('c.client_group_id = :group_id')
               ->setParameter('group_id', (int) $data['group_id']);
        }

        // Created date range filters
        if (!empty($data['date_from'])) {
            $qb->andWhere('c.created_at >= :date_from')
               ->setParameter('date_from', $data['date_from']);
        }

        if (!empty($data['date_to'])) {
            $qb->andWhere('c.created_at <= :date_to')
               ->setParameter('date_to', $data['date_to']);
        }

        // Deterministic ordering
        $orderBy = $data['order_by'] ?? 'id';
        $orderDir = !empty($data['order']) && strtoupper($data['order']) === 'DESC' ? 'DESC' : 'ASC';

        $qb->orderBy('c.' . $orderBy, $orderDir);

        // Fallback ordering for consistency when primary sort field has duplicates
        if ($orderBy !== 'id') {
            $qb->addOrderBy('c.id', 'ASC');
        }

        return $qb;
    }

    /**
     * Find client by ID or throw exception.
     *
     * @param int $id Client ID
     *
     * @throws EntityNotFound When client not found
     */
    public function findOneByIdOrFail(int $id): Client
    {
        $client = $this->find($id);

        if (!$client) {
            throw new EntityNotFound(Client::class, $id);
        }

        return $client;
    }

    /**
     * Find client by email address.
     *
     * @param string $email Client email
     */
    public function findOneByEmail(string $email): ?Client
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Get client pairs for dropdowns (id => full name).
     *
     * Returns a simple array suitable for HTML select options.
     *
     * @param array<string, mixed> $filters Optional filters (status, etc.)
     *
     * @return array<int, string> Array of [id => "First Last"]
     */
    public function getPairs(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c.id', 'c.first_name', 'c.last_name');

        // Apply status filter if provided
        if (!empty($filters['status'])) {
            $qb->andWhere('c.status = :status')
               ->setParameter('status', $filters['status']);
        }

        // Apply group filter if provided
        if (!empty($filters['group_id'])) {
            $qb->andWhere('c.client_group_id = :group_id')
               ->setParameter('group_id', (int) $filters['group_id']);
        }

        // Order by name
        $qb->orderBy('c.first_name', 'ASC')
           ->addOrderBy('c.last_name', 'ASC')
           ->addOrderBy('c.id', 'ASC'); // Fallback for duplicate names

        $results = $qb->getQuery()->getResult();

        $pairs = [];
        foreach ($results as $row) {
            $fullName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            $pairs[$row['id']] = !empty($fullName) ? $fullName : 'Client #' . $row['id'];
        }

        return $pairs;
    }

    /**
     * Check if a client with given email already exists.
     *
     * @param string $email Email to check
     */
    public function emailExists(string $email): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.email = :email')
            ->setParameter('email', $email);

        return ((int) $qb->getQuery()->getSingleScalarResult()) > 0;
    }

    /**
     * Get client count by status.
     *
     * Returns an array with counts for each status.
     *
     * @return array<string, int>
     */
    public function getCountByStatus(): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c.status', 'COUNT(c.id) as counter')
            ->groupBy('c.status');

        $results = $qb->getQuery()->getResult();

        $counts = [
            Client::STATUS_ACTIVE => 0,
            Client::STATUS_SUSPENDED => 0,
            Client::STATUS_CANCELED => 0,
        ];

        foreach ($results as $row) {
            $counts[$row['status']] = (int) $row['counter'];
        }

        $counts['total'] = array_sum($counts);

        return $counts;
    }
}
