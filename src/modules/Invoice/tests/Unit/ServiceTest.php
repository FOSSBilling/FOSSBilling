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
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Service;
use Box\Mod\Invoice\ServiceInvoiceItem;
use Box\Mod\Invoice\ServiceSubscription;
use Box\Mod\Invoice\ServiceTax;
use Box\Mod\Order\Service as OrderService;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Product\Service as ProductService;
use Box\Mod\System\Service as SystemService;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;
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
    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class, ['id' => 1, 'hash' => 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4']);

    $invoiceItemModel = createEntity(\Box\Mod\Invoice\Entity\InvoiceItem::class);

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
    $dbMock->shouldNotReceive('toArray');

    $subscriptionServiceMock->shouldReceive('getSubscriptionPeriod')
        ->byDefault()
        ->andReturn('1W');

    $periodMock = Mockery::mock('\Box_Period');
    $periodMock->shouldReceive('getUnit');
    $periodMock->shouldReceive('getQty');

    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\InvoiceItem::class)
        ->andReturnUsing(function () use ($invoiceItemModel) {
            $repo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceItemRepository::class);
            $repo->shouldReceive('findByInvoiceId')->withAnyArgs()->andReturn([$invoiceItemModel]);

            return $repo;
        });
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
    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class, ['id' => 1, 'hash' => 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4']);

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
    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class);

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_hash_lifetime_days', '90')
        ->andReturn('90');

    $di = container();
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
    expect($invoiceModel->hash_expires_at)->toBeInstanceOf(\DateTimeInterface::class);
});

test('ensure valid hash regenerates a legacy format hash', function (): void {
    $service = new Service();
    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class, ['hash' => 'AAAAAAAAC4C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8']);

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_hash_lifetime_days', '90')
        ->andReturn('90');

    $di = container();
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
    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class, ['id' => 1]);

    $invoiceItemModel = createEntity(\Box\Mod\Invoice\Entity\InvoiceItem::class);

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
    $dbMock->shouldNotReceive('toArray');

    $periodMock = Mockery::mock('\Box_Period');
    $periodMock->shouldReceive('getUnit');
    $periodMock->shouldReceive('getQty');

    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\InvoiceItem::class)
        ->andReturnUsing(function () use ($invoiceItemModel) {
            $repo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceItemRepository::class);
            $repo->shouldReceive('findByInvoiceId')->withAnyArgs()->andReturn([$invoiceItemModel]);

            return $repo;
        });
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

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($emailService, $serviceMock) {
        if ($serviceName === 'invoice') {
            return $serviceMock;
        }
        if ($serviceName === 'email') {
            return $emailService;
        }
    });

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

test('uses the client billing email for invoice notifications', function (): void {
    $service = new Service();
    $email = ['to_client' => 42, 'code' => 'mod_invoice_created'];
    $invoice = ['client' => ['billing_email' => ' billing@example.com ']];

    expect($service->withBillingRecipient($email, $invoice))->toBe([
        'to_client' => 42,
        'code' => 'mod_invoice_created',
        'to' => 'billing@example.com',
    ]);
    expect($service->withBillingRecipient($email, ['client' => ['billing_email' => null]]))->toBe($email);
});

test('handles event after invoice is due', function (): void {
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

    $di = container();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('exec')
        ->once()
        ->with(Mockery::type('string'), [':id' => 1])
        ->andReturn(1);
    $di['db'] = $dbMock;
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

    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);
    $serviceMock->onEventAfterInvoiceIsDue($eventMock);
});

test('releases the claim when sending the overdue invoice email fails', function (): void {
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
        ->atLeast()->once()
        ->andThrow(new Exception('SMTP timeout'));

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_reminder_after_due_days', '5')
        ->andReturn('1, 5, 7');

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('exec')
        ->once()
        ->with(Mockery::type('string'), [':id' => 1])
        ->andReturn(1);
    $dbMock->shouldReceive('exec')
        ->once()
        ->with('UPDATE invoice SET reminded_at = NULL WHERE id = :id', [':id' => 1]);

    $logger = new Tests\Helpers\TestLogger();

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
    $di['logger'] = $logger;

    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);
    $serviceMock->onEventAfterInvoiceIsDue($eventMock);

    expect($logger->calls)->not->toBeEmpty();
});

test('releases the overdue reminder claim when invoice client data is unavailable', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('toApiArray')
        ->once()
        ->andReturn(['total' => 1, 'client' => null]);
    $serviceMock->shouldReceive('getInvoicePdfAttachment')
        ->never();

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn(['days_passed' => 5, 'id' => 1]);

    $emailService = Mockery::mock(EmailService::class);
    $emailService->shouldReceive('sendTemplate')
        ->never();

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_reminder_after_due_days', '5')
        ->andReturn('1, 5, 7');

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('exec')
        ->once()
        ->with(Mockery::type('string'), [':id' => 1])
        ->andReturn(1);
    $dbMock->shouldReceive('exec')
        ->once()
        ->with('UPDATE invoice SET reminded_at = NULL WHERE id = :id', [':id' => 1]);

    $logger = new Tests\Helpers\TestLogger();

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
    $di['logger'] = $logger;

    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);
    $serviceMock->onEventAfterInvoiceIsDue($eventMock);

    expect($logger->calls)->not->toBeEmpty();
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

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('exec')
        ->once()
        ->with(Mockery::type('string'), [':id' => 1])
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(function ($serviceName) use ($serviceMock, $systemService) {
        if ($serviceName == 'invoice') {
            return $serviceMock;
        }
        if ($serviceName == 'system') {
            return $systemService;
        }
    });
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $service->onEventBeforeInvoiceIsDue($eventMock);
});

