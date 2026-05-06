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
            $result = Request::makeRequest('admin/extension/config_save', array_merge(
                ['ext' => 'mod_antispam'],
                $this->originalConfig
            ));
            $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
            $this->originalConfig = null;
        }

        parent::tearDown();
    }

    public function testConfigSave(): void
    {
        $this->captureOriginalConfig();

        $result = Request::makeRequest('admin/extension/config_save', [
            'ext' => 'mod_antispam',
            'captcha_enabled' => true,
            'captcha_provider' => 'hcaptcha',
            'hcaptcha_site_key' => 'site-key',
            'hcaptcha_secret_key' => 'secret-key',
            'check_temp_emails' => false,
            'sfs' => false,
        ]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());

        $result = Request::makeRequest('admin/antispam/get_config');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());

        $config = $result->getResult();
        $this->assertIsArray($config);
        $this->assertTrue((bool) $config['captcha_enabled']);
        $this->assertSame('hcaptcha', $config['captcha_provider']);
        $this->assertSame('site-key', $config['hcaptcha_site_key']);
        $this->assertSame('secret-key', $config['hcaptcha_secret_key']);
        $this->assertFalse((bool) $config['check_temp_emails']);
        $this->assertFalse((bool) $config['sfs']);
    }

    public function testRecaptchaConfig(): void
    {
        $this->captureOriginalConfig();

        $result = Request::makeRequest('admin/extension/config_save', [
            'ext' => 'mod_antispam',
            'captcha_enabled' => true,
            'captcha_provider' => 'turnstile',
            'turnstile_site_key' => 'turnstile-site-key',
        ]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());

        $result = Request::makeRequest('guest/antispam/recaptcha');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());

        $config = $result->getResult();
        $this->assertIsArray($config);
        $this->assertTrue((bool) $config['enabled']);
        $this->assertSame('turnstile', $config['captcha_provider']);
        $this->assertSame('turnstile-site-key', $config['turnstile_site_key']);
        $this->assertNull($config['publickey']);
    }

    private function captureOriginalConfig(): void
    {
        if ($this->originalConfig !== null) {
            return;
        }

        $result = Request::makeRequest('admin/antispam/get_config');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());

        $config = $result->getResult();
        $this->assertIsArray($config);

        $this->originalConfig = $config;
    }
}
