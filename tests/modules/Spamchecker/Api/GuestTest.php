<?php

namespace Box\Mod\Spamchecker\Api;


class GuestTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Spamchecker\Api\Guest
     */
    protected $api = null;

    public function setup(): void
    {
        $this->api = new \Box\Mod\Spamchecker\Api\Guest();
    }

    public function testgetDi()
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public static function datarecaptchaConfig()
    {
        return array(
            array(
                array(
                    'captcha_recaptcha_publickey' => 1234,
                    'captcha_enabled' => true,
                ),
                array(
                    'publickey' => 1234,
                    'enabled' => true,
                    'version' => null
                ),
            ),
            array(
                array(

                    'captcha_enabled' => true,
                ),
                array(
                    'publickey' => null,
                    'enabled' => true,
                    'version' => null
                ),
            ),
            array(
                array(
                    'captcha_recaptcha_publickey' => 1234,
                    'captcha_enabled' => false,
                    'captcha_version' => 2
                ),
                array(
                    'publickey' => 1234,
                    'enabled' => false,
                    'version' => 2
                ),
            ),
            array(
                array(
                    'captcha_enabled' => false,
                ),
                array(
                    'publickey' => null,
                    'enabled' => false,
                    'version' => null
                ),
            ),
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('datarecaptchaConfig')]
    public function testrecaptcha($config, $expected)
    {
        $di = new \Pimple\Container();
        $di['mod_config'] = $di->protect(fn() => $config);


        $this->api->setDi($di);
        $result = $this->api->recaptcha(array());

        $this->assertEquals($expected, $result);


    }
}
