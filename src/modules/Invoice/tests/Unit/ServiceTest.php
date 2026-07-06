<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Client\Service as ClientService;
use Box\Mod\Currency\Entity\Currency as CurrencyEntity;
use Box\Mod\Currency\Repository\CurrencyRepository;
use Box\Mod\Currency\Service as CurrencyService;
use Box\Mod\Email\Service as EmailService;
use Box\Mod\Invoice\Service;
use Box\Mod\Invoice\ServiceInvoiceItem;
use Box\Mod\Invoice\ServicePayGateway;
use Box\Mod\Invoice\ServiceSubscription;
use Box\Mod\Invoice\ServiceTax;
use Box\Mod\Order\Service as OrderService;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Product\Service as ProductService;
use Box\Mod\System\Service as SystemService;

use function Tests\Helpers\container;
use function Tests\Helpers\moduleService;

test('gets dependency injection container', function (): void {
    $service = new Service();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toBe($di);
});

test('gets search query with various parameters', function (array $data, string $expectedStr, array $expectedParams): void {
    $service = new Service();
    $di = container();

    $service->setDi($di);
    $result = $service->getSearchQuery($data);
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();

    expect(str_contains((string) $result[0], $expectedStr))->toBeTrue($result[0]);
    expect($result[1])->toMatchArray($expectedParams);
})->with([
    [[], 'FROM invoice p', []],
    [
        ['order_id' => '1'],
        'AND pi.type = :item_type AND pi.rel_id = :order_id',
        [
            'item_type' => Model_InvoiceItem::TYPE_ORDER,
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
        ['created_at' => '2012-11-23 12:34:56'],
        "AND DATE_FORMAT(p.created_at, '%Y-%m-%d') = :created_at",
        [
            'created_at' => '2012-11-23',
        ],
    ],
    [
        ['date_from' => '2012-11-23 12:34:56'],
        'AND UNIX_TIMESTAMP(p.created_at) >= :date_from',
        [
            'date_from' => 1353674096,
        ],
    ],
    [
        ['date_to' => '2012-11-23 12:34:56'],
        'AND UNIX_TIMESTAMP(p.created_at) <= :date_to',
        [
            'date_to' => 1353674096,
        ],
    ],
    [
        ['paid_at' => '2012-11-23 12:34:56'],
        "AND DATE_FORMAT(p.paid_at, '%Y-%m-%d') = :paid_at",
        [
            'paid_at' => '2012-11-23',
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
    $service = new Service();
    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
    $invoiceModel->hash = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4';

    $invoiceItemModel = new Model_InvoiceItem();
    $invoiceItemModel->loadBean(new Tests\Helpers\DummyBean());

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getCompany')
        ->atLeast()->once();
    $systemService->shouldReceive('getParamValue')
        ->atLeast()->once()
        ->andReturn(5);

    $subscriptionServiceMock = Mockery::mock(ServiceSubscription::class);
    $subscriptionServiceMock->shouldReceive('getSubscriptionPeriod')
        ->byDefault()
        ->andReturn('1W');
    $invoiceItemServiceMock = Mockery::mock(ServiceInvoiceItem::class);

    $modelToArrayResult = [
        'id' => 1,
        'serie' => 'BB',
        'nr' => '0001',
        'serie_nr' => 'BB0001',
        'hash' => 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4',
        'gateway_id' => '',
        'taxname' => '',
        'taxrate' => '',
        'currency' => '',
        'status' => '',
        'notes' => '',
        'text_1' => '',
        'text_2' => '',
        'due_at' => '',
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
        ->byDefault()
        ->andReturn('1W');
    $subscriptionServiceMock->shouldReceive('getSubscriptionPeriod')
        ->byDefault()
        ->andReturn('1W');

    $periodMock = Mockery::mock('\Box_Period');
    $periodMock->shouldReceive('getUnit');
    $periodMock->shouldReceive('getQty');

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($systemService, $subscriptionServiceMock, $invoiceItemServiceMock) {
        $service = null;
        if ($sub == 'InvoiceItem') {
            $service = $invoiceItemServiceMock;
        }
        if (is_string($serviceName) && strtolower($serviceName) === 'system') {
            $service = $systemService;
        }
        if ($sub === 'Subscription') {
            $service = $subscriptionServiceMock;
        }

        return $service;
    });
    $di['period'] = $di->protect(fn (): Mockery\MockInterface => $periodMock);

    $service->setDi($di);

    $result = $service->toApiArray($invoiceModel);
    expect($result)->toBeArray();
    expect($result['currency_rate'])->toBe(1);
    expect($result['paid_at'])->toBeNull();
    expect($result['buyer']['phone_cc'])->toBe('');
});

test('ensure valid hash is a no-op for modern hashes', function (): void {
    $service = new Service();
    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
    $invoiceModel->hash = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldNotReceive('store');

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);
    $service->ensureValidHash($invoiceModel);

    expect($invoiceModel->hash)->toBe('a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4');
});

test('ensure valid hash regenerates a missing hash', function (): void {
    $service = new Service();
    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_hash_lifetime_days', '90')
        ->andReturn('90');

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->once()
        ->with($invoiceModel);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(function ($serviceName) use ($systemService) {
        if ($serviceName === 'system') {
            return $systemService;
        }

        return null;
    });

    $service->setDi($di);
    $service->ensureValidHash($invoiceModel);

    expect($invoiceModel->hash)->toBeString();
    expect(strlen($invoiceModel->hash))->toBeGreaterThanOrEqual(30);
    expect(strlen($invoiceModel->hash))->toBeLessThanOrEqual(60);
    expect($invoiceModel->hash)->toMatch('/^[a-f0-9]+$/');
    expect($invoiceModel->hash_expires_at)->toBeString();
});

test('ensure valid hash regenerates a legacy format hash', function (): void {
    $service = new Service();
    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
    $invoiceModel->hash = 'AAAAAAAAC4C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8';

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_hash_lifetime_days', '90')
        ->andReturn('90');

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->once()
        ->with($invoiceModel);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(function ($serviceName) use ($systemService) {
        if ($serviceName === 'system') {
            return $systemService;
        }

        return null;
    });

    $service->setDi($di);
    $service->ensureValidHash($invoiceModel);

    expect(strlen($invoiceModel->hash))->toBeGreaterThanOrEqual(30);
    expect(strlen($invoiceModel->hash))->toBeLessThanOrEqual(60);
    expect($invoiceModel->hash)->toMatch('/^[a-f0-9]+$/');
});

test('to api array self-heals invoice with missing hash', function (): void {
    $service = new Service();
    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

    $invoiceItemModel = new Model_InvoiceItem();
    $invoiceItemModel->loadBean(new Tests\Helpers\DummyBean());

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getCompany')->atLeast()->once();
    $systemService->shouldReceive('getParamValue')
        ->atLeast()->once()
        ->andReturnUsing(function (string $param, string $default = '') {
            if ($param === 'invoice_hash_lifetime_days') {
                return '90';
            }

            return $default;
        });

    $subscriptionServiceMock = Mockery::mock(ServiceSubscription::class);
    $subscriptionServiceMock->shouldReceive('getSubscriptionPeriod')
        ->byDefault()
        ->andReturn('1W');
    $invoiceItemServiceMock = Mockery::mock(ServiceInvoiceItem::class);

    $modelToArrayResult = [
        'id' => 1,
        'serie' => 'BB',
        'nr' => '0001',
        'serie_nr' => 'BB0001',
        'hash' => null,
        'gateway_id' => '',
        'taxname' => '',
        'taxrate' => '',
        'currency' => '',
        'status' => '',
        'notes' => '',
        'text_1' => '',
        'text_2' => '',
        'due_at' => '',
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
        'buyer_email' => '',
        'buyer_zip' => '',
        'seller_company_vat' => '',
        'seller_company_number' => '',
    ];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('toArray')
        ->atLeast()->once()
        ->andReturnUsing(function () use ($invoiceModel, $modelToArrayResult): array {
            $modelToArrayResult['hash'] = $invoiceModel->hash;

            return $modelToArrayResult;
        });
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([$invoiceItemModel]);
    $dbMock->shouldReceive('getCell')
        ->byDefault()
        ->andReturn('1W');
    $dbMock->shouldReceive('store')
        ->once()
        ->with($invoiceModel);

    $periodMock = Mockery::mock('\Box_Period');
    $periodMock->shouldReceive('getUnit');
    $periodMock->shouldReceive('getQty');

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($systemService, $subscriptionServiceMock, $invoiceItemServiceMock) {
        $service = null;
        if ($sub === 'InvoiceItem') {
            $service = $invoiceItemServiceMock;
        }
        if ($serviceName === 'system' || $serviceName === 'System') {
            $service = $systemService;
        }
        if ($sub === 'Subscription') {
            $service = $subscriptionServiceMock;
        }

        return $service;
    });
    $di['period'] = $di->protect(fn (): Mockery\MockInterface => $periodMock);

    $service->setDi($di);

    $result = $service->toApiArray($invoiceModel);

    expect($result)->toBeArray();
    expect($result['hash'])->toBeString();
    expect(strlen((string) $result['hash']))->toBeGreaterThanOrEqual(30);
    expect(strlen((string) $result['hash']))->toBeLessThanOrEqual(60);
});

test('handles after admin invoice payment received event', function (): void {
    $service = new Service();
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
    $serviceMock->shouldReceive('getInvoicePdfAttachment')
        ->atLeast()->once()
        ->andReturn(null);

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once();

    $emailService = Mockery::mock(EmailService::class);
    $emailService->shouldReceive('sendTemplate')
        ->atLeast()->once();

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($invoiceModel);

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($emailService, $serviceMock) {
        if ($serviceName === 'invoice') {
            return $serviceMock;
        }
        if ($serviceName === 'email') {
            return $emailService;
        }
    });
    $di['db'] = $dbMock;

    $service->setDi($di);
    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $result = $service->onAfterAdminInvoicePaymentReceived($eventMock);
    expect($result)->toBeBool()->toBeTrue();
});

