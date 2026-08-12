<?php

declare(strict_types=1);

use Box\Mod\Custompages\Repository\CustomPageRepository;
use Doctrine\ORM\QueryBuilder;

test('get search query builder applies no filters by default', function (): void {
    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('orderBy')->with('p.id', 'DESC')->once()->andReturn($queryBuilder);
    $queryBuilder->shouldReceive('andWhere')->never();
    $queryBuilder->shouldReceive('setParameter')->never();

    $repository = Mockery::mock(CustomPageRepository::class)->makePartial();
    $repository->shouldReceive('createQueryBuilder')->with('p')->once()->andReturn($queryBuilder);

    $result = $repository->getSearchQueryBuilder([]);

    expect($result)->toBe($queryBuilder);
});

test('get search query builder builds all supported filters', function (): void {
    $whereCalls = [];
    $parameters = [];

    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('andWhere')->times(3)->andReturnUsing(function (string $clause) use (&$whereCalls, $queryBuilder) {
        $whereCalls[] = $clause;

        return $queryBuilder;
    });
    $queryBuilder->shouldReceive('setParameter')->times(3)->andReturnUsing(function (string $name, mixed $value) use (&$parameters, $queryBuilder) {
        $parameters[$name] = $value;

        return $queryBuilder;
    });
    $queryBuilder->shouldReceive('orderBy')->with('p.id', 'DESC')->once()->andReturn($queryBuilder);

    $repository = Mockery::mock(CustomPageRepository::class)->makePartial();
    $repository->shouldReceive('createQueryBuilder')->with('p')->once()->andReturn($queryBuilder);

    $result = $repository->getSearchQueryBuilder([
        'id' => '7',
        'slug' => 'docs',
        'search' => 'landing',
    ]);

    expect($result)->toBe($queryBuilder);
    expect($whereCalls)->toBe([
        'p.id = :id',
        'p.slug LIKE :slug',
        '(p.title LIKE :q OR p.slug LIKE :q OR p.description LIKE :q OR p.keywords LIKE :q OR p.content LIKE :q)',
    ]);
    expect($parameters)->toBe([
        'id' => 7,
        'slug' => '%docs%',
        'q' => '%landing%',
    ]);
});

test('find one by slug excluding id builds slug and id predicates', function (): void {
    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('andWhere')->once()->with('p.slug = :slug')->andReturn($queryBuilder);
    $queryBuilder->shouldReceive('andWhere')->once()->with('p.id != :id')->andReturn($queryBuilder);
    $queryBuilder->shouldReceive('setParameter')->once()->with('slug', 'about')->andReturn($queryBuilder);
    $queryBuilder->shouldReceive('setParameter')->once()->with('id', 5)->andReturn($queryBuilder);
    $queryBuilder->shouldReceive('setMaxResults')->once()->with(1)->andReturn($queryBuilder);

    $query = Mockery::mock(Doctrine\ORM\Query::class);
    $query->shouldReceive('getOneOrNullResult')->once()->andReturn(null);
    $queryBuilder->shouldReceive('getQuery')->once()->andReturn($query);

    $repository = Mockery::mock(CustomPageRepository::class)->makePartial();
    $repository->shouldReceive('createQueryBuilder')->with('p')->once()->andReturn($queryBuilder);

    expect($repository->findOneBySlugExcludingId('about', 5))->toBeNull();
});
