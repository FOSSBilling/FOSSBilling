<?php

declare(strict_types=1);

use function Tests\Helpers\browserBaseUrl;

$protectedPages = [
    '/client',
    '/client/profile',
    '/order/service',
    '/invoice',
    '/support',
    '/email',
];

foreach ($protectedPages as $path) {
    it("redirects logged-out requests for {$path} to the client login", function () use ($path): void {
        $page = visit(browserBaseUrl() . $path);

        $page->assertPathBeginsWith('/login');
        $page->assertSee('Login to Your Account');
    });
}