test('handles after admin invoice reminder sent event', function (): void {
    $service = new Service();
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
    $serviceMock->shouldReceive('getInvoicePdfAttachment')
        ->atLeast()->once()
        ->andReturn(null);

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once();

    $emailService = Mockery::mock(EmailService::class);
    $emailService->shouldReceive('sendTemplate')
        ->atLeast()->once();

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($invoiceModel);
    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($emailService, $serviceMock) {
        if ($serviceName == 'invoice') {
            return $serviceMock;
        }
        if ($serviceName == 'system' || $serviceName == 'System') {
            $systemService = Mockery::mock(SystemService::class);
            $systemService->shouldReceive('getParamValue')
                ->with('invoice_hash_lifetime_days', '90')
                ->andReturn('90');

            return $systemService;
        }
        if ($serviceName == 'email' || $serviceName == 'Email') {
            return $emailService;
        }
    });
    $di['db'] = $dbMock;

    $service->setDi($di);
    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $service->onAfterAdminInvoiceReminderSent($eventMock);
});

test('handles after admin cron run event', function (): void {
    $service = new Service();
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
    $service = new Service();
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
    $serviceMock->shouldReceive('getInvoicePdfAttachment')
        ->atLeast()->once()
        ->andReturn(null);

    $eventMock = Mockery::mock('\Box_Event');
    $params = ['days_passed' => 5, 'id' => 1];
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn($params);

    $emailService = Mockery::mock(EmailService::class);
    $emailService->shouldReceive('sendTemplate')
        ->atLeast()->once();

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_reminder_after_due_days', '5')
        ->andReturn('1, 5, 7');

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($invoiceModel);

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($emailService, $serviceMock, $systemService) {
        if ($serviceName == 'invoice') {
            return $serviceMock;
        }
        if ($serviceName == 'email') {
            return $emailService;
        }
        if ($serviceName == 'system') {
            return $systemService;
        }
    });
    $di['db'] = $dbMock;

    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);
    $serviceMock->onEventAfterInvoiceIsDue($eventMock);
});

