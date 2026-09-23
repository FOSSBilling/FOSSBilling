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

test('sorts email queue search query', function (array $data, string $expectedOrder, string $expectedDirection, ?array $expectedTieBreaker): void {
    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('orderBy')->once()->with($expectedOrder, $expectedDirection)->andReturn($queryBuilder);
    if ($expectedTieBreaker !== null) {
        $queryBuilder->shouldReceive('addOrderBy')->once()->with($expectedTieBreaker[0], $expectedTieBreaker[1])->andReturn($queryBuilder);
    } else {
        $queryBuilder->shouldReceive('addOrderBy')->never();
    }

    $repository = Mockery::mock(QueuedEmailRepository::class)->makePartial();
    $repository->shouldReceive('createQueryBuilder')->with('q')->once()->andReturn($queryBuilder);

    expect($repository->getSearchQueryBuilder($data))->toBe($queryBuilder);
})->with([
    'subject ascending' => [['sort' => 'subject'], 'q.subject', 'ASC', ['q.id', 'ASC']],
    'recipient descending' => [['sort' => 'recipient', 'direction' => 'DESC'], 'q.recipient', 'DESC', ['q.id', 'DESC']],
    'status' => [['sort' => 'status'], 'q.status', 'ASC', ['q.id', 'ASC']],
    'tries' => [['sort' => 'tries', 'direction' => 'desc'], 'q.tries', 'DESC', ['q.id', 'DESC']],
    'priority' => [['sort' => 'priority'], 'q.priority', 'ASC', ['q.id', 'ASC']],
    'created at' => [['sort' => 'created_at'], 'q.createdAt', 'ASC', ['q.id', 'ASC']],
    'id' => [['sort' => 'id'], 'q.id', 'ASC', null],
    'invalid sort falls back to default' => [['sort' => 'q.subject; DROP TABLE mod_email_queue'], 'q.priority', 'DESC', ['q.id', 'ASC']],
    'invalid direction falls back to ascending' => [['sort' => 'subject', 'direction' => 'sideways'], 'q.subject', 'ASC', ['q.id', 'ASC']],
]);
