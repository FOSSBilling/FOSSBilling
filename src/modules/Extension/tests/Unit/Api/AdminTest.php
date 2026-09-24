<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Extension\Entity\Extension;
use Box\Mod\Extension\Event\AfterAdminDeactivateExtensionEvent;
use Box\Mod\Extension\Event\AfterAdminInstallExtensionEvent;
use Box\Mod\Extension\Event\AfterAdminUninstallExtensionEvent;
use Box\Mod\Extension\Event\AfterAdminUpdateExtensionEvent;
use Box\Mod\Extension\Event\BeforeAdminDeactivateExtensionEvent;
use Box\Mod\Extension\Event\BeforeAdminInstallExtensionEvent;
use Box\Mod\Extension\Event\BeforeAdminUninstallExtensionEvent;
use Box\Mod\Extension\Event\BeforeAdminUpdateExtensionEvent;
use Box\Mod\Extension\Repository\ExtensionRepository;
use Box\Mod\Extension\Service;
use Symfony\Component\EventDispatcher\EventDispatcher as SymfonyEventDispatcher;

use function Tests\Helpers\container;
use function Tests\Helpers\setEntityId;

test('getDi returns the dependency injection container', function (): void {
    $api = apiEndpoint(new Box\Mod\Extension\Api\Admin());
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toBe($di);
});

test('activate activates an extension', function (): void {
    $api = apiEndpoint(new Box\Mod\Extension\Api\Admin());
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('activateExistingExtension')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $api->setService($serviceMock);

    $result = $api->activate([]);
    expect($result)->toBeArray();
});

test('install dispatches typed lifecycle events with only extension identity', function (): void {
    $api = apiEndpoint(new Box\Mod\Extension\Api\Admin());
    $steps = [];
    $service = Mockery::mock(Service::class);
    $service->shouldReceive('downloadAndExtract')
        ->once()
        ->with('mod', 'example')
        ->andReturnUsing(function () use (&$steps): bool {
            $steps[] = 'download';

            return true;
        });
    $dispatcher = new SymfonyEventDispatcher();
    $dispatcher->addListener(BeforeAdminInstallExtensionEvent::class, static function (BeforeAdminInstallExtensionEvent $event) use (&$steps): void {
        $steps[] = $event;
    });
    $dispatcher->addListener(AfterAdminInstallExtensionEvent::class, static function (AfterAdminInstallExtensionEvent $event) use (&$steps): void {
        $steps[] = $event;
    });

    $di = $api->getDi();
    $di['event_dispatcher'] = $dispatcher;
    $api->setDi($di);
    $api->setService($service);

    $result = $api->install(['id' => 'example', 'type' => 'mod', 'api_key' => 'secret']);
    expect($result)->toBe(['success' => true, 'id' => 'example', 'type' => 'mod'])
        ->and($steps)->toHaveCount(3)
        ->and($steps[0])->toBeInstanceOf(BeforeAdminInstallExtensionEvent::class)
        ->and($steps[0]->extensionType)->toBe('mod')
        ->and($steps[0]->extensionName)->toBe('example')
        ->and($steps[1])->toBe('download')
        ->and($steps[2])->toBeInstanceOf(AfterAdminInstallExtensionEvent::class);
});

test('uninstall dispatches typed lifecycle events around service cleanup', function (): void {
    $api = apiEndpoint(new Box\Mod\Extension\Api\Admin());
    $steps = [];
    $service = Mockery::mock(Service::class);
    $service->shouldReceive('uninstall')
        ->once()
        ->with('mod', 'example')
        ->andReturnUsing(function () use (&$steps): bool {
            $steps[] = 'uninstall';

            return true;
        });
    $dispatcher = new SymfonyEventDispatcher();
    $dispatcher->addListener(BeforeAdminUninstallExtensionEvent::class, static function (BeforeAdminUninstallExtensionEvent $event) use (&$steps): void {
        $steps[] = $event;
    });
    $dispatcher->addListener(AfterAdminUninstallExtensionEvent::class, static function (AfterAdminUninstallExtensionEvent $event) use (&$steps): void {
        $steps[] = $event;
    });

    $di = $api->getDi();
    $di['event_dispatcher'] = $dispatcher;
    $api->setDi($di);
    $api->setService($service);

    expect($api->uninstall(['id' => 'example', 'type' => 'mod']))->toBeTrue()
        ->and($steps)->toHaveCount(3)
        ->and($steps[0])->toBeInstanceOf(BeforeAdminUninstallExtensionEvent::class)
        ->and($steps[0]->extensionType)->toBe('mod')
        ->and($steps[0]->extensionName)->toBe('example')
        ->and($steps[1])->toBe('uninstall')
        ->and($steps[2])->toBeInstanceOf(AfterAdminUninstallExtensionEvent::class);
});