test('handles event before invoice is due', function (): void {
    $service = new Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('sendInvoiceReminder')
        ->once()
        ->andReturnTrue();

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn(['days_left' => 7, 'id' => 1]);

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_reminder_before_due_days', '')
        ->andReturn('14, 7, 1');

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->once()
        ->with('Invoice', 1)
        ->andReturn($invoiceModel);

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($serviceMock, $systemService) {
        if ($serviceName == 'invoice') {
            return $serviceMock;
        }
        if ($serviceName == 'system') {
            return $systemService;
        }
    });
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $service->onEventBeforeInvoiceIsDue($eventMock);
});

test('skips before due invoice reminder when interval does not match', function (): void {
    $service = new Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('sendInvoiceReminder')
        ->never();

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn(['days_left' => 3, 'id' => 1]);

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_reminder_before_due_days', '')
        ->andReturn('14, 7, 1');

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->never();

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($serviceMock, $systemService) {
        if ($serviceName == 'invoice') {
            return $serviceMock;
        }
        if ($serviceName == 'system') {
            return $systemService;
        }
    });
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $service->onEventBeforeInvoiceIsDue($eventMock);
});

test('skips before due invoice reminder when intervals are blank', function (): void {
    $service = new Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('sendInvoiceReminder')
        ->never();

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn(['days_left' => 7, 'id' => 1]);

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_reminder_before_due_days', '')
        ->andReturn('');

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->never();

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($serviceMock, $systemService) {
        if ($serviceName == 'invoice') {
            return $serviceMock;
        }
        if ($serviceName == 'system') {
            return $systemService;
        }
    });
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $service->onEventBeforeInvoiceIsDue($eventMock);
});

test('marks invoice as paid', function (): void {
    $service = new Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('countIncome')
        ->atLeast()->once();

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
    $invoiceModel->status = Model_Invoice::STATUS_UNPAID;

    $invoiceItemModel = new Model_InvoiceItem();
    $invoiceItemModel->loadBean(new Tests\Helpers\DummyBean());

    $itemInvoiceServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $itemInvoiceServiceMock->shouldReceive('markAsPaid')
        ->atLeast()->once();
    $itemInvoiceServiceMock->shouldReceive('executeTask')
        ->atLeast()->once();

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->atLeast()->once();

    $currencyRepositoryMock = Mockery::mock(CurrencyRepository::class)->shouldIgnoreMissing();
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
    $productServiceMock = Mockery::mock(ProductService::class)->shouldIgnoreMissing();
    $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($systemService, $itemInvoiceServiceMock, $currencyServiceMock, $productServiceMock) {
        if ($serviceName == 'system') {
            return $systemService;
        }
        if ($sub == 'InvoiceItem') {
            return $itemInvoiceServiceMock;
        }
        if ($serviceName == 'currency') {
            return $currencyServiceMock;
        }
        if (strtolower($serviceName) == 'product') {
            return $productServiceMock;
        }
    });
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventManagerMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $result = $serviceMock->markAsPaid($invoiceModel, true, true);
    expect($result)->toBeBool()->toBeTrue();
});

test('admin mark as paid with custom gateway records transaction and marks invoice paid', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('markAsPaid')
        ->once()
        ->with(Mockery::type(Model_Invoice::class), false, true)
        ->andReturn(true);
    $serviceMock->shouldReceive('getTotalWithTax')
        ->once()
        ->with(Mockery::type(Model_Invoice::class))
        ->andReturn(42.50);

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
    $invoiceModel->id = 10;
    $invoiceModel->gateway_id = 5;
    $invoiceModel->currency = 'USD';
    $invoiceModel->status = Model_Invoice::STATUS_UNPAID;

    $gatewayModel = new Model_PayGateway();
    $gatewayModel->loadBean(new Tests\Helpers\DummyBean());
    $gatewayModel->id = 5;
    $gatewayModel->gateway = 'Custom';
    $gatewayModel->enabled = 1;
    $gatewayModel->title = 'Manual payment';

    $transactionModel = new Model_Transaction();
    $transactionModel->loadBean(new Tests\Helpers\DummyBean());
    $transactionModel->invoice_id = 10;

    $transactionServiceMock = Mockery::mock(Box\Mod\Invoice\ServiceTransaction::class);
    $transactionServiceMock->shouldReceive('create')
        ->once()
        ->with(Mockery::on(fn (array $data): bool => $data['invoice_id'] === 10
            && $data['gateway_id'] === 5
            && $data['currency'] === 'USD'
            && $data['source'] === 'admin'
            && $data['post'] === ['invoice_id' => 10, 'txn_id' => 'manual-reference-1']
            && $data['txn_id'] === 'manual-reference-1'))
        ->andReturn(20);
    $transactionServiceMock->shouldNotReceive('processTransaction');

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->once()
        ->with('PayGateway', 5, 'Payment gateway not found')
        ->andReturn($gatewayModel);
    $dbMock->shouldReceive('getExistingModelById')
        ->once()
        ->with('Transaction', 20, 'Transaction not found')
        ->andReturn($transactionModel);
    $dbMock->shouldReceive('store')
        ->once()
        ->with($transactionModel)
        ->andReturn(20);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(moduleService([
        'invoice:transaction' => $transactionServiceMock,
    ]));

    $serviceMock->setDi($di);

    $result = $serviceMock->markAsPaidByAdmin($invoiceModel, [
        'execute' => true,
        'transactionId' => 'manual-reference-1',
    ]);

    expect($result)->toBeTrue()
        ->and($transactionModel->amount)->toBe(42.50)
        ->and($transactionModel->currency)->toBe('USD')
        ->and($transactionModel->status)->toBe(Model_Transaction::STATUS_PROCESSED)
        ->and($transactionModel->note)->toBe('Manual payment transaction No: manual-reference-1');
});

