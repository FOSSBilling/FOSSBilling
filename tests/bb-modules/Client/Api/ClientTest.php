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
        $model->loadBean(new \RedBeanPHP\OODBBean());

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
        $model->loadBean(new \RedBeanPHP\OODBBean());
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

    public function testchange_password_PasswordDoNotMatch()
    {
        $client = new \Box\Mod\Client\Api\Client();

        $data = array(
            'password' => '1234',
            'password_confirm' => '1234567'
        );
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $client->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Passwords do not match.');
        $client->change_password($data);
    }

    public function testupdate()
    {
        $data = array(
            'id'             => 1,
            'first_name'     => 'John',
            'last_name'      => 'Smith',
            'aid'            => '0',
            'gender'         => 'male',
            'birthday'       => '1999-01-01',
            'company'        => 'LTD Testing',
            'company_vat'    => 'VAT0007',
            'address_1'      => 'United States',
            'address_2'      => 'Utah',
            'phone_cc'       => '+1',
            'phone'          => '555-345-345',
            'document_type'  => 'doc',
            'document_nr'    => '1',
            'notes'          => 'none',
            'country'        => 'Moon',
            'postcode'       => 'IL-11123',
            'city'           => 'Chicaco',
            'state'          => 'IL',
            'currency'       => 'USD',
            'tax_exempt'     => 'n/a',
            'created_at'     => '2012-05-10',
            'email'          => 'test@example.com',
            'group_id'       => 1,
            'status'         => 'test status',
            'company_number' => '1234',
            'type'           => '',
            'lang'           => 'en',
            'custom_1'       => '',
            'custom_2'       => '',
            'custom_3'       => '',
            'custom_4'       => '',
            'custom_5'       => '',
            'custom_6'       => '',
            'custom_7'       => '',
            'custom_8'       => '',
            'custom_9'       => '',
            'custom_10'      => '',
        );

        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')->will($this->returnValue(1));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('emailAreadyRegistered')->will($this->returnValue(false));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
        method('fire');

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('isEmailValid');

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;
        $di['validator'] = $validatorMock;
        $di['logger'] = new \Box_Log();

        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $config = array(
            'allow_change_email' => true,
        );

        $di['mod_config'] = $di->protect(function ($modName) use($config){
            return $config;
        });

        $api = new \Box\Mod\Client\Api\Client();
        $api->setDi($di);
        $api->setIdentity($model);
        $api->setService($serviceMock);
        $result = $api->update($data);
        $this->assertTrue($result);
    }

    public function testbalance_get_total()
    {
        $balanceAmount = 0.00;
        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());

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
        $client->loadBean(new \RedBeanPHP\OODBBean());

        $api = new \Box\Mod\Client\Api\Client();
        $api->setService($serviceMock);
        $api->setIdentity($client);

        $result = $api->is_taxable();
        $this->assertIsBool($result);
        $this->assertEquals($clientIsTaxable, $result);

    }
}
 