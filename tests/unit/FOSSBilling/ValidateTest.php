<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

dataset('domainProvider', function () {
    return [
        ['google', true],
        ['example-domain', true],
        ['a1', true],
        ['123', true],
        ['xn--bcher-kva', true],
        ['subdomain', true],
        ['qqq45%%%', false],
        ['()1google', false],
        ['//asdasd()()', false],
        ['--asdasd()()', false],
        ['', false],
        ['sub.domain.example', false], // SLD cannot contain dots
    ];
});

test('is sld valid', function (string $domain, bool $expected) {
    $validate = new \FOSSBilling\Validate();
    expect($validate->isSldValid($domain))->toEqual($expected);
})->with('domainProvider');

dataset('emailProvider', function () {
    return [
        ['test@example.com', true],
        ['test.user@example.com', true],
        ['test+tag@example.com', true],
        ['test@subdomain.example.com', true],
        ['test@example.co.uk', true],
        ['invalid', false],
        ['test@', false],
        ['@example.com', false],
        ['test example.com', false],
    ];
});

test('is email valid using builtin filter', function (string $email, bool $expected) {
    // Validate uses PHP's built-in filter_var for email validation
    expect(filter_var($email, FILTER_VALIDATE_EMAIL) !== false)->toEqual($expected);
})->with('emailProvider');

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

test('check required params for array', function (array $data, array $required, array $variables, bool $expectException) {
    $validate = new \FOSSBilling\Validate();

    if ($expectException) {
        expect(fn () => $validate->checkRequiredParamsForArray($required, $data, $variables))
            ->toThrow(\FOSSBilling\Exception::class);
    } else {
        expect($validate->checkRequiredParamsForArray($required, $data, $variables))->toBeNull();
    }
})->with('requiredParamsProvider');

test('check required params passes with all required', function () {
    $validate = new \FOSSBilling\Validate();

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

    expect($validate->checkRequiredParamsForArray($required, $data))->toBeNull();
});

test('check required params fails with missing key', function () {
    $validate = new \FOSSBilling\Validate();

    $data = ['id' => 1];
    $required = [
        'id' => 'ID is required',
        'name' => 'Name is required',
    ];

    expect(fn () => $validate->checkRequiredParamsForArray($required, $data))
        ->toThrow(\FOSSBilling\Exception::class, 'Name is required');
});

test('check required params fails with empty value', function () {
    $validate = new \FOSSBilling\Validate();

    $data = ['name' => ''];
    $required = [
        'name' => 'Name is required',
    ];

    expect(fn () => $validate->checkRequiredParamsForArray($required, $data))
        ->toThrow(\FOSSBilling\Exception::class, 'Name is required');
});

test('check required params fails with null value', function () {
    $validate = new \FOSSBilling\Validate();

    $data = ['name' => null];
    $required = [
        'name' => 'Name is required',
    ];

    expect(fn () => $validate->checkRequiredParamsForArray($required, $data))
        ->toThrow(\FOSSBilling\Exception::class, 'Name is required');
});

test('check required params with zero value passes', function () {
    $validate = new \FOSSBilling\Validate();

    $data = ['amount' => 0];
    $required = [
        'amount' => 'Amount is required',
    ];

    expect($validate->checkRequiredParamsForArray($required, $data))->toBeNull();
});

test('check required params with false value fails', function () {
    $validate = new \FOSSBilling\Validate();

    $data = ['enabled' => false];
    $required = [
        'enabled' => 'Enabled flag is required',
    ];

    expect(fn () => $validate->checkRequiredParamsForArray($required, $data))
        ->toThrow(\FOSSBilling\Exception::class, 'Enabled flag is required');
});

test('check required params with custom error code', function () {
    $validate = new \FOSSBilling\Validate();

    $data = [];
    $required = ['id' => 'ID is required'];
    $errorCode = 12345;

    try {
        $validate->checkRequiredParamsForArray($required, $data, [], $errorCode);
        expect(false)->toBeTrue('Expected exception was not thrown');
    } catch (\FOSSBilling\Exception $e) {
        expect($e->getCode())->toBe($errorCode);
        expect($e->getMessage())->toBe('ID is required');
    }
});

test('check required params with message placeholder', function () {
    $validate = new \FOSSBilling\Validate();

    $data = [];
    $required = ['key' => 'Key :key must be set'];
    $variables = [':key' => 'my_key'];

    expect(fn () => $validate->checkRequiredParamsForArray($required, $data, $variables))
        ->toThrow(\FOSSBilling\Exception::class, 'Key my_key must be set');
});

test('check required params with multiple placeholders', function () {
    $validate = new \FOSSBilling\Validate();

    $data = [];
    $required = ['key' => 'Key :key must be set for array :array'];
    $variables = [
        ':key' => 'my_key',
        ':array' => 'config',
    ];

    expect(fn () => $validate->checkRequiredParamsForArray($required, $data, $variables))
        ->toThrow(\FOSSBilling\Exception::class, 'Key my_key must be set for array config');
});

test('check required params with whitespace fails', function () {
    $validate = new \FOSSBilling\Validate();

    $data = ['name' => '   '];
    $required = ['name' => 'Name is required'];

    expect(fn () => $validate->checkRequiredParamsForArray($required, $data))
        ->toThrow(\FOSSBilling\Exception::class, 'Name is required');
});
