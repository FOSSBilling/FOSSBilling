<?php

declare(strict_types=1);

use Box\Mod\Client\Entity\ClientBalance;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Filesystem\Path;

beforeEach(function (): void {
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Core\\Tests\\DoctrineProxies');
    $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
    $metadata = $this->entityManager->getClassMetadata(ClientBalance::class);
    (new SchemaTool($this->entityManager))->createSchema([$metadata]);
});

test('rejects duplicate invoice_item_id credits', function (): void {
    $first = (new ClientBalance())->setType('invoice')->setInvoiceItemId(42)->setAmount('-10.00');
    $this->entityManager->persist($first);
    $this->entityManager->flush();

    $duplicate = (new ClientBalance())->setType('invoice')->setInvoiceItemId(42)->setAmount('-10.00');
    $this->entityManager->persist($duplicate);
    $this->entityManager->flush();
})->throws(UniqueConstraintViolationException::class);

test('allows multiple rows with NULL invoice_item_id', function (): void {
    $first = (new ClientBalance())->setType('default')->setAmount('-5.00');
    $second = (new ClientBalance())->setType('transaction')->setAmount('15.00');
    $this->entityManager->persist($first);
    $this->entityManager->persist($second);
    $this->entityManager->flush();

    expect($this->entityManager->getRepository(ClientBalance::class)->count([]))->toBe(2);
});

test('allows distinct invoice_item_id values', function (): void {
    $first = (new ClientBalance())->setType('invoice')->setInvoiceItemId(10)->setAmount('-10.00');
    $second = (new ClientBalance())->setType('invoice')->setInvoiceItemId(20)->setAmount('-20.00');
    $this->entityManager->persist($first);
    $this->entityManager->persist($second);
    $this->entityManager->flush();

    expect($this->entityManager->getRepository(ClientBalance::class)->count([]))->toBe(2);
});
