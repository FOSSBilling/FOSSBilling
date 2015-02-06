<?php

namespace Box\Mod\Spamchecker\Api;


class GuestTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Box\Mod\Spamchecker\Api\Guest
     */
    protected $api = null;

    public function setup()
    {
        $this->api = new \Box\Mod\Spamchecker\Api\Guest();
    }

    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function datarecaptchaConfig()
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
                ),
            ),
            array(
                array(

                    'captcha_enabled' => true,
                ),
                array(
                    'publickey' => null,
                    'enabled' => true,
                ),
            ),
            array(
                array(
                    'captcha_recaptcha_publickey' => 1234,
                    'captcha_enabled' => false,
                ),
                array(
                    'publickey' => 1234,
                    'enabled' => false,
                ),
            ),
            array(
                array(
                    'captcha_enabled' => false,
                ),
                array(
                    'publickey' => null,
                    'enabled' => false,
                ),
            ),
        );
    }

    /**
     * @dataProvider datarecaptchaConfig
     */
    public function testrecaptcha($config, $expected)
    {
        $di = new \Box_Di();
        $di['mod_config'] = $di->protect(function () use ($config){
            return $config;
        });
        
        $this->api->setDi($di);
        $result = $this->api->recaptcha(array());

        $this->assertEquals($expected, $result);


    }
}