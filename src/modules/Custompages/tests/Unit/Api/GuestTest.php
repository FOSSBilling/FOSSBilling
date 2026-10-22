<?php

declare(strict_types=1);

use Box\Mod\Custompages\Api\Guest;
use Box\Mod\Custompages\Service;

test('guest get list delegates to search pages', function (): void {
    $api = apiEndpoint(new Guest());

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('searchPages')->once()->with(['per_page' => 500])->andReturn(['list' => [], 'total' => 0]);

    $api->setService($serviceMock);

    expect($api->get_list(['per_page' => 500]))->toBe(['list' => [], 'total' => 0]);
});

test('guest get by slug returns page', function (): void {
    $api = apiEndpoint(new Guest());

    $page = ['id' => 3, 'slug' => 'about', 'title' => 'About'];
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getPage')->once()->with('about', 'slug')->andReturn($page);

    $api->setService($serviceMock);

    expect($api->get(['slug' => 'about']))->toBe($page);
});

test('guest get by id returns page', function (): void {
    $api = apiEndpoint(new Guest());

    $page = ['id' => 3, 'slug' => 'about', 'title' => 'About'];
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getPage')->once()->with(3)->andReturn($page);

    $api->setService($serviceMock);

    expect($api->get(['id' => 3]))->toBe($page);
});

test('guest get throws when id and slug are missing', function (): void {
    $api = apiEndpoint(new Guest());
    $api->setService(Mockery::mock(Service::class));

    expect(fn () => $api->get([]))->toThrow(FOSSBilling\InformationException::class, 'Custom page ID or slug is missing');
});

test('guest get throws when page is not found', function (): void {
    $api = apiEndpoint(new Guest());

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getPage')->once()->with('missing', 'slug')->andReturn(null);

    $api->setService($serviceMock);

    expect(fn () => $api->get(['slug' => 'missing']))->toThrow(FOSSBilling\InformationException::class, 'Custom page not found');
});
