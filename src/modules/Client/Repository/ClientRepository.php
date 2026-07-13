<?php

declare(strict_types=1);

namespace Box\Mod\Client\Repository;

use Box\Mod\Client\Entity\Client;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class ClientRepository extends EntityRepository
{
    public function findOneByEmail(string $email): ?Client
    {
        $client = $this->findOneBy(['email' => $email]);

        return $client instanceof Client ? $client : null;
    }

    public function findOneByEmailAndActive(string $email): ?Client
    {
        $client = $this->findOneBy(['email' => $email, 'status' => Client::ACTIVE]);

        return $client instanceof Client ? $client : null;
    }

    public function findOneByApiToken(?string $apiToken): ?Client
    {
        if ($apiToken === null || $apiToken === '') {
            return null;
        }

        $client = $this->findOneBy(['apiToken' => $apiToken]);

        return $client instanceof Client ? $client : null;
    }

    /**
     * @return array<int, string>
     */
    public function getIdNamePairs(int $limit = 30): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT id, first_name, last_name, company FROM client ORDER BY id ASC LIMIT ' . $limit
        );

        $pairs = [];
        foreach ($rows as $row) {
            $name = trim($row['first_name'] . ' ' . $row['last_name']);
            if (!empty($row['company'])) {
                $name .= ' (' . $row['company'] . ')';
            }
            $pairs[(int) $row['id']] = $name;
        }

        return $pairs;
    }

    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c');

        $id = $data['id'] ?? null;
        $name = $data['name'] ?? null;
        $email = $data['email'] ?? null;
        $company = $data['company'] ?? null;
        $status = $data['status'] ?? null;
        $groupId = $data['group_id'] ?? null;
        $createdAt = $data['created_at'] ?? null;
        $dateFrom = $data['date_from'] ?? null;
        $dateTo = $data['date_to'] ?? null;
        $search = $data['search'] ?? null;

        if ($id !== null && $id !== '') {
            $qb->andWhere('c.id = :id')
                ->setParameter('id', (int) $id);
        }

        if ($name) {
            $qb->andWhere('(c.firstName LIKE :name OR c.lastName LIKE :name)')
                ->setParameter('name', '%' . $name . '%');
        }

        if ($email) {
            $qb->andWhere('c.email LIKE :email')
                ->setParameter('email', '%' . $email . '%');
        }

        if ($company) {
            $qb->andWhere('c.company LIKE :company')
                ->setParameter('company', '%' . $company . '%');
        }

        if ($status) {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $status);
        }

        if ($groupId) {
            $qb->andWhere('c.clientGroupId = :group_id')
                ->setParameter('group_id', $groupId);
        }

        if ($createdAt) {
            $qb->andWhere("DATE_FORMAT(c.createdAt, '%Y-%m-%d') = :created_at")
                ->setParameter('created_at', date('Y-m-d', strtotime((string) $createdAt)));
        }

        if ($dateFrom) {
            $qb->andWhere('c.createdAt >= :date_from')
                ->setParameter('date_from', new \DateTime('@' . strtotime((string) $dateFrom)));
        }

        if ($dateTo) {
            $qb->andWhere('c.createdAt <= :date_to')
                ->setParameter('date_to', new \DateTime('@' . strtotime((string) $dateTo)));
        }

        if ($search) {
            if (is_numeric($search)) {
                $qb->andWhere('(c.id = :cid OR c.aid = :caid)')
                    ->setParameter('cid', $search)
                    ->setParameter('caid', $search);
            } else {
                $searchParam = '%' . $search . '%';
                $qb->andWhere("(c.company LIKE :s_company OR c.firstName LIKE :s_first_name OR c.lastName LIKE :s_last_name OR c.email LIKE :s_email OR CONCAT(c.firstName, ' ', c.lastName) LIKE :full_name)")
                    ->setParameter('s_company', $searchParam)
                    ->setParameter('s_first_name', $searchParam)
                    ->setParameter('s_last_name', $searchParam)
                    ->setParameter('s_email', $searchParam)
                    ->setParameter('full_name', $searchParam);
            }
        }

        return $qb->orderBy('c.createdAt', 'DESC');
    }

    /**
     * @return array{active: int, suspended: int, canceled: int}
     */
    public function getStatusCounts(): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT status, COUNT(id) AS count FROM client GROUP BY status'
        );

        $counts = ['active' => 0, 'suspended' => 0, 'canceled' => 0];
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }
}
