<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace FOSSBilling\Tests\E2E\Modules\Spamchecker;

use FOSSBilling\Tests\Library\E2E\TestCase;
use FOSSBilling\Tests\Library\E2E\ApiClient;

final class ApiGuestTest extends TestCase
{
    public function testDisposableEmailCheck(): void
    {
        $result = ApiClient::request('admin/extension/activate', ['type' => 'mod', 'id' => 'spamchecker']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());

        $result = ApiClient::request('admin/extension/config_save', ['ext' => 'mod_spamchecker', 'check_temp_emails' => true]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());

        $password = 'A1a' . bin2hex(random_bytes(6));
        $result = ApiClient::request('guest/client/create', [
            'email' => 'email@yopmail.net',
            'first_name' => 'Test',
            'password' => $password,
            'password_confirm' => $password,
        ]);

        $this->assertFalse($result->wasSuccessful());
        $this->assertEquals('Disposable email addresses are not allowed', $result->getErrorMessage());

        if ($result->wasSuccessful()) {
            $id = intval($result->getResult());
            ApiClient::request('admin/client/delete', ['id' => $id]);
        }
    }

    public function testStopForumSpam(): void
    {
        $result = ApiClient::request('admin/extension/activate', ['type' => 'mod', 'id' => 'spamchecker']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());

        $result = ApiClient::request('admin/extension/config_save', ['ext' => 'mod_spamchecker', 'sfs' => true]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());

        $password = 'A1a' . bin2hex(random_bytes(6));
        $result = ApiClient::request('guest/client/create', [
            'email' => 'email@example.com',
            'first_name' => 'Test',
            'password' => $password,
            'password_confirm' => $password,
        ]);

        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
        $this->assertIsNumeric($result->getResult());

        $id = intval($result->getResult());

        $result = ApiClient::request('admin/client/delete', ['id' => $id]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
        $this->assertTrue($result->getResult());
    }
}
