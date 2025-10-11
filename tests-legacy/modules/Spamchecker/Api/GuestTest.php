<?php

namespace Box\Mod\Spamchecker\Api;

class GuestTest extends \BBTestCase
{
    /**
     * @var Guest
     */
    protected $api;

    public function setup(): void
    {
        $this->api = new Guest();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
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
                ],
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('datarecaptchaConfig')]
    public function testrecaptcha(array $config, array $expected): void
    {
        $di = new \Pimple\Container();
        $di['mod_config'] = $di->protect(fn (): array => $config);

        $this->api->setDi($di);
        $result = $this->api->recaptcha([]);

        $this->assertEquals($expected, $result);
    }
}
