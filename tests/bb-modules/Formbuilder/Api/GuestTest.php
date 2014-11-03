<?php


namespace Box\Mod\Formbuilder\Api;


class GuestTest extends \PHPUnit_Framework_TestCase {

    public function setup()
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

        $this->api->setService($serviceMock);
        $result = $this->api->get($data);
        $this->assertInternalType('array', $result);
    }

    public function testgetMissingFormId()
    {
        $data = array();

        $this->setExpectedException('\Box_Exception', 'Form id was not passed');
        $this->api->get($data);
    }
}
 