<?php

/**
 * @group Core
 */
class Api_Client_ServiceBoxBillinglicenseTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'serviceboxbillinglicense.xml';

    public function testserviceboxbillinglicense()
    {
        $data = array(
            'order_id' => 16,
        );

        $serviceMock = $this->getMockBuilder('Box\Mod\Serviceboxbillinglicense\Service')->getMock();
        $serviceMock->expects($this->any())
            ->method('reset')
            ->will($this->returnValue(true));

        $client = new Model_Client();
        $client->loadBean(new RedBeanPHP\OODBBean());
        $client->id = 1;

        $clientApi = new Box\Mod\Serviceboxbillinglicense\Api\Client();
        $clientApi->setService($serviceMock);
        $clientApi->setDi($this->di);
        $clientApi->setIdentity($client);

        $bool = $clientApi->reset($data);
        $this->assertTrue($bool);
    }

    public function test_getServiceMissingOrderId()
    {
        $data = array();

        $clientApi = new Box\Mod\Serviceboxbillinglicense\Api\Client();
        $clientApi->setDi($this->di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Order id is required');

        $bool = $clientApi->reset($data);
    }


    public function test_getServiceOrderNotFound()
    {
        $data = array(
            'order_id' => 160,
        );

        $client = new Model_Client();
        $client->loadBean(new RedBeanPHP\OODBBean());
        $client->id = 1;

        $clientApi = new Box\Mod\Serviceboxbillinglicense\Api\Client();
        $clientApi->setDi($this->di);
        $clientApi->setIdentity($client);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('BoxBilling license order not found');

        $bool = $clientApi->reset($data);
    }

    public function test_getServiceOrderNotActivated()
    {
        $data = array(
            'order_id' => 17,
        );

        $client = new Model_Client();
        $client->loadBean(new RedBeanPHP\OODBBean());
        $client->id = 1;

        $clientApi = new Box\Mod\Serviceboxbillinglicense\Api\Client();
        $clientApi->setDi($this->di);
        $clientApi->setIdentity($client);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Order is not activated');

        $bool = $clientApi->reset($data);
    }
}