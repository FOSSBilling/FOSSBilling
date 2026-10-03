<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

use function Tests\Datasets\domainProvider;
use function Tests\Datasets\emailProvider;

test('is sld valid', function (string $domain, bool $expected): void {
    $validate = new FOSSBilling\Core\Validation\Validator();
    expect($validate->isSldValid($domain))->toEqual($expected);
})->with('domainProvider');

test('is tld valid falls back gracefully when the PSL download fails at the transport level', function (): void {
    $validate = new FOSSBilling\Core\Validation\Validator();

    // Simulates e.g. "Bind failed with errno 97: Address family not supported by protocol",
    // which surfaces as a TransportException when the response is accessed, not when the
    // request is made.
    $httpClient = new MockHttpClient(fn (): MockResponse => new MockResponse('', [
        'error' => 'Address family not supported by protocol',
    ]));

    $di = new Pimple\Container();
    $di['cache'] = fn () => new ArrayAdapter();
    $di['http_client'] = fn () => $httpClient;
    $validate->setDi($di);

    // Must not let the transport exception bubble up and break the caller (e.g. checkout).
    expect(fn () => $validate->isTldValid('com'))->not->toThrow(Throwable::class);

    // Falls back to the simple regex-based check since the PSL couldn't be fetched.
    expect($validate->isTldValid('com'))->toBeTrue();
    expect($validate->isTldValid('123'))->toBeFalse();
});

dataset('domainProvider', fn (): array => domainProvider());

test('is email valid using builtin filter', function (string $email, bool $expected): void {
    $validate = new FOSSBilling\Core\Validation\Validator();
    expect($validate->isEmailValid($email))->toEqual($expected);
})->with('emailProvider');

dataset('emailProvider', fn (): array => emailProvider());

dataset('requiredParamsProvider', fn (): array => [
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
]);

test('check required params for array', function (array $data, array $required, array $variables, bool $expectException): void {
    $validate = new FOSSBilling\Core\Validation\Validator();

    if ($expectException) {
        expect(fn () => $validate->checkRequiredParamsForArray($required, $data, $variables))
            ->toThrow(FOSSBilling\Core\Exception\BaseException::class);
    } else {
        // Method returns void on success - wrap in closure and expect no exception
        expect(fn () => $validate->checkRequiredParamsForArray($required, $data, $variables))
            ->not->toThrow(FOSSBilling\Core\Exception\BaseException::class);
    }
})->with('requiredParamsProvider');

test('check required params passes with all required', function (): void {
    $validate = new FOSSBilling\Core\Validation\Validator();

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
        ->not->toThrow(FOSSBilling\Core\Exception\BaseException::class);
});

test('check required params fails with missing key', function (): void {
    $validate = new FOSSBilling\Core\Validation\Validator();

    $data = ['id' => 1];
    $required = [
        'id' => 'ID is required',
        'name' => 'Name is required',
    ];

    expect(fn () => $validate->checkRequiredParamsForArray($required, $data))
        ->toThrow(FOSSBilling\Core\Exception\BaseException::class, 'Name is required');
});

test('check required params fails with empty value', function (): void {
    $validate = new FOSSBilling\Core\Validation\Validator();

    $data = ['name' => ''];
    $required = [
        'name' => 'Name is required',
    ];

    expect(fn () => $validate->checkRequiredParamsForArray($required, $data))
        ->toThrow(FOSSBilling\Core\Exception\BaseException::class, 'Name is required');
});

test('check required params fails with null value', function (): void {
    $validate = new FOSSBilling\Core\Validation\Validator();

    $data = ['name' => null];
    $required = [
        'name' => 'Name is required',
    ];

    expect(fn () => $validate->checkRequiredParamsForArray($required, $data))
        ->toThrow(FOSSBilling\Core\Exception\BaseException::class, 'Name is required');
});

test('check required params with zero value passes', function (): void {
    $validate = new FOSSBilling\Core\Validation\Validator();

    $data = ['amount' => 0];
    $required = [
        'amount' => 'Amount is required',
    ];

    // Method returns void on success - wrap in closure and expect no exception
    expect(fn () => $validate->checkRequiredParamsForArray($required, $data))
        ->not->toThrow(FOSSBilling\Core\Exception\BaseException::class);
});

test('check required params with false value fails', function (): void {
    $validate = new FOSSBilling\Core\Validation\Validator();

    $data = ['enabled' => false];
    $required = [
        'enabled' => 'Enabled flag is required',
    ];

    expect(fn () => $validate->checkRequiredParamsForArray($required, $data))
        ->toThrow(FOSSBilling\Core\Exception\BaseException::class, 'Enabled flag is required');
});

test('check required params with custom error code', function (): void {
    $validate = new FOSSBilling\Core\Validation\Validator();

    $data = [];
    $required = ['id' => 'ID is required'];
    $errorCode = 12345;

    try {
        $validate->checkRequiredParamsForArray($required, $data, [], $errorCode);
        expect(false)->toBeTrue('Expected exception was not thrown');
    } catch (FOSSBilling\Core\Exception\BaseException $e) {
        expect($e->getCode())->toBe($errorCode);
        expect($e->getMessage())->toBe('ID is required');
    }
});

test('check required params with message placeholder', function (): void {
    $validate = new FOSSBilling\Core\Validation\Validator();

    $data = [];
    $required = ['key' => 'Key :key must be set'];
    $variables = [':key' => 'my_key'];

    expect(fn () => $validate->checkRequiredParamsForArray($required, $data, $variables))
        ->toThrow(FOSSBilling\Core\Exception\BaseException::class, 'Key my_key must be set');
});

test('check required params with multiple placeholders', function (): void {
    $validate = new FOSSBilling\Core\Validation\Validator();

    $data = [];
    $required = ['key' => 'Key :key must be set for array :array'];
    $variables = [
        ':key' => 'my_key',
        ':array' => 'config',
    ];

    expect(fn () => $validate->checkRequiredParamsForArray($required, $data, $variables))
        ->toThrow(FOSSBilling\Core\Exception\BaseException::class, 'Key my_key must be set for array config');
});

test('check required params with whitespace fails', function (): void {
    $validate = new FOSSBilling\Core\Validation\Validator();

    $data = ['name' => '   '];
    $required = ['name' => 'Name is required'];

    expect(fn () => $validate->checkRequiredParamsForArray($required, $data))
        ->toThrow(FOSSBilling\Core\Exception\BaseException::class, 'Name is required');
});
