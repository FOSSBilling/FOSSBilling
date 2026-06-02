<?php

declare(strict_types=1);

use Box\Mod\Massmailer\Repository\MassmailerMessageRepository;
use Doctrine\ORM\QueryBuilder;

test('get search query builder adds ID filter when present', function (): void {
    $whereCalls = [];
    $parameters = [];

    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('andWhere')->twice()->andReturnUsing(function (string $clause) use (&$whereCalls, $queryBuilder) {
        $whereCalls[] = $clause;

        return $queryBuilder;
    });
    $queryBuilder->shouldReceive('setParameter')->twice()->andReturnUsing(function (string $name, mixed $value) use (&$parameters, $queryBuilder) {
        $parameters[$name] = $value;

        return $queryBuilder;
    });
    $queryBuilder->shouldReceive('orderBy')->with('m.createdAt', 'DESC')->once()->andReturn($queryBuilder);

    $repository = Mockery::mock(MassmailerMessageRepository::class)->makePartial();
    $repository->shouldReceive('createQueryBuilder')->with('m')->once()->andReturn($queryBuilder);

    $result = $repository->getSearchQueryBuilder(['id' => '42', 'status' => 'draft']);

    expect($result)->toBe($queryBuilder);
    expect($whereCalls)->toBe(['m.id = :id', 'm.status = :status']);
    expect($parameters)->toBe(['id' => 42, 'status' => 'draft']);
});

test('get search query builder groups search clause when status filter is present', function (): void {
    $whereCalls = [];
    $parameters = [];

    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('andWhere')->twice()->andReturnUsing(function (string $clause) use (&$whereCalls, $queryBuilder) {
        $whereCalls[] = $clause;

        return $queryBuilder;
    });
    $queryBuilder->shouldReceive('setParameter')->twice()->andReturnUsing(function (string $name, mixed $value) use (&$parameters, $queryBuilder) {
        $parameters[$name] = $value;

        return $queryBuilder;
    });
    $queryBuilder->shouldReceive('orderBy')->with('m.createdAt', 'DESC')->once()->andReturn($queryBuilder);

    $repository = Mockery::mock(MassmailerMessageRepository::class)->makePartial();
    $repository->shouldReceive('createQueryBuilder')->with('m')->once()->andReturn($queryBuilder);

    $result = $repository->getSearchQueryBuilder(['status' => 'draft', 'search' => 'newsletter']);

    expect($result)->toBe($queryBuilder);
    expect($whereCalls)->toBe([
        'm.status = :status',
        '(m.subject LIKE :search OR m.content LIKE :search OR m.fromEmail LIKE :search OR m.fromName LIKE :search)',
    ]);
    expect($parameters)->toBe(['status' => 'draft', 'search' => '%newsletter%']);
});
