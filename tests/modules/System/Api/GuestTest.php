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
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }
    public function testVersionAdmin()
    {
        $authMock = $this->getMockBuilder('\Box\Authorization')->getMock();
        $authMock->method('isAdminLoggedIn')->willReturn(true);

        $serviceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getVersion')
            ->willReturn(\FOSSBilling\Version::VERSION);

        $this->api->setDi(['auth' => $authMock]);
        $this->api->setService($serviceMock);
        $result = $this->api->version();

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testVersionShowPublicOn()
    {
        $authMock = $this->getMockBuilder('\Box\Authorization')->getMock();
        $authMock->method('isAdminLoggedIn')->willReturn(false);

        $serviceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getVersion')
            ->willReturn(\FOSSBilling\Version::VERSION);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->with('show_version_public')
            ->willReturn(1);

        $this->api->setDi(['auth' => $authMock]);
        $this->api->setService($serviceMock);
        $result = $this->api->version();

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testVersionShowPublicOff()
    {
        $authMock = $this->getMockBuilder('\Box\Authorization')->getMock();
        $authMock->method('isAdminLoggedIn')->willReturn(false);

        $serviceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->with('show_version_public')
            ->willReturn(0);

        $this->api->setDi(['auth' => $authMock]);
        $this->api->setService($serviceMock);
        $result = $this->api->version();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testCompanyShowPublicOn()
    {
        $companyData = array('companyName' => 'TestCo');

        $serviceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getCompany')
            ->willReturn($companyData);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->with('show_company_public')
            ->willReturn(1);

        $this->api->setService($serviceMock);
        $result = $this->api->company();

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testCompanyShowPublicOff()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->with('show_company_public')
            ->willReturn(0);

        $this->api->setService($serviceMock);
        $result = $this->api->company();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
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
        $di = new \Pimple\Container();

        $this->api->setDi($di);
        $this->api->setService($servuceMock);

        $result = $this->api->period_title($data);
        $this->assertIsString($result);
    }

    public function testperiod_titleMissingCode()
    {
        $data = array();
        $expected = '-';
        $di = new \Pimple\Container();

        $this->api->setDi($di);
        $result = $this->api->period_title($data);
        $this->assertIsString($result);
        $this->assertEquals($expected, $result);
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
        $setLang = 'en_US';
        $di = new \Pimple\Container();

        $di['config'] = [ 'i18n' => ['locale' => 'en_US' ] ];

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
