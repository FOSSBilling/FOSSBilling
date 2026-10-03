<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;

test('register configures routes', function (): void {
    $controller = new Box\Mod\Security\Controller\Admin();
    $boxAppMock = Mockery::mock('\Box_App');
    $boxAppMock->shouldReceive('get')->atLeast()->once();

    $controller->register($boxAppMock);
});

test('ip lookup performs lookup for a valid ip', function (): void {
    $controller = new Box\Mod\Security\Controller\Admin();
    $di = container();

    $expectedRecord = ['country' => 'US', 'ip' => '1.1.1.1'];
    $apiAdmin = Mockery::mock();
    $apiAdmin->shouldReceive('Security_IP_Lookup')
        ->once()
        ->with(['ip' => '1.1.1.1'])
        ->andReturn($expectedRecord);
    $di['api_admin'] = $apiAdmin;
    $di['is_admin_logged'] = true;

    $controller->setDi($di);

    $request = Symfony\Component\HttpFoundation\Request::create('/security/iplookup?ip=1.1.1.1');

    $boxAppMock = Mockery::mock('\Box_App');
    $boxAppMock->shouldReceive('getRequest')->once()->andReturn($request);
    $boxAppMock->shouldReceive('render')
        ->once()
        ->with('mod_security_iplookup', ['record' => $expectedRecord])
        ->andReturn('rendered');

    $result = $controller->ip_lookup($boxAppMock);
    expect($result)->toBe('rendered');
});

test('ip lookup skips lookup for an invalid ip', function (): void {
    $controller = new Box\Mod\Security\Controller\Admin();
    $di = container();

    $apiAdmin = Mockery::mock();
    $apiAdmin->shouldNotReceive('Security_IP_Lookup');
    $di['api_admin'] = $apiAdmin;
    $di['is_admin_logged'] = true;

    $controller->setDi($di);

    $request = Symfony\Component\HttpFoundation\Request::create('/security/iplookup?ip=not-an-ip');

    $boxAppMock = Mockery::mock('\Box_App');
    $boxAppMock->shouldReceive('getRequest')->once()->andReturn($request);
    $boxAppMock->shouldReceive('render')
        ->once()
        ->with('mod_security_iplookup', ['record' => []])
        ->andReturn('rendered');

    $result = $controller->ip_lookup($boxAppMock);
    expect($result)->toBe('rendered');
});

test('ip lookup skips lookup when ip parameter is missing', function (): void {
    $controller = new Box\Mod\Security\Controller\Admin();
    $di = container();

    $apiAdmin = Mockery::mock();
    $apiAdmin->shouldNotReceive('Security_IP_Lookup');
    $di['api_admin'] = $apiAdmin;
    $di['is_admin_logged'] = true;

    $controller->setDi($di);

    $request = Symfony\Component\HttpFoundation\Request::create('/security/iplookup');

    $boxAppMock = Mockery::mock('\Box_App');
    $boxAppMock->shouldReceive('getRequest')->once()->andReturn($request);
    $boxAppMock->shouldReceive('render')
        ->once()
        ->with('mod_security_iplookup', ['record' => []])
        ->andReturn('rendered');

    $result = $controller->ip_lookup($boxAppMock);
    expect($result)->toBe('rendered');
});

test('rate limits sorts counters with request sort parameters before pagination', function (): void {
    $controller = new Box\Mod\Security\Controller\Admin();
    $di = container();
    $di['is_admin_logged'] = true;

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('checkPermissionsAndThrowException')->once()->with('security', 'view');

    $counters = [
        ['ip' => '10.0.0.2', 'policy' => 'b_policy'],
        ['ip' => '10.0.0.1', 'policy' => 'a_policy'],
    ];

    $securityService = Mockery::mock(Box\Mod\Security\Service::class);
    $securityService->shouldReceive('getRateLimitList')->once()->with(null, null)->andReturn($counters);
    $securityService->shouldReceive('sortRateLimitCounters')
        ->once()
        ->with($counters, ['sort' => 'policy', 'direction' => 'DESC'])
        ->andReturn($counters);
    $securityService->shouldReceive('getRateLimitStatus')->once()->andReturn(['enabled' => true]);

    $di['mod_service'] = $di->protect(fn (string $name): object => $name === 'Staff' ? $staffService : $securityService);

    $pager = Mockery::mock();
    $pager->shouldReceive('paginateArray')
        ->once()
        ->with($counters, Mockery::type(FOSSBilling\PaginationOptions::class))
        ->andReturn(['list' => $counters]);
    $di['pager'] = $pager;

    $controller->setDi($di);

    $request = Symfony\Component\HttpFoundation\Request::create('/security/rate-limits?sort=policy&direction=DESC');

    $boxAppMock = Mockery::mock('\Box_App');
    $boxAppMock->shouldReceive('getRequest')->once()->andReturn($request);
    $boxAppMock->shouldReceive('render')
        ->once()
        ->with('mod_security_rate_limits', Mockery::on(fn (array $params): bool => $params['rate_limit_counters'] === ['list' => $counters]))
        ->andReturn('rendered');

    $result = $controller->rate_limits($boxAppMock);
    expect($result)->toBe('rendered');
});
