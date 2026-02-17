<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;
use Box\Mod\Invoice\Service;
use Box\Mod\Invoice\ServiceInvoiceItem;
use Box\Mod\Invoice\ServiceSubscription;
use Box\Mod\Invoice\ServicePayGateway;
use Box\Mod\Invoice\ServiceTax;
use Box\Mod\System\Service as SystemService;
use Box\Mod\Client\Service as ClientService;
use Box\Mod\Email\Service as EmailService;
use Box\Mod\Order\Service as OrderService;
use Box\Mod\Currency\Service as CurrencyService;
use Box\Mod\Currency\Repository\CurrencyRepository;
use Box\Mod\Currency\Entity\Currency as CurrencyEntity;

beforeEach(function () {
    $service = new Service();
});

test('gets dependency injection container', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toBe($di);
});

test('gets search query with various parameters', function (array $data, string $expectedStr, array $expectedParams) {
    $service = new \Box\Mod\Invoice\Service();
    $di = container();

    $service->setDi($di);
    $result = $service->getSearchQuery($data);
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();

    expect(str_contains($result[0], $expectedStr))->toBeTrue($result[0]);
    expect(array_diff_key($result[1], $expectedParams))->toBe([]);
})->with([
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
]);

test('converts to api array', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $invoiceItemModel = new \Model_InvoiceItem();
    $invoiceItemModel->loadBean(new \Tests\Helpers\DummyBean());

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getCompany')
        ->atLeast()->once();
    $systemService->shouldReceive('getParamValue')
        ->atLeast()->once()
        ->andReturn(5);

    $subscriptionServiceMock = Mockery::mock(ServiceSubscription::class);
    $subscriptionServiceMock->shouldReceive('isSubscribable')
        ->atLeast()->once()
        ->andReturn(true);

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

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('toArray')
        ->atLeast()->once()
        ->andReturn($modelToArrayResult);
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([$invoiceItemModel]);
    $dbMock->shouldReceive('getCell')
        ->atLeast()->once()
        ->andReturn('1W');

    $periodMock = Mockery::mock('\Box_Period');
    $periodMock->shouldReceive('getUnit');
    $periodMock->shouldReceive('getQty');

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($systemService, $subscriptionServiceMock) {
        $service = null;
        if ($sub == 'InvoiceItem') {
        }
        if ($serviceName == 'system') {
            $service = $systemService;
        }
        if ($sub == 'Subscription') {
            $service = $subscriptionServiceMock;
        }

        return $service;
    });
    $di['period'] = $di->protect(fn (): Mockery\MockInterface => $periodMock);

    $service->setDi($di);

    $result = $service->toApiArray($invoiceModel);
    expect($result)->toBeArray();
});

test('handles after admin invoice payment received event', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $arr = [
        'total' => 1,
        'client' => [
            'id' => 0,
        ],
    ];
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($arr);

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once();

    $emailService = Mockery::mock(EmailService::class);
    $emailService->shouldReceive('sendTemplate')
        ->atLeast()->once();

    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($invoiceModel);

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($emailService, $serviceMock) {
        if ($serviceName == 'invoice') {
            return $serviceMock;
        }
        if ($serviceName == 'email') {
            return $emailService;
        }
    });
    $di['db'] = $dbMock;

    $service->setDi($di);
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $result = $service->onAfterAdminInvoicePaymentReceived($eventMock);
    expect($result)->toBeBool()->toBeTrue();
});

test('handles after admin invoice reminder sent event', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $arr = [
        'total' => 1,
        'client' => [
            'id' => 1,
        ],
    ];
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($arr);

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once();

    $emailService = Mockery::mock(EmailService::class);
    $emailService->shouldReceive('sendTemplate')
        ->atLeast()->once();

    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($invoiceModel);

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($emailService, $serviceMock) {
        if ($serviceName == 'invoice') {
            return $serviceMock;
        }
        if ($serviceName == 'email' || $serviceName == 'Email') {
            return $emailService;
        }
    });
    $di['db'] = $dbMock;

    $service->setDi($di);
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $service->onAfterAdminInvoiceReminderSent($eventMock);
});