test('admin mark as paid with custom gateway rejects transaction linked to another invoice', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldNotReceive('markAsPaid');
    $serviceMock->shouldReceive('getTotalWithTax')
        ->once()
        ->with(Mockery::type(Model_Invoice::class))
        ->andReturn(42.50);

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
    $invoiceModel->id = 10;
    $invoiceModel->gateway_id = 5;
    $invoiceModel->currency = 'USD';
    $invoiceModel->status = Model_Invoice::STATUS_UNPAID;

    $gatewayModel = new Model_PayGateway();
    $gatewayModel->loadBean(new Tests\Helpers\DummyBean());
    $gatewayModel->id = 5;
    $gatewayModel->gateway = 'Custom';
    $gatewayModel->enabled = 1;

    $transactionModel = new Model_Transaction();
    $transactionModel->loadBean(new Tests\Helpers\DummyBean());
    $transactionModel->invoice_id = 99;

    $transactionServiceMock = Mockery::mock(Box\Mod\Invoice\ServiceTransaction::class);
    $transactionServiceMock->shouldReceive('create')
        ->once()
        ->andReturn(20);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->once()
        ->with('PayGateway', 5, 'Payment gateway not found')
        ->andReturn($gatewayModel);
    $dbMock->shouldReceive('getExistingModelById')
        ->once()
        ->with('Transaction', 20, 'Transaction not found')
        ->andReturn($transactionModel);
    $dbMock->shouldNotReceive('store');

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(moduleService([
        'invoice:transaction' => $transactionServiceMock,
    ]));

    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->markAsPaidByAdmin($invoiceModel, [
        'transactionId' => 'manual-reference-1',
    ]))->toThrow(FOSSBilling\InformationException::class, 'Transaction ID is already associated with another invoice.');
});

test('counts income', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
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

    $clientModel = new Model_Client();
    $clientModel->loadBean(new Tests\Helpers\DummyBean());

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

    $currencyModel = Mockery::mock(CurrencyEntity::class);
    $defaultCurrencyCode = 'USD';
    $currencyModel->shouldReceive('getCode')
        ->andReturn($defaultCurrencyCode);

    $currencyRepositoryMock = Mockery::mock(CurrencyRepository::class)->shouldIgnoreMissing();
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
        if ($serviceName == 'currency' || $serviceName == 'Currency') {
            return $currencyServiceMock;
        }
        if ($sub == 'InvoiceItem') {
            return $itemInvoiceServiceMock;
        }
    });
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $result = $serviceMock->prepareInvoice($clientModel, $data);
    expect($result)->toBeInstanceOf(Model_Invoice::class);
    expect($result->currency)->toBe($defaultCurrencyCode);
});

test('sets invoice defaults', function (): void {
    $service = new Service();
    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

    $clientModel = new Model_Client();
    $clientModel->loadBean(new Tests\Helpers\DummyBean());

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
    $service = new Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('tryPayWithCredits')
        ->atLeast()->once();
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn(['id' => 1]);

    $data['use_credits'] = true;

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->atLeast()->once();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventManagerMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    $result = $serviceMock->approveInvoice($invoiceModel, $data);
    expect($result)->toBeTrue();
});

test('gets total with tax', function (): void {
    $service = new Service();
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

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

    $result = $serviceMock->getTotalWithTax($invoiceModel);
    expect($result)->toBeFloat();
    expect($result)->toBe($expected);
});

test('gets total', function (): void {
    $service = new Service();
    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

    $invoiceItemModel = new Model_InvoiceItem();
    $invoiceItemModel->loadBean(new Tests\Helpers\DummyBean());

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
    $service = new Service();
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

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
    $invoiceModel->id = $newId;

    $invoiceItemModel = new Model_InvoiceItem();
    $invoiceItemModel->loadBean(new Tests\Helpers\DummyBean());

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
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $result = $serviceMock->refundInvoice($invoiceModel, 'customNote');
    expect($result)->toBeInt()->toBe($newId);
});

test('updates an invoice', function (): void {
    $service = new Service();
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

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

    $invoiceItemModel = new Model_InvoiceItem();
    $invoiceItemModel->loadBean(new Tests\Helpers\DummyBean());

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
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn(['id' => 1]);

    $serviceMock->setDi($di);

    $result = $serviceMock->updateInvoice($invoiceModel, $data);
    expect($result)->toBeTrue();
});

test('removes an invoice', function (): void {
    $service = new Service();
    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

    $invoiceItemModel = new Model_InvoiceItem();
    $invoiceItemModel->loadBean(new Tests\Helpers\DummyBean());

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
    $service = new Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('rmInvoice')
        ->once();

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->atLeast()->once();

    $di = container();
    $di['events_manager'] = $eventManagerMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    $result = $serviceMock->deleteInvoiceByAdmin($invoiceModel);
    expect($result)->toBeTrue();
});

test('renews an invoice', function (): void {
    $service = new Service();
    $newId = 2;
    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
    $invoiceModel->id = $newId;

    $clientOrder = new Model_ClientOrder();
    $clientOrder->loadBean(new Tests\Helpers\DummyBean());

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
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $result = $serviceMock->renewInvoice($clientOrder, []);
    expect($result)->toBeInt()->toBe($newId);
});

test('processes batch pay with credits', function (): void {
    $service = new Service();
    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

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
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['db'] = $dbMock;

    $serviceMock->setDi($di);
    $result = $serviceMock->doBatchPayWithCredits([]);
    expect($result)->toBeBool()->toBeTrue();
});

test('pays invoice with credits', function (): void {
    $service = new Service();
    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('tryPayWithCredits')
        ->atLeast()->once();

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $result = $serviceMock->payInvoiceWithCredits($invoiceModel);
    expect($result)->toBeBool()->toBeTrue();
});

test('returns existing invoice when generating for order with unpaid invoice', function (): void {
    $service = new Service();
    $clientOrder = new Model_ClientOrder();
    $clientOrder->loadBean(new Tests\Helpers\DummyBean());
    $clientOrder->unpaid_invoice_id = 2;

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
    $invoiceModel->status = Model_Invoice::STATUS_UNPAID;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($invoiceModel);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);
    $result = $service->generateForOrder($clientOrder);
    expect($result)->toBeInstanceOf(Model_Invoice::class);
});

