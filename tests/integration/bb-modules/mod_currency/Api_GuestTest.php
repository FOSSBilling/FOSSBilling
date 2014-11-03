<?php
/**
 * @group Core
 */
class Api_Guest_CurrencyTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'currencies.xml';

    public function testFormat()
    {
        $string = $this->api_guest->currency_format(array('price'=>'2'));
        $this->assertEquals('$2.00', $string);
        
        $string = $this->api_guest->currency_format(array('price'=>'2', 'convert'=>0));
        $this->assertEquals('$2.00', $string);
        
        $string = $this->api_guest->currency_format(array('price'=>'1', 'code' => 'EUR', 'convert'=>0));
        $this->assertEquals('1.00 EUR', $string);
        
        $string = $this->api_guest->currency_format(array('price'=>'1', 'code' => 'EUR', 'convert'=>1));
        $this->assertEquals('0.75 EUR', $string);
    }

    public function testCurrency()
    {
        $array = $this->api_guest->currency_get_pairs();
        $this->assertInternalType('array', $array);

        $array = $this->api_guest->currency_get(array('code'=>'usd'));
        $this->assertInternalType('array', $array);
        $this->assertEquals('USD', $array['code']);

        $array = $this->api_guest->currency_get(array('code'=>'eur'));
        $this->assertInternalType('array', $array);
        $this->assertEquals('EUR', $array['code']);
    }
}