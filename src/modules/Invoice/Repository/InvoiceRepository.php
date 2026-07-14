<?php

declare(strict_types=1);

namespace Box\Mod\Invoice\Repository;

use Box\Mod\Invoice\Entity\Invoice;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class InvoiceRepository extends EntityRepository
{
    public function findByHash(?string $hash): ?Invoice
    {
        if ($hash === null || $hash === '') {
            return null;
        }

        $invoice = $this->findOneBy(['hash' => $hash]);

        return $invoice instanceof Invoice ? $invoice : null;
    }

    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('i');

        $id = $data['id'] ?? null;
        $clientId = $data['client_id'] ?? null;
        $nr = $data['nr'] ?? null;
        $status = $data['status'] ?? null;
        $approved = $data['approved'] ?? null;
        $currency = $data['currency'] ?? null;
        $gatewayId = $data['gateway_id'] ?? null;
        $dateFrom = $data['date_from'] ?? null;
        $dateTo = $data['date_to'] ?? null;
        $paidAt = $data['paid_at'] ?? null;
        $dueDate = $data['duedate'] ?? null;
        $search = $data['search'] ?? null;

        if ($id !== null && $id !== '') {
            $qb->andWhere('i.id = :id')
                ->setParameter('id', (int) $id);
        }

        if ($clientId !== null && $clientId !== '') {
            $qb->andWhere('i.clientId = :client_id')
                ->setParameter('client_id', (int) $clientId);
        }

        if ($nr) {
            $qb->andWhere('i.nr LIKE :nr')
                ->setParameter('nr', '%' . $nr . '%');
        }

        if ($status) {
            $qb->andWhere('i.status = :status')
                ->setParameter('status', $status);
        }

        if ($approved !== null && $approved !== '') {
            $qb->andWhere('i.approved = :approved')
                ->setParameter('approved', (bool) $approved);
        }

        if ($currency) {
            $qb->andWhere('i.currency = :currency')
                ->setParameter('currency', $currency);
        }

        if ($gatewayId !== null && $gatewayId !== '') {
            $qb->andWhere('i.gatewayId = :gateway_id')
                ->setParameter('gateway_id', (int) $gatewayId);
        }

        if ($dateFrom) {
            $qb->andWhere('i.dueAt >= :date_from')
                ->setParameter('date_from', new \DateTime('@' . strtotime((string) $dateFrom)));
        }

        if ($dateTo) {
            $qb->andWhere('i.dueAt <= :date_to')
                ->setParameter('date_to', new \DateTime('@' . strtotime((string) $dateTo)));
        }

        if ($paidAt) {
            $qb->andWhere("DATE_FORMAT(i.paidAt, '%Y-%m-%d') = :paid_at")
                ->setParameter('paid_at', date('Y-m-d', strtotime((string) $paidAt)));
        }

        if ($dueDate) {
            $qb->andWhere('i.dueAt <= :due_date')
                ->setParameter('due_date', (new \DateTime())->modify('+' . (int) $dueDate . ' days'));
        }

        if ($search) {
            if (is_numeric($search)) {
                $qb->andWhere('(i.id = :sid OR i.nr LIKE :snr)')
                    ->setParameter('sid', $search)
                    ->setParameter('snr', '%' . $search . '%');
            } else {
                $searchParam = '%' . $search . '%';
                $qb->andWhere('(i.nr LIKE :search_nr OR i.buyerFirstName LIKE :search_fn OR i.buyerLastName LIKE :search_ln OR i.buyerCompany LIKE :search_company)')
                    ->setParameter('search_nr', $searchParam)
                    ->setParameter('search_fn', $searchParam)
                    ->setParameter('search_ln', $searchParam)
                    ->setParameter('search_company', $searchParam);
            }
        }

        return $qb->orderBy('i.id', 'DESC');
    }

    /**
     * @return array{active: int, paid: int, unpaid: int, refunded: int, canceled: int}
     */
    public function getStatusCounts(): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT status, COUNT(id) AS count FROM invoice GROUP BY status'
        );

        $counts = ['active' => 0, 'paid' => 0, 'unpaid' => 0, 'refunded' => 0, 'canceled' => 0];
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * @return Invoice[]
     */
    public function findUnpaid(): array
    {
        return $this->findBy(['status' => Invoice::STATUS_UNPAID]);
    }

    /**
     * @return Invoice[]
     */
    public function findPaid(): array
    {
        return $this->findBy(['status' => Invoice::STATUS_PAID]);
    }

    public function getNextInvoiceNumber(): ?string
    {
        $row = $this->getEntityManager()->getConnection()->fetchAssociative(
            'SELECT nr FROM invoice ORDER BY id DESC LIMIT 1'
        );

        return $row ? $row['nr'] : null;
    }

    /**
     * @return Invoice[]
     */
    public function findByClientId(int $clientId): array
    {
        return $this->findBy(['clientId' => $clientId]);
    }

    /**
     * @return Invoice[]
     */
    public function findPaidByClientId(int $clientId): array
    {
        return $this->findBy(['clientId' => $clientId, 'status' => Invoice::STATUS_PAID]);
    }

    /**
     * @return Invoice[]
     */
    public function findApprovedUnpaid(): array
    {
        return $this->findBy(['approved' => true, 'status' => Invoice::STATUS_UNPAID]);
    }
}
