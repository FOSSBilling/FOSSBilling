<?php

declare(strict_types=1);

use function Tests\Helpers\adminEmail;
use function Tests\Helpers\adminPassword;
use function Tests\Helpers\apiRequest;
use function Tests\Helpers\browserBaseUrl;

it('redirects logged-out admin pages to the staff login', function (): void {
    $page = visit(browserBaseUrl() . '/admin/client');

    $page->assertPathBeginsWith('/admin/staff/login');
    $page->assertSee('Login');
});

it('allows authenticated admin profile API requests', function (): void {
    $page = visit(browserBaseUrl() . '/admin/staff/login');

    $page->type('input[name="email"]', adminEmail());
    $page->type('input[name="password"]', adminPassword());
    $page->click('button[type="submit"]');
    $page->assertPathIs('/admin');

    $cookieString = $page->script('document.cookie');
    preg_match('/csrf_token=([^;]+)/', $cookieString, $matches);
    expect($matches)->toHaveKey(1, 'CSRF token should be present in cookies');

    $csrfToken = $matches[1];
    $result = apiRequest('GET', browserBaseUrl() . '/api/admin/profile/get', [], $csrfToken);

    expect($result['status'])->toBe(200);
    expect($result['body']['error'])->toBeNull();
    expect($result['body']['result'])->toHaveKey('id');
    expect($result['body']['result']['email'])->toBe(adminEmail());
});

it('rejects admin API POST requests without a CSRF token', function (): void {
    $result = apiRequest('POST', browserBaseUrl() . '/api/admin/profile/get');

    expect($result['status'])->toBe(403);
    expect($result['body']['result'])->toBeNull();
    expect($result['body']['error']['message'])->toBe('CSRF token invalid');
});
