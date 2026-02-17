<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;

beforeEach(function () {
    $api = new \Box\Mod\Spamchecker\Api\Guest();
});

dataset('recaptcha config', function () {
    return [
        [
            [
                'captcha_recaptcha_publickey' => 1234,
                'captcha_enabled' => true,
            ],
            [
                'publickey' => 1234,
                'enabled' => true,
                'version' => null,
                'captcha_provider' => 'recaptcha_v2',
                'turnstile_site_key' => null,
                'hcaptcha_site_key' => null,
            ],
        ],
        [
            [
                'captcha_enabled' => true,
            ],
            [
                'publickey' => null,
                'enabled' => true,
                'version' => null,
                'captcha_provider' => 'recaptcha_v2',
                'turnstile_site_key' => null,
                'hcaptcha_site_key' => null,
            ],
        ],
        [
            [
                'captcha_recaptcha_publickey' => 1234,
                'captcha_enabled' => false,
                'captcha_version' => 2,
            ],
            [
                'publickey' => 1234,
                'enabled' => false,
                'version' => 2,
                'captcha_provider' => 'recaptcha_v2',
                'turnstile_site_key' => null,
                'hcaptcha_site_key' => null,
            ],
        ],
        [
            [
                'captcha_enabled' => false,
            ],
            [
                'publickey' => null,
                'enabled' => false,
                'version' => null,
                'captcha_provider' => 'recaptcha_v2',
                'turnstile_site_key' => null,
                'hcaptcha_site_key' => null,
            ],
        ],
        [
            [
                'captcha_enabled' => true,
                'captcha_provider' => 'turnstile',
                'turnstile_site_key' => 'abc',
            ],
            [
                'publickey' => null,
                'enabled' => true,
                'version' => null,
                'captcha_provider' => 'turnstile',
                'turnstile_site_key' => 'abc',
                'hcaptcha_site_key' => null,
            ],
        ],
        [
            [
                'captcha_enabled' => true,
                'captcha_provider' => 'hcaptcha',
                'hcaptcha_site_key' => 'abc',
            ],
            [
                'publickey' => null,
                'enabled' => true,
                'version' => null,
                'captcha_provider' => 'hcaptcha',
                'turnstile_site_key' => null,
                'hcaptcha_site_key' => 'abc',
            ],
        ],
    ];
});

test('dependency injection', function (): void {
    $api = new \Box\Mod\Spamchecker\Api\Guest();
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toEqual($di);
});

test('recaptcha', function (array $config, array $expected) {
    $api = new \Box\Mod\Spamchecker\Api\Guest();
    $di = container();
    $di['mod_config'] = $di->protect(fn (): array => $config);

    $api->setDi($di);
    $result = $api->recaptcha([]);

    expect($result)->toEqual($expected);
})->with('recaptcha config');
