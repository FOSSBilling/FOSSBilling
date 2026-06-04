<?php

declare(strict_types=1);

use function Tests\Helpers\browserBaseUrl;

$pages = [
    ['path' => '/', 'text' => null],
    ['path' => '/login', 'text' => 'Login to Your Account'],
    ['path' => '/signup', 'text' => 'Create a new account'],
    ['path' => '/password-reset', 'text' => 'Reset Your Password'],
    ['path' => '/order', 'text' => null],
];

foreach ($pages as ['path' => $path, 'text' => $text]) {
    it("loads {$path}", function () use ($path, $text): void {
        $page = visit(browserBaseUrl() . $path);

        $page->assertVisible('body');

        if ($text !== null) {
            $page->assertSee($text);
        }
    });
}
