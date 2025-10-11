<?php

namespace Box\Mod\Invoice;

class ServiceTransactionTest extends \BBTestCase
{
    /**
     * @var ServiceTransaction
     */
    protected $service;

    public function setup(): void
    {
        $this->service = new ServiceTransaction();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testprocessReceivedATransactions(): void
    {
        $transactionModel = new \Model_Transaction();
        $transactionModel->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . ServiceTransaction::class)
            ->onlyMethods(['getReceived', 'preProcessTransaction'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getReceived')
            ->willReturn([[]]);
        $serviceMock->expects($this->atLeastOnce())
            ->method('preProcessTransaction');

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturnOnConsecutiveCalls($transactionModel);

        $di = new \Pimple\Container();
        $di['logger'] = new \Box_Log();
        $di['db'] = $dbMock;

        $serviceMock->setDi($di);
        $result = $serviceMock->processReceivedATransactions();
        $this->assertTrue($result);
    }

    public function testupdate(): void
    {
        $eventsMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventsMock->expects($this->atLeastOnce())
            ->method('fire');

        $transactionModel = new \Model_Transaction();
        $transactionModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventsMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $data = [
            'invoice_id' => 1,
            'txn_id' => 2,
            'txn_status' => '',
            'gateway_id' => 1,
            'amount' => '',
            'currency' => '',
            'type' => '',
            'note' => '',
            'status' => '',
            'validate_ipn' => '',
        ];
        $result = $this->service->update($transactionModel, $data);
        $this->assertTrue($result);
    }

    public function testcreateInvalidMissinginvoiceId(): void
    {
        $eventsMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventsMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventsMock;

        $this->service->setDi($di);

        $data = [
            'skip_validation' => false,
            'gateway_id' => 1,
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Transaction invoice ID is missing');
        $this->service->create($data);
    }

    public function testcreateInvalidMissingbbGatewayId(): void
    {
        $eventsMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventsMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventsMock;

        $this->service->setDi($di);

        $data = [
            'skip_validation' => false,
            'invoice_id' => 2,
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Payment gateway ID is missing');
        $this->service->create($data);
    }

    public function testdelete(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = new \Pimple\Container();
        $di['logger'] = new \Box_Log();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $transactionModel = new \Model_Transaction();
        $transactionModel->loadBean(new \DummyBean());

        $result = $this->service->delete($transactionModel);
        $this->assertTrue($result);
    }

    public function testtoApiArray(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($payGatewayModel);
        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $expected = [
            'id' => null,
            'invoice_id' => null,
            'txn_id' => null,
            'txn_status' => null,
            'gateway_id' => 1,
            'gateway' => null,
            'amount' => null,
            'currency' => null,
            'type' => null,
            'status' => null,
            'ip' => null,
            'validate_ipn' => null,
            'error' => null,
            'error_code' => null,
            'note' => null,
            'created_at' => null,
            'updated_at' => null,
        ];
        $transactionModel = new \Model_Transaction();
        $transactionModel->loadBean(new \DummyBean());
        $transactionModel->gateway_id = 1;

        $result = $this->service->toApiArray($transactionModel, false);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public static function searchQueryData(): array
    {
        return [
            [
                [], [], 'SELECT m.*',
            ],
            [
                ['search' => 'keyword'], ['note' => '%keyword%', 'search_invoice_id' => '%keyword%', 'search_txn_id' => '%keyword%', 'ipn' => '%keyword%'], 'AND m.note LIKE :note OR m.invoice_id LIKE :search_invoice_id OR m.txn_id LIKE :search_txn_id OR m.ipn LIKE :ipn',
            ],
            [
                ['invoice_hash' => 'hashString'], ['hash' => 'hashString'], 'AND i.hash = :hash',
            ],
            [
                ['invoice_id' => '1'], ['invoice_id' => '1'], 'AND m.invoice_id = :invoice_id',
            ],
            [
                ['gateway_id' => '2'], ['gateway_id' => '2'], 'AND m.gateway_id = :gateway_id',
            ],
            [
                ['client_id' => '3'], ['client_id' => '3'], 'AND i.client_id = :client_id',
            ],
            [
                ['status' => 'active'], ['status' => 'active'], 'AND m.status = :status',
            ],
            [
                ['currency' => 'Eur'], ['currency' => 'Eur'], 'AND m.currency = :currency',
            ],
            [
                ['type' => 'payment'], ['type' => 'payment'], 'AND m.type = :type',
            ],
            [
                ['txn_id' => 'longTxn_id'], ['txn_id' => 'longTxn_id'], 'AND m.txn_id = :txn_id',
            ],
            [
                ['date_from' => '2012-12-12'], ['date_from' => 1_355_270_400], 'AND UNIX_TIMESTAMP(m.created_at) >= :date_from',
            ],
            [
                ['date_to' => '2012-12-12'], ['date_to' => 1_355_270_400], 'AND UNIX_TIMESTAMP(m.created_at) <= :date_to',
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('searchQueryData')]
    public function testgetSearchQuery(array $data, array $expectedParams, string $expectedStringPart): void
    {
        $di = new \Pimple\Container();

        $this->service->setDi($di);
        $result = $this->service->getSearchQuery($data);
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);

        $this->assertTrue(str_contains($result[0], $expectedStringPart));
        $this->assertEquals($expectedParams, $result[1]);
    }

    public function testcounter(): void
    {
        $queryResult = [['status' => \Model_Transaction::STATUS_RECEIVED, 'counter' => 1]];
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn($queryResult);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->counter();
        $this->assertIsArray($result);
        $expected = [
            'total' => 1,
            'received' => 1,
            'approved' => 0,
            'error' => 0,
            'processed' => 0,
        ];
        $this->assertEquals($expected, $result);
    }

    public function testgetStatusPairs(): void
    {
        $result = $this->service->getStatusPairs();
        $this->assertIsArray($result);

        $expected = [
            'received' => 'Received',
            'approved' => 'Approved',
            'processed' => 'Processed',
            'error' => 'Error',
        ];
        $this->assertEquals($expected, $result);
    }

    public function testgetStatus(): void
    {
        $result = $this->service->getStatuses();
        $this->assertIsArray($result);

        $expected = [
            'received' => 'Received',
            'approved' => 'Approved/Verified',
            'processed' => 'Processed',
            'error' => 'Error',
        ];
        $this->assertEquals($expected, $result);
    }

    public function testgetGatewayStatuses(): void
    {
        $result = $this->service->getGatewayStatuses();
        $this->assertIsArray($result);

        $expected = [
            'pending' => 'Pending validation',
            'complete' => 'Complete',
            'unknown' => 'Unknown',
        ];
        $this->assertEquals($expected, $result);
    }

    public function testgetTypes(): void
    {
        $result = $this->service->getTypes();
        $this->assertIsArray($result);

        $expected = [
            'payment' => 'Payment',
            'refund' => 'Refund',
            'subscription_create' => 'Subscription create',
            'subscription_cancel' => 'Subscription cancel',
            'unknown' => 'Unknown',
        ];
        $this->assertEquals($expected, $result);
    }

    public function testpreProcessTransaction(): void
    {
        $transactionModel = new \Model_Transaction();
        $transactionModel->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . ServiceTransaction::class)
            ->onlyMethods(['processTransaction'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('processTransaction')
            ->willReturn('processedOutputString');

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();
        $serviceMock->setDi($di);

        $result = $serviceMock->preProcessTransaction($transactionModel);
        $this->assertIsString($result);
    }

    public function testpreProcessTransactionRegisterException(): void
    {
        $transactionModel = new \Model_Transaction();
        $transactionModel->loadBean(new \DummyBean());

        $exceptionMessage = 'Exception created with PHPUnit Test';

        $serviceMock = $this->getMockBuilder('\\' . ServiceTransaction::class)
            ->onlyMethods(['processTransaction'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('processTransaction')
            ->will($this->throwException(new \FOSSBilling\Exception($exceptionMessage)));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $serviceMock->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage($exceptionMessage);
        $serviceMock->preProcessTransaction($transactionModel);
    }

    public static function paymentsAdapterProvider_withprocessTransaction(): array
    {
        return [
            ['\Payment_Adapter_PayPalEmail'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('paymentsAdapterProvider_withprocessTransaction')]
    public function testprocessTransactionSupportProcessTransaction(string $adapter): void
    {
        $id = 1;
        $transactionModel = new \Model_Transaction();
        $transactionModel->loadBean(new \DummyBean());
        $transactionModel->gateway_id = 2;
        $transactionModel->ipn = '{}';

        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturnOnConsecutiveCalls($transactionModel, $payGatewayModel);

        $paymentAdapterMock = $this->getMockBuilder($adapter)
            ->disableOriginalConstructor()
            ->getMock();

        $paymentAdapterMock->expects($this->atLeastOnce())
            ->method('processTransaction');

        $payGatewayService = $this->getMockBuilder('\\' . ServicePayGateway::class)->getMock();
        $payGatewayService->expects($this->atLeastOnce())
            ->method('getPaymentAdapter')
            ->willReturn($paymentAdapterMock);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $payGatewayService);
        $di['api_system'] = new \Api_Handler(new \Model_Admin());
        $this->service->setDi($di);

        $this->service->processTransaction($id);
    }

    public function getReceived(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . ServiceTransaction::class)
            ->onlyMethods(['getSearchQuery'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getSearchQuery')
            ->willReturn(['SqlString', []]);

        $assoc = [
            [
                'id' => 1,
                'invoice_id' => 1,
            ],
        ];
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($assoc);

        $transactionModel = new \Model_Transaction();
        $transactionModel->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn([[]]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $serviceMock->setDi($di);

        $result = $serviceMock->getReceived();
        $this->assertIsArray($result);
    }

    public function testdebitTransaction(): void
    {
        $currency = 'EUR';
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $invoiceModel->currency = $currency;

        $clientModdel = new \Model_Client();
        $clientModdel->loadBean(new \DummyBean());
        $clientModdel->currency = $currency;

        $transactionModel = new \Model_Transaction();
        $transactionModel->loadBean(new \DummyBean());
        $transactionModel->amount = 11;

        $clientBalanceModel = new \Model_ClientBalance();
        $clientBalanceModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturnOnConsecutiveCalls($invoiceModel, $clientModdel);
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($clientBalanceModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $this->service->debitTransaction($transactionModel);
    }

    public function testcreateAndProcess(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . ServiceTransaction::class)
            ->onlyMethods(['create', 'processTransaction'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('create');
        $serviceMock->expects($this->once())
            ->method('processTransaction');

        $ipn = [];
        $serviceMock->createAndProcess($ipn);
    }

    public function testcreateReturnsExistingForDuplicateIpn(): void
    {
        $existing = new \Model_Transaction();
        $existing->loadBean(new \DummyBean());
        $existing->id = 123;
        $existing->status = \Model_Transaction::STATUS_PROCESSED;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($existing);

        $eventsMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventsMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventsMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $data = [
            'skip_validation' => true,
            'gateway_id' => 2,
            'post' => ['amount' => '10.00', 'mc_currency' => 'EUR'],
            'get' => [],
            'http_raw_post_data' => null,
            'server' => null,
        ];

        $resultId = $this->service->create($data);
        $this->assertEquals(123, $resultId);
    }
}
