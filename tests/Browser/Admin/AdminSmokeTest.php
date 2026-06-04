<?php

declare(strict_types=1);

use function Tests\Helpers\adminEmail;
use function Tests\Helpers\adminPassword;
use function Tests\Helpers\browserBaseUrl;

$pages = [
    '/admin',
    '/admin/client',
    '/admin/order',
    '/admin/invoice',
    '/admin/product',
    '/admin/system',
    '/admin/extension',
];

it('loads core admin pages successfully', function () use ($pages): void {
    $page = visit(browserBaseUrl() . '/admin/staff/login');

    $page->type('input[name="email"]', adminEmail());
    $page->type('input[name="password"]', adminPassword());
    $page->click('button[type="submit"]');
    $page->assertPathIs('/admin');

    foreach ($pages as $path) {
        $page->navigate($path);
        $page->assertPathIs($path);
        $page->assertVisible('body');
    }
});
