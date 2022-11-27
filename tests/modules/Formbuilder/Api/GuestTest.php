<?php


namespace Box\Mod\Formbuilder\Api;


class GuestTest extends \BBTestCase {

    public function setup(): void
    {
        $this->api = new \Box\Mod\Formbuilder\Api\Guest();
    }


    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testget()
    {
        $data['id'] = 1;

        $serviceMock = $this->getMockBuilder('\Box\Mod\Formbuilder\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getForm')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);
        $result = $this->api->get($data);
        $this->assertIsArray($result);
    }

}
 