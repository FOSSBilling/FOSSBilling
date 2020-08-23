<?php
namespace Box\Tests\Mod\Servicedomain\Api;

class Api_ClientTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Servicedomain\Api\Client
     */
    protected $clientApi = null;

    public function setup(): void
    {
        $this->clientApi = new \Box\Mod\Servicedomain\Api\Client();
    }

    public function testUpdate_nameservers()
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $clientApiMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Api\Client')
            ->setMethods(array('_getService'))->getMock();
        $clientApiMock->expects($this->atLeastOnce())->method('_getService')
            ->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('updateNameservers'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('updateNameservers')
            ->will($this->returnValue(true));

        $clientApiMock->setService($serviceMock);

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
        method('fire');

        $di = new \Box_Di();
        $di['events_manager'] = $eventMock;
        $clientApiMock->setDi($di);

        $data   = array();
        $result = $clientApiMock->update_nameservers($data);

        $this->assertTrue($result);
    }

    public function testUpdate_contacts()
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $clientApiMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Api\Client')
            ->setMethods(array('_getService'))->getMock();
        $clientApiMock->expects($this->atLeastOnce())->method('_getService')
            ->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('updateContacts'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('updateContacts')
            ->will($this->returnValue(true));

        $clientApiMock->setService($serviceMock);

        $data   = array();
        $result = $clientApiMock->update_contacts($data);

        $this->assertTrue($result);
    }

    public function testEnable_privacy_protection()
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $clientApiMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Api\Client')
            ->setMethods(array('_getService'))->getMock();
        $clientApiMock->expects($this->atLeastOnce())->method('_getService')
            ->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('enablePrivacyProtection'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('enablePrivacyProtection')
            ->will($this->returnValue(true));

        $clientApiMock->setService($serviceMock);

        $data   = array();
        $result = $clientApiMock->enable_privacy_protection($data);

        $this->assertTrue($result);
    }

    public function testDisable_privacy_protection()
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $clientApiMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Api\Client')
            ->setMethods(array('_getService'))->getMock();
        $clientApiMock->expects($this->atLeastOnce())->method('_getService')
            ->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('disablePrivacyProtection'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('disablePrivacyProtection')
            ->will($this->returnValue(true));

        $clientApiMock->setService($serviceMock);

        $data   = array();
        $result = $clientApiMock->disable_privacy_protection($data);

        $this->assertTrue($result);
    }

    public function testGet_transfer_code()
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $clientApiMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Api\Client')
            ->setMethods(array('_getService'))->getMock();
        $clientApiMock->expects($this->atLeastOnce())->method('_getService')
            ->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('getTransferCode'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getTransferCode')
            ->will($this->returnValue(true));

        $clientApiMock->setService($serviceMock);

        $data   = array();
        $result = $clientApiMock->get_transfer_code($data);

        $this->assertTrue($result);
    }

    public function testLock()
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $clientApiMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Api\Client')
            ->setMethods(array('_getService'))->getMock();
        $clientApiMock->expects($this->atLeastOnce())->method('_getService')
            ->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('lock'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('lock')
            ->will($this->returnValue(true));

        $clientApiMock->setService($serviceMock);

        $data   = array();
        $result = $clientApiMock->lock($data);

        $this->assertTrue($result);
    }

    public function testUnlock()
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $clientApiMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Api\Client')
            ->setMethods(array('_getService'))->getMock();
        $clientApiMock->expects($this->atLeastOnce())->method('_getService')
            ->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('unlock'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('unlock')
            ->will($this->returnValue(true));

        $clientApiMock->setService($serviceMock);

        $data   = array();
        $result = $clientApiMock->unlock($data);

        $this->assertTrue($result);
    }


    public function testGetService()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('lock')
            ->will($this->returnValue(true));

        $this->clientApi->setService($serviceMock);

        $orderService = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderService', 'findForClientById'))->getMock();
        $orderService->expects($this->atLeastOnce())
            ->method('findForClientById')
            ->will($this->returnValue(new \Model_ClientOrder()));
        $orderService->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue(new \Model_ServiceDomain()));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($orderService) {
            return $orderService;
        });
        $this->clientApi->setDi($di);

        $this->clientApi->setIdentity(new \Model_Client());

        $data   = array(
            'order_id' => rand(1, 100)
        );
        $result = $this->clientApi->lock($data);

        $this->assertTrue($result);
    }

    public function testGetServiceOrderIdMissingException()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->never())->method('lock')
            ->will($this->returnValue(true));

        $this->clientApi->setService($serviceMock);

        $orderService = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderService', 'findForClientById'))->getMock();
        $orderService->expects($this->never())
            ->method('findForClientById')
            ->will($this->returnValue(new \Model_ClientOrder()));
        $orderService->expects($this->never())
            ->method('getOrderService')
            ->will($this->returnValue(new \Model_ServiceDomain()));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($orderService) {
            return $orderService;
        });
        $this->clientApi->setDi($di);

        $this->clientApi->setIdentity(new \Model_Client());

        $data   = array();
        
        $this->expectException(\Box_Exception::class);
        $result = $this->clientApi->lock($data);

        $this->assertTrue($result);
    }

    public function testGetServiceOrderNotFoundException()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->never())->method('lock')
            ->will($this->returnValue(true));

        $this->clientApi->setService($serviceMock);

        $orderService = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderService', 'findForClientById'))->getMock();
        $orderService->expects($this->atLeastOnce())
            ->method('findForClientById')
            ->will($this->returnValue(null));
        $orderService->expects($this->never())
            ->method('getOrderService')
            ->will($this->returnValue(new \Model_ServiceDomain()));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($orderService) {
            return $orderService;
        });
        $this->clientApi->setDi($di);

        $this->clientApi->setIdentity(new \Model_Client());

        $data   = array(
            'order_id' => rand(1, 100)
        );
        
        $this->expectException(\Box_Exception::class);
        $result = $this->clientApi->lock($data);

        $this->assertTrue($result);
    }

    public function testGetServiceOrderNotActivatedException()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->never())->method('lock')
            ->will($this->returnValue(true));

        $this->clientApi->setService($serviceMock);

        $orderService = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderService', 'findForClientById'))->getMock();
        $orderService->expects($this->atLeastOnce())
            ->method('findForClientById')
            ->will($this->returnValue(new \Model_ClientOrder()));
        $orderService->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue(null));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($orderService) {
            return $orderService;
        });
        $this->clientApi->setDi($di);

        $this->clientApi->setIdentity(new \Model_Client());

        $data   = array(
            'order_id' => rand(1, 100)
        );
        
        $this->expectException(\Box_Exception::class);
        $result = $this->clientApi->lock($data);

        $this->assertTrue($result);
    }

}
 