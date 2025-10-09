<?php

namespace Box\Mod\Invoice;

class ServiceTest extends \BBTestCase
{
    /**
     * @var Service
     */
    protected $service;

    public function setup(): void
    {
        $this->service = new Service();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $itemInvoiceServiceMock = null;
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public static function dataForSearchQuery(): array
    {
        return [
            [[], 'FROM invoice p', []],
            [
                ['order_id' => '1'],
                'AND pi.type = :item_type AND pi.rel_id = :order_id',
                [
                    'item_type' => \Model_InvoiceItem::TYPE_ORDER,
                    'order_id' => 1,
                ],
            ],
            [
                ['id' => 1],
                'AND p.id = :id',
                [
                    'id' => 1,
                ],
            ],
            [
                ['nr' => 1],
                'AND (p.id = :id_nr OR p.nr = :id_nr)',
                [
                    'id_nr' => 1,
                ],
            ],
            [
                ['approved' => true],
                'AND p.approved = :approved',
                [
                    'approved' => true,
                ],
            ],
            [
                ['status' => 'unpaid'],
                'AND p.status = :status',
                [
                    'status' => 'unpaid',
                ],
            ],
            [
                ['currency' => 'usd'],
                'AND p.currency = :currency',
                [
                    'currency' => 'usd',
                ],
            ],
            [
                ['client_id' => 1],
                'AND p.client_id = :client_id',
                [
                    'client_id' => 1,
                ],
            ],
            [
                ['client' => 'John'],
                'AND (cl.first_name LIKE :client_search OR cl.last_name LIKE :client_search OR cl.id = :client OR cl.email = :client)',
                [
                    'client_search' => 'John%',
                    'client' => 'John',
                ],
            ],
            [
                ['id' => 1],
                'AND p.id = :id',
                [
                    'id' => 1,
                ],
            ],
            [
                ['created_at' => '1353715200'],
                "AND DATE_FORMAT(p.created_at, '%Y-%m-%d') = :created_at",
                [
                    'created_at' => '1353715200',
                ],
            ],
            [
                ['date_from' => '1353715200'],
                'AND UNIX_TIMESTAMP(p.created_at) >= :date_from',
                [
                    'date_from' => '1353715200',
                ],
            ],
            [
                ['date_to' => '1353715200'],
                'AND UNIX_TIMESTAMP(p.created_at) <= :date_to',
                [
                    'date_to' => '1353715200',
                ],
            ],
            [
                ['paid_at' => '1353715200'],
                "AND DATE_FORMAT(p.paid_at, '%Y-%m-%d') = :paid_at",
                [
                    'paid_at' => '1353715200',
                ],
            ],
            [
                ['search' => 'trend'],
                'AND (p.id = :int OR p.nr LIKE :search_like OR p.id LIKE :search OR pi.title LIKE :search_like)',
                [
                    'search' => 'trend',
                    'search_like' => '%trend%',
                    'int' => 0,
                ],
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dataForSearchQuery')]
    public function testgetSearchQuery(array $data, string $expectedStr, array $expectedParams): void
    {
        $di = new \Pimple\Container();

        $this->service->setDi($di);
        $result = $this->service->getSearchQuery($data);
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);

        $this->assertTrue(str_contains($result[0], $expectedStr), $result[0]);
        $this->assertTrue(array_diff_key($result[1], $expectedParams) == []);
    }

    public function testtoApiArray(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());

        $systemService = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();
        $systemService->expects($this->atLeastOnce())
            ->method('getCompany');

        $subscriptionServiceMock = $this->getMockBuilder('\\' . ServiceSubscription::class)->getMock();
        $subscriptionServiceMock->expects($this->atLeastOnce())
            ->method('isSubscribable')
            ->willReturn(true);
        $modelToArrayResult = [
            'id' => 1,
            'serie' => 'BB',
            'nr' => '0001',
            'serie_nr' => 'BB0001',
            'hash' => 'hashedValue',
            'gateway_id' => '',
            'taxname' => '',
            'taxrate' => '',
            'currency' => '',
            'currency_rate' => '',
            'status' => '',
            'notes' => '',
            'text_1' => '',
            'text_2' => '',
            'due_at' => '',
            'paid_at' => '',
            'created_at' => '',
            'updated_at' => '',
            'buyer_first_name' => '',
            'buyer_last_name' => '',
            'buyer_company' => '',
            'buyer_company_vat' => '',
            'buyer_company_number' => '',
            'buyer_address' => '',
            'buyer_city' => '',
            'buyer_state' => '',
            'buyer_country' => '',
            'buyer_phone' => '',
            'buyer_phone_cc' => '',
            'buyer_email' => '',
            'buyer_zip' => '',
            'seller_company_vat' => '',
            'seller_company_number' => '',
        ];
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->willReturn($modelToArrayResult);
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$invoiceItemModel]);
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn('1W');

        $periodMock = $this->getMockBuilder('\Box_Period')
            ->disableOriginalConstructor()
            ->getMock();
        $periodMock->expects($this->atLeastOnce())
            ->method('getUnit');
        $periodMock->expects($this->atLeastOnce())
            ->method('getQty');

        $di = new \Pimple\Container();
        $itemInvoiceServiceMock = null;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($itemInvoiceServiceMock, $systemService, $subscriptionServiceMock) {
            $service = null;
            if ($sub == 'InvoiceItem') {
                $service = $itemInvoiceServiceMock;
            }
            if ($serviceName == 'system') {
                $service = $systemService;
            }
            if ($sub == 'Subscription') {
                $service = $subscriptionServiceMock;
            }

            return $service;
        });
        $di['period'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $periodMock);

