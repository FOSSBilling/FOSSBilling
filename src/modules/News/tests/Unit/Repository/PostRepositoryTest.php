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
