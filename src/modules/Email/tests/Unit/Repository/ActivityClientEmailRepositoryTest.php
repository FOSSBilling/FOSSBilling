<?php

declare(strict_types=1);

use Box\Mod\Email\Repository\ActivityClientEmailRepository;
use Doctrine\ORM\QueryBuilder;

test('get search query builder excludes the attachment blob from the select', function (): void {
    $selectCalls = [];

    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('select')->once()->andReturnUsing(function (string $select) use (&$selectCalls, $queryBuilder) {
        $selectCalls[] = $select;

        return $queryBuilder;
    });
    $queryBuilder->shouldReceive('orderBy')->with('e.id', 'DESC')->once()->andReturn($queryBuilder);

    $repository = Mockery::mock(ActivityClientEmailRepository::class)->makePartial();
    $repository->shouldReceive('createQueryBuilder')->with('e')->once()->andReturn($queryBuilder);

    $result = $repository->getSearchQueryBuilder([]);

    expect($result)->toBe($queryBuilder);
    expect($selectCalls)->toHaveCount(1);
    expect($selectCalls[0])->toContain('attachmentName');
    expect($selectCalls[0])->toContain('attachmentMime');
    expect($selectCalls[0])->not->toContain('attachmentContent');
});

test('sorts email search query', function (array $data, string $expectedOrder, string $expectedDirection, ?string $expectedTieBreakerDirection): void {
    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('select')->once()->andReturn($queryBuilder);
    $queryBuilder->shouldReceive('orderBy')->once()->with($expectedOrder, $expectedDirection)->andReturn($queryBuilder);
    if ($expectedTieBreakerDirection !== null) {
        $queryBuilder->shouldReceive('addOrderBy')->once()->with('e.id', $expectedTieBreakerDirection)->andReturn($queryBuilder);
    } else {
        $queryBuilder->shouldReceive('addOrderBy')->never();
    }

    $repository = Mockery::mock(ActivityClientEmailRepository::class)->makePartial();
    $repository->shouldReceive('createQueryBuilder')->with('e')->once()->andReturn($queryBuilder);

    expect($repository->getSearchQueryBuilder($data))->toBe($queryBuilder);
})->with([
    'sender descending' => [['sort' => 'sender', 'direction' => 'DESC'], 'e.sender', 'DESC', 'DESC'],
    'recipient' => [['sort' => 'recipient'], 'e.recipients', 'ASC', 'ASC'],
    'subject' => [['sort' => 'subject', 'direction' => 'desc'], 'e.subject', 'DESC', 'DESC'],
    'created at' => [['sort' => 'created_at'], 'e.createdAt', 'ASC', 'ASC'],
    'updated at' => [['sort' => 'updated_at', 'direction' => 'DESC'], 'e.updatedAt', 'DESC', 'DESC'],
    'id' => [['sort' => 'id'], 'e.id', 'ASC', null],
    'invalid sort falls back to default' => [['sort' => 'e.subject; DROP TABLE activity_client_email'], 'e.id', 'DESC', null],
    'invalid direction falls back to ascending' => [['sort' => 'subject', 'direction' => 'sideways'], 'e.subject', 'ASC', 'ASC'],
]);
