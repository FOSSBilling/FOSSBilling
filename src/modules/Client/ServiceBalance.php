<?php

declare(strict_types=1);

namespace Box\Mod\Client;

use Box\Mod\Client\Entity\Client;
use Box\Mod\Client\Entity\ClientBalance;
use Box\Mod\Client\Repository\ClientBalanceRepository;
use Doctrine\ORM\QueryBuilder;
use FOSSBilling\Container\InjectionAwareInterface;

class ServiceBalance implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    private ClientBalanceRepository $clientBalanceRepository;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        $this->clientBalanceRepository = $di['em']->getRepository(ClientBalance::class);
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getClientBalance(Client $c): float
    {
        return $this->clientTotal($c);
    }

    /**
     * Must be called within a transaction, held until the deduction has been written. The lock is
     * released when the transaction ends, and the balance is unprotected from that point on.
     */
    public function getClientBalanceForUpdate(Client|int $c): float
    {
        $clientId = $c instanceof Client ? $c->getId() : $c;

        return $this->clientBalanceRepository->getClientBalanceSumForUpdate((int) $clientId);
    }

    public function clientTotal(Client $c): float
    {
        return $this->clientBalanceRepository->getClientBalanceSum((int) $c->getId());
    }

    public function rmByClient(Client $client): void
    {
        $balances = $this->clientBalanceRepository->findBy(['client' => $client]);
        foreach ($balances as $balance) {
            $this->di['em']->remove($balance);
        }
        if (!empty($balances)) {
            $this->di['em']->flush();
        }
    }

    public function rm(ClientBalance $model): void
    {
        $this->di['em']->remove($model);
        $this->di['em']->flush();
    }

    public function toApiArray(ClientBalance $model, ?Client $client = null): array
    {
        $client ??= $model->getClient();
        if (!$client instanceof Client) {
            throw new \FOSSBilling\Exception\InformationException('Client not found');
        }

        return [
            'id' => $model->getId(),
            'description' => $model->getDescription(),
            'amount' => $model->getAmount(),
            'currency' => $client->getCurrency(),
            'created_at' => $model->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $queryBuilder = $this->clientBalanceRepository->createQueryBuilder('m');

        $id = $data['id'] ?? null;
        $clientId = $data['client_id'] ?? null;
        $dateFrom = $data['date_from'] ?? null;
        $dateTo = $data['date_to'] ?? null;

        if ($id !== null && $id !== '') {
            $queryBuilder->andWhere('m.id = :id')
                ->setParameter('id', $id);
        }

        if ($clientId !== null && $clientId !== '') {
            $queryBuilder->andWhere('IDENTITY(m.client) = :client_id')
                ->setParameter('client_id', $clientId);
        }

        if ($dateFrom !== null && $dateFrom !== '') {
            $queryBuilder->andWhere('m.createdAt >= :date_from')
                ->setParameter('date_from', new \DateTimeImmutable(date('Y-m-d H:i:s', strtotime((string) $dateFrom))));
        }

        if ($dateTo !== null && $dateTo !== '') {
            $queryBuilder->andWhere('m.createdAt <= :date_to')
                ->setParameter('date_to', new \DateTimeImmutable(date('Y-m-d H:i:s', strtotime((string) $dateTo))));
        }

        return $queryBuilder->orderBy('m.id', 'DESC');
    }

    public function getSearchQuery($data): array
    {
        $q = 'SELECT m.*, c.currency  as currency
              FROM client_balance as m
                LEFT JOIN client as c on c.id = m.client_id';

        $id = $data['id'] ?? null;
        $client_id = $data['client_id'] ?? null;
        $date_from = $data['date_from'] ?? null;
        $date_to = $data['date_to'] ?? null;

        $where = [];
        $params = [];

        if ($id !== null) {
            $where[] = 'm.id = :id';
            $params['id'] = $id;
        }

        if ($client_id !== null) {
            $where[] = 'm.client_id = :client_id';
            $params['client_id'] = $client_id;
        }

        if ($date_from !== null) {
            $where[] = 'm.created_at >= :date_from';
            $params['date_from'] = strtotime($date_from);
        }

        if ($date_to !== null) {
            $where[] = 'm.created_at <= :date_to';
            $params['date_to'] = strtotime($date_to);
        }

        if (!empty($where)) {
            $q .= ' WHERE ' . implode(' AND ', $where);
        }
        $q .= ' ORDER by m.id DESC';

        return [$q, $params];
    }
}
