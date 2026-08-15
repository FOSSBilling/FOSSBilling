<?php

declare(strict_types=1);

use FOSSBilling\Update;

test('isSafeArchiveEntry accepts a normal relative entry', function (): void {
    expect(Update::isSafeArchiveEntry('src/library/FOSSBilling/Update.php'))->toBeTrue();
});

test('isSafeArchiveEntry rejects a forward-slash traversal segment', function (): void {
    expect(Update::isSafeArchiveEntry('../../etc/passwd'))->toBeFalse();
});

test('isSafeArchiveEntry rejects a backslash traversal segment', function (): void {
    expect(Update::isSafeArchiveEntry('..\\..\\poc.php'))->toBeFalse();
});

test('isSafeArchiveEntry rejects a mixed-separator traversal segment', function (): void {
    expect(Update::isSafeArchiveEntry('src\\..\\..\\poc.php'))->toBeFalse();
});

test('isSafeArchiveEntry rejects a Unix absolute path', function (): void {
    expect(Update::isSafeArchiveEntry('/etc/passwd'))->toBeFalse();
});

test('isSafeArchiveEntry rejects a Windows drive-letter absolute path', function (): void {
    expect(Update::isSafeArchiveEntry('C:\\Windows\\System32\\evil.dll'))->toBeFalse();
});

test('isSafeArchiveEntry rejects a trailing-space-and-dot segment Windows normalizes to a parent traversal', function (): void {
    expect(Update::isSafeArchiveEntry('.. .\\outside.php'))->toBeFalse();
});

test('isSafeArchiveEntry rejects an all-dots segment', function (): void {
    expect(Update::isSafeArchiveEntry('.../outside.php'))->toBeFalse();
});

test('isSafeArchiveEntry accepts a filename that legitimately contains spaces and periods', function (): void {
    expect(Update::isSafeArchiveEntry('src/data/my file v1.2.3.txt'))->toBeTrue();
});