test('handles after admin cron run event', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $eventMock = Mockery::mock('\Box_Event');

    $remove_after_days = 64;
    $systemServiceMock = Mockery::mock(SystemService::class);
    $systemServiceMock->shouldReceive('getParamValue')
        ->with('remove_after_days')
        ->atLeast()->once()
        ->andReturn($remove_after_days);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('exec')
        ->atLeast()->once();

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $systemServiceMock);
    $di['db'] = $dbMock;

    $service->setDi($di);
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $service->onAfterAdminCronRun($eventMock);
});

test('handles event after invoice is due', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $arr = [
        'total' => 1,
        'client' => [
            'id' => 1,
        ],
    ];
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($arr);

    $eventMock = Mockery::mock('\Box_Event');
    $params = ['days_passed' => 5, 'id' => 1];
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn($params);

    $emailService = Mockery::mock(EmailService::class);
    $emailService->shouldReceive('sendTemplate')
        ->atLeast()->once();

    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($invoiceModel);

    $di = container();
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
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);
    $serviceMock->onEventAfterInvoiceIsDue($eventMock);
});

test('marks invoice as paid', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('countIncome')
        ->atLeast()->once();

    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());
    $invoiceModel->status = \Model_Invoice::STATUS_UNPAID;

    $invoiceItemModel = new \Model_InvoiceItem();
    $invoiceItemModel->loadBean(new \Tests\Helpers\DummyBean());

    $itemInvoiceServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $itemInvoiceServiceMock->shouldReceive('markAsPaid')
        ->atLeast()->once();
    $itemInvoiceServiceMock->shouldReceive('executeTask')
        ->atLeast()->once();

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->atLeast()->once();

    $currencyRepositoryMock = Mockery::mock(CurrencyRepository::class);
    $currencyRepositoryMock->shouldReceive('getRateByCode')
        ->atLeast()->once()
        ->andReturn(1.0);

    $currencyServiceMock = Mockery::mock(CurrencyService::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $currencyServiceMock->shouldReceive('getCurrencyRepository')
        ->atLeast()->once()
        ->andReturn($currencyRepositoryMock);

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->atLeast()->once();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([$invoiceItemModel]);
    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $di = container();
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
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $result = $serviceMock->markAsPaid($invoiceModel, true, true);
    expect($result)->toBeBool()->toBeTrue();
});

test('counts income', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();

    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());
    $invoiceModel->currency = 'USD';
    $invoiceModel->refund = 0;

    $currencyService = Mockery::mock(CurrencyService::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $currencyService->shouldReceive('toBaseCurrency')
        ->atLeast()->once()
        ->andReturn(0.0);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([]);
    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $currencyService);

    $serviceMock->setDi($di);
    $serviceMock->countIncome($invoiceModel);
});

test('prepares invoice with undefined currency', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('setInvoiceDefaults')
        ->atLeast()->once();

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
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());

    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $currencyModel = Mockery::mock(CurrencyEntity::class);
    $defaultCurrencyCode = 'USD';
    $currencyModel->shouldReceive('getCode')
        ->andReturn($defaultCurrencyCode);

    $currencyRepositoryMock = Mockery::mock(CurrencyRepository::class);
    $currencyRepositoryMock->shouldReceive('findDefault')
        ->atLeast()->once()
        ->andReturn($currencyModel);

    $currencyServiceMock = Mockery::mock(CurrencyService::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $currencyServiceMock->shouldReceive('getCurrencyRepository')
        ->atLeast()->once()
        ->andReturn($currencyRepositoryMock);

    $itemInvoiceServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $itemInvoiceServiceMock->shouldReceive('addNew')
        ->atLeast()->once();

    $newRecordId = 1;
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($newRecordId);
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($invoiceModel);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($currencyServiceMock, $itemInvoiceServiceMock) {
        if ($serviceName == 'Currency') {
            return $currencyServiceMock;
        }
        if ($sub == 'InvoiceItem') {
            return $itemInvoiceServiceMock;
        }
    });
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $result = $serviceMock->prepareInvoice($clientModel, $data);
    expect($result)->toBeInstanceOf(\Model_Invoice::class);
    expect($result->currency)->toBe($defaultCurrencyCode);
});

