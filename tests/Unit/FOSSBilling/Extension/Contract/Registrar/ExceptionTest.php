<?php

declare(strict_types=1);

test('an int passed as variables is treated as the exception code', function (): void {
    $exception = new FOSSBilling\Extension\Contract\Registrar\Exception('API failure', 500);

    expect($exception->getMessage())->toBe('API failure')
        ->and($exception->getCode())->toBe(500);
});

test('translation variables still work as before', function (): void {
    $exception = new FOSSBilling\Extension\Contract\Registrar\Exception('Hello :name', [':name' => 'world']);

    expect($exception->getMessage())->toBe('Hello world')
        ->and($exception->getCode())->toBe(0);
});

test('an explicit exception code is preserved when variables are null', function (): void {
    $exception = new FOSSBilling\Extension\Contract\Registrar\Exception('plain', null, 3001);

    expect($exception->getMessage())->toBe('plain')
        ->and($exception->getCode())->toBe(3001);
});

test('an int in variables takes precedence over the code argument', function (): void {
    $exception = new FOSSBilling\Extension\Contract\Registrar\Exception('API failure', 500, 999);

    expect($exception->getMessage())->toBe('API failure')
        ->and($exception->getCode())->toBe(500);
});
