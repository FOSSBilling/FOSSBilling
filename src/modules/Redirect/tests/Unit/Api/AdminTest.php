<?php

declare(strict_types=1);

use Box\Mod\Extension\Entity\ExtensionMeta;

test('get returns mapped redirect', function (): void {
    $redirect = (new ExtensionMeta())
        ->setExtension('mod_redirect')
        ->setMetaKey('old-page')
        ->setMetaValue('new-page');

    $service = Mockery::mock(Box\Mod\Redirect\Service::class);
    $service->shouldReceive('get')->with(3)->once()->andReturn($redirect);
    $service->shouldReceive('toApiArray')->with($redirect)->once()->andReturn(['id' => 3, 'path' => 'old-page', 'target' => 'new-page']);

    $api = new Box\Mod\Redirect\Api\Admin();
    $api->setService($service);

    expect($api->get(['id' => 3]))->toBe(['id' => 3, 'path' => 'old-page', 'target' => 'new-page']);
});

test('create delegates to service', function (): void {
    $service = Mockery::mock(Box\Mod\Redirect\Service::class);
    $service->shouldReceive('create')->with('/old-page/', '/new-page/')->once()->andReturn(7);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('checkPermissionsAndThrowException')->with('redirect', 'create_and_edit', Mockery::any(), Mockery::any())->once();

    $di = new Pimple\Container();
    $di['logger'] = new Box_Log();
    $di['mod_service'] = $di->protect(fn (string $name): object => match (strtolower($name)) {
        'staff' => $staffService,
        default => throw new RuntimeException("Unexpected mod service: $name"),
    });

    $api = new Box\Mod\Redirect\Api\Admin();
    $api->setDi($di);
    $api->setService($service);

    expect($api->create(['path' => '/old-page/', 'target' => '/new-page/']))->toBe(7);
});

test('delete delegates to service entity', function (): void {
    $redirect = (new ExtensionMeta())
        ->setExtension('mod_redirect')
        ->setMetaKey('old-page')
        ->setMetaValue('new-page');

    $service = Mockery::mock(Box\Mod\Redirect\Service::class);
    $service->shouldReceive('get')->with(4)->once()->andReturn($redirect);
    $service->shouldReceive('delete')->with($redirect)->once()->andReturn(true);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('checkPermissionsAndThrowException')->with('redirect', 'delete', Mockery::any(), Mockery::any())->once();

    $di = new Pimple\Container();
    $di['logger'] = new Box_Log();
    $di['mod_service'] = $di->protect(fn (string $name): object => match (strtolower($name)) {
        'staff' => $staffService,
        default => throw new RuntimeException("Unexpected mod service: $name"),
    });

    $api = new Box\Mod\Redirect\Api\Admin();
    $api->setDi($di);
    $api->setService($service);

    expect($api->delete(['id' => 4]))->toBeTrue();
});
