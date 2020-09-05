<?php


namespace Box\Tests\Mod\Client;

class ServiceBalanceTest extends \BBTestCase
{

    public function testgetDi()
    {
        $di = new \Box_Di();
        $service = new \Box\Mod\Client\ServiceBalance();
        $service->setDi($di);
        $getDi = $service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testdeductFunds()
    {
        $di = new \Box_Di();

        $clientBalance = new \Model_ClientBalance();
        $clientBalance->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->with('ClientBalance')
            ->willReturn($clientBalance);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->with($clientBalance);
        $di['db'] = $dbMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        
        $service = new \Box\Mod\Client\ServiceBalance();
        $service->setDi($di);

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());

        $description = 'Charged for product';
        $amount = 5.55;

        $extra = array(
            'rel_id' => 1,
        );

        $result = $service->deductFunds($clientModel, $amount, $description, $extra);

        $this->assertInstanceOf('\Model_ClientBalance', $result);
        $this->assertEquals(-$amount, $result->amount);
        $this->assertEquals($description, $result->description);
        $this->assertEquals($extra['rel_id'], $result->rel_id);
        $this->assertEquals('default', $result->type);
    }

    public function testdeductFunds_InvalidDescription()
    {
        $service = new \Box\Mod\Client\ServiceBalance();

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());

        $description = '    ';
        $amount = 5.55;

        $extra = array(
            'rel_id' => 1,
        );

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Funds description is not valid');
        $service->deductFunds($clientModel, $amount, $description, $extra);
    }

    public function testdeductFunds_InvalidAmount()
    {
        $service = new \Box\Mod\Client\ServiceBalance();

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());

        $description = 'Charged';
        $amount = "5.5adadzxc";

        $extra = array(
            'rel_id' => 1,
        );

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Funds amount is not valid');
        $service->deductFunds($clientModel, $amount, $description, $extra);
    }
}
 