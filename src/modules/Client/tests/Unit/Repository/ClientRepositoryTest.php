<?php

declare(strict_types=1);

use Box\Mod\Client\Entity\Client;
use Box\Mod\Client\Repository\ClientRepository;
use Doctrine\ORM\QueryBuilder;

test('builds a Doctrine client search with the legacy filters', function (): void {
    $where = [];
    $parameters = [];

    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('andWhere')->times(10)->andReturnUsing(function (string $clause) use (&$where, $queryBuilder) {
        $where[] = $clause;

        return $queryBuilder;
    });
    $queryBuilder->shouldReceive('setParameter')->times(14)->andReturnUsing(function (string $name, mixed $value) use (&$parameters, $queryBuilder) {
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
            'c.id = :id',
            '(c.firstName LIKE :name OR c.lastName LIKE :name)',
            "DATE_FORMAT(c.createdAt, '%Y-%m-%d') = :created_at",
            "(c.company LIKE :s_company OR c.firstName LIKE :s_first_name OR c.lastName LIKE :s_last_name OR c.email LIKE :s_email OR CONCAT(c.firstName, ' ', c.lastName) LIKE :full_name)",
        )
        ->and($parameters['name'])->toBe('%Ada%')
        ->and($parameters['s_company'])->toBe('%Lovelace%')
        ->and($parameters['date_from'])->toBeInstanceOf(\DateTime::class);
});

test('uses exact id matching for a numeric smart search', function (): void {
    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('andWhere')->once()->with('(c.id = :cid OR c.aid = :caid)')->andReturn($queryBuilder);
    $queryBuilder->shouldReceive('setParameter')->twice()->andReturn($queryBuilder);
    $queryBuilder->shouldReceive('orderBy')->once()->andReturn($queryBuilder);

    $repository = Mockery::mock(ClientRepository::class)->makePartial();
    $repository->shouldReceive('createQueryBuilder')->once()->andReturn($queryBuilder);

    expect($repository->getSearchQueryBuilder(['search' => '42']))->toBe($queryBuilder);
});

test('loads list balances and group titles in one batch', function (): void {
    $entityManager = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $metadata = Mockery::mock(Doctrine\ORM\Mapping\ClassMetadata::class);
    $metadata->name = Client::class;
    $repository = Mockery::mock(ClientRepository::class, [$entityManager, $metadata])->makePartial();
    $repository->shouldReceive('getListContext')->once()->with([7, 9])->andReturn([
        7 => ['balance' => 12.5, 'group' => 'VIP'],
        9 => ['balance' => 0.0, 'group' => null],
    ]);

    expect($repository->getListContext([7, 9]))->toBe([
        7 => ['balance' => 12.5, 'group' => 'VIP'],
        9 => ['balance' => 0.0, 'group' => null],
    ]);
});