test('clears stale paid invoice reference when generating for order', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('setInvoiceDefaults')
        ->once();

    $clientOrder = new Model_ClientOrder();
    $clientOrder->loadBean(new Tests\Helpers\DummyBean());
    $clientOrder->unpaid_invoice_id = 2;
    $clientOrder->price = 10;
    $clientOrder->quantity = 1;

    $paidInvoice = new Model_Invoice();
    $paidInvoice->loadBean(new Tests\Helpers\DummyBean());
    $paidInvoice->status = Model_Invoice::STATUS_PAID;

    $clientModel = new Model_Client();
    $clientModel->loadBean(new Tests\Helpers\DummyBean());

    $newInvoice = new Model_Invoice();
    $newInvoice->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->with('Invoice', 2)
        ->once()
        ->andReturn($paidInvoice);
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($clientModel);
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($newInvoice);
    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('unsetUnpaidInvoice')
        ->with($clientOrder)
        ->once();

    $invoiceItemServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $invoiceItemServiceMock->shouldReceive('generateFromOrder')
        ->with($newInvoice, $clientOrder, Model_InvoiceItem::TASK_RENEW, 10, Mockery::type('array'))
        ->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(function (string $module, ?string $submodule = null) use ($orderServiceMock, $invoiceItemServiceMock): Mockery\MockInterface {
        if ($module === 'Order') {
            return $orderServiceMock;
        }

        if ($module === 'Invoice' && $submodule === 'InvoiceItem') {
            return $invoiceItemServiceMock;
        }

        throw new RuntimeException(sprintf('Unexpected mod_service request: module "%s", submodule "%s"', $module, (string) $submodule));
    });

    $serviceMock->setDi($di);

    $result = $serviceMock->generateForOrder($clientOrder);

    expect($result)->toBe($newInvoice);
});

test('generates invoice for order', function (): void {
    $service = new Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('setInvoiceDefaults')
        ->once();

    $orderModel = new Model_ClientOrder();
    $orderModel->loadBean(new Tests\Helpers\DummyBean());
    $orderModel->price = 10;
    $orderModel->promo_recurring = true;

    $clientModel = new Model_Client();
    $clientModel->loadBean(new Tests\Helpers\DummyBean());

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

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
    expect($result)->toBeInstanceOf(Model_Invoice::class);
});

test('generates invoice for active order using the order price, not the product price', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('setInvoiceDefaults')
        ->once();

    $orderModel = new Model_ClientOrder();
    $orderModel->loadBean(new Tests\Helpers\DummyBean());
    $orderModel->status = Model_ClientOrder::STATUS_ACTIVE;
    $orderModel->product_id = 5;
    $orderModel->currency = 'USD';
    $orderModel->price = 25;
    $orderModel->quantity = 1;

    $clientModel = new Model_Client();
    $clientModel->loadBean(new Tests\Helpers\DummyBean());

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

    $product = Mockery::mock(Product::class)->makePartial();
    $product->shouldReceive('getType')->andReturn('hosting');

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('findProductById')
        ->with(5)
        ->once()
        ->andReturn($product);
    $productService->shouldReceive('getProductRenewalLineConfig')
        ->never();

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
        ->with($invoiceModel, $orderModel, Model_InvoiceItem::TASK_RENEW, 25, Mockery::on(fn ($line): bool => $line['price'] === 25 && $line['quantity'] === 1))
        ->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(function (string $module) use ($productService, $invoiceItemServiceMock): Mockery\MockInterface {
        if ($module === 'Product') {
            return $productService;
        }

        return $invoiceItemServiceMock;
    });

    $serviceMock->setDi($di);
    $result = $serviceMock->generateForOrder($orderModel);
    expect($result)->toBeInstanceOf(Model_Invoice::class);
});

test('throws exception when generating invoice for zero amount order', function (): void {
    $service = new Service();
    $clientOrder = new Model_ClientOrder();
    $clientOrder->loadBean(new Tests\Helpers\DummyBean());
    $clientOrder->price = 0;

    expect(fn () => $service->generateForOrder($clientOrder))
        ->toThrow(FOSSBilling\Exception::class, 'Invoices are not generated for 0 amount orders');
});

test('returns true when no expiring orders found', function (): void {
    $service = new Service();
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
    $service = new Service();
    $clientOrder = new Model_ClientOrder();
    $clientOrder->loadBean(new Tests\Helpers\DummyBean());

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

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
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['db'] = $dbMock;

    $serviceMock->setDi($di);
    $result = $serviceMock->generateInvoicesForExpiringOrders();
    expect($result)->toBeBool()->toBeTrue();
});

test('activates paid invoices in batch', function (): void {
    $service = new Service();
    $invoiceItemModel = new Model_InvoiceItem();
    $invoiceItemModel->loadBean(new Tests\Helpers\DummyBean());

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
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['db'] = $dbMock;

    $service->setDi($di);
    $result = $service->doBatchPaidInvoiceActivation();
    expect($result)->toBeBool()->toBeTrue();
});

test('handles exception during batch paid invoice activation', function (): void {
    $service = new Service();
    $invoiceItemModel = new Model_InvoiceItem();
    $invoiceItemModel->loadBean(new Tests\Helpers\DummyBean());

    $itemInvoiceServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $itemInvoiceServiceMock->shouldReceive('executeTask')
        ->with($invoiceItemModel)
        ->andThrow(new FOSSBilling\Exception('testing exception..'));
    $itemInvoiceServiceMock->shouldReceive('getAllNotExecutePaidItems')
        ->atLeast()->once()
        ->andReturn([[]]);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($invoiceItemModel);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $itemInvoiceServiceMock);
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['db'] = $dbMock;

    $service->setDi($di);
    $result = $service->doBatchPaidInvoiceActivation();
    expect($result)->toBeBool()->toBeTrue();
});

