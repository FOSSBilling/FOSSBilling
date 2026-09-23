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
    $queryBuilder->shouldReceive('orderBy')->once()->with('t.category', 'ASC')->andReturn($queryBuilder);
    $queryBuilder->shouldReceive('addOrderBy')->once()->with('t.actionCode', 'ASC')->andReturn($queryBuilder);

    $repository = Mockery::mock(EmailTemplateRepository::class)->makePartial();
    $repository->shouldReceive('createQueryBuilder')->with('t')->once()->andReturn($queryBuilder);

    expect($repository->getSearchQueryBuilder([]))->toBe($queryBuilder);
});

test('sorts email template search query', function (array $data, string $expectedOrder, string $expectedDirection): void {
    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('orderBy')->once()->with($expectedOrder, $expectedDirection)->andReturn($queryBuilder);
    $queryBuilder->shouldReceive('addOrderBy')->once()->with('t.actionCode', 'ASC')->andReturn($queryBuilder);

    $repository = Mockery::mock(EmailTemplateRepository::class)->makePartial();
    $repository->shouldReceive('createQueryBuilder')->with('t')->once()->andReturn($queryBuilder);

    expect($repository->getSearchQueryBuilder($data))->toBe($queryBuilder);
})->with([
    'code ascending' => [['sort' => 'code'], 't.actionCode', 'ASC'],
    'code descending' => [['sort' => 'code', 'direction' => 'DESC'], 't.actionCode', 'DESC'],
    'category' => [['sort' => 'category'], 't.category', 'ASC'],
    'subject' => [['sort' => 'subject', 'direction' => 'desc'], 't.subject', 'DESC'],
    'enabled' => [['sort' => 'enabled'], 't.enabled', 'ASC'],
    'id' => [['sort' => 'id'], 't.id', 'ASC'],
    'invalid sort falls back to default' => [['sort' => 't.actionCode; DROP TABLE email_template'], 't.category', 'ASC'],
    'invalid direction falls back to ascending' => [['sort' => 'code', 'direction' => 'sideways'], 't.actionCode', 'ASC'],
]);
