<?php

declare(strict_types=1);

use function Tests\Helpers\browserBaseUrl;
use function Tests\Helpers\createTestClient;

$pages = [
    ['path' => '/', 'text' => 'Dashboard'],
    ['path' => '/client/profile', 'text' => 'Update Details'],
    ['path' => '/order/service', 'text' => 'Services'],
    ['path' => '/invoice', 'text' => 'Invoices'],
    ['path' => '/support', 'text' => 'Support Tickets'],
    ['path' => '/email', 'text' => 'Emails'],
];

it('loads core client pages successfully', function () use ($pages): void {
    $client = createTestClient();

    $page = visit(browserBaseUrl() . '/login');

    $page->type('input[name="email"]', $client['email']);
    $page->type('input[name="password"]', $client['password']);
    $page->click('button[type="submit"]');
    $page->assertPathIs('/');

    foreach ($pages as ['path' => $path, 'text' => $text]) {
        $page->navigate($path);
        $page->assertVisible('body');
        $page->assertSee($text);
    }
});
