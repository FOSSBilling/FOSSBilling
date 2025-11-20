<?php

namespace Box\Mod\Client\Api;

class ClientTest extends \BBTestCase
{
    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $client = new Client();
        $client->setDi($di);
        $getDi = $client->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testbalanceGetList(): void
    {
        $data = [];

        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\ServiceBalance::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getSearchQuery')
            ->willReturn(['sql', []]);

        $simpleResultArr = [
            'list' => [
                ['id' => 1],
            ],
        ];

        $pagerMock = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($simpleResultArr);

        $model = new \Model_ClientBalance();
        $model->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);
        $di['pager'] = $pagerMock;
        $di['db'] = $dbMock;

        $client = new Client();
        $client->setDi($di);
        $client->setService($serviceMock);
        $client->setIdentity($model);

        $result = $client->balance_get_list($data);

        $this->assertIsArray($result);
    }

    public function testbalanceGetTotal(): void
    {
        $balanceAmount = 0.00;
        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\ServiceBalance::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getClientBalance')
            ->willReturn($balanceAmount);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn ($name, $sub): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);

        $api = new Client();
        $api->setDi($di);
        $api->setIdentity($model);

        $result = $api->balance_get_total();

        $this->assertIsFloat($result);
        $this->assertEquals($balanceAmount, $result);
    }

    public function testisTaxable(): void
    {
        $clientIsTaxable = true;

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('isClientTaxable')
            ->willReturn($clientIsTaxable);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $api = new Client();
        $api->setService($serviceMock);
        $api->setIdentity($client);

        $result = $api->is_taxable();
        $this->assertIsBool($result);
        $this->assertEquals($clientIsTaxable, $result);
    }
}
