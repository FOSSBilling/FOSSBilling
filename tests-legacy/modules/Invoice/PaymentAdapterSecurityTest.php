<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class PaymentAdapterSecurityTest extends BBTestCase
{
    public function testCustomProcessTransactionRejectsPublicIpnSource(): void
    {
        $adapter = new Payment_Adapter_Custom([]);
        $adapter->setDi($this->getDi());

        $this->expectException(Payment_Exception::class);
        $this->expectExceptionMessage('Custom payment gateway callbacks must be confirmed by an administrator.');

        $adapter->processTransaction(new Api_Handler(new Model_Admin()), 1, ['source' => 'ipn'], 1);
    }

    public function testClientBalanceProcessTransactionRequiresAuthenticatedClient(): void
    {
        $authMock = $this->createMock(Box_Authorization::class);
        $authMock->expects($this->once())
            ->method('isClientLoggedIn')
            ->willReturn(false);

        $di = $this->getDi();
        $di['auth'] = $authMock;

        $adapter = new Payment_Adapter_ClientBalance();
        $adapter->setDi($di);

        $this->expectException(Payment_Exception::class);
        $this->expectExceptionMessage('IPN is invalid');

        $adapter->processTransaction(new Api_Handler(new Model_Admin()), 1, [], 1);
    }

    public function testClientBalanceProcessTransactionRejectsAnotherClientsInvoice(): void
    {
        $authMock = $this->createMock(Box_Authorization::class);
        $authMock->method('isClientLoggedIn')
            ->willReturn(true);

        $transaction = new Model_Transaction();
        $transaction->loadBean(new DummyBean());
        $transaction->invoice_id = 10;

        $invoice = new Model_Invoice();
        $invoice->loadBean(new DummyBean());
        $invoice->id = 10;
        $invoice->client_id = 7;
        $invoice->gateway_id = 4;

        $dbMock = $this->createMock(Box_Database::class);
        $dbMock->method('load')
            ->willReturnCallback(fn (string $model, int $id): Model_Transaction|\Model_Invoice => match ([$model, $id]) {
                ['Transaction', 1] => $transaction,
                ['Invoice', 10] => $invoice,
                default => throw new RuntimeException('Unexpected lookup'),
            });

        $loggedInClient = new Model_Client();
        $loggedInClient->loadBean(new DummyBean());
        $loggedInClient->id = 3;

        $di = $this->getDi();
        $di['auth'] = $authMock;
        $di['db'] = $dbMock;
        $di['loggedin_client'] = $loggedInClient;

        $adapter = new Payment_Adapter_ClientBalance();
        $adapter->setDi($di);

        $this->expectException(Payment_Exception::class);
        $this->expectExceptionMessage('You are not authorized to pay this invoice with client balance.');

        $adapter->processTransaction(
            new Api_Handler(new Model_Admin()),
            1,
            ['get' => ['invoice_id' => 10]],
            4
        );
    }
}
