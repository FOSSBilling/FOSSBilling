<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Tests\Helpers\ApiClient;

if (!getenv('APP_URL') || !getenv('TEST_API_KEY')) {
    return;
}

$originalAntispamConfig = null;

beforeEach(function () use (&$originalAntispamConfig): void {
    $originalAntispamConfig = null;
});

afterEach(function () use (&$originalAntispamConfig): void {
    return;

    $result = ApiClient::request('admin/extension/config_save', array_merge(
        ['ext' => 'mod_antispam'],
        $originalAntispamConfig
    ));
    expect($result->wasSuccessful())->toBeTrue();
    $originalAntispamConfig = null;
});

function captureOriginalAntispamConfig(?array &$originalConfig): void
{
    if ($originalConfig !== null) {
        return;
    }

    $result = ApiClient::request('admin/antispam/get_config');
    expect($result->wasSuccessful())->toBeTrue();

    $config = $result->getResult();
    expect($config)->toBeArray();

    $originalConfig = $config;
}

test('saves antispam config', function () use (&$originalAntispamConfig): void {
    captureOriginalAntispamConfig($originalAntispamConfig);

    $result = ApiClient::request('admin/extension/config_save', [
        'ext' => 'mod_antispam',
        'captcha_enabled' => true,
        'captcha_provider' => 'hcaptcha',
        'hcaptcha_site_key' => 'site-key',
        'hcaptcha_secret_key' => 'secret-key',
        'captcha_recaptcha_v3_threshold' => '0.7',
        'check_temp_emails' => false,
        'sfs' => false,
    ]);
    expect($result->wasSuccessful())->toBeTrue();

    $result = ApiClient::request('admin/antispam/get_config');
    expect($result->wasSuccessful())->toBeTrue();

    $config = $result->getResult();
    expect($config)->toBeArray()
        ->and((bool) $config['captcha_enabled'])->toBeTrue()
        ->and($config['captcha_provider'])->toBe('hcaptcha')
        ->and($config['hcaptcha_site_key'])->toBe('site-key')
        ->and($config['hcaptcha_secret_key'])->toBe('secret-key')
        ->and($config['captcha_recaptcha_v3_threshold'])->toBe('0.7')
        ->and((bool) $config['check_temp_emails'])->toBeFalse()
        ->and((bool) $config['sfs'])->toBeFalse();
});

test('gets recaptcha config', function () use (&$originalAntispamConfig): void {
    captureOriginalAntispamConfig($originalAntispamConfig);

    $result = ApiClient::request('admin/extension/config_save', [
        'ext' => 'mod_antispam',
        'captcha_enabled' => true,
        'captcha_provider' => 'recaptcha_v3',
        'captcha_recaptcha_publickey' => 'recaptcha-site-key',
        'captcha_recaptcha_v3_threshold' => '0.8',
    ]);
    expect($result->wasSuccessful())->toBeTrue();

    $result = ApiClient::request('guest/antispam/recaptcha');
    expect($result->wasSuccessful())->toBeTrue();

    $config = $result->getResult();
    expect($config)->toBeArray()
        ->and((bool) $config['enabled'])->toBeTrue()
        ->and($config['captcha_provider'])->toBe('recaptcha_v3')
        ->and($config['publickey'])->toBe('recaptcha-site-key')
        ->and($config['recaptcha_v3_threshold'])->toBe('0.8');
});
