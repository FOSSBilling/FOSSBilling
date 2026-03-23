<?php

declare(strict_types=1);

namespace Box\Tests\Mod\Client;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ServiceBalanceTest extends \BBTestCase
{
    public function testGetDi(): void
    {
        $di = $this->getDi();
        $service = new \Box\Mod\Client\ServiceBalance();
        $service->setDi($di);
        $getDi = $service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testDeductFunds(): void
    {
        $di = $this->getDi();

        $clientBalance = new \Model_ClientBalance();
        $clientBalance->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->with('ClientBalance')
            ->willReturn($clientBalance);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->with($clientBalance);
        $di['db'] = $dbMock;

        $service = new \Box\Mod\Client\ServiceBalance();
        $service->setDi($di);

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $description = 'Charged for product';
        $amount = 5.55;

        $extra = [
            'rel_id' => 1,
        ];

        $result = $service->deductFunds($clientModel, $amount, $description, $extra);

        $this->assertInstanceOf('\Model_ClientBalance', $result);
        $this->assertEquals(-$amount, $result->amount);
        $this->assertEquals($description, $result->description);
        $this->assertEquals($extra['rel_id'], $result->rel_id);
        $this->assertEquals('default', $result->type);
    }

    public function testDeductFundsInvalidDescription(): void
    {
        $service = new \Box\Mod\Client\ServiceBalance();

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $description = '    ';
        $amount = 5.55;

        $extra = [
            'rel_id' => 1,
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Funds description is invalid');
        $service->deductFunds($clientModel, $amount, $description, $extra);
    }

    public function testDeductFundsInvalidAmount(): void
    {
        $service = new \Box\Mod\Client\ServiceBalance();

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $description = 'Charged';
        $amount = '5.5adadzxc';

        $extra = [
            'rel_id' => 1,
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Funds amount is invalid');
        $service->deductFunds($clientModel, $amount, $description, $extra);
    }
}