test('sends reminders in batch', function (): void {
    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('doBatchInvokeDueEvent')
        ->once()
        ->with(['once_per_day' => false])
        ->andReturnTrue();

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->atLeast()->once();

    $di = container();
    $di['events_manager'] = $eventManagerMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);
    $result = $service->doBatchRemindersSend();
    expect($result)->toBeBool()->toBeTrue();
});

test('invokes due event in batch', function (): void {
    $service = new Service();
    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_overdue_invoked')
        ->andReturn(null);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_reminder_before_due_days', '')
        ->andReturn('14, 7, 1');
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_reminder_after_due_days', '5')
        ->andReturn('5');
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
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    $result = $service->doBatchInvokeDueEvent([]);
    expect($result)->toBeBool()->toBeTrue();
});

test('protects from sending reminders to paid invoices', function (): void {
    $service = new Service();
    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
    $invoiceModel->status = Model_Invoice::STATUS_PAID;

    $result = $service->sendInvoiceReminder($invoiceModel);
    expect($result)->toBeBool()->toBeTrue();
});

test('parses invoice reminder intervals', function (): void {
    $service = new Service();

    expect($service->parseInvoiceReminderIntervals('14, 7 1,7, 0, no'))
        ->toBe([1, 7, 14]);
});

test('sends invoice reminder', function (): void {
    $service = new Service();
    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->atLeast()->once();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventManagerMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    $result = $service->sendInvoiceReminder($invoiceModel);
    expect($result)->toBeBool()->toBeTrue();
});

