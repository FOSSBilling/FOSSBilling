<?php

declare(strict_types=1);

namespace Box\Mod\Invoice;

use Dompdf\Dompdf;
use Dompdf\Options;
use FOSSBilling\Twig\TwigFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as TwigEnvironment;

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    protected ?Service $service;

    public function setUp(): void
    {
        $this->service = new Service();
    }

    private function getMockSystemServiceForAuth(): \Box\Mod\System\Service
    {
        $systemService = $this->createMock(\Box\Mod\System\Service::class);
        $systemService->method('getParamValue')
            ->willReturnCallback(static function (string $param, mixed $default = null): mixed {
                if ($param === 'invoice_accessible_from_hash') {
                    return '0';
                }

                return $default;
            });

        return $systemService;
    }

    private function getMockUnauthenticatedAuth(): \Box_Authorization
    {
        $auth = $this->createMock(\Box_Authorization::class);
        $auth->method('isAdminLoggedIn')->willReturn(false);
        $auth->method('isClientLoggedIn')->willReturn(false);

        return $auth;
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
                    'approved' => 1,
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
            ->method('getSubscriptionPeriod')
            ->with($invoiceModel)
            ->willReturn('1W');

        $invoiceItemServiceMock = $this->createStub(ServiceInvoiceItem::class);

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
        $di['period'] = $di->protect(fn (string $code): \PHPUnit\Framework\MockObject\MockObject => $periodMock);

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
            if ($serviceName === 'invoice') {
                return $serviceMock;
            }
            if ($serviceName === 'email') {
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
            ->onlyMethods(['toApiArray', 'extendInvoiceHashLifetime'])
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
        $serviceMock->expects($this->atLeastOnce())
            ->method('extendInvoiceHashLifetime');

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

        $invoiceService = $this->getMockBuilder(Service::class)
            ->onlyMethods(['extendInvoiceHashLifetime'])
            ->getMock();
        $invoiceService->expects($this->atLeastOnce())
            ->method('extendInvoiceHashLifetime');

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->with('Invoice', 1)
            ->willReturn($invoiceModel);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailService, $invoiceService) {
            if ($serviceName === 'email') {
                return $emailService;
            }
            if ($serviceName === 'invoice') {
                return $invoiceService;
            }

            throw new \RuntimeException('Unexpected service request: ' . $serviceName);
        });
        $di['db'] = $dbMock;

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

        $invoiceService = $this->getMockBuilder(Service::class)
            ->onlyMethods(['extendInvoiceHashLifetime'])
            ->getMock();
        $invoiceService->expects($this->atLeastOnce())
            ->method('extendInvoiceHashLifetime');

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($invoiceModel);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailService, $invoiceService) {
            if ($serviceName === 'email') {
                return $emailService;
            }
            if ($serviceName === 'invoice') {
                return $invoiceService;
            }

            throw new \RuntimeException('Unexpected service request: ' . $serviceName);
        });
        $di['db'] = $dbMock;

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

            return null;
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

    public function testMarkAsPaidByAdminUsesGatewayOverrideAndDoesNotChargeBalance(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $invoiceModel->gateway_id = null;

        $gatewayModel = new \Model_PayGateway();
        $gatewayModel->loadBean(new \DummyBean());
        $gatewayModel->id = 7;
        $gatewayModel->gateway = 'PayPal';
        $gatewayModel->enabled = 1;

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['markAsPaid'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('markAsPaid')
            ->with($invoiceModel, false, true)
            ->willReturn(true);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->once())
            ->method('store')
            ->with($invoiceModel);
        $dbMock->expects($this->once())
            ->method('getExistingModelById')
            ->with('PayGateway', 7, 'Payment gateway not found')
            ->willReturn($gatewayModel);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->markAsPaidByAdmin($invoiceModel, [
            'gateway_id' => 7,
            'execute' => true,
        ]);

        $this->assertTrue($result);
        $this->assertSame(7, $invoiceModel->gateway_id);
    }

    public function testMarkAsPaidByAdminProcessesTrustedCustomTransaction(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $invoiceModel->id = 42;
        $invoiceModel->gateway_id = 5;
        $invoiceModel->currency = 'USD';

        $gatewayModel = new \Model_PayGateway();
        $gatewayModel->loadBean(new \DummyBean());
        $gatewayModel->id = 5;
        $gatewayModel->gateway = 'Custom';
        $gatewayModel->enabled = 1;

        $transactionServiceMock = $this->createMock(ServiceTransaction::class);
        $transactionServiceMock->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $payload) use ($invoiceModel): bool {
                $this->assertSame($invoiceModel->id, $payload['invoice_id']);
                $this->assertSame($invoiceModel->gateway_id, $payload['gateway_id']);
                $this->assertSame($invoiceModel->currency, $payload['currency']);
                $this->assertSame('received', $payload['status']);
                $this->assertSame('admin', $payload['source']);
                $this->assertSame('manual-txn-1', $payload['txn_id']);

                return true;
            }))
            ->willReturn(99);
        $transactionServiceMock->expects($this->once())
            ->method('processTransaction')
            ->with(99)
            ->willReturn(true);

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['markAsPaid'])
            ->getMock();
        $serviceMock->expects($this->never())
            ->method('markAsPaid');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->once())
            ->method('getExistingModelById')
            ->with('PayGateway', 5, 'Payment gateway not found')
            ->willReturn($gatewayModel);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $di['mod_service'] = $di->protect(fn (string $serviceName, string $sub = ''): \PHPUnit\Framework\MockObject\MockObject => match ([$serviceName, $sub]) {
            ['Invoice', 'Transaction'] => $transactionServiceMock,
            default => throw new \RuntimeException('Unexpected service request'),
        });

        $serviceMock->setDi($di);

        $this->assertTrue($serviceMock->markAsPaidByAdmin($invoiceModel, [
            'transactionId' => '  manual-txn-1  ',
        ]));
        $this->assertSame(5, $invoiceModel->gateway_id);
    }

    public function testMarkAsPaidByAdminReturnsBoolWhenCustomGatewayProcessingReturnsNonBool(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $invoiceModel->id = 42;
        $invoiceModel->gateway_id = 5;
        $invoiceModel->currency = 'USD';

        $gatewayModel = new \Model_PayGateway();
        $gatewayModel->loadBean(new \DummyBean());
        $gatewayModel->id = 5;
        $gatewayModel->gateway = 'Custom';
        $gatewayModel->enabled = 1;

        $transactionServiceMock = $this->createMock(ServiceTransaction::class);
        $transactionServiceMock->expects($this->once())
            ->method('create')
            ->willReturn(99);
        $transactionServiceMock->expects($this->once())
            ->method('processTransaction')
            ->with(99)
            ->willReturn(99);

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['markAsPaid'])
            ->getMock();
        $serviceMock->expects($this->never())
            ->method('markAsPaid');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->once())
            ->method('getExistingModelById')
            ->with('PayGateway', 5, 'Payment gateway not found')
            ->willReturn($gatewayModel);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $di['mod_service'] = $di->protect(fn (string $serviceName, string $sub = ''): \PHPUnit\Framework\MockObject\MockObject => match ([$serviceName, $sub]) {
            ['Invoice', 'Transaction'] => $transactionServiceMock,
            default => throw new \RuntimeException('Unexpected service request'),
        });

        $serviceMock->setDi($di);

        $this->assertTrue($serviceMock->markAsPaidByAdmin($invoiceModel, [
            'transactionId' => 'manual-txn-2',
        ]));
    }

    public function testMarkAsPaidByAdminRequiresTransactionIdForCustomGateway(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $invoiceModel->gateway_id = 5;

        $gatewayModel = new \Model_PayGateway();
        $gatewayModel->loadBean(new \DummyBean());
        $gatewayModel->gateway = 'Custom';
        $gatewayModel->enabled = 1;

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['markAsPaid'])
            ->getMock();
        $serviceMock->expects($this->never())
            ->method('markAsPaid');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->once())
            ->method('getExistingModelById')
            ->with('PayGateway', 5, 'Payment gateway not found')
            ->willReturn($gatewayModel);
        $dbMock->expects($this->never())
            ->method('store');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $this->expectException(\FOSSBilling\InformationException::class);
        $this->expectExceptionMessage('Transaction ID is required when using the Custom payment gateway.');
        $serviceMock->markAsPaidByAdmin($invoiceModel, []);
    }

    public function testMarkAsPaidByAdminReturnsEarlyForAlreadyPaidInvoice(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $invoiceModel->id = 42;
        $invoiceModel->status = \Model_Invoice::STATUS_PAID;
        $invoiceModel->gateway_id = 5;

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['markAsPaid'])
            ->getMock();
        $serviceMock->expects($this->never())
            ->method('markAsPaid');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->never())
            ->method('getExistingModelById');
        $dbMock->expects($this->never())
            ->method('store');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $this->assertTrue($serviceMock->markAsPaidByAdmin($invoiceModel, [
            'gateway_id' => 5,
        ]));
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
            ->willReturnOnConsecutiveCalls($productModel, $clientModel);
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

    public function testGenerateForOrderUsesRenewalPricingWhenStoredDomainOrderPriceIsZero(): void
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
        $orderModel->currency = 'EUR';
        $orderModel->price = 0;
        $orderModel->quantity = 1;
        $orderModel->status = \Model_ClientOrder::STATUS_ACTIVE;
        $orderModel->config = json_encode([
            'action' => 'register',
            'register_tld' => '.com',
            'register_years' => 1,
            'period' => '1Y',
        ]);

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $productTable = $this->getMockBuilder(\Model_ProductDomainTable::class)
            ->onlyMethods(['getRenewalLineConfig'])
            ->getMock();
        $productTable->expects($this->once())
            ->method('getRenewalLineConfig')
            ->willReturn([
                'price' => 10.0,
                'quantity' => 1,
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
            ->willReturnOnConsecutiveCalls($productModel, $clientModel);
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
            ->with('EUR')
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
                10.0,
                [
                    'price' => 10.0,
                    'quantity' => 1,
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
        $di['mod_service'] = $di->protect(fn ($serviceName): ?\Box\Mod\System\Service => $serviceName === 'system' ? $this->getMockSystemServiceForAuth() : null);
        $di['auth'] = $this->getMockUnauthenticatedAuth();

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
        $di['mod_service'] = $di->protect(fn ($serviceName): ?\Box\Mod\System\Service => $serviceName === 'system' ? $this->getMockSystemServiceForAuth() : null);
        $di['auth'] = $this->getMockUnauthenticatedAuth();

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
            if ($serviceName === 'system') {
                return $this->getMockSystemServiceForAuth();
            }
        });
        $di['api_admin'] = new \Api_Handler(new \Model_Admin());
        $di['logger'] = new \Box_Log();
        $di['auth'] = $this->getMockUnauthenticatedAuth();

        $serviceMock->setDi($di);
        $result = $serviceMock->processInvoice($data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('service_url', $result);
        $this->assertArrayHasKey('subscription', $result);
        $this->assertArrayHasKey('result', $result);
    }

    public function testProcessInvoiceSinglePaymentsDisabled(): void
    {
        $data = [
            'hash' => 'hashString',
            'gateway_id' => 2,
        ];

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());
        $payGatewayModel->enabled = true;
        $payGatewayModel->allow_recurrent = 0;
        $payGatewayModel->allow_single = 0;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($invoiceModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($payGatewayModel);

        $subscribeService = $this->createMock(ServiceSubscription::class);
        $subscribeService->expects($this->atLeastOnce())
            ->method('isSubscribable')
            ->willReturn(false);

        $payGatewayService = $this->createMock(ServicePayGateway::class);
        $payGatewayService->expects($this->atLeastOnce())
            ->method('canPerformSinglePayment')
            ->willReturn(false);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($payGatewayService, $subscribeService) {
            if ($sub == 'PayGateway') {
                return $payGatewayService;
            }
            if ($sub == 'Subscription') {
                return $subscribeService;
            }
            if ($serviceName === 'system') {
                return $this->getMockSystemServiceForAuth();
            }
        });
        $di['api_admin'] = new \Api_Handler(new \Model_Admin());
        $di['auth'] = $this->getMockUnauthenticatedAuth();

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(815);
        $this->expectExceptionMessage('One-time payments are not enabled for the selected payment gateway');
        $this->service->processInvoice($data);
    }

    public function testProcessInvoiceSubscribableStillAllowsSingleBlockedGateway(): void
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
        $payGatewayModel->allow_recurrent = 1;
        $payGatewayModel->allow_single = 0;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($invoiceModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($payGatewayModel);

        $subscribeService = $this->createMock(ServiceSubscription::class);
        $subscribeService->expects($this->atLeastOnce())
            ->method('isSubscribable')
            ->willReturn(true);

        $payGatewayService = $this->createMock(ServicePayGateway::class);
        $payGatewayService->expects($this->atLeastOnce())
            ->method('canPerformRecurrentPayment')
            ->willReturn(true);
        // canPerformSinglePayment must NOT be called when the subscription path is taken
        $payGatewayService->expects($this->never())
            ->method('canPerformSinglePayment');

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
        $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($payGatewayService, $subscribeService) {
            if ($sub == 'PayGateway') {
                return $payGatewayService;
            }
            if ($sub == 'Subscription') {
                return $subscribeService;
            }
            if ($serviceName === 'system') {
                return $this->getMockSystemServiceForAuth();
            }
        });
        $di['api_admin'] = new \Api_Handler(new \Model_Admin());
        $di['logger'] = new \Box_Log();
        $di['auth'] = $this->getMockUnauthenticatedAuth();

        $serviceMock->setDi($di);

        $result = $serviceMock->processInvoice($data);
        $this->assertIsArray($result);
        $this->assertTrue($result['subscription']);
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

    public function testCheckInvoiceAuthAllowsAdminEvenWhenExpired(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $invoiceModel->client_id = 5;
        $invoiceModel->hash_expires_at = date('Y-m-d H:i:s', strtotime('-1 day'));

        $auth = $this->createMock(\Box_Authorization::class);
        $auth->method('isAdminLoggedIn')->willReturn(true);
        $auth->method('isClientLoggedIn')->willReturn(false);

        $di = $this->getDi();
        $di['auth'] = $auth;
        $this->service->setDi($di);

        $this->service->checkInvoiceAuth($invoiceModel, InvoiceOperation::READ);
        $this->addToAssertionCount(1);
    }

    public function testCheckInvoiceAuthAllowsOwnerEvenWhenExpired(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $invoiceModel->client_id = 5;
        $invoiceModel->hash_expires_at = date('Y-m-d H:i:s', strtotime('-1 day'));

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $clientModel->id = 5;

        $auth = $this->createMock(\Box_Authorization::class);
        $auth->method('isAdminLoggedIn')->willReturn(false);
        $auth->method('isClientLoggedIn')->willReturn(true);

        $di = $this->getDi();
        $di['auth'] = $auth;
        $di['loggedin_client'] = $clientModel;
        $di['mod_service'] = $di->protect(fn ($serviceName) => $serviceName === 'system' ? $this->getMockSystemServiceForAuth() : null);
        $this->service->setDi($di);

        $this->service->checkInvoiceAuth($invoiceModel, InvoiceOperation::READ);
        $this->addToAssertionCount(1);
    }

    public function testCheckInvoiceAuthAllowsGuestWhenNotExpired(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $invoiceModel->client_id = 5;
        $invoiceModel->hash_expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));

        $systemService = $this->createMock(\Box\Mod\System\Service::class);
        $systemService->method('getParamValue')
            ->willReturnCallback(static function (string $param, mixed $default = null): mixed {
                if ($param === 'invoice_accessible_from_hash') {
                    return '1';
                }

                return $default;
            });

        $di = $this->getDi();
        $di['auth'] = $this->getMockUnauthenticatedAuth();
        $di['mod_service'] = $di->protect(fn ($serviceName) => $serviceName === 'system' ? $systemService : null);
        $this->service->setDi($di);

        $this->service->checkInvoiceAuth($invoiceModel, InvoiceOperation::READ);
        $this->addToAssertionCount(1);
    }

    public function testCheckInvoiceAuthAllowsGuestWhenNoExpirationSet(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $invoiceModel->client_id = 5;
        $invoiceModel->hash_expires_at = null;

        $systemService = $this->createMock(\Box\Mod\System\Service::class);
        $systemService->method('getParamValue')
            ->willReturnCallback(static function (string $param, mixed $default = null): mixed {
                if ($param === 'invoice_accessible_from_hash') {
                    return '1';
                }

                return $default;
            });

        $di = $this->getDi();
        $di['auth'] = $this->getMockUnauthenticatedAuth();
        $di['mod_service'] = $di->protect(fn ($serviceName) => $serviceName === 'system' ? $systemService : null);
        $this->service->setDi($di);

        $this->service->checkInvoiceAuth($invoiceModel, InvoiceOperation::READ);
        $this->addToAssertionCount(1);
    }

    public function testComputeHashExpirationReturnsFutureDateByDefault(): void
    {
        $systemService = $this->createMock(\Box\Mod\System\Service::class);
        $systemService->method('getParamValue')
            ->with('invoice_hash_lifetime_days', '90')
            ->willReturn('90');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn ($serviceName) => $serviceName === 'system' ? $systemService : null);
        $this->service->setDi($di);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('computeHashExpiration');
        $result = $method->invoke($this->service);

        $this->assertNotNull($result);
        $this->assertGreaterThan(time(), strtotime($result));
    }

    public function testComputeHashExpirationReturnsNullWhenLifetimeZero(): void
    {
        $systemService = $this->createMock(\Box\Mod\System\Service::class);
        $systemService->method('getParamValue')
            ->with('invoice_hash_lifetime_days', '90')
            ->willReturn('0');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn ($serviceName) => $serviceName === 'system' ? $systemService : null);
        $this->service->setDi($di);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('computeHashExpiration');
        $result = $method->invoke($this->service);

        $this->assertNull($result);
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
            'exact match passes' => [10.00, 10.00, false],
            'overpayment passes' => [10.05, 10.00, false],
            'within epsilon passes' => [9.995, 10.00, false],
            'exactly at epsilon passes' => [9.99, 10.00, false],
            'one cent under fails' => [9.98, 10.00, true],
            'large underpayment fails' => [5.00, 10.00, true],
        ];
    }

    #[DataProvider('dataForValidatePaymentAmount')]
    public function testValidatePaymentAmount(float $received, float $expected, bool $expectException): void
    {
        if ($expectException) {
            $this->expectException(\FOSSBilling\Exception::class);
        }

        $this->service->validatePaymentAmount($received, $expected);
        $this->assertTrue(true);
    }

    public function testGeneratePdfReturnsInlinePdfResponse(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $invoiceModel->client_id = 1;
        $invoiceModel->currency = 'USD';

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->once())
            ->method('findOne')
            ->with('Invoice', 'hash = :hash', [':hash' => 'hash'])
            ->willReturn($invoiceModel);

        $systemService = $this->createMock(\Box\Mod\System\Service::class);
        $systemService->method('getCompany')
            ->willReturn([
                'name' => 'FOSSBilling',
                'bank_name' => '',
                'account_number' => '',
                'bic' => '',
                'display_bank_info' => '',
                'vat_number' => '',
                'number' => '',
                'www' => '',
                'email' => '',
                'tel' => '',
                'signature' => '',
                'address_1' => '',
                'address_2' => '',
                'address_3' => '',
                'logo_url' => '',
            ]);
        $systemService->method('getParamValue')
            ->with('invoice_document_format', 'Letter')
            ->willReturn('Letter');

        $twig = $this->createMock(TwigEnvironment::class);
        $twig->expects($this->once())
            ->method('setLoader');
        $twig->expects($this->once())
            ->method('render')
            ->willReturn('<html>invoice</html>');

        $pdfOptions = new Options();

        $pdfMock = $this->createMock(Dompdf::class);
        $pdfMock->method('getOptions')
            ->willReturn($pdfOptions);
        $pdfMock->expects($this->once())
            ->method('setPaper')
            ->with('Letter', 'portrait');
        $pdfMock->expects($this->once())
            ->method('setOptions')
            ->with($pdfOptions);
        $pdfMock->expects($this->once())
            ->method('loadHtml')
            ->with('<html>invoice</html>');
        $pdfMock->expects($this->once())
            ->method('render');
        $pdfMock->expects($this->once())
            ->method('output')
            ->willReturn('%PDF-test');

        $service = $this->getMockBuilder(Service::class)
            ->onlyMethods(['checkInvoiceAuth', 'toApiArray', 'createPdfGenerator', 'getPdfCss', 'getPdfTemplate'])
            ->getMock();

        $service->expects($this->once())
            ->method('checkInvoiceAuth');

        $service->method('toApiArray')
            ->willReturn([
                'serie_nr' => 'INV-100',
                'seller' => [
                    'company' => 'Seller',
                    'address_1' => '',
                    'address_2' => '',
                    'address_3' => '',
                    'phone' => '',
                    'email' => '',
                    'company_vat' => '',
                ],
                'buyer' => [
                    'company' => '',
                    'first_name' => 'Jane',
                    'last_name' => 'Doe',
                    'address' => '',
                    'city' => '',
                    'state' => '',
                    'zip' => '',
                    'country' => '',
                    'phone' => '',
                    'company_vat' => '',
                    'email' => 'jane@example.com',
                ],
            ]);

        $service->method('createPdfGenerator')
            ->willReturn($pdfMock);

        $service->method('getPdfCss')
            ->willReturn('body { color: black; }');

        $service->method('getPdfTemplate')
            ->willReturn('default-invoice.twig');

        $twigFactory = $this->createMock(TwigFactory::class);
        $twigFactory->method('createBaseEnvironment')->willReturn($twig);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['twig_factory'] = $twigFactory;
        $di['mod_service'] = $di->protect(function (string $service) use ($systemService) {
            if (strtolower($service) === 'system') {
                return $systemService;
            }

            return null;
        });

        $service->setDi($di);

        $_SERVER['DOCUMENT_ROOT'] ??= '/';

        $response = $service->generatePDF('hash', new \Model_Guest());

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('%PDF-test', $response->getContent());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('inline; filename=INV-100.pdf', $response->headers->get('Content-Disposition'));
    }

    public function testCreatePdfResponseSanitizesInvalidFilenameCharacters(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('createPdfResponse');

        /** @var Response $response */
        $response = $method->invoke($this->service, '%PDF-test', 'INV/2026/Å');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('inline;', (string) $response->headers->get('Content-Disposition'));
    }

    public function testExtendInvoiceHashLifetimeRegeneratesLegacyHash(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        // 64-char legacy format (SHA-256 era). 40-char SHA-1 and 32-char
        // MD5 actually match the modern regex and are preserved.
        $invoiceModel->hash = str_repeat('a', 64);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->with($this->callback(function (\Model_Invoice $invoice): bool {
                $this->assertNotSame(
                    str_repeat('a', 64),
                    $invoice->hash,
                    'Legacy 64-char SHA-256 hash must be replaced with a modern 30-60 char hex hash'
                );
                $this->assertMatchesRegularExpression(
                    '/^[a-f0-9]{30,60}$/',
                    $invoice->hash,
                    'New hash must be lowercase hex in the 30-60 char range'
                );
                $this->assertNotNull($invoice->hash_expires_at, 'hash_expires_at must be stamped');

                return true;
            }));

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (string $service): \PHPUnit\Framework\MockObject\MockObject => match (strtolower($service)) {
            'system' => $this->getMockSystemServiceForAuth(),
            default => throw new \Pimple\Exception\UnknownIdentifierException(sprintf('Identifier "%s" is not defined.', $service)),
        });

        $this->service->setDi($di);
        $this->service->extendInvoiceHashLifetime($invoiceModel);
    }

    public function testExtendInvoiceHashLifetimeRegeneratesNullHash(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $invoiceModel->hash = null;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->with($this->callback(function (\Model_Invoice $invoice): bool {
                $this->assertNotNull($invoice->hash, 'Null hash must be replaced');
                $this->assertMatchesRegularExpression(
                    '/^[a-f0-9]{30,60}$/',
                    $invoice->hash,
                    'New hash must be lowercase hex in the 30-60 char range'
                );

                return true;
            }));

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (string $service): \PHPUnit\Framework\MockObject\MockObject => match (strtolower($service)) {
            'system' => $this->getMockSystemServiceForAuth(),
            default => throw new \Pimple\Exception\UnknownIdentifierException(sprintf('Identifier "%s" is not defined.', $service)),
        });

        $this->service->setDi($di);
        $this->service->extendInvoiceHashLifetime($invoiceModel);
    }

    public function testExtendInvoiceHashLifetimePreservesModernHash(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        // Valid modern 30-60 char lowercase hex hash
        $modernHash = bin2hex(random_bytes(20));
        $invoiceModel->hash = $modernHash;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->with($this->callback(function (\Model_Invoice $invoice) use ($modernHash): bool {
                $this->assertSame(
                    $modernHash,
                    $invoice->hash,
                    'Modern hash must be preserved as-is, not regenerated'
                );
                $this->assertNotNull($invoice->hash_expires_at, 'hash_expires_at must still be stamped');

                return true;
            }));

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (string $service): \PHPUnit\Framework\MockObject\MockObject => match (strtolower($service)) {
            'system' => $this->getMockSystemServiceForAuth(),
            default => throw new \Pimple\Exception\UnknownIdentifierException(sprintf('Identifier "%s" is not defined.', $service)),
        });

        $this->service->setDi($di);
        $this->service->extendInvoiceHashLifetime($invoiceModel);
    }
}
