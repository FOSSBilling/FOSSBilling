<?php

declare(strict_types=1);

use FOSSBilling\Requirements;

test('64-bit PHP check reflects the actual PHP_INT_SIZE of the running interpreter', function (): void {
    // FOSSBilling stores several database identifiers as 64-bit integers (MySQL BIGINT),
    // which a 32-bit PHP build can't represent natively without silent precision loss.
    $requirements = new Requirements();

    expect($requirements->isPhp64Bit())->toBe(PHP_INT_SIZE === 8);
});

test('checkCompat reports the PHP architecture alongside the PHP version', function (): void {
    $requirements = new Requirements();
    $result = $requirements->checkCompat();

    expect($result['php_architecture'])->toBe([
        'isOk' => PHP_INT_SIZE === 8,
        'int_size' => PHP_INT_SIZE,
    ]);
});