test('counts invoices', function (): void {
    $service = new Service();
    $sqlResult = [
        ['status' => Model_Invoice::STATUS_PAID,
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
    $service = new Service();
    $clientModel = new Model_Client();
    $clientModel->loadBean(new Tests\Helpers\DummyBean());

    expect(fn () => $service->generateFundsInvoice($clientModel, 10))
        ->toThrow(FOSSBilling\Exception::class, 'You must have at least one active order before you can add funds so you cannot proceed at the current time!');
});

test('throws exception when generating funds invoice below minimum amount', function (): void {
    $service = new Service();
    $clientModel = new Model_Client();
    $clientModel->loadBean(new Tests\Helpers\DummyBean());
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
        ->toThrow(FOSSBilling\Exception::class, 'Amount must be at least ' . $minAmount);
});

test('throws exception when generating funds invoice above maximum amount', function (): void {
    $service = new Service();
    $clientModel = new Model_Client();
    $clientModel->loadBean(new Tests\Helpers\DummyBean());
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
        ->toThrow(FOSSBilling\Exception::class, 'Amount cannot exceed ' . $maxAmount);
});

test('generates funds invoice', function (): void {
    $service = new Service();
    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

    $clientModel = new Model_Client();
    $clientModel->loadBean(new Tests\Helpers\DummyBean());
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
            ++$paramCallCount;
            if ($paramCallCount === 1) {
                return $minAmount;
            }
            if ($paramCallCount === 2) {
                return $maxAmount;
            }

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
    expect($result)->toBeInstanceOf(Model_Invoice::class);
});

test('throws exception when processing invoice not found', function (): void {
    $service = new Service();
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

    expect(fn (): array => $service->processInvoice($data))
        ->toThrow(FOSSBilling\InformationException::class, 'Invoice not found');
});

test('throws exception when processing invoice with gateway not found', function (): void {
    $service = new Service();
    $data = [
        'hash' => 'hashString',
        'gateway_id' => 2,
    ];

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

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

    expect(fn (): array => $service->processInvoice($data))
        ->toThrow(FOSSBilling\InformationException::class, 'Payment method not found');
});

test('throws exception when processing invoice with gateway not enabled', function (): void {
    $service = new Service();
    $data = [
        'hash' => 'hashString',
        'gateway_id' => 2,
    ];

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

    $payGatewayModel = new Model_PayGateway();
    $payGatewayModel->loadBean(new Tests\Helpers\DummyBean());

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

    expect(fn (): array => $service->processInvoice($data))
        ->toThrow(FOSSBilling\Exception::class, 'Payment method not enabled');
});

test('processes an invoice', function (): void {
    $service = new Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getPaymentInvoice')
        ->atLeast()->once()
        ->andReturn(new Payment_Invoice());

    $data = [
        'hash' => 'hashString',
        'gateway_id' => 2,
    ];

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

    $payGatewayModel = new Model_PayGateway();
    $payGatewayModel->loadBean(new Tests\Helpers\DummyBean());
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
    $di['api_admin'] = new FOSSBilling\Api\Proxy(new Model_Admin());
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $result = $serviceMock->processInvoice($data);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('type');
    expect($result)->toHaveKey('service_url');
    expect($result)->toHaveKey('subscription');
    expect($result)->toHaveKey('result');
});

test('paypal email html generation does not require admin api invoice access', function (): void {
    $adapter = new Payment_Adapter_PayPalEmail([
        'email' => 'payments@example.com',
        'test_mode' => false,
        'auto_redirect' => false,
        'thankyou_url' => 'https://example.com/invoice/thank-you/hash',
        'cancel_url' => 'https://example.com/invoice/hash',
        'notify_url' => 'https://example.com/ipn.php',
    ]);

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->once()
        ->with('Invoice', 1)
        ->andReturn($invoiceModel);

    $invoiceService = Mockery::mock(Service::class);
    $invoiceService->shouldReceive('toApiArray')
        ->once()
        ->with($invoiceModel, true)
        ->andReturn([
            'id' => 1,
            'nr' => '1001',
            'serie' => 'INV-',
            'currency' => 'USD',
            'subtotal' => 10.00,
            'tax' => 0.00,
            'lines' => [
                ['title' => 'Hosting'],
            ],
        ]);

    $apiAdmin = Mockery::mock();
    $apiAdmin->shouldNotReceive('invoice_get');

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(function ($serviceName) use ($invoiceService) {
        if ($serviceName === 'Invoice') {
            return $invoiceService;
        }
    });

    $adapter->setDi($di);

    $html = $adapter->getHtml($apiAdmin, 1, false);

    expect($html)->toContain('https://www.paypal.com/cgi-bin/webscr');
    expect($html)->toContain('payments@example.com');
    expect($html)->toContain('Pay with PayPal');
});

test('adds note to invoice', function (): void {
    $service = new Service();
    $note = 'test Note';

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

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
    $service = new Service();
    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

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
    $service = new Service();
    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([$invoiceModel]);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->findAllPaid();
    expect($result)->toBeArray();
    expect($result[0])->toBeInstanceOf(Model_Invoice::class);
});

test('gets unpaid invoices late for', function (): void {
    $service = new Service();
    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([$invoiceModel]);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->getUnpaidInvoicesLateFor();
    expect($result)->toBeArray();
    expect($result[0])->toBeInstanceOf(Model_Invoice::class);
});

test('gets buyer', function (): void {
    $service = new Service();
    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
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
    $service = new Service();
    $di = container();

    $modelInvoiceItem = new Model_InvoiceItem();
    $modelInvoiceItem->loadBean(new Tests\Helpers\DummyBean());
    $modelInvoiceItem->type = Model_InvoiceItem::TYPE_DEPOSIT;

    $invoiceItems = [$modelInvoiceItem];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn($invoiceItems);

    $di['db'] = $dbMock;

    $modelInvoice = new Model_Invoice();
    $modelInvoice->loadBean(new Tests\Helpers\DummyBean());

    $service->setDi($di);
    $result = $service->isInvoiceTypeDeposit($modelInvoice);
    expect($result)->toBeTrue();
});

test('returns false when invoice type is not deposit', function (): void {
    $service = new Service();
    $di = container();

    $modelInvoiceItem = new Model_InvoiceItem();
    $modelInvoiceItem->loadBean(new Tests\Helpers\DummyBean());
    $modelInvoiceItem->type = Model_InvoiceItem::TYPE_ORDER;

    $invoiceItems = [$modelInvoiceItem];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn($invoiceItems);

    $di['db'] = $dbMock;
    $service->setDi($di);

    $modelInvoice = new Model_Invoice();
    $modelInvoice->loadBean(new Tests\Helpers\DummyBean());

    $result = $service->isInvoiceTypeDeposit($modelInvoice);
    expect($result)->toBeFalse();
});

test('returns false when checking deposit with empty items', function (): void {
    $service = new Service();
    $di = container();

    $invoiceItems = [];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn($invoiceItems);

    $di['db'] = $dbMock;
    $service->setDi($di);

    $modelInvoice = new Model_Invoice();
    $modelInvoice->loadBean(new Tests\Helpers\DummyBean());

    $result = $service->isInvoiceTypeDeposit($modelInvoice);
    expect($result)->toBeFalse();
});

test('validatePaymentAmount passes for exact match', function (): void {
    $service = new Service();
    $di = container();
    $service->setDi($di);

    $service->validatePaymentAmount(50.00, 50.00);
    expect(true)->toBeTrue();
});

test('validatePaymentAmount passes within epsilon tolerance', function (): void {
    $service = new Service();
    $di = container();
    $service->setDi($di);

    $service->validatePaymentAmount(49.99, 50.00);
    expect(true)->toBeTrue();
});

test('validatePaymentAmount throws on underpayment', function (): void {
    $service = new Service();
    $di = container();
    $service->setDi($di);

    expect(fn () => $service->validatePaymentAmount(40.00, 50.00))
        ->toThrow(FOSSBilling\Exception::class);
});

test('validatePaymentAmount logs warning on significant overpayment', function (): void {
    $service = new Service();
    $logger = new Tests\Helpers\TestLogger();
    $di = container();
    $di['logger'] = $logger;
    $service->setDi($di);

    $service->validatePaymentAmount(60.00, 50.00);

    $warnings = array_filter($logger->calls, fn ($c): bool => $c['method'] === 'warning');
    expect($warnings)->not->toBeEmpty();
});

test('validatePaymentAmount does not warn for minor overpayment within tolerance', function (): void {
    $service = new Service();
    $logger = new Tests\Helpers\TestLogger();
    $di = container();
    $di['logger'] = $logger;
    $service->setDi($di);

    $service->validatePaymentAmount(50.50, 50.00);

    $warnings = array_filter($logger->calls, fn ($c): bool => $c['method'] === 'warning');
    expect($warnings)->toBeEmpty();
});

test('generateRenewalInvoiceForSubscriptionPayment returns null when subscription not found', function (): void {
    $service = new Service();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->with('Subscription', 'sid = :sid', Mockery::any())
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->generateRenewalInvoiceForSubscriptionPayment('I-TEST123', 1);
    expect($result)->toBeNull();
});

test('generateRenewalInvoiceForSubscriptionPayment returns null when original order is not active', function (): void {
    $service = new Service();

    $subscription = new Model_Subscription();
    $subscription->loadBean(new Tests\Helpers\DummyBean());
    $subscription->rel_type = 'invoice';
    $subscription->rel_id = 82;

    $invoiceItem = new Model_InvoiceItem();
    $invoiceItem->loadBean(new Tests\Helpers\DummyBean());
    $invoiceItem->rel_id = 82;

    $originalOrder = new Model_ClientOrder();
    $originalOrder->loadBean(new Tests\Helpers\DummyBean());
    $originalOrder->status = Model_ClientOrder::STATUS_PENDING_SETUP;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->with('Subscription', 'sid = :sid', Mockery::any())
        ->andReturn($subscription);
    $dbMock->shouldReceive('findOne')
        ->with('InvoiceItem', Mockery::any(), Mockery::any())
        ->andReturn($invoiceItem);
    $dbMock->shouldReceive('load')
        ->with('ClientOrder', 82)
        ->andReturn($originalOrder);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->generateRenewalInvoiceForSubscriptionPayment('I-TEST123', 1);
    expect($result)->toBeNull();
});

test('generateRenewalInvoiceForSubscriptionPayment uses the original order and not a product_id lookup', function (): void {
    $subscription = new Model_Subscription();
    $subscription->loadBean(new Tests\Helpers\DummyBean());
    $subscription->rel_type = 'invoice';
    $subscription->rel_id = 82;

    $invoiceItem = new Model_InvoiceItem();
    $invoiceItem->loadBean(new Tests\Helpers\DummyBean());
    $invoiceItem->rel_id = 82;

    $originalOrder = new Model_ClientOrder();
    $originalOrder->loadBean(new Tests\Helpers\DummyBean());
    $originalOrder->status = Model_ClientOrder::STATUS_ACTIVE;
    $originalOrder->product_id = 1;

    $renewalInvoice = new Model_Invoice();
    $renewalInvoice->loadBean(new Tests\Helpers\DummyBean());
    $renewalInvoice->id = 99;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->with('Subscription', 'sid = :sid', Mockery::any())
        ->andReturn($subscription);
    $dbMock->shouldReceive('findOne')
        ->with('InvoiceItem', Mockery::any(), Mockery::any())
        ->andReturn($invoiceItem);
    $dbMock->shouldReceive('load')
        ->with('ClientOrder', 82)
        ->andReturn($originalOrder);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('generateForOrder')
        ->with(Mockery::on(fn ($order): bool => $order === $originalOrder))
        ->once()
        ->andReturn($renewalInvoice);
    $serviceMock->shouldReceive('approveInvoice')
        ->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $result = $serviceMock->generateRenewalInvoiceForSubscriptionPayment('I-TEST123', 1);

    expect($result)->toBeInstanceOf(Model_Invoice::class);
    expect($result->id)->toBe(99);
});

test('markAsPaid transitions a deposit invoice to paid status', function (): void {
    $service = new Service();

    $depositInvoice = new Model_Invoice();
    $depositInvoice->loadBean(new Tests\Helpers\DummyBean());
    $depositInvoice->id = 89;
    $depositInvoice->status = Model_Invoice::STATUS_UNPAID;
    $depositInvoice->approved = true;
    $depositInvoice->currency = 'USD';

    $depositItem = new Model_InvoiceItem();
    $depositItem->loadBean(new Tests\Helpers\DummyBean());
    $depositItem->id = 96;
    $depositItem->type = Model_InvoiceItem::TYPE_DEPOSIT;
    $depositItem->task = 'void';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->with('InvoiceItem', 'invoice_id = ?', [89])
        ->andReturn([$depositItem]);
    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_series_paid')
        ->andReturn('FOSS');

    $currencyService = Mockery::mock(CurrencyService::class);
    $currencyService->shouldReceive('toBaseCurrency')
        ->andReturn(30.0);
    $currencyRepo = Mockery::mock(CurrencyRepository::class);
    $currencyRepo->shouldReceive('getRateByCode')
        ->with('USD')
        ->andReturn(1.0);
    $currencyService->shouldReceive('getCurrencyRepository')
        ->andReturn($currencyRepo);

    $invoiceItemService = Mockery::mock(ServiceInvoiceItem::class);
    $invoiceItemService->shouldReceive('markAsPaid')
        ->atLeast()->once();
    $invoiceItemService->shouldReceive('getTotal')
        ->andReturn(30.0);

    $eventsManager = Mockery::mock('\Box_EventManager');
    $eventsManager->shouldReceive('fire')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $productService = Mockery::mock(ProductService::class)->shouldIgnoreMissing();
    $di['mod_service'] = $di->protect(fn ($name, $sub = '') => match ([$name, $sub]) {
        ['system', ''] => $systemService,
        ['currency', ''] => $currencyService,
        ['Invoice', 'InvoiceItem'] => $invoiceItemService,
        ['Product', ''], ['product', ''] => $productService,
        default => throw new RuntimeException("Unexpected service: {$name}/{$sub}"),
    });
    $di['events_manager'] = $eventsManager;
    $service->setDi($di);

    $result = $service->markAsPaid($depositInvoice);

    expect($result)->toBeTrue();
    expect($depositInvoice->status)->toBe(Model_Invoice::STATUS_PAID);
    expect($depositInvoice->paid_at)->not->toBeNull();
});

test('getInvoicePdfAttachment returns null when the setting is disabled', function (): void {
    $service = new Service();

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_email_attach_pdf')
        ->atLeast()->once()
        ->andReturn(false);

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name) => match ($name) {
        'system' => $systemService,
        default => throw new RuntimeException("Unexpected service: {$name}"),
    });
    $service->setDi($di);

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

    expect($service->getInvoicePdfAttachment($invoiceModel))->toBeNull();
});

