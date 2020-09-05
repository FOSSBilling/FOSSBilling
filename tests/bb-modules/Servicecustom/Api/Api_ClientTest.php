<?php
namespace Box\Tests\Mod\Servicecustom\Api;


class Api_ClientTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Servicecustom\Api\Client
     */
    protected $api = null;

    public function setup(): void
    {
        $this->api = new \Box\Mod\Servicecustom\Api\Client();
    }

    public function testCall()
    {
        $serviceMock = $this->getMockBuilder('Box\Mod\Servicecustom\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getServiceCustomByOrderId')
            ->will($this->returnValue(new \Model_ServiceCustom()));
        $serviceMock->expects($this->atLeastOnce())
            ->method('customCall')
            ->will($this->returnValue(null));

        $arguments = array(
            0 => array(
                'order_id' => rand(1, 100)
            ),
        );

        $this->api->setService($serviceMock);

        $this->api->__call('delete', $arguments);
    }

    public function testCallArgumentsNotSetException()
    {
        $serviceMock = $this->getMockBuilder('Box\Mod\Servicecustom\Service')->getMock();
        $serviceMock->expects($this->never())
            ->method('getServiceCustomByOrderId')
            ->will($this->returnValue(new \Model_ServiceCustom()));
        $serviceMock->expects($this->never())
            ->method('customCall')
            ->will($this->returnValue(null));

        $arguments = array();

        $this->api->setService($serviceMock);
        $this->expectException(\Exception::class);
        $this->api->__call('delete', $arguments);
    }

    public function testCallOrderIdNotSetException()
    {
        $serviceMock = $this->getMockBuilder('Box\Mod\Servicecustom\Service')->getMock();
        $serviceMock->expects($this->never())
            ->method('getServiceCustomByOrderId')
            ->will($this->returnValue(new \Model_ServiceCustom()));
        $serviceMock->expects($this->never())
            ->method('customCall')
            ->will($this->returnValue(null));

        $arguments = array(
            0 => array(),
        );

        $this->api->setService($serviceMock);
        $this->expectException(\Exception::class);
        $this->api->__call('delete', $arguments);
    }
}
 