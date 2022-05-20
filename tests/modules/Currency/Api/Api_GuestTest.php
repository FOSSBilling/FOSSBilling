<?php

namespace Box\Tests\Mod\Currency\Api;

class Api_GuestTest extends \BBTestCase
{
    public function testGetPairs()
    {
        $guestApi = new \Box\Mod\Currency\Api\Guest();

        $willReturn = array(
            'EUR' => 'Euro',
            'USD' => 'US Dollar'
        );

        $service = $this->getMockBuilder('\Box\Mod\Currency\Service')->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getPairs')
            ->will($this->returnValue($willReturn));

        $guestApi->setService($service);

        $result = $guestApi->get_pairs(array());
        $this->assertEquals($result, $willReturn);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('EUR', $result);
        $this->assertArrayHasKey('USD', $result);
    }

    public function getProvider()
    {
        $model = new \Model_Currency();

        return array(
            array(
                array(
                    'code' => 'EUR'
                ),
                $model,
                $this->atLeastOnce(),
                $this->never()
            ),
            array(
                array(),
                $model,
                $this->never(),
                $this->atLeastOnce()
            )
        );
    }

    /**
     *
     * @dataProvider getProvider
     */
    public function testGet($data, $model, $expectsGetByCode, $expectsGetDefault)
    {
        $guestApi = new \Box\Mod\Currency\Api\Guest();

        $willReturn = array(
            'code'            => 'EUR',
            'title'           => 'Euro',
            'conversion_rate' => 1,
            'format'          => '{{price}}',
            'price_format'    => 1,
            'default'         => 1,
        );

        $service = $this->getMockBuilder('\Box\Mod\Currency\Service')->getMock();
        $service->expects($expectsGetByCode)
            ->method('getByCode')
            ->will($this->returnValue($model));

        $service->expects($expectsGetDefault)
            ->method('getDefault')
            ->will($this->returnValue($model));

        $service->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->will($this->returnValue($willReturn));

        $guestApi->setService($service);

        $result = $guestApi->get($data);
        $this->assertIsArray($result);
        $this->assertEquals($result, $willReturn);
    }

    public function testGetException()
    {
        $guestApi = new \Box\Mod\Currency\Api\Guest();

        $willReturn = array(
            'code'            => 'EUR',
            'title'           => 'Euro',
            'conversion_rate' => 1,
            'format'          => '{{price}}',
            'price_format'    => 1,
            'default'         => 1,
        );

        $service = $this->getMockBuilder('\Box\Mod\Currency\Service')->getMock();
        $service->expects($this->never())
            ->method('getByCode')
            ->will($this->returnValue(null));

        $service->expects($this->atLeastOnce())
            ->method('getDefault')
            ->will($this->returnValue(null));

        $guestApi->setService($service);
        $this->expectException(\Box_Exception::class);
        $result = $guestApi->get(array()); //Expecting \Box_Exception
    }

    public function formatPriceFormatProvider()
    {
        return array(
            array(
                1,
                "€ 60000.00"
            ),
            array(
                2,
                "€ 60,000.00"
            ),
            array(
                3,
                "€ 60.000,00"
            ),
            array(
                4,
                "€ 60,000"
            ),
            array(
                5,
                "€ 60000"
            ),
        );
    }

    /**
     * @dataProvider formatPriceFormatProvider
     */
    public function testFormatPriceFormat($price_format, $expectedResult)
    {

        $willReturn = array(
            'code'            => 'EUR',
            'title'           => 'Euro',
            'conversion_rate' => 0.6,
            'format'          => '€ {{price}}',
            'price_format'    => $price_format,
            'default'         => 1,
        );

        $data     = array(
            'code'             => 'EUR',
            'price'            => 100000,
            'without_currency' => false,
        );
        $guestApi = $this->getMockBuilder('\Box\Mod\Currency\Api\Guest')->setMethods(array('get'))->getMock();
        $guestApi->expects($this->atLeastOnce())
            ->method('get')
            ->will($this->returnValue($willReturn));

        $service = $this->getMockBuilder('\Box\Mod\Currency\Service')->getMock();

        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $guestApi->setDi($di);
        $guestApi->setService($service);

        $result = $guestApi->format($data);
        $this->assertEquals($result, $expectedResult);
    }

    public function formatProvider()
    {
        return array(
            array(
                array(
                    'code' => 'EUR',
                ),
                "€ 0.00" //price is not set, so result should be 0
            ),
            array(
                array(
                    'code'    => 'EUR',
                    'price'   => 100000,
                    'convert' => false //Should not convert
                ),
                "€ 100000.00"
            ),
            array(
                array(
                    'code'             => 'EUR',
                    'price'            => 100000,
                    'without_currency' => true, //Should return number only
                ),
                "60000.00"
            ),
        );
    }

    /**
     * @dataProvider formatProvider
     */
    public function testFormat($data, $expectedResult)
    {

        $willReturn = array(
            'code'            => 'EUR',
            'title'           => 'Euro',
            'conversion_rate' => 0.6,
            'format'          => '€ {{price}}',
            'price_format'    => 1,
            'default'         => 1,
        );


        $guestApi = $this->getMockBuilder('\Box\Mod\Currency\Api\Guest')->setMethods(array('get'))->getMock();
        $guestApi->expects($this->atLeastOnce())
            ->method('get')
            ->will($this->returnValue($willReturn));

        $service = $this->getMockBuilder('\Box\Mod\Currency\Service')->getMock();

        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });$guestApi->setDi($di);
        $guestApi->setService($service);

        $result = $guestApi->format($data);
        $this->assertEquals($result, $expectedResult);
    }
}
 