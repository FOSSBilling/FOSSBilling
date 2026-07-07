<?php

declare(strict_types=1);

use FOSSBilling\Http\RouteMatcher;

test('route matcher matches exact routes for the same HTTP method', function (): void {
    $match = (new RouteMatcher())->match('get', '/invoice', [], '/invoice', 'GET');

    expect($match->matched)->toBeTrue()
        ->and($match->params)->toBe([]);
});

test('route matcher rejects non-matching paths for the same HTTP method', function (): void {
    $match = (new RouteMatcher())->match('get', '/invoice', [], '/invoices', 'GET');

    expect($match->matched)->toBeFalse();
});

test('route matcher rejects different HTTP methods', function (): void {
    $match = (new RouteMatcher())->match('post', '/invoice', [], '/invoice', 'GET');

    expect($match->matched)->toBeFalse();
});

test('route matcher treats HEAD requests as GET requests', function (): void {
    $match = (new RouteMatcher())->match('get', '/invoice/:hash', ['hash' => '[a-z0-9]+'], '/invoice/abc123', 'HEAD');

    expect($match->matched)->toBeTrue()
        ->and($match->params)->toBe(['hash' => 'abc123']);
});

test('route matcher extracts default route parameters', function (): void {
    $match = (new RouteMatcher())->match('get', '/kb/:slug', [], '/kb/getting-started_1', 'GET');

    expect($match->matched)->toBeTrue()
        ->and($match->params)->toBe(['slug' => 'getting-started_1']);
});

test('route matcher applies custom route parameter conditions', function (): void {
    $matcher = new RouteMatcher();

    $matched = $matcher->match('get', '/invoice/:hash', ['hash' => '[a-z0-9]+'], '/invoice/abc123', 'GET');
    $rejected = $matcher->match('get', '/invoice/:hash', ['hash' => '[a-z0-9]+'], '/invoice/ABC123', 'GET');

    expect($matched->matched)->toBeTrue()
        ->and($matched->params)->toBe(['hash' => 'abc123'])
        ->and($rejected->matched)->toBeFalse();
});

test('route matcher accepts null conditions for routes without constrained parameters', function (): void {
    $match = (new RouteMatcher())->match('get', '/servicehosting', null, '/servicehosting', 'GET');

    expect($match->matched)->toBeTrue();
});

test('route matcher uses default parameter pattern when conditions are null', function (): void {
    $match = (new RouteMatcher())->match('get', '/item/:id', null, '/item/42', 'GET');

    expect($match->matched)->toBeTrue()
        ->and($match->params)->toBe(['id' => '42']);
});

test('route matcher extracts full placeholder names', function (): void {
    $match = (new RouteMatcher())->match('get', '/item/:item_id', ['item_id' => '[0-9]+'], '/item/42', 'GET');

    expect($match->matched)->toBeTrue()
        ->and($match->params)->toBe(['item_id' => '42']);
});

test('route matcher treats literal route path characters as literals', function (): void {
    $matcher = new RouteMatcher();

    $matched = $matcher->match('get', '/asset/app.js', [], '/asset/app.js', 'GET');
    $rejected = $matcher->match('get', '/asset/app.js', [], '/asset/app-json', 'GET');

    expect($matched->matched)->toBeTrue()
        ->and($rejected->matched)->toBeFalse();
});
