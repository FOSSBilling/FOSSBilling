<?php


namespace Box\Mod\Client\Api;


class ClientTest extends \BBTestCase {

    public function testgetDi()
    {
        $di = new \Box_Di();
        $client = new \Box\Mod\Client\Api\Client();
        $client->setDi($di);
        $getDi = $client->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testbalance_get_list()
    {
        $data = array();

        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Client\ServiceBalance')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getSearchQuery')
            ->will($this->returnValue(array('sql', array())));

        $simpleResultArr = array(
            'list' => array(
                array('id' => 1),
            ),
        );

        $pagerMock = $this->getMockBuilder('\Box_Pagination')->disableOriginalConstructor()->getMock();
        $pagerMock ->expects($this->atLeastOnce())
            ->method('getSimpleResultSet')
            ->will($this->returnValue($simpleResultArr));

        $model = new \Model_ClientBalance();
        $model->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($name) use($serviceMock) {return $serviceMock;});
        $di['pager'] = $pagerMock;
        $di['db'] = $dbMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $client = new \Box\Mod\Client\Api\Client();
        $client->setDi($di);
        $client->setService($serviceMock);
        $client->setIdentity($model);

        $result = $client->balance_get_list($data);

        $this->assertIsArray($result);
}

    public function testbalance_get_total()
    {
        $balanceAmount = 0.00;
        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Client\ServiceBalance')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getClientBalance')
            ->will($this->returnValue($balanceAmount));

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($name, $sub) use($serviceMock) {return $serviceMock;});

        $api = new \Box\Mod\Client\Api\Client();
        $api->setDi($di);
        $api->setIdentity($model);

        $result = $api->balance_get_total();

        $this->assertIsFloat($result);
        $this->assertEquals($balanceAmount, $result);

    }

    public function testis_taxable()
    {
        $clientIsTaxable = true;

        $serviceMock = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('isClientTaxable')
            ->willReturn($clientIsTaxable);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $api = new \Box\Mod\Client\Api\Client();
        $api->setService($serviceMock);
        $api->setIdentity($client);

        $result = $api->is_taxable();
        $this->assertIsBool($result);
        $this->assertEquals($clientIsTaxable, $result);

    }
}
 