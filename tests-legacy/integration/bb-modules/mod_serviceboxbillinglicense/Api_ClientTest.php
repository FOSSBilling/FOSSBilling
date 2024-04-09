<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Api_Client_ServiceBoxBillinglicenseTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'serviceboxbillinglicense.xml';

    public function testserviceboxbillinglicense(): void
    {
        $data = [
            'order_id' => 16,
        ];

        $serviceMock = $this->getMockBuilder('Box\Mod\Serviceboxbillinglicense\Service')->getMock();
        $serviceMock->expects($this->any())
            ->method('reset')
            ->willReturn(true);

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

    public function testGetServiceMissingOrderId(): void
    {
        $data = [];

        $clientApi = new Box\Mod\Serviceboxbillinglicense\Api\Client();
        $clientApi->setDi($this->di);

        $this->expectException(FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order ID is required');

        $bool = $clientApi->reset($data);
    }

    public function testGetServiceOrderNotFound(): void
    {
        $data = [
            'order_id' => 160,
        ];

        $client = new Model_Client();
        $client->loadBean(new RedBeanPHP\OODBBean());
        $client->id = 1;

        $clientApi = new Box\Mod\Serviceboxbillinglicense\Api\Client();
        $clientApi->setDi($this->di);
        $clientApi->setIdentity($client);

        $this->expectException(FOSSBilling\Exception::class);
        $this->expectExceptionMessage('FOSSBilling license order not found');

        $bool = $clientApi->reset($data);
    }

    public function testGetServiceOrderNotActivated(): void
    {
        $data = [
            'order_id' => 17,
        ];

        $client = new Model_Client();
        $client->loadBean(new RedBeanPHP\OODBBean());
        $client->id = 1;

        $clientApi = new Box\Mod\Serviceboxbillinglicense\Api\Client();
        $clientApi->setDi($this->di);
        $clientApi->setIdentity($client);

        $this->expectException(FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order is not activated');

        $bool = $clientApi->reset($data);
    }
}
