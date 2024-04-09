<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Box_TranslateTest extends PHPUnit\Framework\TestCase
{
    public function testsetLocale(): void
    {
        $locale = 'en_US';
        $translateObj = new Box_Translate();
        $translateObj->setLocale($locale);
        $result = $translateObj->getLocale();

        $this->assertEquals($locale, $result);
    }

    public function testDi(): void
    {
        $di = new Pimple\Container();
        $translateObj = new Box_Translate();
        $translateObj->setDi($di);
        $getDi = $translateObj->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testDomainSetterAndGetter(): void
    {
        $translateObj = new Box_Translate();

        $default = 'messages';
        $result = $translateObj->getDomain();
        $this->assertEquals($default, $result);

        $newDomain = 'admin';
        $result = $translateObj->setDomain($newDomain)->getDomain();
        $this->assertEquals($newDomain, $result);
    }

    public function testTranslate(): void
    {
        $text = 'Translate ME';
        $result = __trans($text);

        $this->assertEquals($text, $result);
    }
}
