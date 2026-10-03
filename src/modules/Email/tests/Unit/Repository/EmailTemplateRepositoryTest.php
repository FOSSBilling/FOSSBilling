<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Email\Repository\EmailTemplateRepository;
use Doctrine\ORM\QueryBuilder;

test('get search query builder orders by category by default', function (): void {
    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('orderBy')->once()->with('t.category', SortDirection::Ascending)->andReturn($queryBuilder);
    $queryBuilder->shouldReceive('addOrderBy')->once()->with('t.actionCode', SortDirection::Ascending)->andReturn($queryBuilder);

    $repository = Mockery::mock(EmailTemplateRepository::class)->makePartial();
    $repository->shouldReceive('createQueryBuilder')->with('t')->once()->andReturn($queryBuilder);

    expect($repository->getSearchQueryBuilder([]))->toBe($queryBuilder);
});

test('sorts email template search query', function (array $data, string $expectedOrder, SortDirection $expectedDirection, ?array $expectedTieBreaker): void {
    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('orderBy')->once()->with($expectedOrder, $expectedDirection)->andReturn($queryBuilder);
    if ($expectedTieBreaker !== null) {
        $queryBuilder->shouldReceive('addOrderBy')->once()->with($expectedTieBreaker[0], $expectedTieBreaker[1])->andReturn($queryBuilder);
    } else {
        $queryBuilder->shouldReceive('addOrderBy')->never();
    }

    $repository = Mockery::mock(EmailTemplateRepository::class)->makePartial();
    $repository->shouldReceive('createQueryBuilder')->with('t')->once()->andReturn($queryBuilder);

    expect($repository->getSearchQueryBuilder($data))->toBe($queryBuilder);
})->with([
    'code ascending' => [['sort' => 'code'], 't.actionCode', SortDirection::Ascending, ['t.id', SortDirection::Ascending]],
    'code descending' => [['sort' => 'code', 'direction' => 'DESC'], 't.actionCode', SortDirection::Descending, ['t.id', SortDirection::Descending]],
    'category' => [['sort' => 'category'], 't.category', SortDirection::Ascending, ['t.id', SortDirection::Ascending]],
    'subject' => [['sort' => 'subject', 'direction' => 'desc'], 't.subject', SortDirection::Descending, ['t.id', SortDirection::Descending]],
    'enabled' => [['sort' => 'enabled'], 't.enabled', SortDirection::Ascending, ['t.id', SortDirection::Ascending]],
    'id' => [['sort' => 'id'], 't.id', SortDirection::Ascending, null],
    'invalid sort falls back to default' => [['sort' => 't.actionCode; DROP TABLE email_template'], 't.category', SortDirection::Ascending, ['t.actionCode', SortDirection::Ascending]],
    'invalid direction falls back to ascending' => [['sort' => 'code', 'direction' => 'sideways'], 't.actionCode', SortDirection::Ascending, ['t.id', SortDirection::Ascending]],
]);
