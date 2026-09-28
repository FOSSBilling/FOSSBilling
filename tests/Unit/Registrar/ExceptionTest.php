<?php

declare(strict_types=1);

test('an int passed as variables is treated as the exception code', function (): void {
    $exception = new Registrar_Exception('API failure', 500);

    expect($exception->getMessage())->toBe('API failure')
        ->and($exception->getCode())->toBe(500);
});

test('translation variables still work as before', function (): void {
    $exception = new Registrar_Exception('Hello :name', [':name' => 'world']);

    expect($exception->getMessage())->toBe('Hello world')
        ->and($exception->getCode())->toBe(0);
});

test('message-only construction still works as before', function (): void {
    $exception = new Registrar_Exception('plain', null, 3001);

    expect($exception->getMessage())->toBe('plain')
        ->and($exception->getCode())->toBe(3001);
});
