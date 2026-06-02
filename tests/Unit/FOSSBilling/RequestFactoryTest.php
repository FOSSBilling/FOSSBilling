<?php

declare(strict_types=1);

use FOSSBilling\Http\RequestFactory;
use Symfony\Component\HttpFoundation\Request;

uses()->afterEach(function (): void {
    Request::setTrustedProxies([], 0);
});

test('configure does not trust forwarded proto without trusted proxy configuration', function (): void {
    $request = Request::create('http://billing.example.com/admin', 'GET', [], [], [], [
        'REMOTE_ADDR' => '198.51.100.10',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ]);

    RequestFactory::configure($request);

    expect($request->isSecure())->toBeFalse();
});

test('pre-config proxy config does not infer trust from local network forwarded headers', function (): void {
    $proxyConfig = RequestFactory::getPreConfigProxyConfig([
        'REMOTE_ADDR' => '172.18.0.5',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_HOST' => 'billing.example.com',
    ]);
    $request = Request::create('http://internal.example/install/install.php', 'GET', [], [], [], [
        'REMOTE_ADDR' => '172.18.0.5',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_HOST' => 'billing.example.com',
    ]);

    RequestFactory::configure($request, $proxyConfig);

    expect($proxyConfig)->toBe([]);
    expect($request->isSecure())->toBeFalse();
    expect($request->getHost())->toBe('internal.example');
});

test('pre-config proxy candidate reports forwarded header details without trusting them', function (): void {
    $proxyCandidate = RequestFactory::getPreConfigProxyCandidate([
        'REMOTE_ADDR' => '172.18.0.5',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_HOST' => 'billing.example.com',
    ]);

    expect($proxyCandidate)->toBe([
        'detected' => true,
        'remote_addr' => '172.18.0.5',
        'remote_addr_is_private' => true,
        'proxies' => ['172.18.0.5'],
        'headers' => 'x_forwarded',
        'header_values' => [
            'X-Forwarded-For' => '203.0.113.10',
            'X-Forwarded-Host' => 'billing.example.com',
            'X-Forwarded-Proto' => 'https',
        ],
        'suggested_url' => 'https://billing.example.com/',
    ]);
});

test('pre-config proxy candidate ignores empty forwarded headers', function (): void {
    $proxyCandidate = RequestFactory::getPreConfigProxyCandidate([
        'REMOTE_ADDR' => '172.18.0.5',
        'HTTP_FORWARDED' => '',
    ]);

    expect($proxyCandidate)->toBe([]);
});

test('pre-config proxy config does not trust forwarded headers from public address', function (): void {
    $proxyConfig = RequestFactory::getPreConfigProxyConfig([
        'REMOTE_ADDR' => '198.51.100.10',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_HOST' => 'billing.example.com',
    ]);
    $request = Request::create('http://internal.example/install/install.php', 'GET', [], [], [], [
        'REMOTE_ADDR' => '198.51.100.10',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_HOST' => 'billing.example.com',
    ]);

    RequestFactory::configure($request, $proxyConfig);

    expect($proxyConfig)->toBe([]);
    expect($request->isSecure())->toBeFalse();
    expect($request->getHost())->toBe('internal.example');
});

test('configure trusts forwarded proto from configured trusted proxy', function (): void {
    $request = Request::create('http://billing.example.com/admin', 'GET', [], [], [], [
        'REMOTE_ADDR' => '198.51.100.10',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_HOST' => 'billing.example.com',
    ]);

    RequestFactory::configure($request, [
        'enabled' => true,
        'proxies' => ['198.51.100.10'],
        'headers' => 'x_forwarded',
    ]);

    expect($request->isSecure())->toBeTrue();
    expect($request->getHost())->toBe('billing.example.com');
});

test('configure ignores forwarded proto from untrusted proxy', function (): void {
    $request = Request::create('http://billing.example.com/admin', 'GET', [], [], [], [
        'REMOTE_ADDR' => '198.51.100.99',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ]);

    RequestFactory::configure($request, [
        'enabled' => true,
        'proxies' => ['198.51.100.10'],
        'headers' => 'x_forwarded',
    ]);

    expect($request->isSecure())->toBeFalse();
});

test('configure supports forwarded header mode', function (): void {
    $request = Request::create('http://internal.example/admin', 'GET', [], [], [], [
        'REMOTE_ADDR' => '198.51.100.10',
        'HTTP_FORWARDED' => 'for=203.0.113.9;host=billing.example.com;proto=https',
    ]);

    RequestFactory::configure($request, [
        'enabled' => true,
        'proxies' => ['198.51.100.10'],
        'headers' => 'forwarded',
    ]);

    expect($request->isSecure())->toBeTrue();
    expect($request->getHost())->toBe('billing.example.com');
});

test('configure supports AWS ELB header mode', function (): void {
    $request = Request::create('http://internal.example/admin', 'GET', [], [], [], [
        'REMOTE_ADDR' => '198.51.100.10',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        'HTTP_X_FORWARDED_HOST' => 'billing.example.com',
        'HTTP_X_FORWARDED_PORT' => '443',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ]);

    RequestFactory::configure($request, [
        'enabled' => true,
        'proxies' => ['198.51.100.10'],
        'headers' => 'aws_elb',
    ]);

    expect($request->getClientIp())->toBe('203.0.113.9');
    expect($request->isSecure())->toBeTrue();
    expect($request->getHost())->toBe('internal.example');
});

test('configure supports Traefik header mode', function (): void {
    $request = Request::create('http://internal.example/admin', 'GET', [], [], [], [
        'REMOTE_ADDR' => '198.51.100.10',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        'HTTP_X_FORWARDED_HOST' => 'billing.example.com',
        'HTTP_X_FORWARDED_PORT' => '443',
        'HTTP_X_FORWARDED_PREFIX' => '/billing',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ]);

    RequestFactory::configure($request, [
        'enabled' => true,
        'proxies' => ['198.51.100.10'],
        'headers' => 'traefik',
    ]);

    expect($request->getClientIp())->toBe('203.0.113.9');
    expect($request->isSecure())->toBeTrue();
    expect($request->getHost())->toBe('billing.example.com');
    expect($request->getBaseUrl())->toBe('/billing');
});

test('configure rejects unknown trusted proxy header mode', function (): void {
    $request = Request::create('http://billing.example.com/admin');

    expect(fn (): Request => RequestFactory::configure($request, [
        'enabled' => true,
        'proxies' => ['198.51.100.10'],
        'headers' => 'custom',
    ]))->toThrow(InvalidArgumentException::class, 'Invalid trusted proxy header configuration.');
});

test('normalize route path rewrites legacy custom page route', function (): void {
    $request = Request::create('http://billing.example.com/page/about-us');

    $path = RequestFactory::normalizeRoutePath($request);

    expect($path)->toBe('/custompages/about-us');
    expect(RequestFactory::getRoutePath($request))->toBe('/custompages/about-us');
});

test('normalize route path rejects invalid path and falls back to root', function (): void {
    $request = Request::create('http://billing.example.com/admin', 'GET', [
        '_url' => "invalid\x00path",
    ]);

    $path = RequestFactory::normalizeRoutePath($request);

    expect($path)->toBe('/');
    expect(RequestFactory::getRoutePath($request))->toBe('/');
});
