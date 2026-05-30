<?php

declare(strict_types=1);

namespace Box\Mod\Antispam\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class GuestTest extends \BBTestCase
{
    protected ?Guest $api;

    public function setUp(): void
    {
        $this->api = new Guest();
    }

    public static function dataRecaptchaConfig(): array
    {
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
                    'recaptcha_v3_threshold' => 0.5,
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
                    'recaptcha_v3_threshold' => 0.5,
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
                    'recaptcha_v3_threshold' => 0.5,
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
                    'recaptcha_v3_threshold' => 0.5,
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
                    'recaptcha_v3_threshold' => 0.5,
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
                    'recaptcha_v3_threshold' => 0.5,
                    'turnstile_site_key' => null,
                    'hcaptcha_site_key' => 'abc',
                ],
            ],
            [
                [
                    'captcha_enabled' => true,
                    'captcha_provider' => 'recaptcha_v3',
                    'captcha_recaptcha_publickey' => 'abc',
                    'captcha_recaptcha_v3_threshold' => '0.7',
                ],
                [
                    'publickey' => 'abc',
                    'enabled' => true,
                    'version' => null,
                    'captcha_provider' => 'recaptcha_v3',
                    'recaptcha_v3_threshold' => '0.7',
                    'turnstile_site_key' => null,
                    'hcaptcha_site_key' => null,
                ],
            ],
        ];
    }

    #[DataProvider('dataRecaptchaConfig')]
    public function testRecaptcha(array $config, array $expected): void
    {
        $di = $this->getDi();
        $di['mod_config'] = $di->protect(fn (): array => $config);

        $this->api->setDi($di);
        $result = $this->api->recaptcha([]);

        $this->assertEquals($expected, $result);
    }
}
