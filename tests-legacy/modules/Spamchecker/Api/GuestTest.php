<?php

declare(strict_types=1);

namespace Box\Mod\Spamchecker\Api;
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

    public function testGetDi(): void
    {
        $di = $this->getDi();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public static function datarecaptchaConfig(): array
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
    }

    #[DataProvider('datarecaptchaConfig')]
    public function testRecaptcha(array $config, array $expected): void
    {
        $di = $this->getDi();
        $di['mod_config'] = $di->protect(fn (): array => $config);

        $this->api->setDi($di);
        $result = $this->api->recaptcha([]);

        $this->assertEquals($expected, $result);
    }
}