test('releases the claim when sending the before-due invoice reminder fails', function (): void {
    $service = new Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('sendInvoiceReminder')
        ->once()
        ->andThrow(new Exception('DB write failed'));

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn(['days_left' => 7, 'id' => 1]);

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_reminder_before_due_days', '')
        ->andReturn('14, 7, 1');

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('exec')
        ->once()
        ->with(Mockery::type('string'), [':id' => 1])
        ->andReturn(1);
    $dbMock->shouldReceive('exec')
        ->once()
        ->with('UPDATE invoice SET reminded_at = NULL WHERE id = :id', [':id' => 1]);

    $logger = new Tests\Helpers\TestLogger();

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
    $di['logger'] = $logger;

    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $service->onEventBeforeInvoiceIsDue($eventMock);

    expect($logger->calls)->not->toBeEmpty();
});

test('skips before due invoice reminder when the invoice was already claimed', function (): void {
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
        ->andReturn('14, 7, 1');

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('exec')
        ->once()
        ->with(Mockery::type('string'), [':id' => 1])
        ->andReturn(0);

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

    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class, ['status' => Model_Invoice::STATUS_UNPAID]);

    $invoiceItemModel = createEntity(\Box\Mod\Invoice\Entity\InvoiceItem::class);

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

    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\InvoiceItem::class)
        ->andReturnUsing(function () use ($invoiceItemModel) {
            $repo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceItemRepository::class);
            $repo->shouldReceive('findByInvoiceId')->withAnyArgs()->andReturn([$invoiceItemModel]);

            return $repo;
        });
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
        ->with(Mockery::type(\Box\Mod\Invoice\Entity\Invoice::class), false, true)
        ->andReturn(true);
    $serviceMock->shouldReceive('getTotalWithTax')
        ->once()
        ->with(Mockery::type(\Box\Mod\Invoice\Entity\Invoice::class))
        ->andReturn(42.50);

    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class, [
        'id' => 10,
        'gateway_id' => 5,
        'currency' => 'USD',
        'status' => Model_Invoice::STATUS_UNPAID,
    ]);

    $payGatewayEntity = new Box\Mod\Invoice\Entity\PayGateway();
    $gatewayProp = new ReflectionProperty($payGatewayEntity, 'gateway');
    $gatewayProp->setValue($payGatewayEntity, 'Custom');
    $enabledProp = new ReflectionProperty($payGatewayEntity, 'enabled');
    $enabledProp->setValue($payGatewayEntity, true);
    $idProp = new ReflectionProperty($payGatewayEntity, 'id');
    $idProp->setValue($payGatewayEntity, 5);
    $nameProp = new ReflectionProperty($payGatewayEntity, 'name');
    $nameProp->setValue($payGatewayEntity, 'Manual payment');

    $transactionEntity = new Box\Mod\Invoice\Entity\Transaction();
    $txIdProp = new ReflectionProperty($transactionEntity, 'id');
    $txIdProp->setValue($transactionEntity, 20);
    $txInvProp = new ReflectionProperty($transactionEntity, 'invoiceId');
    $txInvProp->setValue($transactionEntity, 10);

    $transactionServiceMock = Mockery::mock(Box\Mod\Invoice\ServiceTransaction::class);
    $transactionServiceMock->shouldReceive('create')
        ->once()
        ->with(Mockery::on(fn (array $data): bool => $data['invoice_id'] === 10
            && $data['gateway_id'] === 5
            && $data['currency'] === 'USD'
            && $data['source'] === 'admin'))
        ->andReturn(20);
    $transactionServiceMock->shouldNotReceive('processTransaction');

    $payGatewayRepo = Mockery::mock(Box\Mod\Invoice\Repository\PayGatewayRepository::class);
    $payGatewayRepo->shouldReceive('find')->with(5)->andReturn($payGatewayEntity);

    $transactionRepo = Mockery::mock(Box\Mod\Invoice\Repository\TransactionRepository::class);
    $transactionRepo->shouldReceive('find')->with(20)->andReturn($transactionEntity);

    $currencyRepositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class);
    $currencyRepositoryMock->shouldNotReceive('getRateByCode');

    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\PayGateway::class)
        ->andReturn($payGatewayRepo);
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\Transaction::class)
        ->andReturn($transactionRepo);
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Currency\Entity\Currency::class)
        ->andReturn($currencyRepositoryMock);

    $currencyServiceMock = Mockery::mock(Box\Mod\Currency\Service::class);

    $systemServiceMock = Mockery::mock(SystemService::class);

    $productServiceMock = Mockery::mock(ProductService::class)->shouldIgnoreMissing();

    $di['mod_service'] = $di->protect(function ($moduleName, $sub = '') use ($transactionServiceMock, $currencyServiceMock, $systemServiceMock, $productServiceMock) {
        if ($moduleName === 'Invoice' && $sub === 'Transaction') {
            return $transactionServiceMock;
        }
        if ($moduleName === 'Currency' || $moduleName === 'currency') {
            return $currencyServiceMock;
        }
        if ($moduleName === 'system' || $moduleName === 'System') {
            return $systemServiceMock;
        }
        if ($moduleName === 'Product' || $moduleName === 'product') {
            return $productServiceMock;
        }

        return null;
    });

    $serviceMock->setDi($di);

    $result = $serviceMock->markAsPaidByAdmin($invoiceModel, [
        'execute' => true,
        'transactionId' => 'manual-reference-1',
    ]);

    expect($result)->toBeTrue()
        ->and($transactionEntity->getAmount())->toBe('42.5')
        ->and($transactionEntity->getCurrency())->toBe('USD')
        ->and($transactionEntity->getStatus())->toBe(Model_Transaction::STATUS_PROCESSED)
        ->and(str_contains($transactionEntity->getNote(), 'Manual payment transaction No:'))->toBeTrue();
});

