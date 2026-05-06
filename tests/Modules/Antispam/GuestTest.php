<?php

declare(strict_types=1);

namespace AntispamTests;

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class GuestTest extends TestCase
{
    private ?array $originalConfig = null;

    protected function tearDown(): void
    {
        if ($this->originalConfig !== null) {
            $result = Request::makeRequest('admin/extension/config_save', [
                'ext' => 'mod_antispam',
                'check_temp_emails' => $this->originalConfig['check_temp_emails'] ?? true,
                'sfs' => $this->originalConfig['sfs'] ?? false,
            ]);
            $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
            $this->originalConfig = null;
        }

        parent::tearDown();
    }

    public function testDisposableEmailCheck(): void
    {
        $this->captureOriginalConfig();

        $result = Request::makeRequest('admin/extension/config_save', [
            'ext' => 'mod_antispam',
            'check_temp_emails' => true,
        ]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());

        $this->clearRateLimitCache();

        $password = 'A1a' . bin2hex(random_bytes(6));
        $result = Request::makeRequest('guest/client/create', [
            'email' => 'email@yopmail.net',
            'first_name' => 'Test',
            'password' => $password,
            'password_confirm' => $password,
        ]);

        $this->assertFalse($result->wasSuccessful(), 'The client account was created when it should not have been');
        $this->assertEquals('Disposable email addresses are not allowed', $result->getErrorMessage());

        if ($result->wasSuccessful()) {
            $id = intval($result->getResult());
            Request::makeRequest('admin/client/delete', ['id' => $id]);
        }
    }

    public function testStopForumSpam(): void
    {
        $this->captureOriginalConfig();

        $result = Request::makeRequest('admin/extension/config_save', [
            'ext' => 'mod_antispam',
            'sfs' => true,
        ]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());

        $this->clearRateLimitCache();

        $password = 'A1a' . bin2hex(random_bytes(6));
        $result = Request::makeRequest('guest/client/create', [
            'email' => 'email@example.com',
            'first_name' => 'Test',
            'password' => $password,
            'password_confirm' => $password,
        ]);

        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsNumeric($result->getResult());

        $id = intval($result->getResult());

        $result = Request::makeRequest('admin/client/delete', ['id' => $id]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertTrue($result->getResult());
    }

    private function clearRateLimitCache(): void
    {
        $result = Request::makeRequest('admin/system/clear_cache');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
    }

    private function captureOriginalConfig(): void
    {
        if ($this->originalConfig !== null) {
            return;
        }

        $result = Request::makeRequest('admin/extension/config_get', ['ext' => 'mod_antispam']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());

        $config = $result->getResult();
        $this->assertIsArray($config);

        $this->originalConfig = $config;
    }
}