test('sets invoice defaults', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());

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

    $clientService = Mockery::mock(ClientService::class);
    $clientService->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($buyer);

    $systemService = Mockery::mock(SystemService::class);
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
    $systemService->shouldReceive('getCompany')
        ->atLeast()->once()
        ->andReturn($seller);
    $systemService->shouldReceive('getParamValue')
        ->atLeast()->once()
        ->andReturn(1);
    $systemService->shouldReceive('setParamValue')
        ->atLeast()->once();

    $serviceTaxMock = Mockery::mock(ServiceTax::class);
    $serviceTaxMock->shouldReceive('getTaxRateForClient');

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($clientModel);
    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $di = container();
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

    $service->setDi($di);

    $service->setInvoiceDefaults($invoiceModel);
});

test('approves an invoice', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('tryPayWithCredits')
        ->atLeast()->once();
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn(['id' => 1]);

    $data['use_credits'] = true;

    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->atLeast()->once();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventManagerMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    $result = $serviceMock->approveInvoice($invoiceModel, $data);
    expect($result)->toBeTrue();
});

test('gets total with tax', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $total = 10.0;
    $tax = 2.2;
    $expected = $total + $tax;
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getTotal')
        ->once()
        ->andReturn($total);
    $serviceMock->shouldReceive('getTax')
        ->once()
        ->andReturn($tax);

    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $result = $serviceMock->getTotalWithTax($invoiceModel);
    expect($result)->toBeFloat();
    expect($result)->toBe($expected);
});

test('gets total', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $invoiceItemModel = new \Model_InvoiceItem();
    $invoiceItemModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([$invoiceItemModel]);

    $itemInvoiceServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $itemTotal = 10.0;
    $itemInvoiceServiceMock->shouldReceive('getTotal')
        ->atLeast()->once()
        ->andReturn($itemTotal);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $itemInvoiceServiceMock);

    $service->setDi($di);
    $result = $service->getTotal($invoiceModel);
    expect($result)->toBeFloat();
    expect($result)->toBe($itemTotal);
});

test('refunds invoice with negative invoice logic', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $newId = 1;
    $total = 10.0;
    $tax = 2.2;
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getTotal')
        ->once()
        ->andReturn($total);
    $serviceMock->shouldReceive('getTax')
        ->once()
        ->andReturn($tax);
    $serviceMock->shouldReceive('countIncome')
        ->once();
    $serviceMock->shouldReceive('addNote')
        ->times(3);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn(['id' => $newId]);

    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());
    $invoiceModel->id = $newId;

    $invoiceItemModel = new \Model_InvoiceItem();
    $invoiceItemModel->loadBean(new \Tests\Helpers\DummyBean());

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->atLeast()->once();

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->atLeast()->once()
        ->andReturn('negative_invoice');

    $dbMock = Mockery::mock('\Box_Database');
    $dispenseCount = 0;
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturnUsing(function () use (&$dispenseCount, $invoiceModel, $invoiceItemModel) {
            return ++$dispenseCount === 1 ? $invoiceModel : $invoiceItemModel;
        });
    $dbMock->shouldReceive('store')
        ->atLeast()->once();
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([$invoiceItemModel]);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $systemService);
    $di['events_manager'] = $eventManagerMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $result = $serviceMock->refundInvoice($invoiceModel, 'customNote');
    expect($result)->toBeInt()->toBe($newId);
});

test('updates an invoice', function (): void {
    $service = new \Box\Mod\Invoice\Service();
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
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $invoiceItemModel = new \Model_InvoiceItem();
    $invoiceItemModel->loadBean(new \Tests\Helpers\DummyBean());

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->atLeast()->once();

    $itemInvoiceServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $itemInvoiceServiceMock->shouldReceive('addNew')
        ->atLeast()->once();
    $itemInvoiceServiceMock->shouldReceive('update')
        ->atLeast()->once();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once();
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($invoiceItemModel);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $itemInvoiceServiceMock);
    $di['events_manager'] = $eventManagerMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn(['id' => 1]);

    $serviceMock->setDi($di);

    $result = $serviceMock->updateInvoice($invoiceModel, $data);
    expect($result)->toBeTrue();
});

test('removes an invoice', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $invoiceItemModel = new \Model_InvoiceItem();
    $invoiceItemModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('exec')
        ->atLeast()->once();
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([$invoiceItemModel]);
    $dbMock->shouldReceive('trash')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $result = $service->rmInvoice($invoiceModel);
    expect($result)->toBeTrue();
});