test('admin mark as paid with custom gateway rejects transaction linked to another invoice', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldNotReceive('markAsPaid');
    $serviceMock->shouldReceive('getTotalWithTax')
        ->once()
        ->with(Mockery::type(\Box\Mod\Invoice\Entity\Invoice::class))
        ->andReturn(42.50);

    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class, [
        'id' => 10,
        'gateway_id' => 5,
        'currency' => 'USD',
        'status' => Model_Invoice::STATUS_UNPAID,
    ]);

    $payGatewayEntity = new Box\Mod\Invoice\Entity\PayGateway();
    $gatewayProp = new ReflectionProperty($payGatewayEntity, 'gateway');
    $gatewayProp->setValue($payGatewayEntity, 'Custom');
    $enabledProp = new ReflectionProperty($payGatewayEntity, 'enabled');
    $enabledProp->setValue($payGatewayEntity, true);
    $idProp = new ReflectionProperty($payGatewayEntity, 'id');
    $idProp->setValue($payGatewayEntity, 5);

    $transactionEntity = new Box\Mod\Invoice\Entity\Transaction();
    $txIdProp = new ReflectionProperty($transactionEntity, 'id');
    $txIdProp->setValue($transactionEntity, 20);
    $txInvProp = new ReflectionProperty($transactionEntity, 'invoiceId');
    $txInvProp->setValue($transactionEntity, 99);

    $transactionServiceMock = Mockery::mock(Box\Mod\Invoice\ServiceTransaction::class);
    $transactionServiceMock->shouldReceive('create')
        ->once()
        ->andReturn(20);

    $payGatewayRepo = Mockery::mock(Box\Mod\Invoice\Repository\PayGatewayRepository::class);
    $payGatewayRepo->shouldReceive('find')->with(5)->andReturn($payGatewayEntity);

    $transactionRepo = Mockery::mock(Box\Mod\Invoice\Repository\TransactionRepository::class);
    $transactionRepo->shouldReceive('find')->with(20)->andReturn($transactionEntity);

    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\PayGateway::class)
        ->andReturn($payGatewayRepo);
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\Transaction::class)
        ->andReturn($transactionRepo);
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

    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class, [
        'id' => 1,
        'currency' => 'USD',
        'refund' => 0,
    ]);

    $currencyService = Mockery::mock(CurrencyService::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $currencyService->shouldReceive('toBaseCurrency')
        ->atLeast()->once()
        ->andReturn(0.0);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $currencyService);

    $serviceMock->setDi($di);
    $serviceMock->countIncome($invoiceModel);
});

test('prepares invoice with undefined currency', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('setInvoiceDefaults')
        ->atLeast()->once();

    $data = [
        'gateway_id' => null,
        'text_1' => '',
        'text_2' => '',
        'items' => [
            [
                'id' => 1,
            ],
        ],
        'approve',
    ];

    $clientModel = createEntity(\Box\Mod\Client\Entity\Client::class, ['id' => 1]);

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

    $di = container();
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
    expect($result)->toBeInstanceOf(Invoice::class);
    expect($result->getCurrency())->toBe($defaultCurrencyCode);
});

test('sets invoice defaults', function (): void {
    $service = new Service();
    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class, ['client_id' => 1]);

    $clientModel = createEntity(\Box\Mod\Client\Entity\Client::class);

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
        ->andReturn('1');
    $systemService->shouldReceive('setParamValue')
        ->atLeast()->once();

    $serviceTaxMock = Mockery::mock(ServiceTax::class);
    $serviceTaxMock->shouldReceive('getTaxRateForClient');

    $di = container();
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

    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class);

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->atLeast()->once();

    $di = container();
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

    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class);

    $result = $serviceMock->getTotalWithTax($invoiceModel);
    expect($result)->toBeFloat();
    expect($result)->toBe($expected);
});

test('pays a zero-total invoice without recording a balance transaction', function (): void {
    $invoice = createEntity(\Box\Mod\Invoice\Entity\Invoice::class, [
        'id' => 10,
        'client_id' => 20,
        'approved' => 1,
        'status' => Model_Invoice::STATUS_UNPAID,
    ]);

    $balanceService = Mockery::mock(Box\Mod\Client\ServiceBalance::class);
    $balanceService->shouldReceive('getClientBalance')->once()->with(Mockery::type(Box\Mod\Client\Entity\Client::class))->andReturn(0.0);

    $db = Mockery::mock(Box_Database::class);
    $db->shouldNotReceive('store');

    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getTotalWithTax')->once()->with($invoice)->andReturn(0.0);
    $service->shouldReceive('markAsPaid')->once()->with($invoice, false, true)->andReturn(true);

    $di = container();
    $di['db'] = $db;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $balanceService);
    $service->setDi($di);

    expect($service->tryPayWithCredits($invoice))->toBeTrue();
});

test('gets total', function (): void {
    $service = new Service();
    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class, ['id' => 1]);

    $invoiceItemModel = createEntity(\Box\Mod\Invoice\Entity\InvoiceItem::class);

    $invoiceItemRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceItemRepository::class);
    $invoiceItemRepo->shouldReceive('findByInvoiceId')
        ->with(1)
        ->once()
        ->andReturn([$invoiceItemModel]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\InvoiceItem::class)
        ->andReturn($invoiceItemRepo);

    $itemInvoiceServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $itemTotal = 10.0;
    $itemInvoiceServiceMock->shouldReceive('getTotal')
        ->atLeast()->once()
        ->andReturn($itemTotal);

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $itemInvoiceServiceMock);

    $service->setDi($di);
    $result = $service->getTotal($invoiceModel);
    expect($result)->toBeFloat();
    expect($result)->toBe($itemTotal);
});

