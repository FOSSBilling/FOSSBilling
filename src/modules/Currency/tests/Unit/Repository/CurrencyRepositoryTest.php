<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Currency\Repository\CurrencyRepository;
use Doctrine\ORM\QueryBuilder;

test('get search query builder orders by code by default', function (): void {
    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('orderBy')->once()->with('c.code', 'ASC')->andReturn($queryBuilder);

    $repository = Mockery::mock(CurrencyRepository::class)->makePartial();
    $repository->shouldReceive('createQueryBuilder')->once()->with('c')->andReturn($queryBuilder);

    expect($repository->getSearchQueryBuilder([]))->toBe($queryBuilder);
});

test('sorts currency search query', function (array $data, string $expectedOrder, string $expectedDirection, ?string $expectedTieBreakerDirection): void {
    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('orderBy')->once()->with($expectedOrder, $expectedDirection)->andReturn($queryBuilder);
    if ($expectedTieBreakerDirection !== null) {
        $queryBuilder->shouldReceive('addOrderBy')->once()->with('c.id', $expectedTieBreakerDirection)->andReturn($queryBuilder);
    } else {
        $queryBuilder->shouldReceive('addOrderBy')->never();
    }

    $repository = Mockery::mock(CurrencyRepository::class)->makePartial();
    $repository->shouldReceive('createQueryBuilder')->once()->with('c')->andReturn($queryBuilder);

    expect($repository->getSearchQueryBuilder($data))->toBe($queryBuilder);
})->with([
    'code ascending' => [['sort' => 'code'], 'c.code', 'ASC', 'ASC'],
    'code descending' => [['sort' => 'code', 'direction' => 'DESC'], 'c.code', 'DESC', 'DESC'],
    'conversion rate' => [['sort' => 'conversion_rate', 'direction' => 'desc'], 'c.conversionRate', 'DESC', 'DESC'],
    'created at' => [['sort' => 'created_at'], 'c.createdAt', 'ASC', 'ASC'],
    'updated at' => [['sort' => 'updated_at', 'direction' => 'DESC'], 'c.updatedAt', 'DESC', 'DESC'],
    'id' => [['sort' => 'id'], 'c.id', 'ASC', null],
    'invalid sort falls back to default' => [['sort' => 'c.code; DROP TABLE currency'], 'c.code', 'ASC', null],
    'invalid direction falls back to ascending' => [['sort' => 'code', 'direction' => 'sideways'], 'c.code', 'ASC', 'ASC'],
]);
