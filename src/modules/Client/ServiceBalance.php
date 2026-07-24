<?php

declare(strict_types=1);

namespace Box\Mod\Client;

use Box\Mod\Client\Entity\Client;
use Box\Mod\Client\Entity\ClientBalance;
use Box\Mod\Client\Repository\ClientBalanceRepository;
use Box\Mod\Client\Repository\ClientRepository;
use FOSSBilling\InjectionAwareInterface;

class ServiceBalance implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    private ClientBalanceRepository $clientBalanceRepository;
    private ClientRepository $clientRepository;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        $this->clientBalanceRepository = $di['em']->getRepository(ClientBalance::class);
        $this->clientRepository = $di['em']->getRepository(Client::class);
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getClientBalance(Client|\Model_Client $c): float
    {
        return (float) $this->clientTotal($c);
    }

    public function clientTotal(Client|\Model_Client $c): float
    {
        $clientId = $c instanceof Client ? $c->getId() : $c->id;

        return $this->clientBalanceRepository->getClientBalanceSum((int) $clientId);
    }

    public function rmByClient(Client|\Model_Client $client): void
    {
        $clientId = $client instanceof Client ? $client->getId() : $client->id;
        $balances = $this->clientBalanceRepository->findBy(['clientId' => (int) $clientId]);
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

    public function toApiArray(ClientBalance $model): array
    {
        $clientId = $model->getClientId();
        $client = $clientId !== null ? $this->clientRepository->find($clientId) : null;
        if (!$client instanceof Client) {
            throw new \FOSSBilling\InformationException('Client not found');
        }

        return [
            'id' => $model->getId(),
            'description' => $model->getDescription(),
            'amount' => $model->getAmount(),
            'currency' => $client->getCurrency(),
            'created_at' => $model->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
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
            $params[':id'] = $id;
        }

        if ($client_id !== null) {
            $where[] = 'm.client_id = :client_id';
            $params[':client_id'] = $client_id;
        }

        if ($date_from !== null) {
            $where[] = 'm.created_at >= :date_from';
            $params[':date_from'] = strtotime($date_from);
        }

        if ($date_to !== null) {
            $where[] = 'm.created_at <= :date_to';
            $params[':date_to'] = strtotime($date_to);
        }

        if (!empty($where)) {
            $q .= ' WHERE ' . implode(' AND ', $where);
        }
        $q .= ' ORDER by m.id DESC';

        return [$q, $params];
    }

    /**
     * @param float|string $amount
     *
     * @throws \FOSSBilling\InformationException
     */
    public function deductFunds(Client|\Model_Client $client, $amount, $description, ?array $data = null): ClientBalance
    {
        if (!is_numeric($amount)) {
            throw new \FOSSBilling\InformationException('Funds amount is invalid');
        }

        if (strlen(trim($description)) == 0) {
            throw new \FOSSBilling\InformationException('Funds description is invalid');
        }

        $credit = new ClientBalance();
        $clientId = $client instanceof Client ? $client->getId() : $client->id;
        $credit->setClientId((int) $clientId);
        $credit->setType($data['type'] ?? 'default');
        $credit->setRelId(isset($data['rel_id']) ? (string) $data['rel_id'] : null);
        $credit->setDescription($description);
        $credit->setAmount((string) (-(float) $amount));

        $this->di['em']->persist($credit);
        $this->di['em']->flush();

        return $credit;
    }
}
