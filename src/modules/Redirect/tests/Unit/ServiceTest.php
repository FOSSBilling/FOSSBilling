<?php

declare(strict_types=1);

use FOSSBilling\Exception;

dataset('validTargets', fn (): array => [
    ['/new-page', '/new-page'],
    ['new-page', 'new-page'],
    ['http://example.com/page', 'http://example.com/page'],
    ['https://example.com/page', 'https://example.com/page'],
]);

dataset('invalidTargets', fn (): array => [
    [''],
    ['   '],
    ['javascript:alert(1)'],
    ['JaVaScRiPt:alert(1)'],
    ['data:text/html,<h1>test</h1>'],
    ['ftp://example.com/file'],
    ["/new-page\r\nLocation: https://evil.com"],
    ["/new-page\nLocation: https://evil.com"],
    ["/new-page\rLocation: https://evil.com"],
]);

dataset('validPaths', fn (): array => [
    ['/old-page/', 'old-page'],
    ['/some/nested/path/', 'some/nested/path'],
]);

dataset('invalidPaths', fn (): array => [
    [''],
    ['/'],
    ['../config'],
    ['foo/../../etc/passwd'],
    ["old-page\r\nX-Header: evil"],
]);

test('validate target allows valid targets', function (string $input, string $expected): void {
    $service = new Box\Mod\Redirect\Service();
    expect($service->validateTarget($input))->toBe($expected);
})->with('validTargets');

test('validate target rejects invalid targets', function (string $input): void {
    $service = new Box\Mod\Redirect\Service();
    expect(fn (): string => $service->validateTarget($input))->toThrow(Exception::class);
})->with('invalidTargets');

test('validate target trims whitespace', function (): void {
    $service = new Box\Mod\Redirect\Service();
    expect($service->validateTarget('  /new-page  '))->toBe('/new-page');
});

test('validate path strips slashes', function (string $input, string $expected): void {
    $service = new Box\Mod\Redirect\Service();
    expect($service->validatePath($input))->toBe($expected);
})->with('validPaths');

test('validate path rejects invalid paths', function (string $input): void {
    $service = new Box\Mod\Redirect\Service();
    expect(fn (): string => $service->validatePath($input))->toThrow(Exception::class);
})->with('invalidPaths');
