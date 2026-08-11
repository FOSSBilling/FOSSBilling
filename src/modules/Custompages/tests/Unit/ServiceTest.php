<?php

declare(strict_types=1);

use FOSSBilling\Pagination;
use FOSSBilling\PaginationOptions;

test('search pages builds expanded search query', function (): void {
    $pager = Mockery::mock(Pagination::class);
    $pager->shouldReceive('getPaginatedResultSet')->once()->andReturnUsing(
        function (string $query, array $params, PaginationOptions $opts) {
            expect($query)->toContain('title LIKE :q');
            expect($query)->toContain('slug LIKE :q');
            expect($query)->toContain('description LIKE :q');
            expect($query)->toContain('keywords LIKE :q');
            expect($query)->toContain('content LIKE :q');
            expect($params)->toBe(['q' => '%landing%']);

            return ['list' => []];
        },
    );

    $di = new Pimple\Container();
    $di['pager'] = $pager;

    $service = new Box\Mod\Custompages\Service();
    $service->setDi($di);

    expect($service->searchPages(['search' => 'landing']))->toBe(['list' => []]);
});

test('search pages builds filter query', function (): void {
    $pager = Mockery::mock(Pagination::class);
    $pager->shouldReceive('getPaginatedResultSet')->once()->andReturnUsing(
        function (string $query, array $params, PaginationOptions $opts) {
            expect($query)->toContain('id = :id');
            expect($query)->toContain('slug LIKE :slug');
            expect($params)->toBe(['id' => 12, 'slug' => '%docs%']);

            return ['list' => []];
        },
    );

    $di = new Pimple\Container();
    $di['pager'] = $pager;

    $service = new Box\Mod\Custompages\Service();
    $service->setDi($di);

    expect($service->searchPages(['id' => '12', 'slug' => 'docs']))->toBe(['list' => []]);
});
