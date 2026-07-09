<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request;

class BoxAppRouteDispatchSharedController implements FOSSBilling\InjectionAwareInterface
{
    private ?Pimple\Container $di = null;

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function priority(Box_App $app, string $id): string
    {
        return 'shared:' . $id;
    }

    public function injected(Box_App $app): string
    {
        return $this->di instanceof Pimple\Container && $this->di['shared_marker'] === 'available'
            ? 'di:available'
            : 'di:missing';
    }

    public function collectDebugbar(Box_App $app): string
    {
        $app->getDebugBar()->getData();

        return 'shared-debugbar:collected';
    }
}

class BoxAppRouteDispatchApp extends Box_App
{
    public function __construct(private readonly string $routeMode)
    {
        parent::__construct();
    }

    protected function init(): void
    {
        if ($this->routeMode === 'shared-priority') {
            $this->get('/priority/:id', 'priority', ['id' => '[0-9]+'], BoxAppRouteDispatchSharedController::class);

            return;
        }

        if ($this->routeMode === 'shared-di') {
            $this->get('/shared-di', 'injected', [], BoxAppRouteDispatchSharedController::class);

            return;
        }

        if ($this->routeMode === 'shared-debugbar-collect') {
            $this->get('/shared-debugbar-collect', 'collectDebugbar', [], BoxAppRouteDispatchSharedController::class);

            return;
        }

        if ($this->routeMode === 'default-argument') {
            $this->get('/default', 'withDefault');

            return;
        }

        if ($this->routeMode === 'debugbar-collect') {
            $this->get('/debugbar-collect', 'collectDebugbar');

            return;
        }

        if ($this->routeMode === 'redirect') {
            $this->get('/redirect-me', 'redirectMe');

            return;
        }

        $this->get('/local/:id', 'local', ['id' => '[0-9]+']);
    }

    public function local(string $id): string
    {
        return 'local:' . $id;
    }

    public function priority(string $id): string
    {
        return 'local:' . $id;
    }

    public function withDefault(string $tab = 'overview'): string
    {
        return 'default:' . $tab;
    }

    public function collectDebugbar(): string
    {
        $this->getDebugBar()->getData();

        return 'debugbar:collected';
    }

    public function redirectMe(): Symfony\Component\HttpFoundation\Response
    {
        return $this->redirect('/target');
    }

    #[Override]
    public function render($fileName, $variableArray = []): string
    {
        return 'error';
    }
}

class BoxAppMaintenanceCheckApp extends Box_App
{
    public function pathAllowed(string $requestPath, string $allowedPath): bool
    {
        return $this->pathMatchesMaintenancePattern($requestPath, $allowedPath);
    }

    public function ipAllowed(string $visitorIP, array $allowedIPs): bool
    {
        return $this->ipMatchesMaintenanceAllowlist($visitorIP, $allowedIPs);
    }
}

function routeDispatchApp(string $routeMode, string $path): BoxAppRouteDispatchApp
{
    $app = new BoxAppRouteDispatchApp($routeMode);

    $di = new Pimple\Container();
    $di['request'] = Request::create('http://localhost' . $path);
    $di['shared_marker'] = 'available';
    $di['url'] = new class {
        public function link(string $path): string
        {
            return '/' . ltrim($path, '/');
        }
    };
    $di['logger'] = new class {
        public function setChannel(string $channel): self
        {
            return $this;
        }

        public function info(string|Stringable $message, array $context = []): void
        {
        }
    };

    $app->setDi($di);
    $app->setUrl($path);

    return $app;
}

test('app dispatches local route definitions with matched parameters', function (): void {
    $response = routeDispatchApp('local', '/local/42')->run();

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('local:42');
});

test('app preserves shared controller route priority over local routes', function (): void {
    $response = routeDispatchApp('shared-priority', '/priority/42')->run();

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('shared:42');
});

test('app injects the DI container into shared route controllers', function (): void {
    $response = routeDispatchApp('shared-di', '/shared-di')->run();

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('di:available');
});

test('app dispatch uses controller default arguments when route params are absent', function (): void {
    $response = routeDispatchApp('default-argument', '/default')->run();

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('default:overview');
});

test('app route mapping timing stops before debug bar data is collected during render', function (): void {
    $response = routeDispatchApp('debugbar-collect', '/debugbar-collect')->run();

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('debugbar:collected');
});

test('app shared route mapping timing stops before debug bar data is collected during render', function (): void {
    $response = routeDispatchApp('shared-debugbar-collect', '/shared-debugbar-collect')->run();

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('shared-debugbar:collected');
});

test('app redirect helper returns redirect responses from controllers', function (): void {
    $response = routeDispatchApp('redirect', '/redirect-me')->run();

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toBe('/target');
});

test('maintenance path allowlist uses literal prefixes and explicit wildcards', function (): void {
    $app = new BoxAppMaintenanceCheckApp();

    expect($app->pathAllowed('/status', '/status'))->toBeTrue()
        ->and($app->pathAllowed('/status/deep', '/status'))->toBeTrue()
        ->and($app->pathAllowed('/status-page', '/status'))->toBeFalse()
        ->and($app->pathAllowed('/api/guest/staff/login', '/api/guest/staff/*'))->toBeTrue()
        ->and($app->pathAllowed('/docs/v10', '/docs/v1.0'))->toBeFalse()
        ->and($app->pathAllowed('/api/admin', ''))->toBeFalse();
});

test('maintenance IP allowlist supports exact IPs, CIDR ranges, and IPv6', function (): void {
    $app = new BoxAppMaintenanceCheckApp();

    expect($app->ipAllowed('203.0.113.10', ['203.0.113.10']))->toBeTrue()
        ->and($app->ipAllowed('203.0.113.55', ['203.0.113.0/24']))->toBeTrue()
        ->and($app->ipAllowed('2001:db8::1', ['2001:db8::/32']))->toBeTrue()
        ->and($app->ipAllowed('198.51.100.10', ['203.0.113.0/24']))->toBeFalse()
        ->and($app->ipAllowed('198.51.100.10', ['not-an-ip-range']))->toBeFalse();
});
