<?php

declare(strict_types=1);

namespace CurrencyTests;

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class GuestTest extends TestCase
{
    public function testCurrencyDefaults()
    {
        $result = Request::makeRequest('guest/currency/get_currency_defaults', ['code' => 'USD']);
        $this->assertTrue($result->wasSuccessful());

        $defaults = $result->getResult();
        $this->assertEquals($defaults['code'], 'USD');
        $this->assertEquals($defaults['name'], 'US Dollar');
        $this->assertEquals($defaults['symbol'], '$');
        $this->assertEquals($defaults['minorUnits'], 2);
    }
}
