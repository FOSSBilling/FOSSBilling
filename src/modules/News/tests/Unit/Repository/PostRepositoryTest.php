<?php

declare(strict_types=1);

use Box\Mod\News\Repository\PostRepository;
use Doctrine\ORM\QueryBuilder;

test('get search query builder builds all supported filters', function (): void {
    $whereCalls = [];
    $parameters = [];

    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('andWhere')->times(4)->andReturnUsing(function (string $clause) use (&$whereCalls, $queryBuilder) {
        $whereCalls[] = $clause;

        return $queryBuilder;
    });
    $queryBuilder->shouldReceive('setParameter')->times(4)->andReturnUsing(function (string $name, mixed $value) use (&$parameters, $queryBuilder) {
        $parameters[$name] = $value;

        return $queryBuilder;
    });
    $queryBuilder->shouldReceive('orderBy')->with('p.createdAt', 'DESC')->once()->andReturn($queryBuilder);

    $repository = Mockery::mock(PostRepository::class)->makePartial();
    $repository->shouldReceive('createQueryBuilder')->with('p')->once()->andReturn($queryBuilder);

    $result = $repository->getSearchQueryBuilder([
        'id' => '7',
        'status' => 'active',
        'search' => 'release',
        'section' => 'billing',
    ]);

    expect($result)->toBe($queryBuilder);
    expect($whereCalls)->toBe([
        'p.id = :id',
        'p.status = :status',
        '(p.title LIKE :search OR p.slug LIKE :search OR COALESCE(p.description, \'\') LIKE :search OR COALESCE(p.section, \'\') LIKE :search OR COALESCE(p.content, \'\') LIKE :search)',
        'p.section LIKE :section',
    ]);
    expect($parameters)->toBe([
        'id' => 7,
        'status' => 'active',
        'search' => '%release%',
        'section' => '%billing%',
    ]);
});

test('sorts post search query', function (array $data, string $expectedOrder, string $expectedDirection, ?string $expectedTieBreakerDirection): void {
    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('orderBy')->with($expectedOrder, $expectedDirection)->once()->andReturn($queryBuilder);
    if ($expectedTieBreakerDirection !== null) {
        $queryBuilder->shouldReceive('addOrderBy')->with('p.id', $expectedTieBreakerDirection)->once()->andReturn($queryBuilder);
    } else {
        $queryBuilder->shouldReceive('addOrderBy')->never();
    }

    $repository = Mockery::mock(PostRepository::class)->makePartial();
    $repository->shouldReceive('createQueryBuilder')->with('p')->once()->andReturn($queryBuilder);

    expect($repository->getSearchQueryBuilder($data))->toBe($queryBuilder);
})->with([
    'title ascending' => [['sort' => 'title'], 'p.title', 'ASC', 'ASC'],
    'title descending' => [['sort' => 'title', 'direction' => 'DESC'], 'p.title', 'DESC', 'DESC'],
    'slug' => [['sort' => 'slug'], 'p.slug', 'ASC', 'ASC'],
    'status' => [['sort' => 'status', 'direction' => 'desc'], 'p.status', 'DESC', 'DESC'],
    'section' => [['sort' => 'section'], 'p.section', 'ASC', 'ASC'],
    'created at' => [['sort' => 'created_at'], 'p.createdAt', 'ASC', 'ASC'],
    'updated at' => [['sort' => 'updated_at', 'direction' => 'DESC'], 'p.updatedAt', 'DESC', 'DESC'],
    'published at' => [['sort' => 'published_at'], 'p.publishedAt', 'ASC', 'ASC'],
    'id' => [['sort' => 'id'], 'p.id', 'ASC', null],
    'invalid sort falls back to default' => [['sort' => 'p.title; DROP TABLE post'], 'p.createdAt', 'DESC', null],
    'invalid direction falls back to ascending' => [['sort' => 'title', 'direction' => 'sideways'], 'p.title', 'ASC', 'ASC'],
]);
