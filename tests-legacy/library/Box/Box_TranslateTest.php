<?php

declare(strict_types=1);

#[PHPUnit\Framework\Attributes\Group('Core')]
final class Box_TranslateTest extends PHPUnit\Framework\TestCase
{
    public function testSetLocale(): void
    {
        $locale = 'en_US';
        $translateObj = new Box_Translate();
        $translateObj->setLocale($locale);
        $result = $translateObj->getLocale();

        $this->assertEquals($locale, $result);
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