test('deletes invoice by admin', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('rmInvoice')
        ->once();

    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->atLeast()->once();

    $di = container();
    $di['events_manager'] = $eventManagerMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    $result = $serviceMock->deleteInvoiceByAdmin($invoiceModel);
    expect($result)->toBeTrue();
});

test('deletes invoice by client', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $invoiceItemModel = new \Model_InvoiceItem();
    $invoiceItemModel->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('rmInvoice')
        ->once();

    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->atLeast()->once();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([$invoiceItemModel]);

    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventManagerMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    $result = $serviceMock->deleteInvoiceByClient($invoiceModel);
    expect($result)->toBeTrue();
});

test('throws exception when deleting client invoice related to order', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $invoiceItemModel = new \Model_InvoiceItem();
    $invoiceItemModel->loadBean(new \Tests\Helpers\DummyBean());
    $invoiceItemModel->type = \Model_InvoiceItem::TYPE_ORDER;
    $rel_id = 1;
    $invoiceItemModel->rel_id = $rel_id;

    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->atLeast()->once();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([$invoiceItemModel]);

    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventManagerMock;

    $service->setDi($di);

    expect(fn () => $service->deleteInvoiceByClient($invoiceModel))
        ->toThrow(\FOSSBilling\Exception::class, sprintf('Invoice is related to order #%d. Please cancel order first.', $rel_id));
});

test('renews an invoice', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $newId = 2;
    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());
    $invoiceModel->id = $newId;

    $clientOrder = new \Model_ClientOrder();
    $clientOrder->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('approveInvoice')
        ->once();
    $serviceMock->shouldReceive('generateForOrder')
        ->once()
        ->andReturn($invoiceModel);

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->atLeast()->once();

    $di = container();
    $di['events_manager'] = $eventManagerMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $result = $serviceMock->renewInvoice($clientOrder, []);
    expect($result)->toBeInt()->toBe($newId);
});

test('processes batch pay with credits', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('findAllUnpaid')
        ->atLeast()->once()
        ->andReturn([[]]);
    $serviceMock->shouldReceive('tryPayWithCredits')
        ->atLeast()->once();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($invoiceModel);

    $di = container();
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['db'] = $dbMock;

    $serviceMock->setDi($di);
    $result = $serviceMock->doBatchPayWithCredits([]);
    expect($result)->toBeBool()->toBeTrue();
});

test('pays invoice with credits', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('tryPayWithCredits')
        ->atLeast()->once();

    $di = container();
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $result = $serviceMock->payInvoiceWithCredits($invoiceModel);
    expect($result)->toBeBool()->toBeTrue();
});

test('returns existing invoice when generating for order with unpaid invoice', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $clientOrder = new \Model_ClientOrder();
    $clientOrder->loadBean(new \Tests\Helpers\DummyBean());
    $clientOrder->unpaid_invoice_id = 2;

    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($invoiceModel);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);
    $result = $service->generateForOrder($clientOrder);
    expect($result)->toBeInstanceOf(\Model_Invoice::class);
});

test('generates invoice for order', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('setInvoiceDefaults')
        ->once();

    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());
    $orderModel->price = 10;
    $orderModel->promo_recurring = true;

    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());

    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($clientModel);
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($invoiceModel);
    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $invoiceItemServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $invoiceItemServiceMock->shouldReceive('generateFromOrder')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $invoiceItemServiceMock);

    $serviceMock->setDi($di);
    $result = $serviceMock->generateForOrder($orderModel);
    expect($result)->toBeInstanceOf(\Model_Invoice::class);
});

test('throws exception when generating invoice for zero amount order', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $clientOrder = new \Model_ClientOrder();
    $clientOrder->loadBean(new \Tests\Helpers\DummyBean());
    $clientOrder->price = 0;

    expect(fn () => $service->generateForOrder($clientOrder))
        ->toThrow(\FOSSBilling\Exception::class, 'Invoices are not generated for 0 amount orders');
});

test('returns true when no expiring orders found', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $orderService = Mockery::mock(OrderService::class);
    $orderService->shouldReceive('getSoonExpiringActiveOrders')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderService);

    $service->setDi($di);
    $result = $service->generateInvoicesForExpiringOrders();
    expect($result)->toBeBool()->toBeTrue();
});

