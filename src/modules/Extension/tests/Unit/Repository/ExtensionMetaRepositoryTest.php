<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Extension\Entity\ExtensionMeta;
use Box\Mod\Extension\Repository\ExtensionMetaRepository;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\SchemaTool;
use FOSSBilling\Doctrine\EntityManagerFactory;
use Psr\Log\AbstractLogger;

function extensionMetaRepoCreateRepository(): ExtensionMetaRepository
{
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getEventManager')->andReturn(new EventManager());
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('isTransactionActive')->andReturnFalse();
    $em->shouldReceive('getConnection')->andReturn($connection);
    $classMeta = Mockery::mock(Doctrine\ORM\Mapping\ClassMetadata::class);
    $classMeta->name = ExtensionMeta::class;
    $classMeta->shouldReceive('getName')->andReturn(ExtensionMeta::class);
    $em->shouldReceive('getClassMetadata')->with(ExtensionMeta::class)->andReturn($classMeta);

    $qb = new QueryBuilder($em);
    $em->shouldReceive('createQueryBuilder')->andReturn($qb);

    return new ExtensionMetaRepository($em, $classMeta);
}

test('createQueryBuilderForExtension sets the extension parameter', function (): void {
    $repo = extensionMetaRepoCreateRepository();
    $qb = $repo->createQueryBuilderForExtension('mod_email');

    expect($qb)->toBeInstanceOf(QueryBuilder::class);
    expect($qb->getParameter('extension')->getValue())->toBe('mod_email');
});

test('findOneByExtensionAndId delegates to findOneBy', function (): void {
    $repo = Mockery::mock(ExtensionMetaRepository::class)->makePartial();
    $repo->shouldReceive('findOneBy')
        ->once()
        ->with(['extension' => 'mod_email', 'id' => 5])
        ->andReturn(null);

    expect($repo->findOneByExtensionAndId('mod_email', 5))->toBeNull();
});

test('findOneByExtensionAndScope returns the first match', function (): void {
    $meta = new ExtensionMeta();
    $repo = Mockery::mock(ExtensionMetaRepository::class)->makePartial();
    $repo->shouldReceive('findByExtensionAndScope')
        ->once()
        ->with('mod_email', 'config', null, null, ['id' => 'ASC'], 1)
        ->andReturn([$meta]);

    expect($repo->findOneByExtensionAndScope('mod_email', 'config'))->toBe($meta);
});

test('findOneByExtensionAndScope returns null on empty result', function (): void {
    $repo = Mockery::mock(ExtensionMetaRepository::class)->makePartial();
    $repo->shouldReceive('findByExtensionAndScope')
        ->once()
        ->with('mod_email', 'config', null, null, ['id' => 'ASC'], 1)
        ->andReturn([]);

    expect($repo->findOneByExtensionAndScope('mod_email', 'config'))->toBeNull();
});

