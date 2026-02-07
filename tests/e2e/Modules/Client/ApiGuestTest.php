<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace FOSSBilling\Tests\E2E\Modules\Client;

use FOSSBilling\Tests\Library\E2E\TestCase;
use FOSSBilling\Tests\Library\E2E\ApiClient;
use FOSSBilling\Tests\Library\E2E\Traits\ApiAssertions;

final class ApiGuestTest extends TestCase
{
    public function testCanCreateAndDeleteClient(): void
    {
        $clientId = $this->createClient();

        $result = ApiClient::request('admin/client/delete', ['id' => $clientId]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
        $this->assertTrue($result->getResult());
    }

    public function testPhoneCCMustBeGreaterThanZero(): void
    {
        $password = 'A1a' . bin2hex(random_bytes(6));
        $result = ApiClient::request('guest/client/create', [
            'email' => 'test_' . uniqid() . '@example.com',
            'first_name' => 'Test',
            'password' => $password,
            'password_confirm' => $password,
            'phone_cc' => -1,
        ]);

        $this->assertFalse($result->wasSuccessful());
    }

    public function testPhoneCCMaximumLimit(): void
    {
        $password = 'A1a' . bin2hex(random_bytes(6));
        $result = ApiClient::request('guest/client/create', [
            'email' => 'test_' . uniqid() . '@example.com',
            'first_name' => 'Test',
            'password' => $password,
            'password_confirm' => $password,
            'phone_cc' => 1000,
        ]);

        $this->assertFalse($result->wasSuccessful());
    }

    public function testPhoneNumberLengthValidation(): void
    {
        $password = 'A1a' . bin2hex(random_bytes(6));
        $result = ApiClient::request('guest/client/create', [
            'email' => 'test_' . uniqid() . '@example.com',
            'first_name' => 'Test',
            'password' => $password,
            'password_confirm' => $password,
            'phone' => '123456789101123',
        ]);

        $this->assertFalse($result->wasSuccessful());
    }

    private function createClient(): int
    {
        $password = 'A1a' . bin2hex(random_bytes(6));
        $result = ApiClient::request('guest/client/create', [
            'email' => 'client_' . uniqid() . '@example.com',
            'first_name' => 'Test',
            'password' => $password,
            'password_confirm' => $password,
            'phone_cc' => 1,
            'phone' => '(216) 245-2368',
        ]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
        $this->assertIsInt($result->getResult());

        return (int) $result->getResult();
    }
}
