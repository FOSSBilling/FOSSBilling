<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Datasets\domainProvider;
use function Tests\Datasets\emailProvider;

test('is sld valid', function (string $domain, bool $expected): void {
    $validate = new FOSSBilling\Validate();
    expect($validate->isSldValid($domain))->toEqual($expected);
})->with('domainProvider');

dataset('domainProvider', function (): array {
    return domainProvider();
});

test('is email valid using builtin filter', function (string $email, bool $expected): void {
    // Validate uses PHP's built-in filter_var for email validation
    expect(filter_var($email, FILTER_VALIDATE_EMAIL) !== false)->toEqual($expected);
})->with('emailProvider');

dataset('emailProvider', function (): array {
    return emailProvider();
});

dataset('requiredParamsProvider', function () {
    return [
        [
            ['id' => 1, 'key' => 'value'],
            ['id' => 'ID is required', 'key' => 'Key is required'],
            [],
            false, // expectException
        ],
        [
            ['id' => 1],
            ['id' => 'ID is required', 'key' => 'Key is required'],
            [],
            true, // expectException
        ],
        [
            [],
            ['id' => 'ID is required'],
            [':id' => 1],
            true, // expectException
        ],
    ];
});

test('check required params for array', function (array $data, array $required, array $variables, bool $expectException): void {
    $validate = new FOSSBilling\Validate();

    if ($expectException) {
        expect(fn () => $validate->checkRequiredParamsForArray($required, $data, $variables))
            ->toThrow(FOSSBilling\Exception::class);
    } else {
        // Method returns void on success - wrap in closure and expect no exception
        expect(fn () => $validate->checkRequiredParamsForArray($required, $data, $variables))
            ->not->toThrow(FOSSBilling\Exception::class);
    }
})->with('requiredParamsProvider');

test('check required params passes with all required', function (): void {
    $validate = new FOSSBilling\Validate();

    $data = [
        'id' => 1,
        'name' => 'test',
        'email' => 'test@example.com',
    ];

    $required = [
        'id' => 'ID is required',
        'name' => 'Name is required',
        'email' => 'Email is required',
    ];

    // Method returns void on success - wrap in closure and expect no exception
    expect(fn () => $validate->checkRequiredParamsForArray($required, $data))
        ->not->toThrow(FOSSBilling\Exception::class);
});

test('check required params fails with missing key', function (): void {
    $validate = new FOSSBilling\Validate();

    $data = ['id' => 1];
    $required = [
        'id' => 'ID is required',
        'name' => 'Name is required',
    ];

    expect(fn () => $validate->checkRequiredParamsForArray($required, $data))
        ->toThrow(FOSSBilling\Exception::class, 'Name is required');
});

test('check required params fails with empty value', function (): void {
    $validate = new FOSSBilling\Validate();

    $data = ['name' => ''];
    $required = [
        'name' => 'Name is required',
    ];

    expect(fn () => $validate->checkRequiredParamsForArray($required, $data))
        ->toThrow(FOSSBilling\Exception::class, 'Name is required');
});

test('check required params fails with null value', function (): void {
    $validate = new FOSSBilling\Validate();

    $data = ['name' => null];
    $required = [
        'name' => 'Name is required',
    ];

    expect(fn () => $validate->checkRequiredParamsForArray($required, $data))
        ->toThrow(FOSSBilling\Exception::class, 'Name is required');
});

test('check required params with zero value passes', function (): void {
    $validate = new FOSSBilling\Validate();

    $data = ['amount' => 0];
    $required = [
        'amount' => 'Amount is required',
    ];

    // Method returns void on success - wrap in closure and expect no exception
    expect(fn () => $validate->checkRequiredParamsForArray($required, $data))
        ->not->toThrow(FOSSBilling\Exception::class);
});

test('check required params with false value fails', function (): void {
    $validate = new FOSSBilling\Validate();

    $data = ['enabled' => false];
    $required = [
        'enabled' => 'Enabled flag is required',
    ];

    expect(fn () => $validate->checkRequiredParamsForArray($required, $data))
        ->toThrow(FOSSBilling\Exception::class, 'Enabled flag is required');
});

test('check required params with custom error code', function (): void {
    $validate = new FOSSBilling\Validate();

    $data = [];
    $required = ['id' => 'ID is required'];
    $errorCode = 12345;

    try {
        $validate->checkRequiredParamsForArray($required, $data, [], $errorCode);
        expect(false)->toBeTrue('Expected exception was not thrown');
    } catch (FOSSBilling\Exception $e) {
        expect($e->getCode())->toBe($errorCode);
        expect($e->getMessage())->toBe('ID is required');
    }
});

test('check required params with message placeholder', function (): void {
    $validate = new FOSSBilling\Validate();

    $data = [];
    $required = ['key' => 'Key :key must be set'];
    $variables = [':key' => 'my_key'];

    expect(fn () => $validate->checkRequiredParamsForArray($required, $data, $variables))
        ->toThrow(FOSSBilling\Exception::class, 'Key my_key must be set');
});

test('check required params with multiple placeholders', function (): void {
    $validate = new FOSSBilling\Validate();

    $data = [];
    $required = ['key' => 'Key :key must be set for array :array'];
    $variables = [
        ':key' => 'my_key',
        ':array' => 'config',
    ];

    expect(fn () => $validate->checkRequiredParamsForArray($required, $data, $variables))
        ->toThrow(FOSSBilling\Exception::class, 'Key my_key must be set for array config');
});

test('check required params with whitespace fails', function (): void {
    $validate = new FOSSBilling\Validate();

    $data = ['name' => '   '];
    $required = ['name' => 'Name is required'];

    expect(fn () => $validate->checkRequiredParamsForArray($required, $data))
        ->toThrow(FOSSBilling\Exception::class, 'Name is required');
});