test('caches repeated single scope lookups and invalidates on ORM and bulk writes', function (): void {
    $logger = new class extends AbstractLogger {
        /** @var list<string> */
        public array $queries = [];

        public function log($level, string|Stringable $message, array $context = []): void
        {
            if (isset($context['sql'])) {
                $this->queries[] = $context['sql'];
            }
        }

        public function selectCount(): int
        {
            return count(array_filter(
                $this->queries,
                static fn (string $query): bool => str_starts_with(strtoupper(ltrim($query)), 'SELECT') && str_contains(strtolower($query), 'extension_meta')
            ));
        }
    };
    $configuration = new Configuration();
    $configuration->setMiddlewares([new Middleware($logger)]);
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
    $entityManager = EntityManagerFactory::create($connection);
    (new SchemaTool($entityManager))->createSchema([$entityManager->getClassMetadata(ExtensionMeta::class)]);
    $repository = $entityManager->getRepository(ExtensionMeta::class);

    $config = (new ExtensionMeta())
        ->setExtension('mod_mailer')
        ->setMetaKey('config')
        ->setMetaValue('encrypted-config');
    $entityManager->persist($config);

    $nullScope = (new ExtensionMeta())
        ->setExtension('mod_theme')
        ->setRelType('settings')
        ->setRelId('huraga')
        ->setMetaKey(null)
        ->setMetaValue('null-key');
    $emptyScope = (new ExtensionMeta())
        ->setExtension('mod_theme')
        ->setRelType('settings')
        ->setRelId('huraga')
        ->setMetaKey('')
        ->setMetaValue('empty-key');
    $entityManager->persist($nullScope);
    $entityManager->persist($emptyScope);
    $entityManager->flush();
    $entityManager->clear();
    $logger->queries = [];

    $firstConfig = $repository->findOneByExtensionAndScope('mod_mailer', 'config');
    $secondConfig = $repository->findOneByExtensionAndScope('mod_mailer', 'config');
    expect($secondConfig)->toBe($firstConfig)
        ->and($secondConfig?->getMetaValue())->toBe('encrypted-config')
        ->and($logger->selectCount())->toBe(1);

    $wildcardScope = $repository->findOneByExtensionAndScope('mod_theme', null, 'settings', 'huraga');
    $emptyScope = $repository->findOneByExtensionAndScope('mod_theme', '', 'settings', 'huraga');
    expect($wildcardScope?->getMetaKey())->toBeNull()
        ->and($emptyScope?->getMetaValue())->toBe('empty-key');

    $managedConfig = $repository->findOneByExtensionAndScope('mod_mailer', 'config');
    $managedConfig?->setMetaValue('encrypted-config-v2');
    $entityManager->flush();
    $updatedConfig = $repository->findOneByExtensionAndScope('mod_mailer', 'config');
    expect($updatedConfig?->getMetaValue())->toBe('encrypted-config-v2')
        ->and($logger->selectCount())->toBe(4);

    expect($repository->findOneByExtensionAndScope('mod_mailer', 'missing'))->toBeNull();
    $inserted = (new ExtensionMeta())
        ->setExtension('mod_mailer')
        ->setMetaKey('missing')
        ->setMetaValue('created');
    $entityManager->persist($inserted);
    $entityManager->flush();
    expect($repository->findOneByExtensionAndScope('mod_mailer', 'missing'))->toBe($inserted);

    $entityManager->remove($inserted);
    $entityManager->flush();
    expect($repository->findOneByExtensionAndScope('mod_mailer', 'missing'))->toBeNull();

    $repository->findOneByExtensionAndScope('mod_theme', null, 'settings', 'huraga');
    expect($repository->deleteByExtensionAndScope('mod_theme', null, 'settings', 'huraga'))->toBe(2)
        ->and($repository->findOneByExtensionAndScope('mod_theme', null, 'settings', 'huraga'))->toBeNull();

    $repository->findOneByExtensionAndScope('mod_mailer', 'config');
    $entityManager->clear();
    $connection->executeStatement("UPDATE extension_meta SET meta_value = 'after-clear' WHERE extension = 'mod_mailer' AND meta_key = 'config'");
    $reloaded = $repository->findOneByExtensionAndScope('mod_mailer', 'config');
    if (!$reloaded instanceof ExtensionMeta) {
        throw new RuntimeException('Expected the metadata row to be available after clear.');
    }
    expect($reloaded?->getMetaValue())->toBe('after-clear')
        ->and($entityManager->contains($reloaded))->toBeTrue();
});

test('does not memoize extension metadata read inside a transaction that rolls back', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $entityManager = EntityManagerFactory::create($connection);
    (new SchemaTool($entityManager))->createSchema([$entityManager->getClassMetadata(ExtensionMeta::class)]);
    $repository = $entityManager->getRepository(ExtensionMeta::class);
    $existing = (new ExtensionMeta())
        ->setExtension('mod_rollback')
        ->setMetaKey('existing')
        ->setMetaValue('committed');
    $entityManager->persist($existing);
    $entityManager->flush();
    $entityManager->clear();

    expect($repository->findOneByExtensionAndScope('mod_rollback', 'existing'))->toBeInstanceOf(ExtensionMeta::class)
        ->and($repository->findOneByExtensionAndScope('mod_rollback', 'missing'))->toBeNull();

    $connection->beginTransaction();
    $connection->executeStatement("DELETE FROM extension_meta WHERE extension = 'mod_rollback' AND meta_key = 'existing'");
    $connection->executeStatement("INSERT INTO extension_meta (extension, meta_key, meta_value) VALUES ('mod_rollback', 'missing', 'temporary')");

    expect($repository->findOneByExtensionAndScope('mod_rollback', 'existing'))->toBeNull();
    $uncommitted = $repository->findOneByExtensionAndScope('mod_rollback', 'missing');
    expect($uncommitted?->getMetaValue())->toBe('temporary');
    if ($uncommitted instanceof ExtensionMeta) {
        $entityManager->detach($uncommitted);
    }

    $connection->rollBack();
    expect($repository->findOneByExtensionAndScope('mod_rollback', 'existing')?->getMetaValue())->toBe('committed')
        ->and($repository->findOneByExtensionAndScope('mod_rollback', 'missing'))->toBeNull();
});
