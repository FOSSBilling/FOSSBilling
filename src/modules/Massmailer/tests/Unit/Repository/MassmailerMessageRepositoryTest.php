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

test('sorts massmailer message search query', function (array $data, string $expectedOrder, string $expectedDirection, ?string $expectedTieBreakerDirection): void {
    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('orderBy')->with($expectedOrder, $expectedDirection)->once()->andReturn($queryBuilder);
    if ($expectedTieBreakerDirection !== null) {
        $queryBuilder->shouldReceive('addOrderBy')->with('m.id', $expectedTieBreakerDirection)->once()->andReturn($queryBuilder);
    } else {
        $queryBuilder->shouldReceive('addOrderBy')->never();
    }

    $repository = Mockery::mock(MassmailerMessageRepository::class)->makePartial();
    $repository->shouldReceive('createQueryBuilder')->with('m')->once()->andReturn($queryBuilder);

    expect($repository->getSearchQueryBuilder($data))->toBe($queryBuilder);
})->with([
    'subject ascending' => [['sort' => 'subject'], 'm.subject', 'ASC', 'ASC'],
    'subject descending' => [['sort' => 'subject', 'direction' => 'DESC'], 'm.subject', 'DESC', 'DESC'],
    'status' => [['sort' => 'status', 'direction' => 'desc'], 'm.status', 'DESC', 'DESC'],
    'from email' => [['sort' => 'from_email'], 'm.fromEmail', 'ASC', 'ASC'],
    'from name' => [['sort' => 'from_name'], 'm.fromName', 'ASC', 'ASC'],
    'sent at' => [['sort' => 'sent_at'], 'm.sentAt', 'ASC', 'ASC'],
    'created at' => [['sort' => 'created_at'], 'm.createdAt', 'ASC', 'ASC'],
    'updated at' => [['sort' => 'updated_at', 'direction' => 'DESC'], 'm.updatedAt', 'DESC', 'DESC'],
    'id' => [['sort' => 'id'], 'm.id', 'ASC', null],
    'invalid sort falls back to default' => [['sort' => 'm.subject; DROP TABLE mod_massmailer'], 'm.createdAt', 'DESC', null],
    'invalid direction falls back to ascending' => [['sort' => 'subject', 'direction' => 'sideways'], 'm.subject', 'ASC', 'ASC'],
]);