test('generates invoices for expiring orders', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $clientOrder = new \Model_ClientOrder();
    $clientOrder->loadBean(new \Tests\Helpers\DummyBean());

    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $newId = 4;
    $invoiceModel->id = $newId;

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('approveInvoice')
        ->once();
    $serviceMock->shouldReceive('generateForOrder')
        ->once()
        ->andReturn($invoiceModel);

    $orderService = Mockery::mock(OrderService::class);
    $orderService->shouldReceive('getSoonExpiringActiveOrders')
        ->atLeast()->once()
        ->andReturn([[]]);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($clientOrder);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderService);
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['db'] = $dbMock;

    $serviceMock->setDi($di);
    $result = $serviceMock->generateInvoicesForExpiringOrders();
    expect($result)->toBeBool()->toBeTrue();
});

test('activates paid invoices in batch', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $invoiceItemModel = new \Model_InvoiceItem();
    $invoiceItemModel->loadBean(new \Tests\Helpers\DummyBean());

    $itemInvoiceServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $itemInvoiceServiceMock->shouldReceive('executeTask')
        ->with($invoiceItemModel);
    $itemInvoiceServiceMock->shouldReceive('getAllNotExecutePaidItems')
        ->atLeast()->once()
        ->andReturn([[]]);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($invoiceItemModel);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $itemInvoiceServiceMock);
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['db'] = $dbMock;

    $service->setDi($di);
    $result = $service->doBatchPaidInvoiceActivation();
    expect($result)->toBeBool()->toBeTrue();
});

test('handles exception during batch paid invoice activation', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $invoiceItemModel = new \Model_InvoiceItem();
    $invoiceItemModel->loadBean(new \Tests\Helpers\DummyBean());

    $itemInvoiceServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $itemInvoiceServiceMock->shouldReceive('executeTask')
        ->with($invoiceItemModel)
        ->andThrow(new \FOSSBilling\Exception('testing exception..'));
    $itemInvoiceServiceMock->shouldReceive('getAllNotExecutePaidItems')
        ->atLeast()->once()
        ->andReturn([[]]);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($invoiceItemModel);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $itemInvoiceServiceMock);
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['db'] = $dbMock;

    $service->setDi($di);
    $result = $service->doBatchPaidInvoiceActivation();
    expect($result)->toBeBool()->toBeTrue();
});

test('sends reminders in batch', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('sendInvoiceReminder')
        ->once();
    $serviceMock->shouldReceive('getUnpaidInvoicesLateFor')
        ->once()
        ->andReturn([$invoiceModel]);

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->atLeast()->once();

    $di = container();
    $di['events_manager'] = $eventManagerMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $result = $serviceMock->doBatchRemindersSend();
    expect($result)->toBeBool()->toBeTrue();
});

test('invokes due event in batch', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->atLeast()->once();
    $systemService->shouldReceive('setParamValue')
        ->atLeast()->once();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')
        ->atLeast()->once()
        ->andReturn([[]]);

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventManagerMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $systemService);
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $service->setDi($di);

    $result = $service->doBatchInvokeDueEvent([]);
    expect($result)->toBeBool()->toBeTrue();
});

test('protects from sending reminders to paid invoices', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());
    $invoiceModel->status = \Model_Invoice::STATUS_PAID;

    $result = $service->sendInvoiceReminder($invoiceModel);
    expect($result)->toBeBool()->toBeTrue();
});

test('sends invoice reminder', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->atLeast()->once();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventManagerMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $service->setDi($di);

    $result = $service->sendInvoiceReminder($invoiceModel);
    expect($result)->toBeBool()->toBeTrue();
});

test('counts invoices', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $sqlResult = [
        ['status' => \Model_Invoice::STATUS_PAID,
            'counter' => 2],
    ];
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')
        ->atLeast()->once()
        ->andReturn($sqlResult);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);
    $result = $service->counter();
    expect($result)->toBeArray();
});

test('throws exception when generating funds invoice without active order', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());

    expect(fn () => $service->generateFundsInvoice($clientModel, 10))
        ->toThrow(\FOSSBilling\Exception::class, 'You must have at least one active order before you can add funds so you cannot proceed at the current time!');
});

