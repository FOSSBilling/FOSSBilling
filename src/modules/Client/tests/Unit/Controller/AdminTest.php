<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Client\Controller\Admin;
use FOSSBilling\Core\Exception\InformationException;
use FOSSBilling\Core\Http\App;
use FOSSBilling\Core\Http\RequestFactory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

use function Tests\Helpers\container;

function clientImpersonationApp(Request $request, mixed $sessionToken = 'test-token'): array
{
    $di = container();
    $session = Mockery::mock(FOSSBilling\Core\Security\Session::class);
    $session->shouldReceive('get')->with('csrf_token')->andReturn($sessionToken);
    $api = Mockery::mock();
    $di['request'] = $request;
    $di['session'] = $session;
    $di['api_admin'] = $api;
    $app = new class extends App {
        public function render($fileName, $variableArray = []): string
        {
            return 'error';
        }
    };
    $app->setDi($di);
    $app->setUrl(RequestFactory::normalizeRoutePath($request));
    (new Admin())->register($app);

    return [$app, $api, $di];
}

test('register configures a POST-only client impersonation route', function (): void {
    $controller = new Admin();
    $app = Mockery::mock(App::class);
    $app->shouldReceive('get')->atLeast()->once()->andReturnUsing(function (string $path): void {
        expect($path)->not->toBe('/client/login/:id');
    });
    $app->shouldReceive('post')->once()->with('/client/login/:id', 'post_login', ['id' => '[0-9]+'], Admin::class);
    $controller->register($app);
});

test('POST impersonation redirects with GET after switching client identity', function (?string $redirect): void {
    $uri = '/client/login/5' . ($redirect === null ? '' : '?r=' . rawurlencode($redirect));
    [$app, $api] = clientImpersonationApp(Request::create($uri, 'POST', ['CSRFToken' => 'test-token']));
    $api->shouldReceive('client_login')->once()->with(['id' => '5'])->andReturnTrue();
    $response = $app->run();
    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getStatusCode())->toBe(303)
        ->and($response->getTargetUrl())->toBe(rtrim(SYSTEM_URL, '/') . ($redirect ?? '/'));
})->with([null, '/client/manage/5']);

test('POST impersonation uses the body token independently of query parameters', function (): void {
    [$app, $api] = clientImpersonationApp(Request::create('/client/login/5?CSRFToken=wrong', 'POST', ['CSRFToken' => 'test-token']));
    $api->shouldReceive('client_login')->once()->with(['id' => '5'])->andReturnTrue();
    expect($app->run()->getStatusCode())->toBe(303);
});

test('POST impersonation rejects invalid CSRF before invoking the API', function (array $body, mixed $sessionToken, string $query): void {
    [$app, $api] = clientImpersonationApp(Request::create('/client/login/5' . $query, 'POST', $body), $sessionToken);
    $api->shouldNotReceive('client_login');
    expect(fn () => $app->run())->toThrow(InformationException::class, 'CSRF token invalid');
})->with([
    'missing token' => [[], 'test-token', ''],
    'empty token' => [['CSRFToken' => ''], 'test-token', ''],
    'wrong token' => [['CSRFToken' => 'wrong'], 'test-token', ''],
    'non-string token' => [['CSRFToken' => 123], 'test-token', ''],
    'no session token' => [['CSRFToken' => 'test-token'], null, ''],
    'empty session token' => [['CSRFToken' => 'test-token'], '', ''],
    'non-string session token' => [['CSRFToken' => 'test-token'], 123, ''],
    'query-only token' => [[], 'test-token', '?CSRFToken=test-token'],
    'query cannot rescue wrong body token' => [['CSRFToken' => 'wrong'], 'test-token', '?CSRFToken=test-token'],
]);

test('POST impersonation rejects an array CSRF token before invoking the API', function (): void {
    [$app, $api] = clientImpersonationApp(Request::create('/client/login/5', 'POST', ['CSRFToken' => ['test-token']]));
    $api->shouldNotReceive('client_login');
    expect(fn () => $app->run())->toThrow(Symfony\Component\HttpFoundation\Exception\BadRequestException::class);
});

test('non-POST browser requests cannot impersonate a client', function (string $method, bool $queryRoute): void {
    $uri = $queryRoute ? '/index.php?_url=/client/login/5&CSRFToken=test-token' : '/client/login/5?CSRFToken=test-token';
    [$app, $api] = clientImpersonationApp(Request::create($uri, $method, ['CSRFToken' => 'test-token']));
    $api->shouldNotReceive('client_login');
    expect($app->run()->getStatusCode())->toBe(404);
})->with(['GET', 'HEAD', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'])->with([false, true]);

test('POST browser impersonation supports query-based routing', function (): void {
    [$app, $api] = clientImpersonationApp(Request::create('/index.php?_url=/client/login/5', 'POST', ['CSRFToken' => 'test-token']));
    $api->shouldReceive('client_login')->once()->with(['id' => '5'])->andReturnTrue();
    expect($app->run()->getStatusCode())->toBe(303);
});

test('the impersonation handler rejects direct non-POST invocation', function (): void {
    [$app, $api, $di] = clientImpersonationApp(Request::create('/client/login/5?CSRFToken=test-token', 'GET'));
    $api->shouldNotReceive('client_login');
    $controller = new Admin();
    $controller->setDi($di);
    expect(fn () => $controller->post_login($app, '5'))->toThrow(InformationException::class, 'requires POST');
});

test('POST impersonation preserves API authorization failures', function (): void {
    [$app, $api] = clientImpersonationApp(Request::create('/client/login/5', 'POST', ['CSRFToken' => 'test-token']));
    $api->shouldReceive('client_login')->once()->andThrow(new InformationException('Impersonation denied', null, 403));
    expect(fn () => $app->run())->toThrow(InformationException::class, 'Impersonation denied');
});