test('deactivate dispatches typed events around service deactivation', function (): void {
    $api = apiEndpoint(new Box\Mod\Extension\Api\Admin());
    $extension = new Extension();
    setEntityId($extension, 52);
    $extension->setType('mod');
    $extension->setName('example');
    $repository = Mockery::mock(ExtensionRepository::class);
    $repository->shouldReceive('findOneByTypeAndName')
        ->once()
        ->with('mod', 'example')
        ->andReturn($extension);

    $steps = [];
    $service = Mockery::mock(Service::class);
    $service->shouldReceive('getExtensionRepository')->once()->andReturn($repository);
    $service->shouldReceive('deactivate')
        ->once()
        ->with($extension)
        ->andReturnUsing(function () use (&$steps): bool {
            $steps[] = 'deactivate';

            return true;
        });
    $dispatcher = new SymfonyEventDispatcher();
    $dispatcher->addListener(BeforeAdminDeactivateExtensionEvent::class, static function (BeforeAdminDeactivateExtensionEvent $event) use (&$steps): void {
        $steps[] = $event;
    });
    $dispatcher->addListener(AfterAdminDeactivateExtensionEvent::class, static function (AfterAdminDeactivateExtensionEvent $event) use (&$steps): void {
        $steps[] = $event;
    });

    $di = $api->getDi();
    $di['event_dispatcher'] = $dispatcher;
    $api->setDi($di);
    $api->setService($service);

    expect($api->deactivate(['id' => 'example', 'type' => 'mod']))->toBeTrue()
        ->and($steps)->toHaveCount(3)
        ->and($steps[0])->toBeInstanceOf(BeforeAdminDeactivateExtensionEvent::class)
        ->and($steps[0]->extensionRecordId)->toBe(52)
        ->and($steps[0]->extensionType)->toBe('mod')
        ->and($steps[0]->extensionName)->toBe('example')
        ->and($steps[1])->toBe('deactivate')
        ->and($steps[2])->toBeInstanceOf(AfterAdminDeactivateExtensionEvent::class)
        ->and($steps[2]->extensionRecordId)->toBe(52);
});

test('update dispatches the typed before event before the current unsupported update failure', function (): void {
    $api = apiEndpoint(new Box\Mod\Extension\Api\Admin());
    $extension = new Extension();
    setEntityId($extension, 53);
    $extension->setType('mod');
    $extension->setName('example');
    $repository = Mockery::mock(ExtensionRepository::class);
    $repository->shouldReceive('findOneByTypeAndName')
        ->once()
        ->with('mod', 'example')
        ->andReturn($extension);

    $steps = [];
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getExtensionRepository')->once()->andReturn($repository);
    $service->shouldReceive('update')
        ->once()
        ->with($extension)
        ->andThrow(new FOSSBilling\InformationException('Update is unavailable'));
    $dispatcher = new SymfonyEventDispatcher();
    $dispatcher->addListener(BeforeAdminUpdateExtensionEvent::class, static function (BeforeAdminUpdateExtensionEvent $event) use (&$steps): void {
        $steps[] = $event;
    });
    $dispatcher->addListener(AfterAdminUpdateExtensionEvent::class, static function (AfterAdminUpdateExtensionEvent $event) use (&$steps): void {
        $steps[] = $event;
    });

    $di = $api->getDi();
    $di['event_dispatcher'] = $dispatcher;
    $api->setDi($di);
    $api->setService($service);

    expect(fn () => $api->update(['id' => 'example', 'type' => 'mod']))
        ->toThrow(FOSSBilling\InformationException::class)
        ->and($steps)->toHaveCount(1)
        ->and($steps[0])->toBeInstanceOf(BeforeAdminUpdateExtensionEvent::class)
        ->and($steps[0]->extensionRecordId)->toBe(53)
        ->and($steps[0]->extensionName)->toBe('example');
});

test('configGet gets extension config', function (): void {
    $api = apiEndpoint(new Box\Mod\Extension\Api\Admin());
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getConfig')
        ->atLeast()
        ->once()
        ->with('mod_example')
        ->andReturn(['key' => 'value']);
    $serviceMock->shouldReceive('hasManagePermission')
        ->with('mod_example')
        ->atLeast()
        ->once()
        ->andReturn(true);

    $api->setService($serviceMock);

    $result = $api->config_get(['ext' => 'mod_example']);
    expect($result)->toBeArray();
    expect($result)->toBe(['key' => 'value']);
});

test('configSave saves extension config', function (): void {
    $api = apiEndpoint(new Box\Mod\Extension\Api\Admin());
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('hasManagePermission')
        ->with('mod_example')
        ->atLeast()
        ->once()
        ->andReturn(true);
    $serviceMock->shouldReceive('setConfig')
        ->atLeast()
        ->once()
        ->andReturn(true);

    $api->setService($serviceMock);

    $result = $api->config_save(['ext' => 'mod_example']);
    expect($result)->toBeTrue();
});

test('getList returns extensions list', function (): void {
    $api = apiEndpoint(new Box\Mod\Extension\Api\Admin());
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getExtensionsList')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $api->setService($serviceMock);

    $result = $api->get_list([]);
    expect($result)->toBeArray();
});

test('getNavigation returns admin navigation', function (): void {
    $api = apiEndpoint(new Box\Mod\Extension\Api\Admin());
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getAdminNavigation')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $api->setService($serviceMock);

    $result = $api->get_navigation([]);
    expect($result)->toBeArray();
});
