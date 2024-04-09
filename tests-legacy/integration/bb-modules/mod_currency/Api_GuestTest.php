<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Api_GuestTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'currencies.xml';

    public function testFormat(): void
    {
        $string = $this->api_guest->currency_format(['price' => '2']);
        $this->assertEquals('$2.00', $string);

        $string = $this->api_guest->currency_format(['price' => '2', 'convert' => 0]);
        $this->assertEquals('$2.00', $string);

        $string = $this->api_guest->currency_format(['price' => '1', 'code' => 'EUR', 'convert' => 0]);
        $this->assertEquals('1.00 EUR', $string);

        $string = $this->api_guest->currency_format(['price' => '1', 'code' => 'EUR', 'convert' => 1]);
        $this->assertEquals('0.75 EUR', $string);
    }

    public function testCurrency(): void
    {
        $array = $this->api_guest->currency_get_pairs();
        $this->assertIsArray($array);

        $array = $this->api_guest->currency_get(['code' => 'usd']);
        $this->assertIsArray($array);
        $this->assertEquals('USD', $array['code']);

        $array = $this->api_guest->currency_get(['code' => 'eur']);
        $this->assertIsArray($array);
        $this->assertEquals('EUR', $array['code']);
    }
}
