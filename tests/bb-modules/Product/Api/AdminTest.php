<?php


namespace Box\Mod\Product\Api;


class AdminTest extends \BBTestCase {

    /**
     * @var \Box\Mod\Product\Api\Admin
     */
    protected $api = null;

    public function setup(): void
    {
        $this->api= new \Box\Mod\Product\Api\Admin();
    }


    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testget_list()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Product\Service')->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getProductSearchQuery')
            ->will($this->returnValue(array('sqlString', array())));


        $pagerMock = $this->getMockBuilder('\Box_Pagination')->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getSimpleResultSet')
            ->will($this->returnValue(array('list' => array())));

        $di = new \Box_Di();
        $di['pager'] = $pagerMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $this->api->setService($serviceMock);
        $this->api->setDi($di);
        $result = $this->api->get_list(array());
        $this->assertIsArray($result);
    }

    public function testget_pairs()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Product\Service')->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getPairs')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);
        $result = $this->api->get_pairs(array());
        $this->assertIsArray($result);
    }

    public function testget()
    {
        $data = array('id' => 1);

        $model = new \Model_Product();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Product\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
        ->method('toApiArray')
        ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
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

    public function testget_types()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Product\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTypes')
            ->will($this->returnValue(array()));


        $this->api->setService($serviceMock);
        $result = $this->api->get_types();
        $this->assertIsArray($result);
    }

    public function testprepareDomainProductAlreadyCreated()
    {
        $data = array(
            'title' => 'testTitle',
            'type' => 'domain',
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Product\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getMainDomainProduct')
            ->will($this->returnValue(new \Model_ProductDomain));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(413);
        $this->expectExceptionMessage('You have already created domain product');
        $this->api->prepare($data);
    }

    public function testprepare_TypeIsNotRecognized()
    {
        $data = array(
            'title' => 'testTitle',
            'type' => 'customForTestException',
        );

        $typeArray = array(
            'license' => 'License',
            'domain' => 'Domain'
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Product\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTypes')
            ->will($this->returnValue($typeArray));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(413);
        $this->expectExceptionMessage(sprintf('Product type %s is not registered', $data['type']));
        $this->api->prepare($data);
    }

    public function testprepare()
    {
        $data = array(
            'title' => 'testTitle',
            'type' => 'license',
        );

        $typeArray = array(
            'license' => 'License',
            'domain' => 'Domain'
        );

        $newProductId = 1;

        $serviceMock = $this->getMockBuilder('\Box\Mod\Product\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTypes')
            ->will($this->returnValue($typeArray));

        $serviceMock->expects($this->atLeastOnce())
            ->method('createProduct')
            ->will($this->returnValue($newProductId));
        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->prepare($data);
        $this->assertIsInt($result);
        $this->assertEquals($newProductId, $result);
    }

    public function testupdate()
    {
        $data = array('id' => 1);
        $model = new \Model_Product();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Product\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateProduct')
            ->will($this->returnValue(true));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testupdate_priorityMissingPriorityParam()
    {
        $data = array();

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('priority params is missing');
        $this->api->update_priority($data);
    }

    public function testupdate_priority()
    {
        $data = array(
            'priority' => array(),
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Product\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updatePriority')
            ->will($this->returnValue(true));

        $this->api->setService($serviceMock);

        $result = $this->api->update_priority($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testupdate_config()
    {
        $data = array('id' => 1);
        $model = new \Model_Product();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Product\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateConfig')
            ->will($this->returnValue(true));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->update_config($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testaddon_get_pairs()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Product\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAddons')
            ->will($this->returnValue(array()));


        $this->api->setService($serviceMock);
        $result = $this->api->addon_get_pairs(array());
        $this->assertIsArray($result);
    }

    public function testaddon_create()
    {
        $data = array('title' => 'Title4test');
        $newAddonId = 1;
        $serviceMock = $this->getMockBuilder('\Box\Mod\Product\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('createAddon')
            ->will($this->returnValue($newAddonId));

        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->addon_create($data);
        $this->assertIsInt($result);
        $this->assertEquals($newAddonId, $result);
    }

    public function testaddon_get()
    {
        $data = array('id' => 1);

        $model = new \Model_Product();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->is_addon = true;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Product\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->addon_get($data);
        $this->assertIsArray($result);
    }

    public function testaddon_update()
    {
        $data = array('id' => 1);

        $apiMock = $this->getMockBuilder('\Box\Mod\Product\Api\Admin')
            ->setMethods(array('update'))
            ->getMock();

        $apiMock->expects($this->atLeastOnce())
            ->method('update')
            ->will($this->returnValue(array()));

        $model = new \Model_Product();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->is_addon = true;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $apiMock->setDi($di);

        $result = $apiMock->addon_update($data);
        $this->assertIsArray($result);
    }

    public function testaddon_delete()
    {
        $apiMock = $this->getMockBuilder('\Box\Mod\Product\Api\Admin')
            ->setMethods(array('delete'))
            ->getMock();

        $apiMock->expects($this->atLeastOnce())
            ->method('delete')
            ->will($this->returnValue(true));

        $result = $apiMock->addon_delete(array());
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testdelete()
    {
        $data = array('id' => 1);

        $model = new \Model_Product();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Product\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('deleteProduct')
            ->will($this->returnValue(true));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testcategory_get_pairs()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Product\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getProductCategoryPairs')
            ->will($this->returnValue(array()));


        $this->api->setService($serviceMock);
        $result = $this->api->category_get_pairs(array());
        $this->assertIsArray($result);
    }

    public function testcategory_update()
    {
        $data = array('id' => 1);

        $model = new \Model_ProductCategory();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Product\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateCategory')
            ->will($this->returnValue(true));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->category_update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testcategory_get()
    {
        $data = array('id' => 1);

        $model = new \Model_ProductCategory();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Product\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('toProductCategoryApiArray')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->category_get($data);
        $this->assertIsArray($result);
    }

    public function testcategory_create()
    {
        $data = array('title' => 'test Title');
        $newCategoryId = 1;

        $serviceMock = $this->getMockBuilder('\Box\Mod\Product\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('createCategory')
            ->will($this->returnValue($newCategoryId));

        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->category_create($data);
        $this->assertIsInt($result);
        $this->assertEquals($newCategoryId, $result);
    }

    public function testcategory_delete()
    {
        $data = array('id' => 1);

        $model = new \Model_ProductCategory();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Product\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('removeProductCategory')
            ->will($this->returnValue(true));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->category_delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testpromo_get_list()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Product\Service')->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getPromoSearchQuery')
            ->will($this->returnValue(array('sqlString', array())));


        $pagerMock = $this->getMockBuilder('\Box_Pagination')->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getSimpleResultSet')
            ->will($this->returnValue(array('list' => array())));

        $di = new \Box_Di();
        $di['pager'] = $pagerMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $this->api->setService($serviceMock);
        $this->api->setDi($di);
        $result = $this->api->promo_get_list(array());
        $this->assertIsArray($result);
    }

    public function testpromo_create()
    {
        $data = array(
            'code' => 'test',
            'type' => 'addon',
            'value' => '10',
            'products' => array(),
            'periods' => array(),
        );
        $newPromoId = 1;

        $serviceMock = $this->getMockBuilder('\Box\Mod\Product\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('createPromo')
            ->will($this->returnValue($newPromoId));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->promo_create($data);
        $this->assertIsInt($result);
        $this->assertEquals($newPromoId, $result);
    }

    public function promo_getMissingId()
    {
        $data = array();

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Promo id is missing');
        $this->api->promo_get($data);
    }

    public function testpromo_get()
    {
        $data = array('id' => 1);

        $model = new \Model_Promo();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $serviceMock = $this->getMockBuilder('\Box\Mod\Product\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('toPromoApiArray')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->promo_get($data);
        $this->assertIsArray($result);
    }

    public function testpromo_update()
    {
        $data = array('id' => 1);

        $model = new \Model_Promo();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Product\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updatePromo')
            ->will($this->returnValue(true));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->promo_update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testpromo_delete()
    {
        $data = array('id' => 1);
        $model = new \Model_Promo();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Product\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('deletePromo')
            ->will($this->returnValue(true));

        $di = new \Box_Di();
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->promo_delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }


































}
 