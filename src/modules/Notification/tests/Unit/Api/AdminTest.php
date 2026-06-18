<?php

declare(strict_types=1);

use Box\Mod\Extension\Entity\ExtensionMeta;
use FOSSBilling\PaginationOptions;

use function Tests\Helpers\moduleService;

test('get list uses doctrine pagination', function (): void {
    $queryBuilder = Mockery::mock(Doctrine\ORM\QueryBuilder::class);

    $service = Mockery::mock(Box\Mod\Notification\Service::class);
    $service->shouldReceive('getSearchQueryBuilder')->with(['per_page' => 10])->once()->andReturn($queryBuilder);

    $pager = Mockery::mock(FOSSBilling\Pagination::class);
    $pager->shouldReceive('paginateDoctrineQuery')->once()->andReturnUsing(
        function ($qb, PaginationOptions $pagination) use ($queryBuilder) {
            expect($qb)->toBe($queryBuilder);
            expect($pagination->perPage)->toBe(10);

            return ['list' => []];
        },
    );

    $di = new Pimple\Container();
    $di['pager'] = $pager;
    $di['mod_service'] = $di->protect(moduleService());

    $api = new Box\Mod\Notification\Api\Admin();
    $api->setDi($di);
    $api->setService($service);

    expect($api->get_list(['per_page' => 10]))->toBe(['list' => []]);
});

test('get returns mapped notification', function (): void {
    $meta = (new ExtensionMeta())
        ->setExtension('mod_notification')
        ->setMetaKey('message')
        ->setMetaValue('Test');

    $service = Mockery::mock(Box\Mod\Notification\Service::class);
    $service->shouldReceive('get')->with(5)->once()->andReturn($meta);
    $service->shouldReceive('toApiArray')->with($meta)->once()->andReturn(['id' => 5, 'meta_value' => 'Test']);

    $api = new Box\Mod\Notification\Api\Admin();
    $api->setService($service);

    expect($api->get(['id' => 5]))->toBe(['id' => 5, 'meta_value' => 'Test']);
});

test('delete delegates to service', function (): void {
    $service = Mockery::mock(Box\Mod\Notification\Service::class);
    $service->shouldReceive('delete')->with(9)->once()->andReturn(true);

    $api = new Box\Mod\Notification\Api\Admin();
    $api->setService($service);

    expect($api->delete(['id' => 9]))->toBeTrue();
});