test('refunds invoice with negative invoice logic', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getTotal')
        ->once()
        ->andReturn(10.0);
    $serviceMock->shouldReceive('getTax')
        ->once()
        ->andReturn(2.2);
    $serviceMock->shouldReceive('countIncome')
        ->once();
    $serviceMock->shouldReceive('addNote')
        ->times(3);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn(['id' => 1]);

    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class, ['id' => 1]);

    $invoiceItemModel = createEntity(\Box\Mod\Invoice\Entity\InvoiceItem::class);

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->atLeast()->once();

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->atLeast()->once()
        ->andReturn('negative_invoice');

    $di = container();
    $emMock = Tests\Helpers\entityManagerWithIds($di);
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\InvoiceItem::class)
        ->andReturnUsing(function () use ($invoiceItemModel) {
            $repo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceItemRepository::class)->shouldIgnoreMissing();
            $repo->shouldReceive('findByInvoiceId')
                ->andReturn([$invoiceItemModel]);

            return $repo;
        });
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $systemService);
    $di['events_manager'] = $eventManagerMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $result = $serviceMock->refundInvoice($invoiceModel, 'customNote');
    expect($result)->toBeInt();
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

    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class);

    $invoiceItemModel = createEntity(\Box\Mod\Invoice\Entity\InvoiceItem::class);

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->atLeast()->once();

    $itemInvoiceServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $itemInvoiceServiceMock->shouldReceive('addNew')
        ->atLeast()->once();
    $itemInvoiceServiceMock->shouldReceive('update')
        ->atLeast()->once();

    $invoiceItemEntity = new Box\Mod\Invoice\Entity\InvoiceItem();
    $ref = new ReflectionProperty($invoiceItemEntity, 'id');
    $ref->setValue($invoiceItemEntity, 0);

    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\InvoiceItem::class)
        ->andReturnUsing(function () use ($invoiceItemEntity) {
            $repo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceItemRepository::class)->shouldIgnoreMissing();
            $repo->shouldReceive('find')->with(0)->andReturn($invoiceItemEntity);

            return $repo;
        });
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
    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class, ['id' => 1]);

    $invoiceItemModel = createEntity(\Box\Mod\Invoice\Entity\InvoiceItem::class);

    $connectionMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connectionMock->shouldReceive('executeStatement')
        ->atLeast()->once();

    $productServiceMock = Mockery::mock(ProductService::class)->shouldIgnoreMissing();

    $di = container();
    $di['em']->shouldReceive('getConnection')
        ->andReturn($connectionMock);
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\InvoiceItem::class)
        ->andReturnUsing(function () use ($invoiceItemModel) {
            $repo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceItemRepository::class);
            $repo->shouldReceive('findByInvoiceId')->withAnyArgs()->andReturn([$invoiceItemModel]);

            return $repo;
        });
    $di['em']->shouldReceive('remove')->atLeast()->once();
    $di['em']->shouldReceive('flush')->atLeast()->once();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $productServiceMock);

    $service->setDi($di);

    $result = $service->rmInvoice($invoiceModel);
    expect($result)->toBeTrue();
});

test('deletes invoice by admin', function (): void {
    $service = new Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('rmInvoice')
        ->once();

    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class);

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
    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class, ['id' => $newId]);

    $clientOrder = createEntity(\Box\Mod\Order\Entity\Order::class);

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
    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('findAllUnpaid')
        ->atLeast()->once()
        ->andReturn([[]]);
    $serviceMock->shouldReceive('tryPayWithCredits')
        ->atLeast()->once();

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $result = $serviceMock->doBatchPayWithCredits([]);
    expect($result)->toBeBool()->toBeTrue();
});

test('pays invoice with credits', function (): void {
    $service = new Service();
    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class);

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
    $clientOrder = createEntity(\Box\Mod\Order\Entity\Order::class, ['unpaid_invoice_id' => 2]);

    $di = container();
    $service->setDi($di);
    $result = $service->generateForOrder($clientOrder);
    expect($result)->toBeInstanceOf(Invoice::class);
    expect($result->getStatus())->toBe(Model_Invoice::STATUS_UNPAID);
});

test('clears stale paid invoice reference when generating for order', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();

    $clientOrder = createEntity(\Box\Mod\Order\Entity\Order::class, [
        'unpaid_invoice_id' => 2,
        'client_id' => 20,
        'price' => 10,
        'quantity' => 1,
    ]);

    $di = container();
    $serviceMock->setDi($di);

    $result = $serviceMock->generateForOrder($clientOrder);

    expect($result)->toBeInstanceOf(Invoice::class);
});

test('generates invoice for order', function (): void {
    $service = new Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('setInvoiceDefaults')
        ->once();

    $orderModel = createEntity(\Box\Mod\Order\Entity\Order::class, [
        'price' => 10,
        'promo_recurring' => true,
    ]);

    $invoiceItemServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $invoiceItemServiceMock->shouldReceive('generateFromOrder')
        ->atLeast()->once();

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $invoiceItemServiceMock);

    $serviceMock->setDi($di);
    $result = $serviceMock->generateForOrder($orderModel);
    expect($result)->toBeInstanceOf(Invoice::class);
});

test('generates invoice for active order using the order price, not the product price', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('setInvoiceDefaults')
        ->once();

    $orderModel = createEntity(\Box\Mod\Order\Entity\Order::class, [
        'status' => Model_ClientOrder::STATUS_ACTIVE,
        'product_id' => 5,
        'currency' => 'USD',
        'price' => 25,
        'quantity' => 1,
    ]);

    $product = Mockery::mock(Product::class)->makePartial();
    $product->shouldReceive('getType')->andReturn('hosting');

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('findProductById')
        ->with(5)
        ->once()
        ->andReturn($product);
    $productService->shouldReceive('getProductRenewalLineConfig')
        ->never();

    $invoiceItemServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $invoiceItemServiceMock->shouldReceive('generateFromOrder')
        ->once();

    $di = container();
    $clientEntity = createEntity(\Box\Mod\Client\Entity\Client::class, ['id' => 1]);
    $clientRepoMock = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepoMock->shouldReceive('find')->withAnyArgs()->andReturn($clientEntity);
    $di['em']->shouldReceive('getRepository')->with(\Box\Mod\Client\Entity\Client::class)->andReturn($clientRepoMock);
    $di['mod_service'] = $di->protect(function (string $module) use ($productService, $invoiceItemServiceMock): Mockery\MockInterface {
        if ($module === 'Product') {
            return $productService;
        }

        return $invoiceItemServiceMock;
    });

    $serviceMock->setDi($di);
    $result = $serviceMock->generateForOrder($orderModel);
    expect($result)->toBeInstanceOf(Invoice::class);
});

