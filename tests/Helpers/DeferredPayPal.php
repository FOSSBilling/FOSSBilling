<?php

declare(strict_types=1);

function deletionPayPalApi(array $c): object
{
    return new class($c) {
        public bool $crash = false;
        public ?Closure $beforeInvoiceRead = null;

        public function __construct(private array $c)
        {
        }

        public function invoice_transaction_get(array $data): array
        {
            return $this->c['connection']->fetchAssociative('SELECT invoice_id, type, txn_id, txn_status, amount, currency, status FROM ' . $this->c['connection']->quoteSingleIdentifier('transaction') . ' WHERE id = ?', [$data['id']]);
        }

        public function invoice_transaction_update(array $data): bool
        {
            if ($this->crash && ($data['status'] ?? '') === 'processed') {
                throw new Error('Interrupted before receipt completion');
            }
            $service = new Box\Mod\Invoice\ServiceTransaction();
            $service->setDi($this->c['di']);

            return $service->update($this->c['em']->find(Box\Mod\Invoice\Entity\Transaction::class, $data['id']), $data);
        }

        public function invoice_get(array $data): array
        {
            if ($this->beforeInvoiceRead !== null) {
                $callback = $this->beforeInvoiceRead;
                $this->beforeInvoiceRead = null;
                $callback();
            }

            return ['client' => ['id' => $this->c['client']->getId()], 'currency' => 'USD'];
        }

        public function invoice_refund(array $data): never
        {
            throw new RuntimeException('A deferred credit refund must not refund an invoice');
        }

        public function client_balance_add_funds(array $data): bool
        {
            $service = new Box\Mod\Client\Service();
            $service->setDi($this->c['di']);

            return $service->addFunds($this->c['client'], $data['amount'], $data['description'], $data);
        }
    };
}

function deferredPayPalMysqlEntityManager(?string $connectionClass = null): Doctrine\ORM\EntityManager
{
    $connection = Doctrine\DBAL\DriverManager::getConnection([
        'driver' => 'pdo_mysql', 'host' => '127.0.0.1', 'port' => (int) getenv('DELETION_HOLD_MYSQL_PORT'),
        'user' => 'root', 'password' => getenv('DELETION_HOLD_MYSQL_PASSWORD'), 'dbname' => 'hold_regression',
    ] + ($connectionClass === null ? [] : ['wrapperClass' => $connectionClass]));
    $connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 4');
    $config = Doctrine\ORM\ORMSetup::createAttributeMetadataConfig([PATH_MODS], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('DeferredPayPalMysqlProxies');

    return new Doctrine\ORM\EntityManager($connection, $config);
}
