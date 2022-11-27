<?php
namespace Box\Mod\Servicehosting\Api;

class GuestTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Servicehosting\Api\Guest
     */
    protected $api = null;

    public function setup(): void
    {
        $this->api = new \Box\Mod\Servicehosting\Api\Guest();
    }

    public function testfree_tlds()
    {
        $di = new \Box_Di();
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di['validator'] = $validatorMock;

        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $model = new \Model_Product();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->type = \Model_Product::HOSTING;
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di['db'] = $dbMock;

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicehosting\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getFreeTlds')
            ->with($model)
            ->willReturn(array());
        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->free_tlds(array('product_id' => 1));
        $this->assertIsArray($result);
    }

    public function testfree_tlds_ProductTypeIsNotHosting()
    {
        $di = new \Box_Di();
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di['validator'] = $validatorMock;

        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $model = new \Model_Product();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di['db'] = $dbMock;

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicehosting\Service')->getMock();
        $serviceMock->expects($this->never())->method('getFreeTlds');
        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Product type is invalid');
        $this->api->free_tlds(array('product_id' => 1));
    }
}