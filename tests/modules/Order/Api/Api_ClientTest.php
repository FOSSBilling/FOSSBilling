<?php

/**
 * Created by PhpStorm.
 * User: giedrius
 * Date: 8/5/14
 * Time: 4:52 PM
 */
class Api_ClientTest extends PHPUnit\Framework\TestCase
{
    /**
     * @var \Box\Mod\Order\Api\Client
     */
    protected $api = null;

    public function setup(): void
    {
        $this->api = new \Box\Mod\Order\Api\Client();
    }

    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testGet_list()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('getSearchQuery', 'toApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSearchQuery')
            ->will($this->returnValue(array('query', array())));
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue(array()));

        $resultSet = array(
            'list' => array(
                0 => array('id' => 1),
            ),
        );
        $paginatorMock = $this->getMockBuilder('\Box_Pagination')->disableOriginalConstructor()->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getAdvancedResultSet')
            ->will($this->returnValue($resultSet));

        $clientOrderMock = new \Model_ClientOrder();
        $clientOrderMock->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->with('ClientOrder')
            ->will($this->returnValue($clientOrderMock));

        $di          = new \Box_Di();
        $di['pager'] = $paginatorMock;
        $di['db'] = $dbMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->api->setDi($di);

        $client = new Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());
        $client->id = rand(1, 100);

        $this->api->setIdentity($client);
        $this->api->setService($serviceMock);

        $result = $this->api->get_list(array());

        $this->assertIsArray($result);
    }

    public function testGet_listExpiring()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('getSoonExpiringActiveOrdersQuery'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSoonExpiringActiveOrdersQuery')
            ->will($this->returnValue(array('query', array())));

        $paginatorMock = $this->getMockBuilder('\Box_Pagination')->disableOriginalConstructor()->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getAdvancedResultSet')
            ->will($this->returnValue(array('list' => array())));

        $di          = new \Box_Di();
        $di['pager'] = $paginatorMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->api->setDi($di);

        $client = new Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());
        $client->id = rand(1, 100);

        $this->api->setIdentity($client);
        $this->api->setService($serviceMock);

        $data   = array(
            'expiring' => true
        );
        $result = $this->api->get_list($data);

        $this->assertIsArray($result);
    }

    public function testGet()
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $apiMock = $this->getMockBuilder('\\Box\Mod\Order\Api\Client')->setMethods(array('_getOrder'))->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->will($this->returnValue($order));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('toApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue(array()));

        $apiMock->setService($serviceMock);

        $data   = array(
            'id' => rand(1, 100)
        );
        $result = $apiMock->get($data);

        $this->assertIsArray($result);
    }

    public function testAddons()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('getOrderAddonsList', 'toApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderAddonsList')
            ->will($this->returnValue(array(new Model_ClientOrder())));
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue(array()));

        $order = new Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $apiMock = $this->getMockBuilder('\\Box\Mod\Order\Api\Client')->setMethods(array('_getOrder'))->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->will($this->returnValue($order));

        $apiMock->setService($serviceMock);

        $data   = array(
            'status' => Model_ClientOrder::STATUS_ACTIVE
        );
        $result = $apiMock->addons($data);

        $this->assertIsArray($result);
        $this->assertIsArray($result[0]);
    }

    public function testService()
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $apiMock = $this->getMockBuilder('\\Box\Mod\Order\Api\Client')->setMethods(array('_getOrder'))->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->will($this->returnValue($order));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('getOrderServiceData'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->will($this->returnValue(array()));

        $client = new Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());

        $apiMock->setService($serviceMock);
        $apiMock->setIdentity($client);

        $data   = array(
            'id' => rand(1, 100)
        );
        $result = $apiMock->service($data);

        $this->assertIsArray($result);
    }

    public function testUpgradables()
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $apiMock = $this->getMockBuilder('\\Box\Mod\Order\Api\Client')->setMethods(array('_getOrder'))->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->will($this->returnValue($order));

        $productServiceMock = $this->getMockBuilder('\Box\Mod\Product\Service')->setMethods(array('getUpgradablePairs'))->getMock();
        $productServiceMock->expects($this->atLeastOnce())
            ->method("getUpgradablePairs")
            ->will($this->returnValue(array()));

        $product = new Model_Product();
        $product->loadBean(new RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($product));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($productServiceMock) {
            return $productServiceMock;
        });
        $apiMock->setDi($di);
        $data = array();

        $result = $apiMock->upgradables($data);
        $this->assertIsArray($result);
    }

    public function testDelete()
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $order->status = Model_ClientOrder::STATUS_PENDING_SETUP;

        $apiMock = $this->getMockBuilder('\\Box\Mod\Order\Api\Client')->setMethods(array('_getOrder'))->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->will($this->returnValue($order));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('deleteFromOrder'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('deleteFromOrder')
            ->will($this->returnValue(true));

        $apiMock->setService($serviceMock);

        $data   = array(
            'id' => rand(1, 100)
        );
        $result = $apiMock->delete($data);

        $this->assertTrue($result);
    }

    public function testDeleteNotPendingException()
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $apiMock = $this->getMockBuilder('\\Box\Mod\Order\Api\Client')->setMethods(array('_getOrder'))->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->will($this->returnValue($order));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('deleteFromOrder'))->getMock();
        $serviceMock->expects($this->never())->method('deleteFromOrder')
            ->will($this->returnValue(true));

        $apiMock->setService($serviceMock);

        $data   = array(
            'id' => rand(1, 100)
        );        
        
        $this->expectException(\Box_Exception::class);
        $result = $apiMock->delete($data);

        $this->assertTrue($result);
    }

    public function testGetOrder()
    {
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $order = new Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('findForClientById', 'toApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('findForClientById')
            ->will($this->returnValue($order));
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue(array()));

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $client = new Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());

        $di              = new Box_Di();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);
        $this->api->setIdentity($client);

        $data = array(
            'id' => rand(1, 100)
        );
        $this->api->get($data);
    }

    public function testGetOrderNotFoundException()
    {
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('findForClientById', 'toApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('findForClientById')
            ->will($this->returnValue(null));
        $serviceMock->expects($this->never())->method('toApiArray')
            ->will($this->returnValue(array()));

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $client = new Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());

        $di              = new Box_Di();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);
        $this->api->setIdentity($client);

        $data = array(
            'id' => rand(1, 100)
        );

        $this->expectException(\Box_Exception::class);
        $this->api->get($data);
    }
}
 