test('throws exception when generating funds invoice below minimum amount', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());
    $clientModel->currency = 'EUR';
    $fundsAmount = 2;

    $minAmount = 10;
    $maxAmount = 50;
    $systemService = Mockery::mock(SystemService::class);
    $paramCallCount = 0;
    $systemService->shouldReceive('getParamValue')
        ->atLeast()->once()
        ->andReturnUsing(function () use (&$paramCallCount, $minAmount, $maxAmount) {
            return ++$paramCallCount === 1 ? $minAmount : $maxAmount;
        });

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $systemService);

    $service->setDi($di);

    expect(fn () => $service->generateFundsInvoice($clientModel, $fundsAmount))
        ->toThrow(\FOSSBilling\Exception::class, 'Amount must be at least ' . $minAmount);
});

test('throws exception when generating funds invoice above maximum amount', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());
    $clientModel->currency = 'EUR';
    $fundsAmount = 200;

    $minAmount = 10;
    $maxAmount = 50;
    $systemService = Mockery::mock(SystemService::class);
    $paramCallCount = 0;
    $systemService->shouldReceive('getParamValue')
        ->atLeast()->once()
        ->andReturnUsing(function () use (&$paramCallCount, $minAmount, $maxAmount) {
            return ++$paramCallCount === 1 ? $minAmount : $maxAmount;
        });

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $systemService);

    $service->setDi($di);

    expect(fn () => $service->generateFundsInvoice($clientModel, $fundsAmount))
        ->toThrow(\FOSSBilling\Exception::class, 'Amount cannot exceed ' . $maxAmount);
});

test('generates funds invoice', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());
    $clientModel->currency = 'EUR';
    $fundsAmount = 20;

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('setInvoiceDefaults')
        ->atLeast()->once();

    $minAmount = 10;
    $maxAmount = 50;

    $systemService = Mockery::mock(SystemService::class);
    $paramCallCount = 0;
    $systemService->shouldReceive('getParamValue')
        ->atLeast()->once()
        ->andReturnUsing(function () use (&$paramCallCount, $minAmount, $maxAmount) {
            $paramCallCount++;
            if ($paramCallCount === 1) return $minAmount;
            if ($paramCallCount === 2) return $maxAmount;
            return true;
        });

    $itemInvoiceServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $itemInvoiceServiceMock->shouldReceive('generateForAddFunds')
        ->atLeast()->once();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($invoiceModel);
    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $di = container();
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
    expect($result)->toBeInstanceOf(\Model_Invoice::class);
});

test('throws exception when processing invoice not found', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $data = [
        'hash' => 'hashString',
        'gateway_id' => 2,
    ];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    expect(fn () => $service->processInvoice($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Invoice not found');
});

test('throws exception when processing invoice with gateway not found', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $data = [
        'hash' => 'hashString',
        'gateway_id' => 2,
    ];

    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($invoiceModel);
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    expect(fn () => $service->processInvoice($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Payment method not found');
});

test('throws exception when processing invoice with gateway not enabled', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $data = [
        'hash' => 'hashString',
        'gateway_id' => 2,
    ];

    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $payGatewayModel = new \Model_PayGateway();
    $payGatewayModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($invoiceModel);
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($payGatewayModel);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    expect(fn () => $service->processInvoice($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Payment method not enabled');
});

test('processes an invoice', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getPaymentInvoice')
        ->atLeast()->once()
        ->andReturn(new \Payment_Invoice());

    $data = [
        'hash' => 'hashString',
        'gateway_id' => 2,
    ];

    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $payGatewayModel = new \Model_PayGateway();
    $payGatewayModel->loadBean(new \Tests\Helpers\DummyBean());
    $payGatewayModel->enabled = true;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($invoiceModel);
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($payGatewayModel);

    $subscribeService = Mockery::mock(ServiceSubscription::class);
    $subscribeService->shouldReceive('isSubscribable')
        ->atLeast()->once()
        ->andReturn(true);

    $payGatewayService = Mockery::mock(ServicePayGateway::class);
    $payGatewayService->shouldReceive('canPerformRecurrentPayment')
        ->atLeast()->once()
        ->andReturn(true);

    $adapterMock = Mockery::mock('\Payment_Adapter_Dummy');
    $adapterMock->shouldReceive('getConfig')
        ->atLeast()->once()
        ->andReturn([]);
    $adapterMock->shouldReceive('recurrentPayment')
        ->atLeast()->once()
        ->andReturn(['type' => 'html', 'result' => 'test']);
    $adapterMock->shouldReceive('getType')
        ->atLeast()->once()
        ->andReturn('html');
    $adapterMock->shouldReceive('getServiceURL')
        ->atLeast()->once()
        ->andReturn('http://example.com/payment');

    $payGatewayService->shouldReceive('getPaymentAdapter')
        ->atLeast()->once()
        ->andReturn($adapterMock);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($payGatewayService, $subscribeService) {
        if ($sub == 'PayGateway') {
            return $payGatewayService;
        }
        if ($sub == 'Subscription') {
            return $subscribeService;
        }
    });
    $di['api_admin'] = new \Api_Handler(new \Model_Admin());
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $result = $serviceMock->processInvoice($data);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('type');
    expect($result)->toHaveKey('service_url');
    expect($result)->toHaveKey('subscription');
    expect($result)->toHaveKey('result');
});

test('adds note to invoice', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $note = 'test Note';

    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->addNote($invoiceModel, $note);
    expect($result)->toBeTrue();
});

