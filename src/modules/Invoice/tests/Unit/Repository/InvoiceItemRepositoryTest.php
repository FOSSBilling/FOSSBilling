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
