<?php
/**
 * @group Core
 */
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
        $di = new \Box_Di();
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
        global $di;

        $text = 'Translate ME';
        $result = $di['translate']()->__($text);

        $this->assertEquals($text, $result);
    }

}