test('getInvoicePdfAttachment builds a sanitized PDF attachment when enabled', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_email_attach_pdf')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name) => match ($name) {
        'system' => $systemService,
        default => throw new RuntimeException("Unexpected service: {$name}"),
    });
    $serviceMock->setDi($di);

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

    $serviceMock->shouldReceive('toApiArray')
        ->once()
        ->with($invoiceModel, false)
        ->andReturn(['serie_nr' => 'BB/2026/00042']);
    $serviceMock->shouldReceive('renderInvoicePdfContent')
        ->once()
        ->with($invoiceModel, ['serie_nr' => 'BB/2026/00042'])
        ->andReturn('%PDF-1.4 fake invoice contents');

    $result = $serviceMock->getInvoicePdfAttachment($invoiceModel);

    expect($result)->toBe([
        'content' => '%PDF-1.4 fake invoice contents',
        'name' => 'BB-2026-00042.pdf',
        'mime' => 'application/pdf',
    ]);
});

test('getInvoicePdfAttachment returns null and logs when PDF generation fails', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_email_attach_pdf')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name) => match ($name) {
        'system' => $systemService,
        default => throw new RuntimeException("Unexpected service: {$name}"),
    });
    $logger = new Tests\Helpers\TestLogger();
    $di['logger'] = $logger;
    $serviceMock->setDi($di);

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

    $serviceMock->shouldReceive('toApiArray')
        ->once()
        ->andThrow(new Exception('boom'));

    $result = $serviceMock->getInvoicePdfAttachment($invoiceModel);

    expect($result)->toBeNull();
    $errors = array_filter($logger->calls, fn ($c): bool => $c['method'] === 'error');
    expect($errors)->not->toBeEmpty();
});
