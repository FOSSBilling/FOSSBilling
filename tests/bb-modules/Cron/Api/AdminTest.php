<?php


namespace Box\Mod\Cron\Api;


class AdminTest extends \BBTestCase {

    public function testgetDi()
    {
        $di = new \Box_Di();
        $api_admin = new \Box\Mod\Cron\Api\Admin();
        $api_admin->setDi($di);
        $getDi = $api_admin->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testinfo()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Cron\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getCronInfo')->will($this->returnValue(array()));

        $api_admin = new \Box\Mod\Cron\Api\Admin();
        $api_admin->setService($serviceMock);

        $result = $api_admin->info(array());
        $this->assertIsArray($result);
    }

    public function testrun()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Cron\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('runCrons')->will($this->returnValue(true));

        $api_admin = new \Box\Mod\Cron\Api\Admin();
        $api_admin->setService($serviceMock);

        $result = $api_admin->run(array());
        $this->assertIsBool($result);
    }


}
 