test('generates invoice for zero amount order', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('setInvoiceDefaults')
        ->once();

    $orderModel = createEntity(\Box\Mod\Order\Entity\Order::class, [
        'price' => 0,
        'quantity' => 1,
        'currency' => 'USD',
    ]);

    $invoiceItemServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $invoiceItemServiceMock->shouldReceive('generateFromOrder')
        ->atLeast()->once();

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $invoiceItemServiceMock);

    $serviceMock->setDi($di);
    $result = $serviceMock->generateForOrder($orderModel);
    expect($result)->toBeInstanceOf(Invoice::class);
});

test('throws exception when generating invoice for negative amount order', function (): void {
    $service = new Service();
    $clientOrder = createEntity(\Box\Mod\Order\Entity\Order::class, [
        'price' => -1,
        'quantity' => 1,
    ]);

    expect(fn () => $service->generateForOrder($clientOrder))
        ->toThrow(FOSSBilling\Exception::class, 'Invoices are not generated for negative amount orders.');
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

    $newId = 4;
    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class, ['id' => $newId]);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('approveInvoice')
        ->once();
    $serviceMock->shouldReceive('generateForOrder')
        ->once()
        ->andReturn($invoiceModel);

    $orderService = Mockery::mock(OrderService::class);
    $orderService->shouldReceive('getSoonExpiringActiveOrders')
        ->atLeast()->once()
        ->andReturn([['id' => 1]]);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderService);
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $result = $serviceMock->generateInvoicesForExpiringOrders();
    expect($result)->toBeBool()->toBeTrue();
});

test('activates paid invoices in batch', function (): void {
    $service = new Service();
    $invoiceItemModel = createEntity(\Box\Mod\Invoice\Entity\InvoiceItem::class);

    $itemInvoiceServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $itemInvoiceServiceMock->shouldReceive('executeTask')
        ->with($invoiceItemModel);
    $itemInvoiceServiceMock->shouldReceive('getAllNotExecutePaidItems')
        ->atLeast()->once()
        ->andReturn([['id' => 42]]);

    $invoiceItemRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceItemRepository::class);
    $invoiceItemRepo->shouldReceive('find')->with(42)->andReturn($invoiceItemModel);

    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\InvoiceItem::class)
        ->andReturn($invoiceItemRepo);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $itemInvoiceServiceMock);
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);
    $result = $service->doBatchPaidInvoiceActivation();
    expect($result)->toBeBool()->toBeTrue();
});

test('handles exception during batch paid invoice activation', function (): void {
    $service = new Service();
    $invoiceItemModel = createEntity(\Box\Mod\Invoice\Entity\InvoiceItem::class);

    $itemInvoiceServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $itemInvoiceServiceMock->shouldReceive('executeTask')
        ->with($invoiceItemModel)
        ->andThrow(new FOSSBilling\Exception('testing exception..'));
    $itemInvoiceServiceMock->shouldReceive('getAllNotExecutePaidItems')
        ->atLeast()->once()
        ->andReturn([['id' => 42]]);

    $invoiceItemRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceItemRepository::class);
    $invoiceItemRepo->shouldReceive('find')->with(42)->andReturn($invoiceItemModel);

    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\InvoiceItem::class)
        ->andReturn($invoiceItemRepo);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $itemInvoiceServiceMock);
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);
    $result = $service->doBatchPaidInvoiceActivation();
    expect($result)->toBeBool()->toBeTrue();
});

test('sends reminders in batch at most once per day', function (): void {
    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('doBatchInvokeDueEvent')
        ->once()
        ->with(['once_per_day' => true])
        ->andReturnTrue();

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->atLeast()->once();

    $logger = new Tests\Helpers\TestLogger();

    $di = container();
    $di['events_manager'] = $eventManagerMock;
    $di['logger'] = $logger;

    $service->setDi($di);
    $result = $service->doBatchRemindersSend();
    expect($result)->toBeBool()->toBeTrue();
    expect($logger->calls)->toContain([
        'method' => 'info',
        'params' => ['Executed action to send invoice payment reminders.'],
    ]);
});

