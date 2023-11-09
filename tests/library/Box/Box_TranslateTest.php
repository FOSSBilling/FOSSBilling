<?php
#[\PHPUnit\Framework\Attributes\Group('Core')]
class Box_TranslateTest extends PHPUnit\Framework\TestCase
{
    public function testsetLocale()
    {
        $locale = 'en_US';
        $translateObj = new \Box_Translate();
        $translateObj->setLocale($locale);
        $result = $translateObj->getLocale();

        $this->assertEquals($locale, $result);
    }

    public function testDi()
    {
        $di = new \Pimple\Container();
        $translateObj = new \Box_Translate();
        $translateObj->setDi($di);
        $getDi = $translateObj->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testDomain_setterAndGetter()
    {
        $translateObj = new \Box_Translate();

        $default = 'messages';
        $result = $translateObj->getDomain();
        $this->assertEquals($default, $result);

        $newDomain = 'admin';
        $result = $translateObj->setDomain($newDomain)->getDomain();
        $this->assertEquals($newDomain, $result);
    }

    public function testTranslate()
    {
        $text = 'Translate ME';
        $result = __trans($text);

        $this->assertEquals($text, $result);
    }

}