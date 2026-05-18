<?php

declare(strict_types=1);

namespace Box\Mod\Product\Api;

use Box\Mod\Product\Entity\Product;
use Box\Mod\Product\Entity\ProductCategory;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class AdminTest extends \BBTestCase
{
    protected ?Admin $api;

    public function setUp(): void
    {
        $this->api = new Admin();
    }

    public function testGetList(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
        $serviceMock->expects($this->once())
            ->method('getPaginatedProducts')
            ->with([], null)
            ->willReturn(['list' => []]);

        $this->api->setService($serviceMock);
        $this->api->setDi($this->getDi());
        $result = $this->api->get_list([]);
        $this->assertIsArray($result);
    }

    public function testGetPairs(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);

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
        $model = new Product();

        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
        $serviceMock->expects($this->once())
        ->method('findProductById')
        ->with(1)
        ->willReturn($model);
        $serviceMock->expects($this->once())
        ->method('toApiArray')
        ->willReturn([]);

        $di = $this->getDi();

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->get($data);
        $this->assertIsArray($result);
    }

    public function testGetTypes(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
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

        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getMainDomainProduct')
            ->willReturn((new Product())->setType('domain'));

        $di = $this->getDi();
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

        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTypes')
            ->willReturn($typeArray);

        $di = $this->getDi();
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

        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTypes')
            ->willReturn($typeArray);

        $serviceMock->expects($this->atLeastOnce())
            ->method('createProduct')
            ->willReturn($newProductId);
        $di = $this->getDi();

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->prepare($data);
        $this->assertIsInt($result);
        $this->assertEquals($newProductId, $result);
    }

    public function testUpdate(): void
    {
        $data = ['id' => 1];
        $model = new Product();

        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
        $serviceMock->expects($this->once())
            ->method('findProductById')
            ->with(1)
            ->willReturn($model);
        $serviceMock->expects($this->once())
            ->method('updateProduct')
            ->willReturn(true);

        $di = $this->getDi();

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

        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
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
        $model = new Product();

        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
        $serviceMock->expects($this->once())
            ->method('findProductById')
            ->with(1)
            ->willReturn($model);
        $serviceMock->expects($this->once())
            ->method('updateConfig')
            ->willReturn(true);

        $di = $this->getDi();

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->update_config($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testAddonGetPairs(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
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
        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('createAddon')
            ->willReturn($newAddonId);

        $di = $this->getDi();

        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->addon_create($data);
        $this->assertIsInt($result);
        $this->assertEquals($newAddonId, $result);
    }

    public function testAddonGet(): void
    {
        $data = ['id' => 1];

        $model = new Product();
        $reflection = new \ReflectionProperty($model, 'isAddon');
        $reflection->setAccessible(true);
        $reflection->setValue($model, true);

        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
        $serviceMock->expects($this->once())
            ->method('findProductById')
            ->with(1)
            ->willReturn($model);
        $serviceMock->expects($this->once())
            ->method('toApiArray')
            ->willReturn([]);

        $di = $this->getDi();

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->addon_get($data);
        $this->assertIsArray($result);
    }

    public function testAddonUpdate(): void
    {
        $data = ['id' => 1];

        $apiMock = $this->getMockBuilder(Admin::class)
            ->onlyMethods(['update'])
            ->getMock();

        $apiMock->expects($this->atLeastOnce())
            ->method('update')
            ->willReturn([]);

        $model = new Product();
        $reflection = new \ReflectionProperty($model, 'isAddon');
        $reflection->setAccessible(true);
        $reflection->setValue($model, true);

        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
        $serviceMock->expects($this->once())
            ->method('findProductById')
            ->with(1)
            ->willReturn($model);

        $di = $this->getDi();
        $di['logger'] = new \Box_Log();

        $apiMock->setService($serviceMock);
        $apiMock->setDi($di);

        $result = $apiMock->addon_update($data);
        $this->assertIsArray($result);
    }

    public function testAddonDelete(): void
    {
        $apiMock = $this->getMockBuilder(Admin::class)
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

        $model = new Product();

        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
        $serviceMock->expects($this->once())
            ->method('findProductById')
            ->with(1)
            ->willReturn($model);
        $serviceMock->expects($this->once())
            ->method('deleteProduct')
            ->willReturn(true);

        $di = $this->getDi();

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testCategoryGetPairs(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
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

        $model = new ProductCategory();

        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
        $serviceMock->expects($this->once())
            ->method('findProductCategoryById')
            ->with(1)
            ->willReturn($model);
        $serviceMock->expects($this->once())
            ->method('updateCategory')
            ->willReturn(true);

        $this->api->setService($serviceMock);
        $this->api->setDi($this->getDi());

        $result = $this->api->category_update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testCategoryGet(): void
    {
        $data = ['id' => 1];

        $model = new ProductCategory();

        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
        $serviceMock->expects($this->once())
            ->method('findProductCategoryById')
            ->with(1)
            ->willReturn($model);
        $serviceMock->expects($this->once())
            ->method('toProductCategoryApiArray')
            ->willReturn([]);

        $this->api->setService($serviceMock);
        $this->api->setDi($this->getDi());

        $result = $this->api->category_get($data);
        $this->assertIsArray($result);
    }

    public function testCategoryCreate(): void
    {
        $data = ['title' => 'test Title'];
        $newCategoryId = 1;

        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('createCategory')
            ->willReturn($newCategoryId);

        $di = $this->getDi();

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->category_create($data);
        $this->assertIsInt($result);
        $this->assertEquals($newCategoryId, $result);
    }

    public function testCategoryDelete(): void
    {
        $data = ['id' => 1];

        $model = new ProductCategory();

        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
        $serviceMock->expects($this->once())
            ->method('findProductCategoryById')
            ->with(1)
            ->willReturn($model);
        $serviceMock->expects($this->once())
            ->method('removeProductCategory')
            ->willReturn(true);

        $this->api->setService($serviceMock);
        $this->api->setDi($this->getDi());

        $result = $this->api->category_delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testPromoGetList(): void
    {
        $qbMock = $this->getMockBuilder(\Doctrine\ORM\QueryBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $promo = new \Box\Mod\Product\Entity\Promo();
        $reflection = new \ReflectionProperty($promo, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($promo, 1);

        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);

        $serviceMock->expects($this->atLeastOnce())
            ->method('getPromoSearchQueryBuilder')
            ->willReturn($qbMock);
        $serviceMock->expects($this->once())
            ->method('toPromoApiArray')
            ->with($promo)
            ->willReturn(['id' => 1]);

        $pagerMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['paginateDoctrineQuery'])
        ->disableOriginalConstructor()
        ->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('paginateDoctrineQuery')
            ->willReturn(['list' => [$promo]]);

        $di = $this->getDi();
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

        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('createPromo')
            ->willReturn($newPromoId);

        $di = $this->getDi();
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
        $this->expectExceptionMessage('Promo ID is missing');
        $this->api->promo_get($data);
    }

    public function testPromoGet(): void
    {
        $data = ['id' => 1];

        $promo = new \Box\Mod\Product\Entity\Promo();
        $di = $this->getDi();

        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('findPromoById')
            ->with(1)
            ->willReturn($promo);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toPromoApiArray')
            ->willReturn([]);

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->promo_get($data);
        $this->assertIsArray($result);
    }

    public function testPromoRedemptionGetList(): void
    {
        $qbMock = $this->getMockBuilder(\Doctrine\ORM\QueryBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $repoMock = $this->getMockBuilder(\Box\Mod\Product\Repository\PromoRedemptionRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repoMock->expects($this->atLeastOnce())
            ->method('getSearchQueryBuilder')
            ->willReturn($qbMock);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Product\Service::class)
            ->onlyMethods(['getPromoRedemptionRepository', 'enrichPromoRedemptionApiArray'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getPromoRedemptionRepository')
            ->willReturn($repoMock);
        $serviceMock->expects($this->atLeastOnce())
            ->method('enrichPromoRedemptionApiArray')
            ->willReturn([]);

        $pagerMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
            ->onlyMethods(['paginateDoctrineQuery'])
            ->disableOriginalConstructor()
            ->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('paginateDoctrineQuery')
            ->willReturn(['list' => [[]]]);

        $di = $this->getDi();
        $di['pager'] = $pagerMock;

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->promo_redemption_get_list(['promo_id' => 1]);
        $this->assertIsArray($result);
    }

    public function testPromoUpdate(): void
    {
        $data = ['id' => 1];

        $model = new \Box\Mod\Product\Entity\Promo();

        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('findPromoById')
            ->with(1)
            ->willReturn($model);
        $serviceMock->expects($this->atLeastOnce())
            ->method('updatePromo')
            ->with($model, $data)
            ->willReturn(true);

        $di = $this->getDi();

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->promo_update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testPromoDelete(): void
    {
        $data = ['id' => 1];
        $model = new \Box\Mod\Product\Entity\Promo();

        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('findPromoById')
            ->with(1)
            ->willReturn($model);
        $serviceMock->expects($this->atLeastOnce())
            ->method('deletePromo')
            ->with($model)
            ->willReturn(true);

        $di = $this->getDi();

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->promo_delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }
}
