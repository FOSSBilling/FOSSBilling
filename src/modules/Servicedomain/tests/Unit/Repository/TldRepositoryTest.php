<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Box\Mod\Servicedomain\Entity\Tld;
use Box\Mod\Servicedomain\Entity\TldRegistrar;
use Box\Mod\Servicedomain\Repository\TldRepository;
use Doctrine\DBAL\Configuration as DBALConfiguration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware as DBALLoggingMiddleware;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Log\AbstractLogger;
use Symfony\Component\Filesystem\Path;

function tldRepositoryEntityManager(?AbstractLogger $logger = null): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(dirname(__DIR__, 3), 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');

    $connectionConfig = new DBALConfiguration();
    if ($logger !== null) {
        $connectionConfig->setMiddlewares([new DBALLoggingMiddleware($logger)]);
    }

    return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $connectionConfig), $config);
}

test('builds the legacy domain pricing array keyed by tld', function (): void {
    $registrar = (new TldRegistrar())->setName('Registrar A');
    $reflection = new ReflectionProperty($registrar, 'id');
    $reflection->setValue($registrar, 1);

    $com = (new Tld())
        ->setTld('.com')
        ->setRegistrar($registrar)
        ->setPriceRegistration('10.00')
        ->setPriceRenew('12.00')
        ->setPriceTransfer('14.00')
        ->setActive(true)
        ->setAllowRegister(true)
        ->setAllowTransfer(true)
        ->setMinYears(1)
        ->setPeriods('1,2,5');

    $org = (new Tld())
        ->setTld('.org')
        ->setPriceRegistration('9.00')
        ->setPriceRenew('11.00')
        ->setPriceTransfer('13.00')
        ->setActive(true)
        ->setAllowRegister(false)
        ->setAllowTransfer(null)
        ->setMinYears(2);

    $repository = Mockery::mock(TldRepository::class)->makePartial();
    $repository->shouldReceive('findAllActive')->once()->andReturn([$com, $org]);

    $result = $repository->getActivePricing();

    expect($result)->toHaveKey('.com')
        ->and($result)->toHaveKey('.org')
        ->and($result['.com']['price_registration'])->toBe('10.00')
        ->and($result['.com']['active'])->toBe(1)
        ->and($result['.com']['allow_register'])->toBe(1)
        ->and($result['.com']['allow_transfer'])->toBe(1)
        ->and($result['.com']['min_years'])->toBe(1)
        ->and($result['.com']['periods'])->toBe([1, 2, 5])
        ->and($result['.com']['registrar'])->toBe(['id' => 1, 'title' => 'Registrar A'])
        ->and($result['.org']['periods'])->toBeNull()
        ->and($result['.org']['allow_register'])->toBe(0)
        ->and($result['.org']['allow_transfer'])->toBeNull()
        ->and($result['.org']['registrar'])->toBe(['id' => null, 'title' => null]);
});

test('caches active TLDs and refreshes after writes, clear, and rollback', function (): void {
    $logger = new class extends AbstractLogger {
        /** @var list<string> */
        public array $queries = [];

        public function log($level, string|Stringable $message, array $context = []): void
        {
            if (isset($context['sql'])) {
                $this->queries[] = $context['sql'];
            }
        }

        public function activeTldSelectCount(): int
        {
            return count(array_filter($this->queries, static function (string $query): bool {
                $query = strtolower(ltrim($query));

                return str_starts_with($query, 'select') && str_contains($query, 'from tld t0_');
            }));
        }
    };

    $entityManager = tldRepositoryEntityManager($logger);
    (new SchemaTool($entityManager))->createSchema(array_map(
        $entityManager->getClassMetadata(...),
        [Tld::class, TldRegistrar::class],
    ));

    $registrar = (new TldRegistrar())->setName('Registrar A')->setRegistrar('registrar_a');
    $com = (new Tld())
        ->setTld('.com')
        ->setRegistrar($registrar)
        ->setPriceRegistration('10.00')
        ->setActive(true)
        ->setPeriods('1,2');
    $net = (new Tld())
        ->setTld('.net')
        ->setRegistrar($registrar)
        ->setPriceRegistration('12.00')
        ->setActive(true);
    $inactive = (new Tld())->setTld('.invalid')->setActive(false);

    foreach ([$registrar, $com, $net, $inactive] as $entity) {
        $entityManager->persist($entity);
    }
    $entityManager->flush();
    $entityManager->clear();
    $logger->queries = [];

    $repository = $entityManager->getRepository(Tld::class);
    $activeTlds = $repository->findAllActive();
    $firstPricing = $repository->getActivePricing();
    $firstPricing['.com']['price_registration'] = 'mutated';
    $secondPricing = $repository->getActivePricing();

    expect(array_map(static fn (Tld $tld): ?string => $tld->getTld(), $activeTlds))->toBe(['.com', '.net'])
        ->and($secondPricing['.com']['price_registration'])->toBe('10')
        ->and($secondPricing['.com']['periods'])->toBe([1, 2])
        ->and($secondPricing['.com']['registrar']['title'])->toBe('Registrar A')
        ->and($logger->activeTldSelectCount())->toBe(1);

    $logger->queries = [];
    $activeTlds[0]->setPriceRegistration('11.00');
    $entityManager->flush();
    expect($repository->getActivePricing()['.com']['price_registration'])->toBe('11.00')
        ->and($logger->activeTldSelectCount())->toBe(1);

    $logger->queries = [];
    $activeTlds[0]->getRegistrar()?->setName('Registrar B');
    $entityManager->flush();
    expect($repository->getActivePricing()['.com']['registrar']['title'])->toBe('Registrar B')
        ->and($logger->activeTldSelectCount())->toBe(1);

    $logger->queries = [];
    $activeTlds[1]->setActive(false);
    $entityManager->flush();
    expect(array_keys($repository->getActivePricing()))->toBe(['.com'])
        ->and($logger->activeTldSelectCount())->toBe(1);

    $logger->queries = [];
    $newTld = (new Tld())->setTld('.io')->setRegistrar($activeTlds[0]->getRegistrar())->setActive(true);
    $entityManager->persist($newTld);
    $entityManager->flush();
    expect(array_keys($repository->getActivePricing()))->toBe(['.com', '.io'])
        ->and($logger->activeTldSelectCount())->toBe(1);

    $logger->queries = [];
    $entityManager->remove($newTld);
    $entityManager->flush();
    expect(array_keys($repository->getActivePricing()))->toBe(['.com'])
        ->and($logger->activeTldSelectCount())->toBe(1);

    $logger->queries = [];
    $entityManager->clear();
    expect(array_keys($repository->getActivePricing()))->toBe(['.com'])
        ->and($logger->activeTldSelectCount())->toBe(1);

    $connection = $entityManager->getConnection();
    $connection->beginTransaction();
    $entityManager->persist((new Tld())->setTld('.transaction')->setActive(true));
    $entityManager->flush();

    expect(array_keys($repository->getActivePricing()))->toBe(['.com', '.transaction']);
    $connection->rollBack();

    expect(array_keys($repository->getActivePricing()))->toBe(['.com'])
        ->and($logger->activeTldSelectCount())->toBe(3);
});
