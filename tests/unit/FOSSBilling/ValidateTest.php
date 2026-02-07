<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace FOSSBilling\Tests\Unit\FOSSBilling;

require_once __DIR__ . '/../../../src/load.php';
require_once __DIR__ . '/../../../src/vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ValidateTest extends TestCase
{
    public static function domainProvider(): array
    {
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
    }

    #[DataProvider('domainProvider')]
    public function testIsSldValid(string $domain, bool $expected): void
    {
        $validate = new \FOSSBilling\Validate();
        $this->assertEquals($expected, $validate->isSldValid($domain));
    }

    public static function emailProvider(): array
    {
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
    }

    #[DataProvider('emailProvider')]
    public function testIsEmailValidUsingBuiltinFilter(string $email, bool $expected): void
    {
        // Validate uses PHP's built-in filter_var for email validation
        $this->assertEquals($expected, filter_var($email, FILTER_VALIDATE_EMAIL) !== false);
    }

    public static function requiredParamsProvider(): array
    {
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
    }

    #[DataProvider('requiredParamsProvider')]
    public function testCheckRequiredParamsForArray(array $data, array $required, array $variables, bool $expectException): void
    {
        $validate = new \FOSSBilling\Validate();

        if ($expectException) {
            $this->expectException(\FOSSBilling\Exception::class);
            $validate->checkRequiredParamsForArray($required, $data, $variables);
        } else {
            $this->assertNull($validate->checkRequiredParamsForArray($required, $data, $variables));
        }
    }

    public function testCheckRequiredParamsPassesWithAllRequired(): void
    {
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

        $this->assertNull($validate->checkRequiredParamsForArray($required, $data));
    }

    public function testCheckRequiredParamsFailsWithMissingKey(): void
    {
        $validate = new \FOSSBilling\Validate();

        $data = ['id' => 1];
        $required = [
            'id' => 'ID is required',
            'name' => 'Name is required',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Name is required');

        $validate->checkRequiredParamsForArray($required, $data);
    }

    public function testCheckRequiredParamsFailsWithEmptyValue(): void
    {
        $validate = new \FOSSBilling\Validate();

        $data = ['name' => ''];
        $required = [
            'name' => 'Name is required',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Name is required');

        $validate->checkRequiredParamsForArray($required, $data);
    }

    public function testCheckRequiredParamsFailsWithNullValue(): void
    {
        $validate = new \FOSSBilling\Validate();

        $data = ['name' => null];
        $required = [
            'name' => 'Name is required',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Name is required');

        $validate->checkRequiredParamsForArray($required, $data);
    }

    public function testCheckRequiredParamsWithZeroValuePasses(): void
    {
        $validate = new \FOSSBilling\Validate();

        $data = ['amount' => 0];
        $required = [
            'amount' => 'Amount is required',
        ];

        $this->assertNull($validate->checkRequiredParamsForArray($required, $data));
    }

    public function testCheckRequiredParamsWithFalseValueFails(): void
    {
        $validate = new \FOSSBilling\Validate();

        $data = ['enabled' => false];
        $required = [
            'enabled' => 'Enabled flag is required',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Enabled flag is required');

        $validate->checkRequiredParamsForArray($required, $data);
    }

    public function testCheckRequiredParamsWithCustomErrorCode(): void
    {
        $validate = new \FOSSBilling\Validate();

        $data = [];
        $required = ['id' => 'ID is required'];
        $errorCode = 12345;

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode($errorCode);
        $this->expectExceptionMessage('ID is required');

        $validate->checkRequiredParamsForArray($required, $data, [], $errorCode);
    }

    public function testCheckRequiredParamsWithMessagePlaceholder(): void
    {
        $validate = new \FOSSBilling\Validate();

        $data = [];
        $required = ['key' => 'Key :key must be set'];
        $variables = [':key' => 'my_key'];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Key my_key must be set');

        $validate->checkRequiredParamsForArray($required, $data, $variables);
    }

    public function testCheckRequiredParamsWithMultiplePlaceholders(): void
    {
        $validate = new \FOSSBilling\Validate();

        $data = [];
        $required = ['key' => 'Key :key must be set for array :array'];
        $variables = [
            ':key' => 'my_key',
            ':array' => 'config',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Key my_key must be set for array config');

        $validate->checkRequiredParamsForArray($required, $data, $variables);
    }

    public function testCheckRequiredParamsWithWhitespaceFails(): void
    {
        $validate = new \FOSSBilling\Validate();

        $data = ['name' => '   '];
        $required = ['name' => 'Name is required'];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Name is required');

        $validate->checkRequiredParamsForArray($required, $data);
    }
}
