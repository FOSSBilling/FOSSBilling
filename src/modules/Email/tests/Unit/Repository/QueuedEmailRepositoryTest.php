<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Email\Repository\QueuedEmailRepository;
use Doctrine\ORM\QueryBuilder;

test('get search query builder orders by priority by default', function (): void {
    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('orderBy')->once()->with('q.priority', 'DESC')->andReturn($queryBuilder);
    $queryBuilder->shouldReceive('addOrderBy')->once()->with('q.id', 'ASC')->andReturn($queryBuilder);

    $repository = Mockery::mock(QueuedEmailRepository::class)->makePartial();
    $repository->shouldReceive('createQueryBuilder')->with('q')->once()->andReturn($queryBuilder);

    expect($repository->getSearchQueryBuilder([]))->toBe($queryBuilder);
});

test('sorts email queue search query', function (array $data, string $expectedOrder, string $expectedDirection): void {
    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('orderBy')->once()->with($expectedOrder, $expectedDirection)->andReturn($queryBuilder);
    $queryBuilder->shouldReceive('addOrderBy')->once()->with('q.id', 'ASC')->andReturn($queryBuilder);

    $repository = Mockery::mock(QueuedEmailRepository::class)->makePartial();
    $repository->shouldReceive('createQueryBuilder')->with('q')->once()->andReturn($queryBuilder);

    expect($repository->getSearchQueryBuilder($data))->toBe($queryBuilder);
})->with([
    'subject ascending' => [['sort' => 'subject'], 'q.subject', 'ASC'],
    'recipient descending' => [['sort' => 'recipient', 'direction' => 'DESC'], 'q.recipient', 'DESC'],
    'status' => [['sort' => 'status'], 'q.status', 'ASC'],
    'tries' => [['sort' => 'tries', 'direction' => 'desc'], 'q.tries', 'DESC'],
    'priority' => [['sort' => 'priority'], 'q.priority', 'ASC'],
    'created at' => [['sort' => 'created_at'], 'q.createdAt', 'ASC'],
    'id' => [['sort' => 'id'], 'q.id', 'ASC'],
    'invalid sort falls back to default' => [['sort' => 'q.subject; DROP TABLE mod_email_queue'], 'q.priority', 'DESC'],
    'invalid direction falls back to ascending' => [['sort' => 'subject', 'direction' => 'sideways'], 'q.subject', 'ASC'],
]);
