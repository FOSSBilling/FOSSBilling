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
            'captcha_recaptcha_v3_threshold' => '0.7',
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
        $this->assertSame('0.7', $config['captcha_recaptcha_v3_threshold']);
        $this->assertFalse((bool) $config['check_temp_emails']);
        $this->assertFalse((bool) $config['sfs']);
    }

    public function testRecaptchaConfig(): void
    {
        $this->captureOriginalConfig();

        $result = Request::makeRequest('admin/extension/config_save', [
            'ext' => 'mod_antispam',
            'captcha_enabled' => true,
            'captcha_provider' => 'recaptcha_v3',
            'captcha_recaptcha_publickey' => 'recaptcha-site-key',
            'captcha_recaptcha_v3_threshold' => '0.8',
        ]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());

        $result = Request::makeRequest('guest/antispam/recaptcha');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());

        $config = $result->getResult();
        $this->assertIsArray($config);
        $this->assertTrue((bool) $config['enabled']);
        $this->assertSame('recaptcha_v3', $config['captcha_provider']);
        $this->assertSame('recaptcha-site-key', $config['publickey']);
        $this->assertSame('0.8', $config['recaptcha_v3_threshold']);
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