test('does not log reminder batch as executed when it is throttled', function (): void {
    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('doBatchInvokeDueEvent')
        ->once()
        ->with(['once_per_day' => true])
        ->andReturnFalse();
    $service->shouldReceive('doBatchInvokePendingReminderEvents')
        ->once()
        ->andReturnFalse();

    $eventManagerMock = Mockery::mock('\\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->once()
        ->with(['event' => 'onBeforeAdminInvoiceSendReminders']);

    $logger = new Tests\Helpers\TestLogger();

    $di = container();
    $di['events_manager'] = $eventManagerMock;
    $di['logger'] = $logger;

    $service->setDi($di);

    expect($service->doBatchRemindersSend())->toBeFalse()
        ->and($logger->calls)->toBeEmpty();
});

test('fires due events via the pending reminder fallback when the primary batch is throttled', function (): void {
    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('doBatchInvokeDueEvent')
        ->once()
        ->with(['once_per_day' => true])
        ->andReturnFalse();

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_reminder_before_due_days', '')
        ->andReturn('7');
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_reminder_after_due_days', '5')
        ->andReturn('5');

    $connectionMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connectionMock->shouldReceive('fetchAllAssociative')
        ->times(2)
        ->andReturn([['id' => 2, 'days_left' => 7]], []);

    $eventManagerMock = Mockery::mock('\\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->once()
        ->with(['event' => 'onBeforeAdminInvoiceSendReminders']);
    $eventManagerMock->shouldReceive('fire')
        ->once()
        ->with(['event' => 'onEventBeforeInvoiceIsDue', 'params' => ['id' => 2, 'days_left' => 7, 'reminder_intervals' => [7]]]);

    $di = container();
    $di['em']->shouldReceive('getConnection')
        ->andReturn($connectionMock);
    $di['events_manager'] = $eventManagerMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $systemService);
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    expect($service->doBatchRemindersSend())->toBeTrue();
});

test('guards the primary reminder batch throttle while the fallback still dispatches', function (): void {
    $lastInvocation = date('Y-m-d H:i:s');

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->times(3)
        ->with('invoice_overdue_invoked')
        ->andReturn(null, $lastInvocation, $lastInvocation);
    $systemService->shouldReceive('getParamValue')
        ->twice()
        ->with('invoice_reminder_before_due_days', '')
        ->andReturn('7');
    $systemService->shouldReceive('getParamValue')
        ->twice()
        ->with('invoice_reminder_after_due_days', '5')
        ->andReturn('5');
    $systemService->shouldReceive('setParamValue')
        ->once()
        ->with('invoice_overdue_invoked', Mockery::type('string'));

    $connectionMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connectionMock->shouldReceive('fetchAllAssociative')
        ->times(4)
        ->andReturn([['id' => 1, 'days_left' => 7]], [], [], []);

    $eventManagerMock = Mockery::mock('\\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->twice()
        ->with(['event' => 'onBeforeAdminInvoiceSendReminders']);
    $eventManagerMock->shouldReceive('fire')
        ->once()
        ->with(['event' => 'onEventBeforeInvoiceIsDue', 'params' => ['id' => 1, 'days_left' => 7, 'reminder_intervals' => [7]]]);

    $di = container();
    $di['em']->shouldReceive('getConnection')
        ->andReturn($connectionMock);
    $di['events_manager'] = $eventManagerMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $systemService);
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service = new Service();
    $service->setDi($di);

    // Primary batch runs, then is throttled on the direct call, then the fallback still
    // dispatches due events (broadly, for any listener) even though the primary is throttled.
    expect($service->doBatchRemindersSend())->toBeTrue()
        ->and($service->doBatchInvokeDueEvent([]))->toBeFalse()
        ->and($service->doBatchRemindersSend())->toBeTrue();
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

    $connectionMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connectionMock->shouldReceive('fetchAllAssociative')
        ->atLeast()->once()
        ->andReturn([['id' => 1]]);

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->atLeast()->once();

    $di = container();
    $di['em']->shouldReceive('getConnection')
        ->andReturn($connectionMock);
    $di['events_manager'] = $eventManagerMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $systemService);
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    $result = $service->doBatchInvokeDueEvent([]);
    expect($result)->toBeBool()->toBeTrue();
});

test('protects from sending reminders to paid invoices', function (): void {
    $service = new Service();
    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class, ['status' => Model_Invoice::STATUS_PAID]);

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
    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class);

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->atLeast()->once();

    $di = container();
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
    $connectionMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connectionMock->shouldReceive('fetchAllAssociative')
        ->atLeast()->once()
        ->andReturn($sqlResult);

    $di = container();
    $di['em']->shouldReceive('getConnection')
        ->andReturn($connectionMock);

    $service->setDi($di);
    $result = $service->counter();
    expect($result)->toBeArray();
});

test('throws exception when generating funds invoice without active order', function (): void {
    $service = new Service();
    $clientModel = createEntity(\Box\Mod\Client\Entity\Client::class);

    expect(fn () => $service->generateFundsInvoice($clientModel, 10))
        ->toThrow(FOSSBilling\Exception::class, 'You must have at least one active order before you can add funds so you cannot proceed at the current time!');
});

test('throws exception when generating funds invoice below minimum amount', function (): void {
    $service = new Service();
    $clientModel = createEntity(\Box\Mod\Client\Entity\Client::class, ['currency' => 'EUR']);
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
    $clientModel = createEntity(\Box\Mod\Client\Entity\Client::class, ['currency' => 'EUR']);
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
    $clientModel = createEntity(\Box\Mod\Client\Entity\Client::class, ['currency' => 'EUR']);
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

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($systemService, $itemInvoiceServiceMock) {
        if ($serviceName == 'system') {
            return $systemService;
        }
        if ($sub == 'InvoiceItem') {
            return $itemInvoiceServiceMock;
        }
    });

    $serviceMock->setDi($di);

    $result = $serviceMock->generateFundsInvoice($clientModel, $fundsAmount);
    expect($result)->toBeInstanceOf(Invoice::class);
});

