<?php


namespace Box\Mod\Cron\Api;


class GuestTest extends \BBTestCase {

    public function testgetDi()
    {
        $di = new \Box_Di();
        $api = new \Box\Mod\Cron\Api\Guest();
        $api->setDi($di);
        $getDi = $api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testcheck()
    {
        $api = new \Box\Mod\Cron\Api\Guest();

        $result = $api->check();
        $this->assertInternalType('bool', $result);
        $this->assertFalse($result);
    }

    public  function testsettings()
    {
        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())->method('getConfig')->will($this->returnValue(array()));

        $api = new \Box\Mod\Cron\Api\Guest();
        $api->setMod($modMock);

        $result = $api->settings();
        $this->assertInternalType('array', $result);
    }

    public function testis_late()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Cron\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('isLate')->will($this->returnValue(true));

        $api = new \Box\Mod\Cron\Api\Guest();
        $api->setService($serviceMock);

        $result = $api->is_late();
        $this->assertInternalType('bool', $result);
        $this->assertTrue($result);
    }

}
 