<?php


namespace Box\Mod\System\Api;


class GuestTest extends \BBTestCase {
    /**
     * @var \Box\Mod\System\Api\Guest
     */
    protected $api = null;

    public function setup(): void
    {
        $this->api= new \Box\Mod\System\Api\Guest();
    }

    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testversion()
    {
        $servuceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $servuceMock->expects($this->atLeastOnce())
            ->method('getVersion')
            ->will($this->returnValue(\Box_Version::VERSION));

        $this->api->setService($servuceMock);
        $result = $this->api->version();
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testcompany()
    {
        $data = array(

        );
        $servuceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $servuceMock->expects($this->atLeastOnce())
            ->method('getCompany')
            ->will($this->returnValue(array()));

        $this->api->setService($servuceMock);

        $result = $this->api->company($data);
        $this->assertIsArray($result);
    }

    public function testphone_codes()
    {
        $data = array(

        );
        $servuceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $servuceMock->expects($this->atLeastOnce())
            ->method('getPhoneCodes')
            ->will($this->returnValue(array()));

        $this->api->setService($servuceMock);

        $result = $this->api->phone_codes($data);
        $this->assertIsArray($result);
    }

    public function teststates()
    {
        $servuceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $servuceMock->expects($this->atLeastOnce())
            ->method('getStates')
            ->will($this->returnValue(array()));

        $this->api->setService($servuceMock);

        $result = $this->api->states();
        $this->assertIsArray($result);
    }

    public function testcountries_eunion()
    {
        $servuceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $servuceMock->expects($this->atLeastOnce())
            ->method('getEuCountries')
            ->will($this->returnValue(array()));

        $this->api->setService($servuceMock);

        $result = $this->api->countries_eunion();
        $this->assertIsArray($result);
    }

    public function testcountries()
    {
        $servuceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $servuceMock->expects($this->atLeastOnce())
            ->method('getCountries')
            ->will($this->returnValue(array()));

        $this->api->setService($servuceMock);

        $result = $this->api->countries();
        $this->assertIsArray($result);
    }

    public function testparam()
    {
        $data = array('key' => 'keyValue');

        $servuceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $servuceMock->expects($this->atLeastOnce())
            ->method('getPublicParamValue')
            ->will($this->returnValue('paramValue'));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($servuceMock);

        $result = $this->api->param($data);
        $this->assertIsString($result);
    }

    public function testPeriods()
    {
        $result = $this->api->periods();
        $this->assertIsArray($result);
    }

    public function testperiod_title()
    {
        $data = array('code' => 'periodCode');

        $servuceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $servuceMock->expects($this->atLeastOnce())
            ->method('getPeriod')
            ->will($this->returnValue('periodTtitleValue'));
        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->api->setDi($di);
        $this->api->setService($servuceMock);

        $result = $this->api->period_title($data);
        $this->assertIsString($result);
    }

    public function testperiod_titleMissingCode()
    {
        $data = array();
        $expected = '-';
        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->api->setDi($di);
        $result = $this->api->period_title($data);
        $this->assertIsString($result);
        $this->assertEquals($expected, $result);
    }

    public function testcurrent_url()
    {
        $requestMock = $this->getMockBuilder('\Box_Request')->getMock();
        $requestMock->expects($this->atLeastOnce())
            ->method('getURI')
            ->will($this->returnValue('StringTypeUrl'));

        $di = new \Box_Di();
        $di['request'] = $requestMock;

        $this->api->setDi($di);
        $result = $this->api->current_url();
        $this->assertIsString($result);
    }

    public function testtemplate_exists()
    {
        $data = array(
            'file' => 'testing.txt',
        );

        $servuceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $servuceMock->expects($this->atLeastOnce())
            ->method('templateExists')
            ->will($this->returnValue(true));

        $this->api->setService($servuceMock);

        $result = $this->api->template_exists($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testtemplate_existsFileParamMissing()
    {
        $data = array(
        );

        $result = $this->api->template_exists($data);
        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    public function testlocale()
    {
        $setLang = 'FR';
        $di = new \Box_Di();

        $cookieMock = $this->getMockBuilder('\Box_Cookie')->getMock();
        $cookieMock->expects($this->atLeastOnce())
            ->method('has')
            ->will($this->returnValue(true));
        $cookieMock->expects($this->atLeastOnce())
            ->method('get')
            ->with('BBLANG')
            ->will($this->returnValue($setLang));

        $di['cookie'] = $cookieMock;
        $di['config'] = array('locale' => 'EN');


        $this->api->setDi($di);

        $result = $this->api->locale();
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
        $this->assertEquals($setLang, $result);
    }

    public function testget_pending_messages()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $messageArr = array('Important message to user');
        $serviceMock->expects($this->atLeastOnce())
            ->method('getPendingMessages')
            ->willReturn($messageArr);

        $serviceMock->expects($this->atLeastOnce())
            ->method('clearPendingMessages');

        $this->api->setService($serviceMock);
        $result = $this->api->get_pending_messages();
        $this->assertIsArray($result);
        $this->assertEquals($messageArr, $result);
    }

}
 