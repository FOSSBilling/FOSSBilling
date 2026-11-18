<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Invoice\Repository\InvoiceItemRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;

test('getSearchQueryBuilder orders by id descending', function (): void {
    $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 3) . '/Entity'], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Core\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
    $repository = $entityManager->getRepository(InvoiceItem::class);

    $dql = $repository->getSearchQueryBuilder([])->getDQL();

    expect($dql)->toBe('SELECT ii FROM ' . InvoiceItem::class . ' ii ORDER BY ii.id DESC');
});

test('repository is wired to the InvoiceItem entity', function (): void {
    $entityManager = Mockery::mock(EntityManager::class);
    $repository = new InvoiceItemRepository($entityManager, new ClassMetadata(InvoiceItem::class));

    expect($repository->getClassName())->toBe(InvoiceItem::class);
});

test('paid pending renewals respect typed links and historical payment references', function (?int $typedId, string $reference, bool $matches): void {
    $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $db->executeStatement('CREATE TABLE invoice (id INTEGER PRIMARY KEY, status TEXT)');
    $db->executeStatement('CREATE TABLE invoice_item (id INTEGER PRIMARY KEY, invoice_id INTEGER, type TEXT, task TEXT, status TEXT, order_id INTEGER, rel_id TEXT COLLATE RTRIM)');
    $db->insert('invoice', ['id' => 9, 'status' => 'paid']);
    $db->insert('invoice_item', ['id' => 1, 'invoice_id' => 9, 'type' => InvoiceItem::TYPE_ORDER, 'task' => InvoiceItem::TASK_RENEW, 'status' => InvoiceItem::STATUS_PENDING_SETUP, 'order_id' => $typedId, 'rel_id' => $reference]);
    $item = new InvoiceItem();
    $em = Mockery::mock(EntityManager::class);
    $em->shouldReceive('getConnection')->andReturn($db);
    $em->shouldReceive('find')->with(InvoiceItem::class, 1, Doctrine\DBAL\LockMode::NONE, null)->times($matches ? 2 : 0)->andReturn($item);
    $repository = new InvoiceItemRepository($em, new ClassMetadata(InvoiceItem::class));

    expect($repository->findPaidPendingRenewal(7))->toBe($matches ? $item : null);
    $db->executeStatement("UPDATE invoice_item SET status = 'failed'");
    expect($repository->findPaidPendingRenewal(7))->toBe($matches ? $item : null);
    $db->executeStatement("UPDATE invoice_item SET status = 'executed'");
    expect($repository->findPaidPendingRenewal(7))->toBeNull();
    $db->executeStatement("UPDATE invoice_item SET status = 'pending_setup'");
    $db->executeStatement("UPDATE invoice SET status = 'unpaid'");
    expect($repository->findPaidPendingRenewal(7))->toBeNull();
})->with([
    'typed link takes precedence' => [7, '99', true],
    'different typed order' => [8, '7', false],
    'legacy canonical' => [null, '7', true],
    'legacy padded and trailing spaces' => [null, '007 ', true],
    'legacy fractional' => [null, '7.0', false],
    'legacy exponent' => [null, '7e0', false],
]);
