<?php

declare(strict_types=1);

use function Tests\Helpers\adminEmail;
use function Tests\Helpers\adminPassword;
use function Tests\Helpers\browserBaseUrl;

it('logs in as the installed administrator', function (): void {
    $page = visit(browserBaseUrl() . '/admin/staff/login');

    $page->type('input[name="email"]', adminEmail());
    $page->type('input[name="password"]', adminPassword());
    $page->click('button[type="submit"]');

    $page->assertPathIs('/admin');
    $page->assertTitleContains('Dashboard');
    $page->assertSee('Clients');
});
