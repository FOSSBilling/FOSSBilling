<?php


namespace Box\Tests\Mod\Branding;


class ServiceTest extends \BBTestCase
{

    public function testDi()
    {
        $service = new \Box\Mod\Branding\Service();

        $di = new \Box_Di();
        $db = $this->getMockBuilder('Box_Database')->getMock();

        $di['db'] = $db;
        $service->setDi($di);
        $result = $service->getDi();
        $this->assertEquals($di, $result);
    }

    public function testuninstallException()
    {
        $licenseMock = $this->getMockBuilder('\Box_License')->getMock();
        $licenseMock->expects($this->atLeastOnce())
            ->method('isPro')
            ->will($this->returnValue(false));

        $di = new \Box_Di();
        $di['license'] = $licenseMock;

        $brandingService = new \Box\Mod\Branding\Service();
        $brandingService->setDi($di);
		
        $this->expectException(\Exception::class);
		$this->expectExceptionCode(509);
        $this->expectExceptionMessage('Branding module can only be disabled for PRO license owners');
        $brandingService->uninstall();

    }

    public function testuninstal()
    {
        $licenseMock = $this->getMockBuilder('\Box_License')->getMock();
        $licenseMock->expects($this->atLeastOnce())
            ->method('isPro')
            ->will($this->returnValue(true));

        $di = new \Box_Di();
        $di['license'] = $licenseMock;

        $brandingService = new \Box\Mod\Branding\Service();
        $brandingService->setDi($di);

        $result = $brandingService->uninstall();
        $this->assertTrue($result);
    }

}
 