test('throws exception when processing invoice not found', function (): void {
    $service = new Service();
    $data = [
        'hash' => 'hashString',
        'gateway_id' => 2,
    ];

    $di = container();

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

    $invoiceModel = new Invoice();
    $invoiceRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceRepository::class);
    $invoiceRepo->shouldReceive('findByHash')
        ->with('hashString')
        ->andReturn($invoiceModel);

    $payGatewayRepo = Mockery::mock(Box\Mod\Invoice\Repository\PayGatewayRepository::class);
    $payGatewayRepo->shouldReceive('find')
        ->with(2)
        ->andReturn(null);

    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Invoice::class)
        ->andReturn($invoiceRepo);
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\PayGateway::class)
        ->andReturn($payGatewayRepo);

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

    $invoiceModel = new Invoice();
    $invoiceRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceRepository::class);
    $invoiceRepo->shouldReceive('findByHash')
        ->with('hashString')
        ->andReturn($invoiceModel);

    $payGatewayEntity = new Box\Mod\Invoice\Entity\PayGateway();
    $payGatewayEntity->setEnabled(false);

    $payGatewayRepo = Mockery::mock(Box\Mod\Invoice\Repository\PayGatewayRepository::class);
    $payGatewayRepo->shouldReceive('find')
        ->with(2)
        ->andReturn($payGatewayEntity);

    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Invoice::class)
        ->andReturn($invoiceRepo);
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\PayGateway::class)
        ->andReturn($payGatewayRepo);

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

    $invoiceModel = new Invoice();
    $invoiceRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceRepository::class);
    $invoiceRepo->shouldReceive('findByHash')
        ->with('hashString')
        ->andReturn($invoiceModel);

    $payGatewayEntity = new Box\Mod\Invoice\Entity\PayGateway();
    $enabledProp = new ReflectionProperty($payGatewayEntity, 'enabled');
    $enabledProp->setValue($payGatewayEntity, true);

    $payGatewayRepo = Mockery::mock(Box\Mod\Invoice\Repository\PayGatewayRepository::class);
    $payGatewayRepo->shouldReceive('find')
        ->with(2)
        ->andReturn($payGatewayEntity);

    $subscribeService = Mockery::mock(ServiceSubscription::class);
    $subscribeService->shouldReceive('isSubscribable')
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

    $payGatewayService = Mockery::namedMock('InvoiceServicePayGatewayMock');
    $payGatewayService->shouldReceive('canPerformRecurrentPayment')
        ->atLeast()->once()
        ->andReturn(true);
    $payGatewayService->shouldReceive('getPaymentAdapter')
        ->atLeast()->once()
        ->andReturn($adapterMock);

    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Invoice::class)
        ->andReturn($invoiceRepo);
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\PayGateway::class)
        ->andReturn($payGatewayRepo);
    $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($payGatewayService, $subscribeService) {
        if ($sub == 'PayGateway') {
            return $payGatewayService;
        }
        if ($sub == 'Subscription') {
            return $subscribeService;
        }
    });
    $di['api_admin'] = new FOSSBilling\Api\Proxy(createEntity(\Box\Mod\Staff\Entity\Admin::class));
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

    $invoiceModel = new Invoice();

    $invoiceRepo = Mockery::mock(Doctrine\ORM\EntityRepository::class);
    $invoiceRepo->shouldReceive('find')
        ->once()
        ->with(1)
        ->andReturn($invoiceModel);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->with(Invoice::class)
        ->andReturn($invoiceRepo);

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
    $di['em'] = $emMock;
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

    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class);

    $di = container();
    $service->setDi($di);

    $result = $service->addNote($invoiceModel, $note);
    expect($result)->toBeTrue();
});

test('finds all unpaid invoices', function (): void {
    $service = new Service();
    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class);

    $getAllResult = [
        [
            'id' => 1,
            'client_id' => 1,
            'serie' => 'BB',
            'nr' => '00',
        ],
    ];
    $connectionMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connectionMock->shouldReceive('fetchAllAssociative')
        ->atLeast()->once()
        ->andReturn($getAllResult);

    $di = container();
    $di['em']->shouldReceive('getConnection')
        ->andReturn($connectionMock);
    $service->setDi($di);

    $result = $service->findAllUnpaid();
    expect($result)->toBeArray();
});

test('finds all paid invoices', function (): void {
    $service = new Service();
    $invoiceEntity = new Invoice();

    $invoiceRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceRepository::class);
    $invoiceRepo->shouldReceive('findPaid')
        ->once()
        ->andReturn([$invoiceEntity]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')
        ->with(Invoice::class)
        ->andReturn($invoiceRepo);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $result = $service->findAllPaid();
    expect($result)->toBeArray();
    expect($result[0])->toBeInstanceOf(Invoice::class);
});

test('gets unpaid invoices late for', function (): void {
    $service = new Service();
    $invoiceRow = ['id' => 1, 'status' => 'unpaid'];

    $connectionMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connectionMock->shouldReceive('fetchAllAssociative')
        ->atLeast()->once()
        ->andReturn([$invoiceRow]);

    $di = container();
    $di['em']->shouldReceive('getConnection')
        ->andReturn($connectionMock);
    $service->setDi($di);

    $result = $service->getUnpaidInvoicesLateFor();
    expect($result)->toBeArray();
    expect($result[0])->toBeArray();
});

test('gets buyer', function (): void {
    $service = new Service();
    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class, [
        'buyer_first_name' => 'John',
        'buyer_last_name' => 'Doe',
        'buyer_email' => 'john@example.com',
    ]);

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

    $modelInvoiceItem = createEntity(\Box\Mod\Invoice\Entity\InvoiceItem::class, ['type' => Model_InvoiceItem::TYPE_DEPOSIT]);

    $invoiceItems = [$modelInvoiceItem];

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\InvoiceItem::class)
        ->andReturnUsing(function () use ($invoiceItems) {
            $repo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceItemRepository::class);
            $repo->shouldReceive('findByInvoiceId')->andReturn($invoiceItems);

            return $repo;
        });
    $di['em'] = $emMock;

    $modelInvoice = createEntity(\Box\Mod\Invoice\Entity\Invoice::class, ['id' => 1]);

    $service->setDi($di);
    $result = $service->isInvoiceTypeDeposit($modelInvoice);
    expect($result)->toBeTrue();
});

test('returns false when invoice type is not deposit', function (): void {
    $service = new Service();
    $di = container();

    $modelInvoiceItem = createEntity(\Box\Mod\Invoice\Entity\InvoiceItem::class, ['type' => Model_InvoiceItem::TYPE_ORDER]);

    $invoiceItems = [$modelInvoiceItem];

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\InvoiceItem::class)
        ->andReturnUsing(function () use ($invoiceItems) {
            $repo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceItemRepository::class);
            $repo->shouldReceive('findByInvoiceId')->andReturn($invoiceItems);

            return $repo;
        });
    $di['em'] = $emMock;
    $service->setDi($di);

    $modelInvoice = createEntity(\Box\Mod\Invoice\Entity\Invoice::class, ['id' => 1]);

    $result = $service->isInvoiceTypeDeposit($modelInvoice);
    expect($result)->toBeFalse();
});

