<?php

declare(strict_types=1);

namespace Box\Mod\Invoice;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    protected ?Service $service;

    public function setUp(): void
    {
        $this->service = new Service();
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
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
                'AND (p.id = :search_numeric_id OR p.nr LIKE :search_like OR p.id LIKE :search OR pi.title LIKE :search_like)',
                [
                    'search' => 'trend',
                    'search_like' => '%trend%',
                    'search_numeric_id' => 0,
                ],
            ],
        ];
    }

    #[DataProvider('dataForSearchQuery')]
    public function testGetSearchQuery(array $data, string $expectedStr, array $expectedParams): void
    {
        $di = $this->getDi();

        $this->service->setDi($di);
        $result = $this->service->getSearchQuery($data);
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);

        $this->assertTrue(str_contains($result[0], $expectedStr), $result[0]);
        $this->assertEquals([], array_diff_key($result[1], $expectedParams));
    }

    public function testToApiArray(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());

        $systemService = $this->createMock(\Box\Mod\System\Service::class);
        $systemService->expects($this->atLeastOnce())
            ->method('getCompany');

        $subscriptionServiceMock = $this->createMock(ServiceSubscription::class);
        $subscriptionServiceMock->expects($this->atLeastOnce())
            ->method('isSubscribable')
            ->willReturn(true);

        $invoiceItemServiceMock = $this->createMock(ServiceInvoiceItem::class);

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
        $dbMock = $this->createMock('\Box_Database');
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

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($systemService, $subscriptionServiceMock, $invoiceItemServiceMock) {
            $service = null;
            if ($sub == 'InvoiceItem') {
                $service = $invoiceItemServiceMock;
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

    public function testOnAfterAdminInvoicePaymentReceived(): void
    {
        $serviceMock = $this->getMockBuilder(Service::class)
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

        $emailService = $this->createMock(\Box\Mod\Email\Service::class);
        $emailService->expects($this->atLeastOnce())
            ->method('sendTemplate');

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($invoiceModel);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailService, $serviceMock) {
            if ($serviceName == 'invoice') {
                return $serviceMock;
            }
            if ($serviceName == 'email') {
                return $emailService;
            }

            return null;
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

    public function testOnAfterAdminInvoiceReminderSent(): void
    {
        $serviceMock = $this->getMockBuilder(Service::class)
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

        $emailService = $this->createMock(\Box\Mod\Email\Service::class);
        $emailService->expects($this->atLeastOnce())
            ->method('sendTemplate');

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($invoiceModel);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailService, $serviceMock) {
            if ($serviceName === 'invoice') {
                return $serviceMock;
            }
            if ($serviceName === 'email') {
                return $emailService;
            }

            throw new \RuntimeException('Unexpected service request: ' . $serviceName);
        });
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $this->service->onAfterAdminInvoiceReminderSent($eventMock);
    }

    public function testOnAfterAdminInvoiceApprove(): void
    {
        $params = [
            'id' => 1,
            'total' => 10,
            'client' => [
                'id' => 2,
            ],
        ];

        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->willReturn($params);

        $emailService = $this->createMock(\Box\Mod\Email\Service::class);
        $emailService->expects($this->once())
            ->method('sendTemplate')
            ->with([
                'to_client' => 2,
                'code' => 'mod_invoice_created',
                'invoice' => $params,
            ]);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailService) {
            if ($serviceName === 'email') {
                return $emailService;
            }

            throw new \RuntimeException('Unexpected service request: ' . $serviceName);
        });

        $this->service->setDi($di);
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $result = $this->service->onAfterAdminInvoiceApprove($eventMock);
        $this->assertTrue($result);
    }

    public function testOnAfterAdminInvoiceApproveSkipsPaidInvoices(): void
    {
        $params = [
            'id' => 1,
            'total' => 10,
            'status' => \Model_Invoice::STATUS_PAID,
            'client' => [
                'id' => 2,
            ],
        ];

        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->willReturn($params);

        $emailService = $this->createMock(\Box\Mod\Email\Service::class);
        $emailService->expects($this->never())
            ->method('sendTemplate');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailService) {
            if ($serviceName === 'email') {
                return $emailService;
            }

            throw new \RuntimeException('Unexpected service request: ' . $serviceName);
        });

        $this->service->setDi($di);
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $result = $this->service->onAfterAdminInvoiceApprove($eventMock);
        $this->assertTrue($result);
    }

    public function testOnAfterAdminCronRun(): void
    {
        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->getMock();

        $remove_after_days = 64;
        $systemServiceMock = $this->createMock(\Box\Mod\System\Service::class);
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->with('remove_after_days')
            ->willReturn($remove_after_days);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('exec');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $this->service->onAfterAdminCronRun($eventMock);
    }

    public function testOnEventAfterInvoiceIsDue(): void
    {
        $serviceMock = $this->getMockBuilder(Service::class)
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

        $emailService = $this->createMock(\Box\Mod\Email\Service::class);
        $emailService->expects($this->atLeastOnce())
            ->method('sendTemplate');

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($invoiceModel);

        $di = $this->getDi();
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
        $serviceMock->onEventAfterInvoiceIsDue($eventMock);
    }

    public function testMarkAsPaid(): void
    {
        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['countIncome'])
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('countIncome');

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $invoiceModel->status = \Model_Invoice::STATUS_UNPAID;

        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());

        $itemInvoiceServiceMock = $this->createMock(ServiceInvoiceItem::class);
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('markAsPaid');
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('executeTask');

        $systemService = $this->createMock(\Box\Mod\System\Service::class);
        $systemService->expects($this->atLeastOnce())
            ->method('getParamValue');

        $currencyRepositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $currencyRepositoryMock->expects($this->atLeastOnce())
            ->method('getRateByCode')
            ->willReturn(1.0);

        $currencyServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)
            ->onlyMethods(['getCurrencyRepository'])->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())->method('getCurrencyRepository')
            ->willReturn($currencyRepositoryMock);

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$invoiceItemModel]);
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($systemService, $itemInvoiceServiceMock, $currencyServiceMock) {
            if ($serviceName == 'system') {
                return $systemService;
            }
            if ($sub == 'InvoiceItem') {
                return $itemInvoiceServiceMock;
            }
            if ($serviceName == 'currency') {
                return $currencyServiceMock;
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

    public function testCountIncome(): void
    {
        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['getTotal'])
            ->getMock();

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $invoiceModel->currency = 'USD';
        $invoiceModel->refund = 0;
        $currencyService = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)
            ->onlyMethods(['toBaseCurrency'])
            ->getMock();
        $currencyService->expects($this->atLeastOnce())
            ->method('toBaseCurrency')
            ->willReturn(0.0);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $currencyService);

        $serviceMock->setDi($di);
        $serviceMock->countIncome($invoiceModel);
    }

    public function testPrepareInvoiceCurrencyWasNotDefined(): void
    {
        $serviceMock = $this->getMockBuilder(Service::class)
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
            'approve' => true,
        ];

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $currencyModel = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();
        $defaultCurrencyCode = 'USD';
        $currencyModel->expects($this->any())
            ->method('getCode')
            ->willReturn($defaultCurrencyCode);

        $currencyRepositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $currencyRepositoryMock->expects($this->atLeastOnce())
            ->method('findDefault')
            ->willReturn($currencyModel);

        $currencyServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)
            ->onlyMethods(['getCurrencyRepository'])
            ->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())
            ->method('getCurrencyRepository')
            ->willReturn($currencyRepositoryMock);

        $itemInvoiceServiceMock = $this->createMock(ServiceInvoiceItem::class);
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('addNew');

        $newRecordId = 1;
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($newRecordId);

        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($invoiceModel);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($currencyServiceMock, $itemInvoiceServiceMock) {
            if ($serviceName == 'currency') {
                return $currencyServiceMock;
            }
            if ($sub == 'InvoiceItem') {
                return $itemInvoiceServiceMock;
            }
        });
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);
        $result = $serviceMock->prepareInvoice($clientModel, $data);
        $this->assertInstanceOf('Model_Invoice', $result);
        $this->assertSame($result->currency, $defaultCurrencyCode);
    }

    public function testSetInvoiceDefaults(): void
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
        $clientService = $this->createMock(\Box\Mod\Client\Service::class);
        $clientService->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn($buyer);

        $systemService = $this->createMock(\Box\Mod\System\Service::class);
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

        $serviceTaxMock = $this->createMock(ServiceTax::class);
        $serviceTaxMock->expects($this->atLeastOnce())
            ->method('getTaxRateForClient');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($clientModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = $this->getDi();
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

    public function testApproveInvoice(): void
    {
        $data['use_credits'] = true;

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $invoiceModel->status = \Model_Invoice::STATUS_UNPAID;

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['tryPayWithCredits', 'toApiArray'])
            ->getMock();

        $serviceMock->expects($this->once())
            ->method('tryPayWithCredits')
            ->willReturnCallback(function () use ($invoiceModel): void {
                $invoiceModel->status = \Model_Invoice::STATUS_PAID;
            });

        $serviceMock->expects($this->exactly(2))
            ->method('toApiArray')
            ->willReturnCallback(fn (): array => [
                'id' => 1,
                'status' => $invoiceModel->status,
            ]);

        $events = [];
        $eventManagerMock = $this->createMock('\Box_EventManager');
        $eventManagerMock->expects($this->exactly(2))
            ->method('fire')
            ->willReturnCallback(function (array $event) use (&$events): void {
                $events[] = $event;
            });

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventManagerMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->approveInvoice($invoiceModel, $data);
        $this->assertTrue($result);
        $this->assertSame('onBeforeAdminInvoiceApprove', $events[0]['event']);
        $this->assertSame('onAfterAdminInvoiceApprove', $events[1]['event']);
        $this->assertSame(\Model_Invoice::STATUS_PAID, $events[1]['params']['status']);
    }

    public function testGetTotalWithTax(): void
    {
        $total = 10.0;
        $tax = 2.2;
        $expected = $total + $tax;
        $serviceMock = $this->getMockBuilder(Service::class)
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

    public function testGetTotal(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$invoiceItemModel]);

        $itemInvoiceServiceMock = $this->createMock(ServiceInvoiceItem::class);

        $itemTotal = 10.0;
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('getTotal')
            ->willReturn($itemTotal);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $itemInvoiceServiceMock);

        $this->service->setDi($di);
        $result = $this->service->getTotal($invoiceModel);
        $this->assertIsFloat($result);
        $this->assertEquals($itemTotal, $result);
    }

    public function testRefundInvoiceWithNegativeInvoiceLogic(): void
    {
        $newId = 1;
        $total = 10.0;
        $tax = 2.2;
        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['getTotal', 'getTax', 'countIncome', 'addNote', 'toApiArray'])
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
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn(['id' => $newId]);

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $invoiceModel->id = $newId;

        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());

        $eventManagerMock = $this->createMock('\Box_EventManager');
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $systemService = $this->createMock(\Box\Mod\System\Service::class);
        $systemService->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->willReturn('negative_invoice');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturnOnConsecutiveCalls($invoiceModel, $invoiceItemModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$invoiceItemModel]);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemService);
        $di['events_manager'] = $eventManagerMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);
        $result = $serviceMock->refundInvoice($invoiceModel, 'customNote');
        $this->assertIsInt($result);
        $this->assertEquals($newId, $result);
    }

    public function testUpdateInvoice(): void
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

        $eventManagerMock = $this->createMock('\Box_EventManager');
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $itemInvoiceServiceMock = $this->createMock(ServiceInvoiceItem::class);
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('addNew');
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('update');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($invoiceItemModel);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $itemInvoiceServiceMock);
        $di['events_manager'] = $eventManagerMock;
        $di['logger'] = new \Box_Log();

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['toApiArray'])
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn(['id' => 1]);

        $serviceMock->setDi($di);

        $result = $serviceMock->updateInvoice($invoiceModel, $data);
        $this->assertTrue($result);
    }

    public function testRmInvoice(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('exec');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$invoiceItemModel]);
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->rmInvoice($invoiceModel);
        $this->assertTrue($result);
    }

    public function testDeleteInvoiceByAdmin(): void
    {
        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['rmInvoice'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('rmInvoice');

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $eventManagerMock = $this->createMock('\Box_EventManager');
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = $this->getDi();
        $di['events_manager'] = $eventManagerMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->deleteInvoiceByAdmin($invoiceModel);
        $this->assertTrue($result);
    }

    public function testRenewInvoice(): void
    {
        $newId = 2;
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $invoiceModel->id = $newId;

        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['generateForOrder', 'approveInvoice'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('approveInvoice');
        $serviceMock->expects($this->once())
            ->method('generateForOrder')
            ->willReturn($invoiceModel);

        $eventManagerMock = $this->createMock('\Box_EventManager');
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = $this->getDi();
        $di['events_manager'] = $eventManagerMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);
        $result = $serviceMock->renewInvoice($clientOrder, []);
        $this->assertIsInt($result);
        $this->assertEquals($newId, $result);
    }

    public function testDoBatchPayWithCredits(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['findAllUnpaid', 'tryPayWithCredits'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('findAllUnpaid')
            ->willReturn([[]]);
        $serviceMock->expects($this->atLeastOnce())
            ->method('tryPayWithCredits');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($invoiceModel);

        $di = $this->getDi();
        $di['logger'] = new \Box_Log();
        $di['db'] = $dbMock;

        $serviceMock->setDi($di);
        $result = $serviceMock->doBatchPayWithCredits([]);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testPayInvoiceWithCredits(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['tryPayWithCredits'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('tryPayWithCredits');

        $di = $this->getDi();
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);
        $result = $serviceMock->payInvoiceWithCredits($invoiceModel);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testGenerateForOrderInvoiceIsCreatedAlready(): void
    {
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \DummyBean());
        $clientOrder->unpaid_invoice_id = 2;

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($invoiceModel);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->generateForOrder($clientOrder);
        $this->assertInstanceOf('\Model_Invoice', $result);
    }

    public function testGenerateForOrder(): void
    {
        $serviceMock = $this->getMockBuilder(Service::class)
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

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($clientModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($invoiceModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $invoiceItemServiceMock = $this->getMockBuilder(ServiceInvoiceItem::class)
            ->getMock();
        $invoiceItemServiceMock->expects($this->atLeastOnce())
            ->method('generateFromOrder');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $invoiceItemServiceMock);

        $serviceMock->setDi($di);
        $result = $serviceMock->generateForOrder($orderModel);
        $this->assertInstanceOf('\Model_Invoice', $result);
    }

    public function testGenerateForOrderUsesRenewalPricingForActiveDomainOrders(): void
    {
        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['setInvoiceDefaults'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('setInvoiceDefaults');

        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());
        $orderModel->client_id = 1;
        $orderModel->product_id = 2;
        $orderModel->currency = 'USD';
        $orderModel->price = 33;
        $orderModel->quantity = 1;
        $orderModel->status = \Model_ClientOrder::STATUS_ACTIVE;
        $orderModel->config = json_encode([
            'action' => 'register',
            'register_tld' => '.com',
            'register_years' => 2,
            'period' => '2Y',
        ]);

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $productTable = $this->getMockBuilder(\Model_ProductDomainTable::class)
            ->onlyMethods(['getRenewalLineConfig'])
            ->getMock();
        $productTable->expects($this->once())
            ->method('getRenewalLineConfig')
            ->willReturn([
                'price' => 20.0,
                'quantity' => 2,
            ]);

        $productModel = $this->getMockBuilder(\Model_Product::class)
            ->onlyMethods(['getTable'])
            ->getMock();
        $productModel->expects($this->once())
            ->method('getTable')
            ->willReturn($productTable);

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->exactly(2))
            ->method('getExistingModelById')
            ->willReturnOnConsecutiveCalls($clientModel, $productModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($invoiceModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $currencyRepository = $this->getMockBuilder(\Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $currencyRepository->expects($this->once())
            ->method('getRateByCode')
            ->with('USD')
            ->willReturn(1.0);

        $currencyService = $this->getMockBuilder(\Box\Mod\Currency\Service::class)
            ->onlyMethods(['getCurrencyRepository'])
            ->getMock();
        $currencyService->expects($this->once())
            ->method('getCurrencyRepository')
            ->willReturn($currencyRepository);

        $invoiceItemServiceMock = $this->getMockBuilder(ServiceInvoiceItem::class)
            ->onlyMethods(['generateFromOrder'])
            ->getMock();
        $invoiceItemServiceMock->expects($this->once())
            ->method('generateFromOrder')
            ->with(
                $this->identicalTo($invoiceModel),
                $this->identicalTo($orderModel),
                \Model_InvoiceItem::TASK_RENEW,
                20.0,
                [
                    'price' => 20.0,
                    'quantity' => 2,
                ]
            );

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function (string $service, ?string $sub = null) use ($currencyService, $invoiceItemServiceMock) {
            if ($service === 'Currency') {
                return $currencyService;
            }

            if ($service === 'Invoice' && $sub === 'InvoiceItem') {
                return $invoiceItemServiceMock;
            }

            throw new \RuntimeException('Unexpected service request');
        });

        $serviceMock->setDi($di);
        $result = $serviceMock->generateForOrder($orderModel);
        $this->assertInstanceOf('\Model_Invoice', $result);
    }

    public function testGenerateForOrderAmountIsZero(): void
    {
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \DummyBean());
        $clientOrder->price = 0;

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Invoices are not generated for 0 amount orders');
        $this->service->generateForOrder($clientOrder);
    }

    public function testGenerateInvoicesForExpiringOrdersNoExpOrders(): void
    {
        $orderService = $this->createMock(\Box\Mod\Order\Service::class);
        $orderService->expects($this->atLeastOnce())
            ->method('getSoonExpiringActiveOrders')
            ->willReturn([]);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderService);

        $this->service->setDi($di);
        $result = $this->service->generateInvoicesForExpiringOrders();
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testGenerateInvoicesForExpiringOrders(): void
    {
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \DummyBean());

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $newId = 4;
        $invoiceModel->id = $newId;

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['generateForOrder', 'approveInvoice'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('approveInvoice');
        $serviceMock->expects($this->once())
            ->method('generateForOrder')
            ->willReturn($invoiceModel);

        $orderService = $this->createMock(\Box\Mod\Order\Service::class);
        $orderService->expects($this->atLeastOnce())
            ->method('getSoonExpiringActiveOrders')
            ->willReturn([[]]);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($clientOrder);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderService);
        $di['logger'] = new \Box_Log();
        $di['db'] = $dbMock;

        $serviceMock->setDi($di);
        $result = $serviceMock->generateInvoicesForExpiringOrders();
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testDoBatchPaidInvoiceActivation(): void
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());

        $itemInvoiceServiceMock = $this->createMock(ServiceInvoiceItem::class);
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('executeTask')
            ->with($invoiceItemModel);
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('getAllNotExecutePaidItems')
            ->willReturn([[]]);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($invoiceItemModel);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $itemInvoiceServiceMock);
        $di['logger'] = new \Box_Log();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->doBatchPaidInvoiceActivation();
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testDoBatchPaidInvoiceActivationException(): void
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());

        $itemInvoiceServiceMock = $this->createMock(ServiceInvoiceItem::class);
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('executeTask')
            ->with($invoiceItemModel)
            ->willThrowException(new \FOSSBilling\Exception('testing exception..'));
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('getAllNotExecutePaidItems')
            ->willReturn([[]]);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($invoiceItemModel);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $itemInvoiceServiceMock);
        $di['logger'] = new \Box_Log();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->doBatchPaidInvoiceActivation();
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testDoBatchRemindersSend(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['getUnpaidInvoicesLateFor', 'sendInvoiceReminder'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('sendInvoiceReminder');
        $serviceMock->expects($this->once())
            ->method('getUnpaidInvoicesLateFor')
            ->willReturn([$invoiceModel]);

        $eventManagerMock = $this->createMock('\Box_EventManager');
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = $this->getDi();
        $di['events_manager'] = $eventManagerMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);
        $result = $serviceMock->doBatchRemindersSend();
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testDoBatchInvokeDueEvent(): void
    {
        $systemService = $this->createMock(\Box\Mod\System\Service::class);
        $systemService->expects($this->atLeastOnce())
            ->method('getParamValue');
        $systemService->expects($this->atLeastOnce())
            ->method('setParamValue');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn([[]]);

        $eventManagerMock = $this->createMock('\Box_EventManager');
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventManagerMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemService);
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->doBatchInvokeDueEvent([]);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testSendInvoiceReminderProtectionFromAccidentalReminders(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $invoiceModel->status = \Model_Invoice::STATUS_PAID;

        $result = $this->service->sendInvoiceReminder($invoiceModel);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testSendInvoiceReminder(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $eventManagerMock = $this->createMock('\Box_EventManager');
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventManagerMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->sendInvoiceReminder($invoiceModel);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testCounter(): void
    {
        $sqlResult = [
            ['status' => \Model_Invoice::STATUS_PAID,
                'counter' => 2],
        ];
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn($sqlResult);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->counter();
        $this->assertIsArray($result);
    }

    public function testGenerateFundsInvoiceNoActiveOrder(): void
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('You must have at least one active order before you can add funds so you cannot proceed at the current time!');
        $this->service->generateFundsInvoice($clientModel, 10);
    }

    public function testGenerateFundsInvoiceMinAmountLimit(): void
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $clientModel->currency = 'EUR';
        $fundsAmount = 2;

        $minAmount = 10;
        $maxAmount = 50;
        $systemService = $this->createMock(\Box\Mod\System\Service::class);
        $systemService->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->willReturnOnConsecutiveCalls($minAmount, $maxAmount);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemService);

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(981);
        $this->expectExceptionMessage('Amount must be at least ' . $minAmount);
        $this->service->generateFundsInvoice($clientModel, $fundsAmount);
    }

    public function testGenerateFundsInvoiceMaxAmountLimit(): void
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $clientModel->currency = 'EUR';
        $fundsAmount = 200;

        $minAmount = 10;
        $maxAmount = 50;
        $systemService = $this->createMock(\Box\Mod\System\Service::class);
        $systemService->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->willReturnOnConsecutiveCalls($minAmount, $maxAmount);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemService);

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(982);
        $this->expectExceptionMessage('Amount cannot exceed ' . $maxAmount);
        $this->service->generateFundsInvoice($clientModel, $fundsAmount);
    }

    public function testGenerateFundsInvoice(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $clientModel->currency = 'EUR';
        $fundsAmount = 20;

        $minAmount = 10;
        $maxAmount = 50;

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['setInvoiceDefaults'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('setInvoiceDefaults');

        $systemService = $this->createMock(\Box\Mod\System\Service::class);
        $systemService->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->willReturnOnConsecutiveCalls($minAmount, $maxAmount, true);

        $itemInvoiceServiceMock = $this->createMock(ServiceInvoiceItem::class);
        $itemInvoiceServiceMock->expects($this->atLeastOnce())
            ->method('generateForAddFunds');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($invoiceModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = $this->getDi();
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

    public function testProcessInvoiceInvoiceNotFound(): void
    {
        $data = [
            'hash' => 'hashString',
            'gateway_id' => 2,
        ];

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(null);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(812);
        $this->expectExceptionMessage('Invoice not found');
        $this->service->processInvoice($data);
    }

    public function testProcessInvoicePayGatewayNotFound(): void
    {
        $data = [
            'hash' => 'hashString',
            'gateway_id' => 2,
        ];

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($invoiceModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn(null);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(813);
        $this->expectExceptionMessage('Payment method not found');
        $this->service->processInvoice($data);
    }

    public function testProcessInvoicePayGatewayNotEnabled(): void
    {
        $data = [
            'hash' => 'hashString',
            'gateway_id' => 2,
        ];

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($invoiceModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($payGatewayModel);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(814);
        $this->expectExceptionMessage('Payment method not enabled');
        $this->service->processInvoice($data);
    }

    public function testProcessInvoice(): void
    {
        $serviceMock = $this->getMockBuilder(Service::class)
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

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($invoiceModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($payGatewayModel);

        $subcribeService = $this->createMock(ServiceSubscription::class);
        $subcribeService->expects($this->atLeastOnce())
            ->method('isSubscribable')
            ->willReturn(true);

        $payGatewayService = $this->createMock(ServicePayGateway::class);
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

        $di = $this->getDi();
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

    public function testAddNote(): void
    {
        $note = 'test Note';

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->addNote($invoiceModel, $note);
        $this->assertTrue($result);
    }

    public function testFindAllUnpaid(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
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

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->findAllUnpaid();
        $this->assertIsArray($result);
    }

    public function testFindAllPaid(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$invoiceModel]);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->findAllPaid();
        $this->assertIsArray($result);
        $this->assertInstanceOf('\Model_Invoice', $result[0]);
    }

    public function testGetUnpaidInvoicesLateFor(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$invoiceModel]);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getUnpaidInvoicesLateFor();
        $this->assertIsArray($result);
        $this->assertInstanceOf('\Model_Invoice', $result[0]);
    }

    public function testGetBuyer(): void
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

    public function testIsInvoiceTypeDeposit(): void
    {
        $di = $this->getDi();

        $modelInvoiceItem = new \Model_InvoiceItem();
        $modelInvoiceItem->loadBean(new \DummyBean());
        $modelInvoiceItem->type = \Model_InvoiceItem::TYPE_DEPOSIT;

        $invoiceItems = [$modelInvoiceItem];

        $dbMock = $this->createMock('\Box_Database');
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

    public function testIsInvoiceTypeDepositTypeIsNotDeposit(): void
    {
        $di = $this->getDi();

        $modelInvoiceItem = new \Model_InvoiceItem();
        $modelInvoiceItem->loadBean(new \DummyBean());
        $modelInvoiceItem->type = \Model_InvoiceItem::TYPE_ORDER;

        $invoiceItems = [$modelInvoiceItem];

        $dbMock = $this->createMock('\Box_Database');
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

    public function testIsInvoiceTypeDepositEmptyArray(): void
    {
        $di = $this->getDi();

        $invoiceItems = [];

        $dbMock = $this->createMock('\Box_Database');
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

    public static function dataForValidatePaymentAmount(): array
    {
        return [
            'exact match passes'           => [10.00, 10.00, false],
            'overpayment passes'           => [10.05, 10.00, false],
            'within epsilon passes'        => [9.995, 10.00, false],
            'exactly at epsilon passes'    => [9.99, 10.00, false],
            'one cent under fails'         => [9.98, 10.00, true],
            'large underpayment fails'     => [5.00, 10.00, true],
        ];
    }

    #[DataProvider('dataForValidatePaymentAmount')]
    public function testValidatePaymentAmount(float $received, float $expected, bool $expectException): void
    {
        if ($expectException) {
            $this->expectException(\FOSSBilling\Exception::class);
        }

        $this->service->validatePaymentAmount($received, $expected);

        if (!$expectException) {
            $this->assertTrue(true); // no exception means pass
        }
    }
}