test('finds all unpaid invoices', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $getAllResult = [
        [
            'id' => 1,
            'client_id' => 1,
            'serie' => 'BB',
            'nr' => '00',
        ],
    ];
    $dbMock->shouldReceive('getAll')
        ->atLeast()->once()
        ->andReturn($getAllResult);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->findAllUnpaid();
    expect($result)->toBeArray();
});

test('finds all paid invoices', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([$invoiceModel]);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->findAllPaid();
    expect($result)->toBeArray();
    expect($result[0])->toBeInstanceOf(\Model_Invoice::class);
});

test('gets unpaid invoices late for', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([$invoiceModel]);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->getUnpaidInvoicesLateFor();
    expect($result)->toBeArray();
    expect($result[0])->toBeInstanceOf(\Model_Invoice::class);
});

test('gets buyer', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());
    $invoiceModel->buyer_first_name = 'John';
    $invoiceModel->buyer_last_name = 'Doe';
    $invoiceModel->buyer_email = 'john@example.com';

    $result = $service->getBuyer($invoiceModel);
    expect($result)->toBeArray();
    expect($result)->toHaveKey('first_name');
    expect($result)->toHaveKey('last_name');
    expect($result)->toHaveKey('email');
    expect($result['first_name'])->toBe('John');
    expect($result['last_name'])->toBe('Doe');
    expect($result['email'])->toBe('john@example.com');
});

test('checks if invoice type is deposit', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $di = container();

    $modelInvoiceItem = new \Model_InvoiceItem();
    $modelInvoiceItem->loadBean(new \Tests\Helpers\DummyBean());
    $modelInvoiceItem->type = \Model_InvoiceItem::TYPE_DEPOSIT;

    $invoiceItems = [$modelInvoiceItem];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn($invoiceItems);

    $di['db'] = $dbMock;

    $modelInvoice = new \Model_Invoice();
    $modelInvoice->loadBean(new \Tests\Helpers\DummyBean());

    $service->setDi($di);
    $result = $service->isInvoiceTypeDeposit($modelInvoice);
    expect($result)->toBeTrue();
});

test('returns false when invoice type is not deposit', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $di = container();

    $modelInvoiceItem = new \Model_InvoiceItem();
    $modelInvoiceItem->loadBean(new \Tests\Helpers\DummyBean());
    $modelInvoiceItem->type = \Model_InvoiceItem::TYPE_ORDER;

    $invoiceItems = [$modelInvoiceItem];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn($invoiceItems);

    $di['db'] = $dbMock;
    $service->setDi($di);

    $modelInvoice = new \Model_Invoice();
    $modelInvoice->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->isInvoiceTypeDeposit($modelInvoice);
    expect($result)->toBeFalse();
});

test('returns false when checking deposit with empty items', function (): void {
    $service = new \Box\Mod\Invoice\Service();
    $di = container();

    $invoiceItems = [];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn($invoiceItems);

    $di['db'] = $dbMock;
    $service->setDi($di);

    $modelInvoice = new \Model_Invoice();
    $modelInvoice->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->isInvoiceTypeDeposit($modelInvoice);
    expect($result)->toBeFalse();
});