test('returns false when checking deposit with empty items', function (): void {
    $service = new Service();
    $di = container();

    $invoiceItems = [];

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\InvoiceItem::class)
        ->andReturnUsing(function () use ($invoiceItems) {
            $repo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceItemRepository::class);
            $repo->shouldReceive('findByInvoiceId')->andReturn($invoiceItems);

            return $repo;
        });
    $di['em'] = $emMock;
    $service->setDi($di);

    $modelInvoice = createEntity(\Box\Mod\Invoice\Entity\Invoice::class, ['id' => 1]);

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

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->generateRenewalInvoiceForSubscriptionPayment('I-TEST123', 1);
    expect($result)->toBeNull();
});

test('generateRenewalInvoiceForSubscriptionPayment returns null when original order is not active', function (): void {
    $subscriptionEntity = new Box\Mod\Invoice\Entity\Subscription();
    $relTypeProp = new ReflectionProperty($subscriptionEntity, 'relType');
    $relTypeProp->setValue($subscriptionEntity, 'invoice');
    $relIdProp = new ReflectionProperty($subscriptionEntity, 'relId');
    $relIdProp->setValue($subscriptionEntity, 82);

    $originalOrder = createEntity(\Box\Mod\Order\Entity\Order::class, ['status' => Model_ClientOrder::STATUS_PENDING_SETUP]);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->with('ClientOrder', 82)
        ->andReturn($originalOrder);

    $subscriptionRepo = Mockery::mock(Box\Mod\Invoice\Repository\SubscriptionRepository::class);
    $subscriptionRepo->shouldReceive('findBySId')
        ->with('I-TEST123')
        ->andReturn($subscriptionEntity);

    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\Subscription::class)
        ->andReturn($subscriptionRepo);
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getOrderIdFromInvoice')
        ->with(82)
        ->once()
        ->andReturn(82);
    $serviceMock->setDi($di);

    $result = $serviceMock->generateRenewalInvoiceForSubscriptionPayment('I-TEST123', 1);
    expect($result)->toBeNull();
});

test('generateRenewalInvoiceForSubscriptionPayment uses the original order and not a product_id lookup', function (): void {
    $subscriptionEntity = new Box\Mod\Invoice\Entity\Subscription();
    $relTypeProp = new ReflectionProperty($subscriptionEntity, 'relType');
    $relTypeProp->setValue($subscriptionEntity, 'invoice');
    $relIdProp = new ReflectionProperty($subscriptionEntity, 'relId');
    $relIdProp->setValue($subscriptionEntity, 82);

    $renewalInvoice = new Invoice();
    $prop = new ReflectionProperty($renewalInvoice, 'id');
    $prop->setValue($renewalInvoice, 99);

    $subscriptionRepo = Mockery::mock(Box\Mod\Invoice\Repository\SubscriptionRepository::class);
    $subscriptionRepo->shouldReceive('findBySId')
        ->with('I-TEST123')
        ->andReturn($subscriptionEntity);

    $originalOrderEntity = new Box\Mod\Order\Entity\Order();
    $orderIdProp = new ReflectionProperty($originalOrderEntity, 'id');
    $orderIdProp->setValue($originalOrderEntity, 82);
    $originalOrderEntity->setStatus(Model_ClientOrder::STATUS_ACTIVE);

    $orderRepo = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class);
    $orderRepo->shouldReceive('find')
        ->with(82)
        ->andReturn($originalOrderEntity);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getOrderIdFromInvoice')
        ->with(82)
        ->once()
        ->andReturn(82);
    $serviceMock->shouldReceive('generateForOrder')
        ->with($originalOrderEntity)
        ->once()
        ->andReturn($renewalInvoice);
    $serviceMock->shouldReceive('approveInvoice')
        ->once();

    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\Subscription::class)
        ->andReturn($subscriptionRepo);
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Order\Entity\Order::class)
        ->andReturn($orderRepo);
    $di['logger'] = new Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $result = $serviceMock->generateRenewalInvoiceForSubscriptionPayment('I-TEST123', 1);

    expect($result)->toBeInstanceOf(Invoice::class);
    expect($result->getId())->toBe(99);
});

test('markAsPaid transitions a deposit invoice to paid status', function (): void {
    $service = new Service();

    $depositInvoice = createEntity(\Box\Mod\Invoice\Entity\Invoice::class, [
        'id' => 89,
        'status' => Model_Invoice::STATUS_UNPAID,
        'approved' => true,
        'currency' => 'USD',
    ]);

    $depositItem = createEntity(\Box\Mod\Invoice\Entity\InvoiceItem::class, [
        'id' => 96,
        'type' => Model_InvoiceItem::TYPE_DEPOSIT,
        'task' => 'void',
    ]);

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
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\InvoiceItem::class)
        ->andReturnUsing(function () use ($depositItem) {
            $repo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceItemRepository::class);
            $repo->shouldReceive('findByInvoiceId')->with(89)->andReturn([$depositItem]);

            return $repo;
        });
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

    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class);

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

    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class);

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

    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class);

    $serviceMock->shouldReceive('toApiArray')
        ->once()
        ->andThrow(new Exception('boom'));

    $result = $serviceMock->getInvoicePdfAttachment($invoiceModel);

    expect($result)->toBeNull();
    $errors = array_filter($logger->calls, fn ($c): bool => $c['method'] === 'error');
    expect($errors)->not->toBeEmpty();
});