        $this->service->setDi($di);

        $result = $this->service->toApiArray($invoiceModel);
        $this->assertIsArray($result);
    }

    public function testonAfterAdminInvoicePaymentReceived(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['toApiArray'])
            ->getMock();
        $arr = [
            'total' => 1,
            'client' => [
                'id' => 0,
            ],
        ];
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn($arr);

        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters');

        $emailService = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('sendTemplate');

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($invoiceModel);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailService, $serviceMock) {
            if ($serviceName == 'invoice') {
                return $serviceMock;
            }
            if ($serviceName == 'email') {
                return $emailService;
            }
        });
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $result = $this->service->onAfterAdminInvoicePaymentReceived($eventMock);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testonAfterAdminInvoiceReminderSent(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['toApiArray'])
            ->getMock();
        $arr = [
            'total' => 1,
            'client' => [
                'id' => 1,
            ],
        ];
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn($arr);

        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters');

        $emailService = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('sendTemplate');

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($invoiceModel);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailService, $serviceMock) {
            if ($serviceName == 'invoice') {
                return $serviceMock;
            }
            if ($serviceName == 'email') {
                return $emailService;
            }
        });
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $this->service->setDi($di);
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $result = $this->service->onAfterAdminInvoicePaymentReceived($eventMock);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testonAfterAdminCronRun(): void
    {
        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();

        $remove_after_days = 64;
        $systemServiceMock = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->with('remove_after_days')
            ->willReturn($remove_after_days);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('exec');

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $this->service->onAfterAdminCronRun($eventMock);
    }

    public function testonEventAfterInvoiceIsDue(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['toApiArray'])
            ->getMock();
        $arr = [
            'total' => 1,
            'client' => [
                'id' => 1,
            ],
        ];
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn($arr);

        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();
        $params = ['days_passed' => 5, 'id' => 1];
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->willReturn($params);

        $emailService = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)->getMock();
        $emailService->expects($this->atLeastOnce())
            ->method('sendTemplate');

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($invoiceModel);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailService, $serviceMock) {
            if ($serviceName == 'invoice') {
                return $serviceMock;
            }
            if ($serviceName == 'email') {
                return $emailService;
            }
        });
        $di['db'] = $dbMock;

        $serviceMock->setDi($di);
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);
        $result = $serviceMock->onEventAfterInvoiceIsDue($eventMock);
    }

    public function testmarkAsPaid(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['countIncome'])
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('countIncome');

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $invoiceModel->status = \Model_Invoice::STATUS_UNPAID;

        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());

        $itemInvoiceServiceMock = $this->getMockBuilder('\\' . ServiceInvoiceItem::class)->getMock();
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('markAsPaid');
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('executeTask');

        $systemService = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();
        $systemService->expects($this->atLeastOnce())
            ->method('getParamValue');

        $currencyService = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $currencyService->expects($this->atLeastOnce())
            ->method('getRateByCode');

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$invoiceItemModel]);
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($systemService, $itemInvoiceServiceMock, $currencyService) {
            if ($serviceName == 'system') {
                return $systemService;
            }
            if ($sub == 'InvoiceItem') {
                return $itemInvoiceServiceMock;
            }
            if ($serviceName == 'Currency') {
                return $currencyService;
            }
        });
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventManagerMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);
        $result = $serviceMock->markAsPaid($invoiceModel, true, true);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testcountIncome(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['getTotal'])
            ->getMock();

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $currencyService = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $currencyService->expects($this->atLeastOnce())
            ->method('toBaseCurrency');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $currencyService);

        $serviceMock->setDi($di);
        $serviceMock->countIncome($invoiceModel);
    }

    public function testprepareInvoiceCurrencyWasNotDefined(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['setInvoiceDefaults'])
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('setInvoiceDefaults');

        $data = [
            'gateway_id' => '',
            'text_1' => '',
            'text_2' => '',
            'items' => [
                [
                    'id' => 1,
                ],
            ],
            'approve',
        ];

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $currencyModel = new \Model_Currency();
        $currencyModel->loadBean(new \DummyBean());

        $currencyService = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $currencyService->expects($this->atLeastOnce())
            ->method('getDefault')
            ->willReturn($currencyModel);

        $itemInvoiceServiceMock = $this->getMockBuilder('\\' . ServiceInvoiceItem::class)->getMock();
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('addNew');

        $newRecordId = 1;
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($newRecordId);

        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($invoiceModel);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($currencyService, $itemInvoiceServiceMock) {
            if ($serviceName == 'Currency') {
                return $currencyService;
            }
            if ($sub == 'InvoiceItem') {
                return $itemInvoiceServiceMock;
            }
        });
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);
        $result = $serviceMock->prepareInvoice($clientModel, $data);
        $this->assertInstanceOf('Model_Invoice', $result);
    }

    public function testsetInvoiceDefaults(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $buyer = [
            'first_name' => '',
            'last_name' => '',
            'company' => '',
            'company_vat' => '',
            'company_number' => '',
            'address_1' => '',
            'address_2' => '',
            'city' => '',
            'state' => '',
            'country' => '',
            'phone_cc' => '',
            'phone' => '',
            'email' => '',
            'postcode' => '',
        ];
        $clientService = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)->getMock();
        $clientService->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn($buyer);

        $systemService = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();
        $seller = [
            'name' => '',
            'vat_number' => '',
            'number' => '',
            'address_1' => '',
            'address_2' => '',
            'address_3' => '',
            'tel' => '',
            'email' => '',
        ];
        $systemService->expects($this->atLeastOnce())
            ->method('getCompany')
            ->willReturn($seller);
        $systemService->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->willReturn(1);

        $serviceTaxMock = $this->getMockBuilder('\\' . ServiceTax::class)->getMock();
        $serviceTaxMock->expects($this->atLeastOnce())
            ->method('getTaxRateForClient');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($clientModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($clientService, $systemService, $serviceTaxMock) {
            if ($serviceName == 'Client') {
                return $clientService;
            }
            if ($serviceName == 'system') {
                return $systemService;
            }
            if ($sub == 'Tax') {
                return $serviceTaxMock;
            }
        });

        $this->service->setDi($di);

        $this->service->setInvoiceDefaults($invoiceModel);
    }

    public function testapproveInvoice(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['tryPayWithCredits'])
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('tryPayWithCredits');

        $data['use_credits'] = true;

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventManagerMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->approveInvoice($invoiceModel, $data);
        $this->assertTrue($result);
    }

    public function testgetTotalWithTax(): void
    {
        $total = 10.0;
        $tax = 2.2;
        $expected = $total + $tax;
        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['getTotal', 'getTax'])
            ->getMock();

        $serviceMock->expects($this->once())
            ->method('getTotal')
            ->willReturn($total);
        $serviceMock->expects($this->once())
            ->method('getTax')
            ->willReturn($tax);

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $result = $serviceMock->getTotalWithTax($invoiceModel);
        $this->assertIsFloat($result);
        $this->assertEquals($expected, $result);
    }

    public function testgetTotal(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$invoiceItemModel]);

        $itemInvoiceServiceMock = $this->getMockBuilder('\\' . ServiceInvoiceItem::class)->getMock();

        $itemTotal = 10.0;
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('getTotal')
            ->willReturn($itemTotal);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $itemInvoiceServiceMock);

        $this->service->setDi($di);
        $result = $this->service->getTotal($invoiceModel);
        $this->assertIsFloat($result);
        $this->assertEquals($itemTotal, $result);
    }

    public function testrefundInvoiceWithNegativeInvoiceLogic(): void
    {
        $newId = 1;
        $total = 10.0;
        $tax = 2.2;
        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['getTotal', 'getTax', 'countIncome', 'addNote'])
            ->getMock();

        $serviceMock->expects($this->once())
            ->method('getTotal')
            ->willReturn($total);
        $serviceMock->expects($this->once())
            ->method('getTax')
            ->willReturn($tax);
        $serviceMock->expects($this->once())
            ->method('countIncome');
        $serviceMock->expects($this->exactly(3))
            ->method('addNote');

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $invoiceModel->id = $newId;

        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $systemService = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();
        $systemService->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->willReturn('negative_invoice');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturnOnConsecutiveCalls($invoiceModel, $invoiceItemModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$invoiceItemModel]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemService);
        $di['events_manager'] = $eventManagerMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);
        $result = $serviceMock->refundInvoice($invoiceModel, 'custonNote');
        $this->assertIsInt($result);
        $this->assertEquals($newId, $result);
    }

    public function testupdateInvoice(): void
    {
        $data = [
            'gateway_id' => '',
            'taxname' => '',
            'taxrate' => '',
            'status' => '',
            'notes' => '',
            'text_1' => '',
            'text_2' => '',
            'due_at' => '',
            'paid_at' => '',
            'buyer_first_name' => '',
            'buyer_last_name' => '',
            'buyer_company' => '',
            'buyer_company_vat' => '',
            'buyer_company_number' => '',
            'buyer_address' => '',
            'buyer_city' => '',
            'buyer_state' => '',
            'buyer_country' => '',
            'buyer_phone' => '',
            'buyer_email' => '',
            'buyer_zip' => '',
            'seller_company' => '',
            'seller_address' => '',
            'seller_phone' => '',
            'seller_email' => '',
            'seller_company_vat' => '',
            'seller_company_number' => '',
            'approved' => '',
            'items' => [0 => []],
            'new_item' => ['title' => 'new Item'],
        ];
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $itemInvoiceServiceMock = $this->getMockBuilder('\\' . ServiceInvoiceItem::class)->getMock();
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('addNew');
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('update');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($invoiceItemModel);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $itemInvoiceServiceMock);
        $di['events_manager'] = $eventManagerMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->updateInvoice($invoiceModel, $data);
        $this->assertTrue($result);
    }

    public function testrmInvoice(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('exec');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$invoiceItemModel]);
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->rmInvoice($invoiceModel);
        $this->assertTrue($result);
    }

    public function testdeleteInvoiceByAdmin(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['rmInvoice'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('rmInvoice');

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventManagerMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->deleteInvoiceByAdmin($invoiceModel);
        $this->assertTrue($result);
    }

    public function testdeleteInvoiceByClient(): void
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['rmInvoice'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('rmInvoice');

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$invoiceItemModel]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventManagerMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->deleteInvoiceByClient($invoiceModel);
        $this->assertTrue($result);
    }

    public function testdeleteInvoiceByClientInvoiceIsRelatedToOrder(): void
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());
        $invoiceItemModel->type = \Model_InvoiceItem::TYPE_ORDER;
        $rel_id = 1;
        $invoiceItemModel->rel_id = $rel_id;

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$invoiceItemModel]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventManagerMock;

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage(sprintf('Invoice is related to order #%d. Please cancel order first.', $rel_id));
        $this->service->deleteInvoiceByClient($invoiceModel);
    }

    public function testrenewInvoice(): void
    {
        $newId = 2;
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $invoiceModel->id = $newId;

        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['generateForOrder', 'approveInvoice'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('approveInvoice');
        $serviceMock->expects($this->once())
            ->method('generateForOrder')
            ->willReturn($invoiceModel);

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventManagerMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);
        $result = $serviceMock->renewInvoice($clientOrder, []);
        $this->assertIsInt($result);
        $this->assertEquals($newId, $result);
    }

    public function testdoBatchPayWithCredits(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['findAllUnpaid', 'tryPayWithCredits'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('findAllUnpaid')
            ->willReturn([[]]);
        $serviceMock->expects($this->atLeastOnce())
            ->method('tryPayWithCredits');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($invoiceModel);

        $di = new \Pimple\Container();
        $di['logger'] = new \Box_Log();
        $di['db'] = $dbMock;

        $serviceMock->setDi($di);
        $result = $serviceMock->doBatchPayWithCredits([]);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testpayInvoiceWithCredits(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['tryPayWithCredits'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('tryPayWithCredits');

        $di = new \Pimple\Container();
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);
        $result = $serviceMock->payInvoiceWithCredits($invoiceModel);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testgenerateForOrderInvoiceIsCreatedAlready(): void
    {
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \DummyBean());
        $clientOrder->unpaid_invoice_id = 2;

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($invoiceModel);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->generateForOrder($clientOrder);
        $this->assertInstanceOf('\Model_Invoice', $result);
    }

    public function testgenerateForOrder(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['setInvoiceDefaults'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('setInvoiceDefaults');

        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());
        $orderModel->price = 10;
        $orderModel->promo_recurring = true;

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($clientModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($invoiceModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $invoiceItemServiceMock = $this->getMockBuilder('\\' . ServiceInvoiceItem::class)
            ->getMock();
        $invoiceItemServiceMock->expects($this->atLeastOnce())
            ->method('generateFromOrder');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $invoiceItemServiceMock);

        $serviceMock->setDi($di);
        $result = $serviceMock->generateForOrder($orderModel);
        $this->assertInstanceOf('\Model_Invoice', $result);
    }

    public function testsgenerateForOrderAmountIsZero(): void
    {
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \DummyBean());
        $clientOrder->price = 0;

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Invoices are not generated for 0 amount orders');
        $this->service->generateForOrder($clientOrder);
    }

    public function testgenerateInvoicesForExpiringOrdersNoExpOrders(): void
    {
        $orderService = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderService->expects($this->atLeastOnce())
            ->method('getSoonExpiringActiveOrders')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderService);

        $this->service->setDi($di);
        $result = $this->service->generateInvoicesForExpiringOrders();
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testgenerateInvoicesForExpiringOrders(): void
    {
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \DummyBean());

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $newId = 4;
        $invoiceModel->id = $newId;

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['generateForOrder', 'approveInvoice'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('approveInvoice');
        $serviceMock->expects($this->once())
            ->method('generateForOrder')
            ->willReturn($invoiceModel);

        $orderService = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderService->expects($this->atLeastOnce())
            ->method('getSoonExpiringActiveOrders')
            ->willReturn([[]]);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($clientOrder);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderService);
        $di['logger'] = new \Box_Log();
        $di['db'] = $dbMock;

        $serviceMock->setDi($di);
        $result = $serviceMock->generateInvoicesForExpiringOrders();
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testdoBatchPaidInvoiceActivation(): void
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());

        $itemInvoiceServiceMock = $this->getMockBuilder('\\' . ServiceInvoiceItem::class)->getMock();
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('executeTask')
            ->with($invoiceItemModel);
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('getAllNotExecutePaidItems')
            ->willReturn([[]]);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($invoiceItemModel);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $itemInvoiceServiceMock);
        $di['logger'] = new \Box_Log();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->doBatchPaidInvoiceActivation();
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testdoBatchPaidInvoiceActivationException(): void
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());

        $itemInvoiceServiceMock = $this->getMockBuilder('\\' . ServiceInvoiceItem::class)->getMock();
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('executeTask')
            ->with($invoiceItemModel)
            ->willThrowException(new \FOSSBilling\Exception('tesitng exception..'));
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('getAllNotExecutePaidItems')
            ->willReturn([[]]);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($invoiceItemModel);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $itemInvoiceServiceMock);
        $di['logger'] = new \Box_Log();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->doBatchPaidInvoiceActivation();
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testdoBatchRemindersSend(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['getUnpaidInvoicesLateFor', 'sendInvoiceReminder'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('sendInvoiceReminder');
        $serviceMock->expects($this->once())
            ->method('getUnpaidInvoicesLateFor')
            ->willReturn([$invoiceModel]);

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventManagerMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);
        $result = $serviceMock->doBatchRemindersSend();
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testdoBatchInvokeDueEvent(): void
    {
        $systemService = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();
        $systemService->expects($this->atLeastOnce())
            ->method('getParamValue');
        $systemService->expects($this->atLeastOnce())
            ->method('setParamValue');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn([[]]);

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventManagerMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemService);
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->doBatchInvokeDueEvent([]);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testsendInvoiceReminderProtectionFromAccidentalReminders(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $invoiceModel->status = \Model_Invoice::STATUS_PAID;

        $result = $this->service->sendInvoiceReminder($invoiceModel);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testsendInvoiceReminder(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventManagerMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->sendInvoiceReminder($invoiceModel);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testcounter(): void
    {
        $sqlResult = [
            ['status' => \Model_Invoice::STATUS_PAID,
                'counter' => 2],
        ];
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn($sqlResult);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->counter();
        $this->assertIsArray($result);
    }

    public function testgenerateFundsInvoiceNoActiveOrder(): void
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('You must have at least one active order before you can add funds so you cannot proceed at the current time!');
        $this->service->generateFundsInvoice($clientModel, 10);
    }

    public function testgenerateFundsInvoiceMinAmountLimit(): void
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $clientModel->currency = 'EUR';
        $fundsAmount = 2;

        $minAmount = 10;
        $maxAmount = 50;
        $systemService = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();
        $systemService->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->willReturnOnConsecutiveCalls($minAmount, $maxAmount);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemService);

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(981);
        $this->expectExceptionMessage('Amount must be at least ' . $minAmount);
        $this->service->generateFundsInvoice($clientModel, $fundsAmount);
    }

    public function testgenerateFundsInvoiceMaxAmountLimit(): void
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $clientModel->currency = 'EUR';
        $fundsAmount = 200;

        $minAmount = 10;
        $maxAmount = 50;
        $systemService = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();
        $systemService->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->willReturnOnConsecutiveCalls($minAmount, $maxAmount);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemService);

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(982);
        $this->expectExceptionMessage('Amount cannot exceed ' . $maxAmount);
        $this->service->generateFundsInvoice($clientModel, $fundsAmount);
    }

    public function testgenerateFundsInvoice(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $clientModel->currency = 'EUR';
        $fundsAmount = 20;

        $minAmount = 10;
        $maxAmount = 50;

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['setInvoiceDefaults'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('setInvoiceDefaults');

        $systemService = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();
        $systemService->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->willReturnOnConsecutiveCalls($minAmount, $maxAmount, true);

        $itemInvoiceServiceMock = $this->getMockBuilder('\\' . ServiceInvoiceItem::class)->getMock();
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('generateForAddFunds');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($invoiceModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($systemService, $itemInvoiceServiceMock) {
            if ($serviceName == 'system') {
                return $systemService;
            }
            if ($sub == 'InvoiceItem') {
                return $itemInvoiceServiceMock;
            }
        });
        $di['db'] = $dbMock;

        $serviceMock->setDi($di);

        $result = $serviceMock->generateFundsInvoice($clientModel, $fundsAmount);
        $this->assertInstanceOf('\Model_Invoice', $result);
    }

    public function testprocessInvoiceInvoiceNotFound(): void
    {
        $data = [
            'hash' => 'hashString',
            'gateway_id' => 2,
        ];

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(812);
        $this->expectExceptionMessage('Invoice not found');
        $this->service->processInvoice($data);
    }

    public function testprocessInvoicePayGatewayNotFound(): void
    {
        $data = [
            'hash' => 'hashString',
            'gateway_id' => 2,
        ];

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($invoiceModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(813);
        $this->expectExceptionMessage('Payment method not found');
        $this->service->processInvoice($data);
    }

    public function testprocessInvoicePayGatewayNotEnabled(): void
    {
        $data = [
            'hash' => 'hashString',
            'gateway_id' => 2,
        ];

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($invoiceModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($payGatewayModel);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(814);
        $this->expectExceptionMessage('Payment method not enabled');
        $this->service->processInvoice($data);
    }

    public function testprocessInvoice(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['getPaymentInvoice'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getPaymentInvoice')
            ->willReturn(new \Payment_Invoice());
        $data = [
            'hash' => 'hashString',
            'gateway_id' => 2,
        ];

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());
        $payGatewayModel->enabled = true;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($invoiceModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($payGatewayModel);

        $subcribeService = $this->getMockBuilder('\\' . ServiceSubscription::class)->getMock();
        $subcribeService->expects($this->atLeastOnce())
            ->method('isSubscribable')
            ->willReturn(true);

        $payGatewayService = $this->getMockBuilder('\\' . ServicePayGateway::class)->getMock();
        $payGatewayService->expects($this->atLeastOnce())
            ->method('canPerformRecurrentPayment')
            ->willReturn(true);

        $adapterMock = $this->getMockBuilder('\Payment_Adapter_Dummy')
            ->disableOriginalConstructor()
            ->getMock();

        $adapterMock->expects($this->atLeastOnce())
            ->method('setDi');
        $adapterMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([]);

        $payGatewayService->expects($this->atLeastOnce())
            ->method('getPaymentAdapter')
            ->willReturn($adapterMock);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($payGatewayService, $subcribeService) {
            if ($sub == 'PayGateway') {
                return $payGatewayService;
            }
            if ($sub == 'Subscription') {
                return $subcribeService;
            }
        });
        $di['api_admin'] = new \Api_Handler(new \Model_Admin());
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);
        $result = $serviceMock->processInvoice($data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('service_url', $result);
        $this->assertArrayHasKey('subscription', $result);
        $this->assertArrayHasKey('result', $result);
    }

    public function testaddNote(): void
    {
        $note = 'test Note';

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->addNote($invoiceModel, $note);
        $this->assertTrue($result);
    }

    public function testfindAllUnpaid(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $getAllResult = [
            [
                'id' => 1,
                'client_id' => 1,
                'serie' => 'BB',
                'nr' => '00',
            ],
        ];
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn($getAllResult);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->findAllUnpaid();
        $this->assertIsArray($result);
    }

    public function testfindAllPaid(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$invoiceModel]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->findAllPaid();
        $this->assertIsArray($result);
        $this->assertInstanceOf('\Model_Invoice', $result[0]);
    }

    public function testgetUnpaidInvoicesLateFor(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$invoiceModel]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getUnpaidInvoicesLateFor();
        $this->assertIsArray($result);
        $this->assertInstanceOf('\Model_Invoice', $result[0]);
    }

    public function testgetBuyer(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $expected = [
            'first_name' => '',
            'last_name' => '',
            'company' => '',
            'address' => '',
            'city' => '',
            'state' => '',
            'country' => '',
            'phone' => '',
            'phone_cc' => '',
            'email' => '',
            'zip' => '',
        ];

        $result = $this->service->getBuyer($invoiceModel);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testisInvoiceTypeDeposit(): void
    {
        $di = new \Pimple\Container();

        $modelInvoiceItem = new \Model_InvoiceItem();
        $modelInvoiceItem->loadBean(new \DummyBean());
        $modelInvoiceItem->type = \Model_InvoiceItem::TYPE_DEPOSIT;

        $invoiceItems = [$modelInvoiceItem];

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->with('InvoiceItem')
            ->willReturn($invoiceItems);

        $di['db'] = $dbMock;

        $modelInvoice = new \Model_Invoice();
        $modelInvoice->loadBean(new \DummyBean());

        $this->service->setDi($di);
        $result = $this->service->isInvoiceTypeDeposit($modelInvoice);
        $this->assertTrue($result);
    }

    public function testisInvoiceTypeDepositTypeIsNotDeposit(): void
    {
        $di = new \Pimple\Container();

        $modelInvoiceItem = new \Model_InvoiceItem();
        $modelInvoiceItem->loadBean(new \DummyBean());
        $modelInvoiceItem->type = \Model_InvoiceItem::TYPE_ORDER;

        $invoiceItems = [$modelInvoiceItem];

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->with('InvoiceItem')
            ->willReturn($invoiceItems);

        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $modelInvoice = new \Model_Invoice();
        $modelInvoice->loadBean(new \DummyBean());

        $result = $this->service->isInvoiceTypeDeposit($modelInvoice);
        $this->assertFalse($result);
    }

    public function testisInvoiceTypeDepositEmptyArray(): void
    {
        $di = new \Pimple\Container();

        $invoiceItems = [];

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->with('InvoiceItem')
            ->willReturn($invoiceItems);

        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $modelInvoice = new \Model_Invoice();
        $modelInvoice->loadBean(new \DummyBean());

        $result = $this->service->isInvoiceTypeDeposit($modelInvoice);
        $this->assertFalse($result);
    }
}
