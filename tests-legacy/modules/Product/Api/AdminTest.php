<?php

declare(strict_types=1);

namespace Box\Mod\Product\Api;

#[PHPUnit\Framework\Attributes\Group('Core')]
final class AdminTest extends \BBTestCase
{
    protected ?Admin $api;

    public function setUp(): void
    {
        $this->api = new Admin();
    }

    public function testGetDi(): void
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testGetList(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getProductSearchQuery')
            ->willReturn(['sqlString', []]);

        $pagerMock = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn(['list' => []]);

        $di = new \Pimple\Container();
        $di['pager'] = $pagerMock;

        $this->api->setService($serviceMock);
        $this->api->setDi($di);
        $result = $this->api->get_list([]);
        $this->assertIsArray($result);
    }

    public function testGetPairs(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getPairs')
            ->willReturn([]);

        $this->api->setService($serviceMock);
        $result = $this->api->get_pairs([]);
        $this->assertIsArray($result);
    }

    public function testGet(): void
    {
        $data = ['id' => 1];

        $model = new \Model_Product();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
        ->method('toApiArray')
        ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->get($data);
        $this->assertIsArray($result);
    }

    public function testGetTypes(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTypes')
            ->willReturn([]);

        $this->api->setService($serviceMock);
        $result = $this->api->get_types();
        $this->assertIsArray($result);
    }

    public function testPrepareDomainProductAlreadyCreated(): void
    {
        $data = [
            'title' => 'testTitle',
            'type' => 'domain',
        ];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getMainDomainProduct')
            ->willReturn(new \Model_ProductDomain());

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(413);
        $this->expectExceptionMessage('You have already created domain product');
        $this->api->prepare($data);
    }

    public function testPrepareTypeIsNotRecognized(): void
    {
        $data = [
            'title' => 'testTitle',
            'type' => 'customForTestException',
        ];

        $typeArray = [
            'license' => 'License',
            'domain' => 'Domain',
        ];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTypes')
            ->willReturn($typeArray);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(413);
        $this->expectExceptionMessage("Product type {$data['type']} is not registered.");
        $this->api->prepare($data);
    }

    public function testPrepare(): void
    {
        $data = [
            'title' => 'testTitle',
            'type' => 'license',
        ];

        $typeArray = [
            'license' => 'License',
            'domain' => 'Domain',
        ];

        $newProductId = 1;

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTypes')
            ->willReturn($typeArray);

        $serviceMock->expects($this->atLeastOnce())
            ->method('createProduct')
            ->willReturn($newProductId);
        $di = new \Pimple\Container();

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->prepare($data);
        $this->assertIsInt($result);
        $this->assertEquals($newProductId, $result);
    }

    public function testUpdate(): void
    {
        $data = ['id' => 1];
        $model = new \Model_Product();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateProduct')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testUpdatePriorityMissingPriorityParam(): void
    {
        $data = [];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('priority params is missing');
        $this->api->update_priority($data);
    }

    public function testUpdatePriority(): void
    {
        $data = [
            'priority' => [],
        ];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updatePriority')
            ->willReturn(true);

        $this->api->setService($serviceMock);

        $result = $this->api->update_priority($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testUpdateConfig(): void
    {
        $data = ['id' => 1];
        $model = new \Model_Product();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateConfig')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->update_config($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testAddonGetPairs(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAddons')
            ->willReturn([]);

        $this->api->setService($serviceMock);
        $result = $this->api->addon_get_pairs([]);
        $this->assertIsArray($result);
    }

    public function testAddonCreate(): void
    {
        $data = ['title' => 'Title4test'];
        $newAddonId = 1;
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('createAddon')
            ->willReturn($newAddonId);

        $di = new \Pimple\Container();

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->addon_create($data);
        $this->assertIsInt($result);
        $this->assertEquals($newAddonId, $result);
    }

    public function testAddonGet(): void
    {
        $data = ['id' => 1];

        $model = new \Model_Product();
        $model->loadBean(new \DummyBean());
        $model->is_addon = true;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->addon_get($data);
        $this->assertIsArray($result);
    }

    public function testAddonUpdate(): void
    {
        $data = ['id' => 1];

        $apiMock = $this->getMockBuilder('\\' . Admin::class)
            ->onlyMethods(['update'])
            ->getMock();

        $apiMock->expects($this->atLeastOnce())
            ->method('update')
            ->willReturn([]);

        $model = new \Model_Product();
        $model->loadBean(new \DummyBean());
        $model->is_addon = true;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $apiMock->setDi($di);

        $result = $apiMock->addon_update($data);
        $this->assertIsArray($result);
    }

    public function testAddonDelete(): void
    {
        $apiMock = $this->getMockBuilder('\\' . Admin::class)
            ->onlyMethods(['delete'])
            ->getMock();

        $apiMock->expects($this->atLeastOnce())
            ->method('delete')
            ->willReturn(true);

        $result = $apiMock->addon_delete([]);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testDelete(): void
    {
        $data = ['id' => 1];

        $model = new \Model_Product();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('deleteProduct')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testCategoryGetPairs(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getProductCategoryPairs')
            ->willReturn([]);

        $this->api->setService($serviceMock);
        $result = $this->api->category_get_pairs([]);
        $this->assertIsArray($result);
    }

    public function testCategoryUpdate(): void
    {
        $data = ['id' => 1];

        $model = new \Model_ProductCategory();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateCategory')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->category_update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testCategoryGet(): void
    {
        $data = ['id' => 1];

        $model = new \Model_ProductCategory();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('toProductCategoryApiArray')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->category_get($data);
        $this->assertIsArray($result);
    }

    public function testCategoryCreate(): void
    {
        $data = ['title' => 'test Title'];
        $newCategoryId = 1;

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('createCategory')
            ->willReturn($newCategoryId);

        $di = new \Pimple\Container();

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->category_create($data);
        $this->assertIsInt($result);
        $this->assertEquals($newCategoryId, $result);
    }

    public function testCategoryDelete(): void
    {
        $data = ['id' => 1];

        $model = new \Model_ProductCategory();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('removeProductCategory')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->category_delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testPromoGetList(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getPromoSearchQuery')
            ->willReturn(['sqlString', []]);

        $pagerMock = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn(['list' => []]);

        $di = new \Pimple\Container();
        $di['pager'] = $pagerMock;

        $this->api->setService($serviceMock);
        $this->api->setDi($di);
        $result = $this->api->promo_get_list([]);
        $this->assertIsArray($result);
    }

    public function testPromoCreate(): void
    {
        $data = [
            'code' => 'test',
            'type' => 'addon',
            'value' => '10',
            'products' => [],
            'periods' => [],
        ];
        $newPromoId = 1;

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('createPromo')
            ->willReturn($newPromoId);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->promo_create($data);
        $this->assertIsInt($result);
        $this->assertEquals($newPromoId, $result);
    }

    public function promo_getMissingId(): void
    {
        $data = [];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Promo id is missing');
        $this->api->promo_get($data);
    }

    public function testPromoGet(): void
    {
        $data = ['id' => 1];

        $model = new \Model_Promo();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('toPromoApiArray')
            ->willReturn([]);

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->promo_get($data);
        $this->assertIsArray($result);
    }

    public function testPromoUpdate(): void
    {
        $data = ['id' => 1];

        $model = new \Model_Promo();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updatePromo')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->promo_update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testPromoDelete(): void
    {
        $data = ['id' => 1];
        $model = new \Model_Promo();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('deletePromo')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->promo_delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }
}
