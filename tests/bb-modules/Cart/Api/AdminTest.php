<?php

namespace Box\Tests\Mod\Cart\Api;

class AdminTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Cart\Api\Admin
     */
    protected $adminApi = null;

    public function setup(): void
    {
        $this->adminApi = new \Box\Mod\Cart\Api\Admin();
    }

    public function testGet_list()
    {
        $simpleResultArr = array(
            'list' => array(
                array('id' => 1),
            ),
        );

        $paginatorMock = $this->getMockBuilder('\Box_Pagination')->disableOriginalConstructor()->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getSimpleResultSet')
            ->will($this->returnValue($simpleResultArr));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('getSearchQuery', 'toApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSearchQuery')
            ->will($this->returnValue(array('query', array())));
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->will($this->returnValue(array()));

        $model = new \Model_Cart();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di          = new \Box_Di();
        $di['pager'] = $paginatorMock;
        $di['db'] = $dbMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array();
        $result = $this->adminApi->get_list($data);

        $this->assertIsArray($result);
    }

    public function testGet()
    {
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_Cart()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('toApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue(array()));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db']        = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array(
            'id' => rand(1, 100)
        );
        $result = $this->adminApi->get($data);

        $this->assertIsArray($result);
    }


    public function testBatch_expire()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->will($this->returnValue(array(rand(1, 100), date('Y-m-d H:i:s'))));
        $dbMock->expects($this->atLeastOnce())
            ->method('exec')
            ->will($this->returnValue(null));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->adminApi->setDi($di);

        $data   = array(
            'id' => rand(1, 100)
        );
        $result = $this->adminApi->batch_expire($data);

        $this->assertTrue($result);
    }

}
 