<?php

declare(strict_types=1);

use Box\Mod\Client\Entity\Client;
use Box\Mod\Client\Repository\ClientRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;

use function Tests\Helpers\createEntity;

test('builds a Doctrine client search with the legacy filters', function (): void {
    $where = [];
    $parameters = [];

    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('andWhere')->times(10)->andReturnUsing(function (string $clause) use (&$where, $queryBuilder) {
        $where[] = $clause;

        return $queryBuilder;
    });
    $queryBuilder->shouldReceive('setParameter')->times(11)->andReturnUsing(function (string $name, mixed $value) use (&$parameters, $queryBuilder) {
        $parameters[$name] = $value;

        return $queryBuilder;
    });
    $queryBuilder->shouldReceive('orderBy')->once()->with('c.createdAt', 'DESC')->andReturn($queryBuilder);

    $repository = Mockery::mock(ClientRepository::class)->makePartial();
    $repository->shouldReceive('createQueryBuilder')->once()->with('c')->andReturn($queryBuilder);

    $result = $repository->getSearchQueryBuilder([
        'id' => '7',
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'company' => 'Analytical',
        'status' => 'active',
        'group_id' => 3,
        'created_at' => '2026-07-19',
        'date_from' => '2026-07-01',
        'date_to' => '2026-07-31',
        'search' => 'Lovelace',
    ]);

    expect($result)->toBe($queryBuilder)
        ->and($where)->toContain(
            '(c.id = :id OR c.aid = :id)',
            '(c.firstName LIKE :name OR c.lastName LIKE :name)',
            'c.createdAt >= :created_from AND c.createdAt < :created_to',
            "(c.company LIKE :search OR c.firstName LIKE :search OR c.lastName LIKE :search OR c.email LIKE :search OR CONCAT(CONCAT(c.firstName, ' '), c.lastName) LIKE :search)",
        )
        ->and($parameters['name'])->toBe('%Ada%')
        ->and($parameters['search'])->toBe('%Lovelace%')
        ->and($parameters['created_from'])->toBeInstanceOf(DateTimeImmutable::class);
});

test('uses exact id matching for a numeric smart search', function (): void {
    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('andWhere')->once()->with('(c.id = :search_id OR c.aid = :search_id)')->andReturn($queryBuilder);
    $queryBuilder->shouldReceive('setParameter')->once()->with('search_id', '42')->andReturn($queryBuilder);
    $queryBuilder->shouldReceive('orderBy')->once()->andReturn($queryBuilder);

    $repository = Mockery::mock(ClientRepository::class)->makePartial();
    $repository->shouldReceive('createQueryBuilder')->once()->andReturn($queryBuilder);

    expect($repository->getSearchQueryBuilder(['search' => '42']))->toBe($queryBuilder);
});

test('builds filtered client name pairs from entities', function (): void {
    $query = Mockery::mock(Doctrine\ORM\Query::class);
    $query->shouldReceive('getResult')->once()->andReturn([
        createEntity(Client::class, ['id' => 7, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'company' => 'Analytical Engines']),
        createEntity(Client::class, ['id' => 9, 'first_name' => 'Grace', 'last_name' => 'Hopper']),
    ]);

    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('setMaxResults')->once()->with(30)->andReturn($queryBuilder);
    $queryBuilder->shouldReceive('getQuery')->once()->andReturn($query);

    $repository = Mockery::mock(ClientRepository::class)->makePartial();
    $repository->shouldReceive('getSearchQueryBuilder')->once()->with(['search' => 'Ada'])->andReturn($queryBuilder);

    expect($repository->getIdNamePairs(['search' => 'Ada']))->toBe([
        7 => 'Ada Lovelace (Analytical Engines)',
        9 => 'Grace Hopper',
    ]);
});

test('sorts client search query', function (array $data, string $expectedOrder, string $expectedDirection, ?string $expectedTieBreakerDirection): void {
    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('orderBy')->once()->with($expectedOrder, $expectedDirection)->andReturn($queryBuilder);
    if ($expectedTieBreakerDirection !== null) {
        $queryBuilder->shouldReceive('addOrderBy')->once()->with('c.id', $expectedTieBreakerDirection)->andReturn($queryBuilder);
    } else {
        $queryBuilder->shouldReceive('addOrderBy')->never();
    }

    $repository = Mockery::mock(ClientRepository::class)->makePartial();
    $repository->shouldReceive('createQueryBuilder')->once()->with('c')->andReturn($queryBuilder);

    expect($repository->getSearchQueryBuilder($data))->toBe($queryBuilder);
})->with([
    'email ascending' => [['sort' => 'email'], 'c.email', 'ASC', 'ASC'],
    'email descending' => [['sort' => 'email', 'direction' => 'DESC'], 'c.email', 'DESC', 'DESC'],
    'first name' => [['sort' => 'first_name', 'direction' => 'desc'], 'c.firstName', 'DESC', 'DESC'],
    'last name' => [['sort' => 'last_name'], 'c.lastName', 'ASC', 'ASC'],
    'company' => [['sort' => 'company'], 'c.company', 'ASC', 'ASC'],
    'status' => [['sort' => 'status'], 'c.status', 'ASC', 'ASC'],
    'created at' => [['sort' => 'created_at'], 'c.createdAt', 'ASC', 'ASC'],
    'updated at' => [['sort' => 'updated_at', 'direction' => 'DESC'], 'c.updatedAt', 'DESC', 'DESC'],
    'id' => [['sort' => 'id'], 'c.id', 'ASC', null],
    'invalid sort falls back to default' => [['sort' => 'c.email; DROP TABLE client'], 'c.createdAt', 'DESC', null],
    'invalid direction falls back to ascending' => [['sort' => 'email', 'direction' => 'sideways'], 'c.email', 'ASC', 'ASC'],
]);

test('loads list balances and group titles in one batch', function (): void {
    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('fetchAllAssociative')
        ->once()
        ->with(
            Mockery::pattern('/FROM client c/'),
            ['ids' => [7, 9]],
            ['ids' => ArrayParameterType::INTEGER],
        )
        ->andReturn([
            ['id' => '7', 'balance' => '12.50', 'group_title' => 'VIP'],
            ['id' => '9', 'balance' => '0.00', 'group_title' => null],
        ]);

    $entityManager = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $entityManager->shouldReceive('getConnection')->once()->andReturn($connection);
    $metadata = Mockery::mock(Doctrine\ORM\Mapping\ClassMetadata::class);
    $metadata->name = Client::class;
    $repository = new ClientRepository($entityManager, $metadata);

    expect($repository->getListContext([7, 9]))->toBe([
        7 => ['balance' => 12.5, 'group' => 'VIP'],
        9 => ['balance' => 0.0, 'group' => null],
    ]);
});
