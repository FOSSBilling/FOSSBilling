<?php

declare(strict_types=1);

use Box\Mod\Massmailer\Entity\MassmailerMessage;
use Box\Mod\Massmailer\Repository\MassmailerMessageRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use FOSSBilling\InformationException;

function createMassmailerDi(?Connection $dbal = null): Pimple\Container
{
    $di = new Pimple\Container();
    $repo = Mockery::mock(MassmailerMessageRepository::class);
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(MassmailerMessage::class)->andReturn($repo);
    $di['em'] = $em;
    $di['dbal'] = $dbal ?? DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

    return $di;
}

function createMassmailerDbal(): Connection
{
    return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
}

function seedReceiverTables(Connection $dbal): void
{
    $dbal->executeStatement('CREATE TABLE client_group (id INTEGER PRIMARY KEY)');
    $dbal->executeStatement('CREATE TABLE product (id INTEGER PRIMARY KEY)');
    $dbal->executeStatement('CREATE TABLE client (id INTEGER PRIMARY KEY, status TEXT, client_group_id INTEGER)');
    $dbal->executeStatement('CREATE TABLE client_order (id INTEGER PRIMARY KEY, client_id INTEGER, product_id INTEGER, status TEXT)');

    $dbal->executeStatement('INSERT INTO client_group (id) VALUES (1), (2)');
    $dbal->executeStatement('INSERT INTO product (id) VALUES (10), (11)');
    $dbal->executeStatement("INSERT INTO client (id, status, client_group_id) VALUES (1, 'active', 1), (2, 'canceled', 1), (3, 'active', 2)");
    $dbal->executeStatement("INSERT INTO client_order (id, client_id, product_id, status) VALUES (1, 1, 10, 'active'), (2, 2, 10, 'suspended'), (3, 3, 11, 'active')");
}

test('normalize filter returns canonical enum values', function (): void {
    $service = new Box\Mod\Massmailer\Service();

    $normalized = $service->normalizeFilter([
        'client_status' => ['canceled', 'active', 'active'],
        'has_order_with_status' => ['suspended', 'active', 'active'],
    ], true);

    expect($normalized)->toBe([
        'client_status' => ['active', 'canceled'],
        'has_order_with_status' => ['active', 'suspended'],
    ]);
});

test('normalize filter rejects unexpected keys in strict mode', function (): void {
    $service = new Box\Mod\Massmailer\Service();
    $service->setDi(createMassmailerDi());

    expect(fn () => $service->normalizeFilter(['unexpected' => ['anything']], true))
        ->toThrow(InformationException::class, 'Mass mail filter contains invalid values for "unexpected"');
});

test('normalize filter rejects unknown IDs in strict mode', function (): void {
    $dbal = createMassmailerDbal();
    $dbal->executeStatement('CREATE TABLE client_group (id INTEGER PRIMARY KEY)');
    $dbal->executeStatement('INSERT INTO client_group (id) VALUES (1)');

    $service = new Box\Mod\Massmailer\Service();
    $service->setDi(createMassmailerDi($dbal));

    expect(fn () => $service->normalizeFilter(['client_groups' => ['2', '1']], true))
        ->toThrow(InformationException::class, 'Mass mail filter contains invalid values for "client_groups"');
});

test('get message receivers builds parameterized query', function (): void {
    $dbal = createMassmailerDbal();
    seedReceiverTables($dbal);

    $model = (new MassmailerMessage())->setFilter(json_encode([
        'client_status' => ['active'],
        'client_groups' => [1],
        'has_order' => [10],
        'has_order_with_status' => ['active'],
    ], JSON_THROW_ON_ERROR));

    $service = new Box\Mod\Massmailer\Service();
    $service->setDi(createMassmailerDi($dbal));

    expect($service->getMessageReceivers($model))->toBe([['id' => 1]]);
});

test('get message receivers rejects invalid stored filter', function (): void {
    $model = (new MassmailerMessage())->setFilter(json_encode([
        'client_status' => ['active', 'not-valid'],
    ], JSON_THROW_ON_ERROR));

    $service = new Box\Mod\Massmailer\Service();
    $service->setDi(createMassmailerDi());

    expect(fn () => $service->getMessageReceivers($model))
        ->toThrow(InformationException::class, 'Mass mail filter contains invalid values for "client_status"');
});
