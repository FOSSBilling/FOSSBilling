<?php

/**
 * Created by PhpStorm.
 * User: giedrius
 * Date: 8/5/14
 * Time: 4:52 PM.
 */
namespace Box\Tests\Mod\Order\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class Api_ClientTest extends \BBTestCase
{
    protected ?\Box\Mod\Order\Api\Client $api;

    public function setUp(): void
    {
        $this->api = new \Box\Mod\Order\Api\Client();
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testGetList(): void
    {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->onlyMethods(['getSearchQuery', 'toApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSearchQuery')
            ->willReturn(['query', []]);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn([]);

        $resultSet = [
            'list' => [
                0 => ['id' => 1],
            ],
        ];
        $paginatorMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($resultSet);

        $clientOrderMock = new \Model_ClientOrder();
        $clientOrderMock->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->with('ClientOrder')
            ->willReturn($clientOrderMock);

        $di = $this->getDi();
        $di['pager'] = $paginatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 1;

        $this->api->setIdentity($client);
        $this->api->setService($serviceMock);

        $result = $this->api->get_list([]);

        $this->assertIsArray($result);
    }

    public function testGetListExpiring(): void
    {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->onlyMethods(['getSoonExpiringActiveOrdersQuery'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSoonExpiringActiveOrdersQuery')
            ->willReturn(['query', []]);

        $paginatorMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn(['list' => []]);

        $di = $this->getDi();
        $di['pager'] = $paginatorMock;

        $this->api->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 1;

        $this->api->setIdentity($client);
        $this->api->setService($serviceMock);

        $data = [
            'expiring' => true,
        ];
        $result = $this->api->get_list($data);

        $this->assertIsArray($result);
    }

    public function testGet(): void
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $apiMock = $this->getMockBuilder(\Box\Mod\Order\Api\Client::class)->onlyMethods(['_getOrder'])->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->willReturn($order);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->onlyMethods(['toApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn([]);

        $apiMock->setService($serviceMock);

        $data = [
            'id' => 1,
        ];
        $result = $apiMock->get($data);

        $this->assertIsArray($result);
    }

    public function testAddons(): void
    {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->onlyMethods(['getOrderAddonsList', 'toApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderAddonsList')
            ->willReturn([new \Model_ClientOrder()]);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn([]);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $apiMock = $this->getMockBuilder(\Box\Mod\Order\Api\Client::class)->onlyMethods(['_getOrder'])->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->willReturn($order);

        $apiMock->setService($serviceMock);

        $data = [
            'status' => \Model_ClientOrder::STATUS_ACTIVE,
        ];
        $result = $apiMock->addons($data);

        $this->assertIsArray($result);
        $this->assertIsArray($result[0]);
    }

    public function testService(): void
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $apiMock = $this->getMockBuilder(\Box\Mod\Order\Api\Client::class)->onlyMethods(['_getOrder'])->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->willReturn($order);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->onlyMethods(['getOrderServiceData'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->willReturn([]);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $apiMock->setService($serviceMock);
        $apiMock->setIdentity($client);

        $data = [
            'id' => 1,
        ];
        $result = $apiMock->service($data);

        $this->assertIsArray($result);
    }

    public function testUpgradables(): void
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $apiMock = $this->getMockBuilder(\Box\Mod\Order\Api\Client::class)->onlyMethods(['_getOrder'])->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->willReturn($order);

        $productServiceMock = $this->getMockBuilder(\Box\Mod\Product\Service::class)->onlyMethods(['getUpgradablePairs'])->getMock();
        $productServiceMock->expects($this->atLeastOnce())
            ->method('getUpgradablePairs')
            ->willReturn([]);

        $product = new \Model_Product();
        $product->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($product);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn () => $productServiceMock);
        $apiMock->setDi($di);
        $data = [];

        $result = $apiMock->upgradables($data);
        $this->assertIsArray($result);
    }

    public function testDelete(): void
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->status = \Model_ClientOrder::STATUS_PENDING_SETUP;

        $apiMock = $this->getMockBuilder(\Box\Mod\Order\Api\Client::class)->onlyMethods(['_getOrder'])->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->willReturn($order);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->onlyMethods(['deleteFromOrder'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('deleteFromOrder')
            ->willReturn(true);

        $apiMock->setService($serviceMock);

        $data = [
            'id' => 1,
        ];
        $result = $apiMock->delete($data);

        $this->assertTrue($result);
    }

    public function testDeleteNotPendingException(): void
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $apiMock = $this->getMockBuilder(\Box\Mod\Order\Api\Client::class)->onlyMethods(['_getOrder'])->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->willReturn($order);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->onlyMethods(['deleteFromOrder'])->getMock();
        $serviceMock->expects($this->never())->method('deleteFromOrder')
            ->willReturn(true);

        $apiMock->setService($serviceMock);

        $data = [
            'id' => 1,
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $apiMock->delete($data);

        $this->assertTrue($result);
    }

    public function testGetOrder(): void
    {
        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->any())->method('checkRequiredParamsForArray')
            ;

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->onlyMethods(['findForClientById', 'toApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('findForClientById')
            ->willReturn($order);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn([]);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);
        $this->api->setIdentity($client);

        $data = [
            'id' => 1,
        ];
        $this->api->get($data);
    }

    public function testGetOrderNotFoundException(): void
    {
        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->any())->method('checkRequiredParamsForArray')
            ;

        $serviceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->onlyMethods(['findForClientById', 'toApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('findForClientById')
            ->willReturn(null);
        $serviceMock->expects($this->never())->method('toApiArray')
            ->willReturn([]);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);
        $this->api->setIdentity($client);

        $data = [
            'id' => 1,
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->api->get($data);
    }
}
