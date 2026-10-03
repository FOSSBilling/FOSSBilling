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
    $queryBuilder->shouldReceive('orderBy')->with('e.id', SortDirection::Descending)->once()->andReturn($queryBuilder);

    $repository = Mockery::mock(ActivityClientEmailRepository::class)->makePartial();
    $repository->shouldReceive('createQueryBuilder')->with('e')->once()->andReturn($queryBuilder);

    $result = $repository->getSearchQueryBuilder([]);

    expect($result)->toBe($queryBuilder);
    expect($selectCalls)->toHaveCount(1);
    expect($selectCalls[0])->toContain('attachmentName');
    expect($selectCalls[0])->toContain('attachmentMime');
    expect($selectCalls[0])->not->toContain('attachmentContent');
});

test('sorts email search query', function (array $data, string $expectedOrder, SortDirection $expectedDirection, ?SortDirection $expectedTieBreakerDirection): void {
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
    'sender descending' => [['sort' => 'sender', 'direction' => 'DESC'], 'e.sender', SortDirection::Descending, SortDirection::Descending],
    'recipient' => [['sort' => 'recipient'], 'e.recipients', SortDirection::Ascending, SortDirection::Ascending],
    'subject' => [['sort' => 'subject', 'direction' => 'desc'], 'e.subject', SortDirection::Descending, SortDirection::Descending],
    'created at' => [['sort' => 'created_at'], 'e.createdAt', SortDirection::Ascending, SortDirection::Ascending],
    'updated at' => [['sort' => 'updated_at', 'direction' => 'DESC'], 'e.updatedAt', SortDirection::Descending, SortDirection::Descending],
    'id' => [['sort' => 'id'], 'e.id', SortDirection::Ascending, null],
    'invalid sort falls back to default' => [['sort' => 'e.subject; DROP TABLE activity_client_email'], 'e.id', SortDirection::Descending, null],
    'invalid direction falls back to ascending' => [['sort' => 'subject', 'direction' => 'sideways'], 'e.subject', SortDirection::Ascending, SortDirection::Ascending],
]);
