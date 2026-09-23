<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Client\Entity\ClientBalance;
use Box\Mod\Client\Service as ClientService;
use Box\Mod\Cron\Event\AfterAdminCronRunEvent;
use Box\Mod\Currency\Entity\Currency as CurrencyEntity;
use Box\Mod\Currency\Repository\CurrencyRepository;
use Box\Mod\Currency\Service as CurrencyService;
use Box\Mod\Email\Service as EmailService;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\Subscription;
use Box\Mod\Invoice\Entity\Transaction;
use Box\Mod\Invoice\Event\AfterAdminGenerateRenewalInvoiceEvent;
use Box\Mod\Invoice\Event\AfterAdminInvoiceApproveEvent;
use Box\Mod\Invoice\Event\AfterAdminInvoiceDebitEvent;
use Box\Mod\Invoice\Event\AfterAdminInvoiceDeleteEvent;
use Box\Mod\Invoice\Event\AfterAdminInvoicePaymentReceivedEvent;
use Box\Mod\Invoice\Event\AfterAdminInvoiceRefundEvent;
use Box\Mod\Invoice\Event\AfterAdminInvoiceUpdateEvent;
use Box\Mod\Invoice\Event\BeforeAdminGenerateRenewalInvoiceEvent;
use Box\Mod\Invoice\Event\BeforeAdminInvoiceApproveEvent;
use Box\Mod\Invoice\Event\BeforeAdminInvoiceDebitEvent;
use Box\Mod\Invoice\Event\BeforeAdminInvoiceDeleteEvent;
use Box\Mod\Invoice\Event\BeforeAdminInvoiceRefundEvent;
use Box\Mod\Invoice\Event\BeforeAdminInvoiceUpdateEvent;
use Box\Mod\Invoice\Repository\InvoiceItemRepository;
use Box\Mod\Invoice\Repository\InvoiceRepository;
use Box\Mod\Invoice\Repository\PayGatewayRepository;
use Box\Mod\Invoice\Repository\SubscriptionRepository;
use Box\Mod\Invoice\Repository\TransactionRepository;
use Box\Mod\Invoice\Service;
use Box\Mod\Invoice\ServiceInvoiceItem;
use Box\Mod\Invoice\ServicePayGateway;
use Box\Mod\Invoice\ServiceSubscription;
use Box\Mod\Invoice\ServiceTax;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Repository\OrderRepository;
use Box\Mod\Order\Service as OrderService;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Product\Service as ProductService;
use Box\Mod\System\Service as SystemService;
use Doctrine\ORM\EntityManagerInterface;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;
use function Tests\Helpers\moduleService;
use function Tests\Helpers\setEntityId;

/**
 * @return array{0: EntityManagerInterface, 1: InvoiceItemRepository}
 */
function invoiceItemEmAndRepo(): array
{
    $repo = Mockery::mock(InvoiceItemRepository::class);
    $invoiceRepo = Mockery::mock(InvoiceRepository::class)->shouldIgnoreMissing();
    $invoiceRepo->shouldReceive('lockAndGetState')
        ->byDefault()
        ->andReturn(['status' => Invoice::STATUS_UNPAID, 'approved' => false]);
    $em = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $em->shouldReceive('wrapInTransaction')->andReturnUsing(fn (callable $callback): mixed => $callback());
    $em->shouldReceive('getRepository')->with(InvoiceItem::class)->andReturn($repo);
    $em->shouldReceive('getRepository')->with(Invoice::class)->byDefault()->andReturn($invoiceRepo);
    $em->shouldReceive('refresh')->byDefault();

    return [$em, $repo];
}

/**
 * @param array{status?: string, approved?: bool} $state
 */
function invoiceLockingRepository(array $state = []): InvoiceRepository
{
    $repository = Mockery::mock(InvoiceRepository::class)->shouldIgnoreMissing();
    $repository->shouldReceive('lockAndGetState')
        ->byDefault()
        ->andReturn([
            'status' => $state['status'] ?? Invoice::STATUS_UNPAID,
            'approved' => $state['approved'] ?? true,
        ]);

    return $repository;
}

test('gets dependency injection container', function (): void {
    $service = new Service();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toBe($di);
});

test('converts to api array', function (): void {
    $service = new Service();
    $invoiceModel = createEntity(Invoice::class);

    $invoiceModel->hash = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4';

    $invoiceItemModel = createEntity(InvoiceItem::class, []);

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

    [$em, $invoiceItemRepo] = invoiceItemEmAndRepo();
    $invoiceItemRepo->shouldReceive('findByInvoiceId')
        ->atLeast()->once()
        ->andReturn([$invoiceItemModel]);

    $periodMock = Mockery::mock(FOSSBilling\Period::class);
    $periodMock->shouldReceive('getUnit');
    $periodMock->shouldReceive('getQty');

    $di = container();
    $di['em'] = $em;
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

test('converts an invoice entity to an api summary', function (): void {
    $service = new Service();

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->once()
        ->with('invoice_number_padding')
        ->andReturn('5');

    $di = container();
    $di['em']->shouldReceive('getConnection')->never();
    $di['mod_service'] = $di->protect(moduleService(['system' => $systemService]));
    $service->setDi($di);

    $invoice = createEntity(Invoice::class, [
        'id' => 42,
        'serie' => 'INV-',
        'nr' => '42',
        'client_id' => 7,
        'currency' => 'USD',
        'taxrate' => '20',
        'status' => 'unpaid',
        'due_at' => '2026-08-01 00:00:00',
        'created_at' => '2026-07-19 00:00:00',
        'updated_at' => '2026-07-19 00:00:00',
        'buyer_first_name' => 'Ada',
        'buyer_last_name' => 'Lovelace',
        'buyer_email' => 'ada@example.com',
        'approved' => 1,
    ]);

    $result = $service->toApiSummaryFromEntity($invoice, [
        'subtotal' => 25.0,
        'taxable_subtotal' => 10.0,
    ]);

    expect($result)
        ->toMatchArray([
            'id' => 42,
            'serie_nr' => 'INV-00042',
            'client' => ['id' => 7],
            'subtotal' => 25.0,
            'tax' => 2.0,
            'total' => 27.0,
            'approved' => true,
            'paid_at' => null,
            'buyer' => [
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
                'email' => 'ada@example.com',
            ],
        ]);
});

test('defaults missing totals to zero in the api summary', function (): void {
    $service = new Service();

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->once()
        ->with('invoice_number_padding')
        ->andReturn('5');

    $di = container();
    $di['mod_service'] = $di->protect(moduleService(['system' => $systemService]));
    $service->setDi($di);

    $invoice = createEntity(Invoice::class, [
        'id' => 43,
        'serie' => 'INV-',
        'nr' => null,
        'taxrate' => '20',
        'status' => 'unpaid',
    ]);

    $result = $service->toApiSummaryFromEntity($invoice, []);

    expect($result['subtotal'])->toBe(0.0)
        ->and($result['tax'])->toBe(0)
        ->and($result['total'])->toBe(0.0)
        ->and($result['serie_nr'])->toBe('INV-00043');
});

test('ensure valid hash is a no-op for modern hashes', function (): void {
    $service = new Service();
    $invoiceModel = createEntity(Invoice::class);

    $invoiceModel->hash = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4';

    $di = container();
    $di['em']->shouldNotReceive('flush');

    $service->setDi($di);
    $service->ensureValidHash($invoiceModel);

    expect($invoiceModel->hash)->toBe('a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4');
});

test('ensure valid hash regenerates a missing hash', function (): void {
    $service = new Service();
    $invoiceModel = createEntity(Invoice::class);

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_hash_lifetime_days', '90')
        ->andReturn('90');

    $di = container();
    $di['em']->shouldReceive('persist')->once()->with($invoiceModel);
    $di['em']->shouldReceive('flush')->once()->with($invoiceModel);
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
    expect($invoiceModel->hash_expires_at)->toBeInstanceOf(DateTime::class);
});

test('ensure valid hash regenerates a legacy format hash', function (): void {
    $service = new Service();
    $invoiceModel = createEntity(Invoice::class);

    $invoiceModel->hash = 'AAAAAAAAC4C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8C8';

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_hash_lifetime_days', '90')
        ->andReturn('90');

    $di = container();
    $di['em']->shouldReceive('persist')->once()->with($invoiceModel);
    $di['em']->shouldReceive('flush')->once()->with($invoiceModel);
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
    $invoiceModel = createEntity(Invoice::class);

    $invoiceItemModel = createEntity(InvoiceItem::class, []);

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

    [$em, $invoiceItemRepo] = invoiceItemEmAndRepo();
    $invoiceItemRepo->shouldReceive('findByInvoiceId')
        ->atLeast()->once()
        ->andReturn([$invoiceItemModel]);
    $em->shouldReceive('persist')
        ->atLeast()->once();
    $em->shouldReceive('flush')
        ->atLeast()->once();

    $periodMock = Mockery::mock(FOSSBilling\Period::class);
    $periodMock->shouldReceive('getUnit');
    $periodMock->shouldReceive('getQty');

    $di = container();
    $di['em'] = $em;
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
    $service = Mockery::mock(Service::class)->makePartial();
    $arr = [
        'total' => 1,
        'client' => [
            'id' => 0,
        ],
    ];
    $service->shouldReceive('toApiArray')->once()->andReturn($arr);
    $service->shouldReceive('withBillingRecipient')->once()->andReturnUsing(static fn (array $email): array => $email);
    $service->shouldReceive('getInvoicePdfAttachment')->once()->andReturnNull();

    $emailService = Mockery::mock(EmailService::class);
    $emailService->shouldReceive('sendTemplate')
        ->once()
        ->with(Mockery::on(static fn (array $email): bool => $email['code'] === 'mod_invoice_paid' && $email['invoice'] === $arr));

    $invoiceModel = createEntity(Invoice::class, ['id' => 18]);

    $di = container();
    $di['em']->getRepository(Invoice::class)->shouldReceive('find')
        ->once()->with(18)
        ->andReturn($invoiceModel);
    $di['mod_service'] = $di->protect(moduleService(['email' => $emailService]));

    $service->setDi($di);
    $service->sendPaidInvoiceEmail(new AfterAdminInvoicePaymentReceivedEvent(18));
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

    $invoiceModel = createEntity(Invoice::class);

    $di = container();
    $di['em']->getRepository(Invoice::class)->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn($invoiceModel);
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

test('removes expired unpaid invoices on typed after cron event', function (): void {
    $remove_after_days = 64;
    $systemServiceMock = Mockery::mock(SystemService::class);
    $systemServiceMock->shouldReceive('getParamValue')
        ->with('remove_after_days')
        ->atLeast()->once()
        ->andReturn($remove_after_days);

    $invoiceModel = createEntity(Invoice::class, ['id' => 1]);

    $invoiceServiceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $invoiceServiceMock->shouldReceive('rmInvoice')
        ->once()
        ->with($invoiceModel)
        ->andReturn(true);

    $di = container();
    $di['em']->getRepository(Invoice::class)->shouldReceive('findUnpaidOlderThan')
        ->with(64)
        ->once()
        ->andReturn([$invoiceModel]);
    $di['mod_service'] = $di->protect(fn (string $name = ''): object => match (strtolower($name)) {
        'system' => $systemServiceMock,
        'invoice' => $invoiceServiceMock,
        default => Mockery::mock()->shouldIgnoreMissing(),
    });

    $invoiceServiceMock->setDi($di);
    $invoiceServiceMock->removeExpiredUnpaidInvoices(new AfterAdminCronRunEvent());
});

test('uses the client billing email for invoice notifications', function (): void {
    $service = new Service();
    $email = ['to_client' => 42, 'code' => 'mod_invoice_created'];
    $invoice = ['client' => ['billing_email' => ' billing@example.com ']];

    expect($service->withBillingRecipient($email, $invoice))->toBe([
        'to_client' => 42,
        'code' => 'mod_invoice_created',
        'client_billing_email' => 'billing@example.com',
    ]);
    expect($service->withBillingRecipient($email, ['client' => ['billing_email' => null]]))->toBe($email);
    expect($service->withBillingRecipient($email, ['client' => ['billing_email' => 'not-an-email']]))->toBe($email);
    expect($service->withBillingRecipient($email, ['client' => []]))->toBe($email);
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

    $invoiceModel = createEntity(Invoice::class);

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('executeStatement')
        ->once()
        ->with(Mockery::type('string'), Mockery::on(fn (array $params): bool => $params['id'] === 1 && isset($params['now'])))
        ->andReturn(1);

    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $di['em']->getRepository(Invoice::class)->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn($invoiceModel);
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

test('skips overdue invoice reminder when the invoice was already claimed', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('toApiArray')
        ->never();
    $serviceMock->shouldReceive('getInvoicePdfAttachment')
        ->never();

    $eventMock = Mockery::mock('\\Box_Event');
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

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('executeStatement')
        ->once()
        ->with(Mockery::type('string'), Mockery::on(fn (array $params): bool => $params['id'] === 1 && isset($params['now'])))
        ->andReturn(0);

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
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $di['logger'] = new Tests\Helpers\TestLogger();

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

    $invoiceModel = createEntity(Invoice::class);

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('executeStatement')
        ->once()
        ->with(Mockery::type('string'), Mockery::on(fn (array $params): bool => $params['id'] === 1 && isset($params['now'])))
        ->andReturn(1);
    $connection->shouldReceive('executeStatement')
        ->once()
        ->with('UPDATE invoice SET reminded_at = NULL WHERE id = :id', ['id' => 1]);

    $logger = new Tests\Helpers\TestLogger();

    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $di['em']->getRepository(Invoice::class)->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn($invoiceModel);
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

    $invoiceModel = createEntity(Invoice::class);

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('executeStatement')
        ->once()
        ->with(Mockery::type('string'), Mockery::on(fn (array $params): bool => $params['id'] === 1 && isset($params['now'])))
        ->andReturn(1);
    $connection->shouldReceive('executeStatement')
        ->once()
        ->with('UPDATE invoice SET reminded_at = NULL WHERE id = :id', ['id' => 1]);

    $logger = new Tests\Helpers\TestLogger();

    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $di['em']->getRepository(Invoice::class)->shouldReceive('find')
        ->once()
        ->andReturn($invoiceModel);
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

    $invoiceModel = createEntity(Invoice::class);

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('executeStatement')
        ->once()
        ->with(Mockery::type('string'), Mockery::on(fn (array $params): bool => $params['id'] === 1 && isset($params['now'])))
        ->andReturn(1);

    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $di['em']->getRepository(Invoice::class)->shouldReceive('find')
        ->once()
        ->andReturn($invoiceModel);
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

    $invoiceModel = createEntity(Invoice::class);

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('executeStatement')
        ->once()
        ->with(Mockery::type('string'), Mockery::on(fn (array $params): bool => $params['id'] === 1 && isset($params['now'])))
        ->andReturn(1);
    $connection->shouldReceive('executeStatement')
        ->once()
        ->with('UPDATE invoice SET reminded_at = NULL WHERE id = :id', ['id' => 1]);

    $logger = new Tests\Helpers\TestLogger();

    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $di['em']->getRepository(Invoice::class)->shouldReceive('find')
        ->once()
        ->andReturn($invoiceModel);
    $di['mod_service'] = $di->protect(function ($serviceName) use ($serviceMock, $systemService) {
        if ($serviceName == 'invoice') {
            return $serviceMock;
        }
        if ($serviceName == 'system') {
            return $systemService;
        }
    });
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

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('executeStatement')
        ->once()
        ->with(Mockery::type('string'), Mockery::on(fn (array $params): bool => $params['id'] === 1 && isset($params['now'])))
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
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
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

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($serviceMock, $systemService) {
        if ($serviceName == 'invoice') {
            return $serviceMock;
        }
        if ($serviceName == 'system') {
            return $systemService;
        }
    });
    $di['em']->shouldReceive('getConnection')->never();
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

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($serviceMock, $systemService) {
        if ($serviceName == 'invoice') {
            return $serviceMock;
        }
        if ($serviceName == 'system') {
            return $systemService;
        }
    });
    $di['em']->shouldReceive('getConnection')->never();
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

    $invoiceModel = createEntity(Invoice::class);

    $invoiceModel->status = Invoice::STATUS_UNPAID;

    $invoiceItemModel = createEntity(InvoiceItem::class, []);

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

    $events = [];
    $eventDispatcher = new class($events) {
        public function __construct(private array &$events)
        {
        }

        public function dispatch(FOSSBilling\Events\Event $event): FOSSBilling\Events\Event
        {
            $this->events[] = $event;

            return $event;
        }
    };

    [$em, $invoiceItemRepo] = invoiceItemEmAndRepo();
    $invoiceItemRepo->shouldReceive('findByInvoiceId')
        ->atLeast()->once()
        ->andReturn([$invoiceItemModel]);
    $em->shouldReceive('persist')
        ->atLeast()->once();
    $em->shouldReceive('flush')
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
    $di['em'] = $em;
    $di['event_dispatcher'] = $eventDispatcher;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $result = $serviceMock->markAsPaid($invoiceModel, true, true);
    expect($result)->toBeBool()->toBeTrue()
        ->and($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(AfterAdminInvoicePaymentReceivedEvent::class)
        ->and($events[0]->invoiceId)->toBe((int) $invoiceModel->getId());
});

test('admin mark as paid with custom gateway records transaction and marks invoice paid', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('markAsPaid')
        ->once()
        ->with(Mockery::type(Invoice::class), false, true)
        ->andReturn(true);
    $serviceMock->shouldReceive('getTotalWithTax')
        ->once()
        ->with(Mockery::type(Invoice::class))
        ->andReturn(42.50);

    $gatewayModel = createEntity(PayGateway::class, [
        'id' => 5,
        'gateway' => 'Custom',
        'enabled' => true,
        'name' => 'Manual payment',
    ]);

    $invoiceModel = createEntity(Invoice::class);
    $invoiceModel->id = 10;
    $invoiceModel->gateway = $gatewayModel;
    $invoiceModel->currency = 'USD';
    $invoiceModel->status = Invoice::STATUS_UNPAID;

    $transactionModel = createEntity(Transaction::class, ['id' => 20, 'invoice' => $invoiceModel]);

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

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn($gatewayRepo = Mockery::mock(PayGatewayRepository::class));
    $gatewayRepo->shouldReceive('find')->once()->with(5)->andReturn($gatewayModel);
    $em->shouldReceive('getRepository')->with(Transaction::class)->andReturn($transactionRepo = Mockery::mock(TransactionRepository::class));
    $transactionRepo->shouldReceive('find')->once()->with(20)->andReturn($transactionModel);
    $em->shouldReceive('flush')->once();

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(moduleService([
        'invoice:transaction' => $transactionServiceMock,
    ]));

    $serviceMock->setDi($di);

    $result = $serviceMock->markAsPaidByAdmin($invoiceModel, [
        'execute' => true,
        'transactionId' => 'manual-reference-1',
    ]);

    expect($result)->toBeTrue()
        ->and($transactionModel->getAmount())->toBe('42.5')
        ->and($transactionModel->getCurrency())->toBe('USD')
        ->and($transactionModel->getStatus())->toBe(Transaction::STATUS_PROCESSED)
        ->and($transactionModel->getNote())->toBe('Manual payment transaction No: manual-reference-1');
});

test('admin mark as paid with custom gateway rejects transaction linked to another invoice', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldNotReceive('markAsPaid');
    $serviceMock->shouldReceive('getTotalWithTax')
        ->once()
        ->with(Mockery::type(Invoice::class))
        ->andReturn(42.50);

    $gatewayModel = createEntity(PayGateway::class, [
        'id' => 5,
        'gateway' => 'Custom',
        'enabled' => true,
    ]);

    $invoiceModel = createEntity(Invoice::class);
    $invoiceModel->id = 10;
    $invoiceModel->gateway = $gatewayModel;
    $invoiceModel->currency = 'USD';
    $invoiceModel->status = Invoice::STATUS_UNPAID;

    $otherInvoice = createEntity(Invoice::class);
    $otherInvoice->id = 99;

    $transactionModel = createEntity(Transaction::class, ['id' => 20, 'invoice' => $otherInvoice]);

    $transactionServiceMock = Mockery::mock(Box\Mod\Invoice\ServiceTransaction::class);
    $transactionServiceMock->shouldReceive('create')
        ->once()
        ->andReturn(20);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn($gatewayRepo = Mockery::mock(PayGatewayRepository::class));
    $gatewayRepo->shouldReceive('find')->once()->with(5)->andReturn($gatewayModel);
    $em->shouldReceive('getRepository')->with(Transaction::class)->andReturn($transactionRepo = Mockery::mock(TransactionRepository::class));
    $transactionRepo->shouldReceive('find')->once()->with(20)->andReturn($transactionModel);
    $em->shouldNotReceive('flush');

    $di = container();
    $di['em'] = $em;
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

    $invoiceModel = createEntity(Invoice::class);

    $invoiceModel->currency = 'USD';
    $invoiceModel->refund = 0;

    $currencyService = Mockery::mock(CurrencyService::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $currencyService->shouldReceive('toBaseCurrency')
        ->atLeast()->once()
        ->andReturn(0.0);

    [$em, $invoiceItemRepo] = invoiceItemEmAndRepo();
    $invoiceItemRepo->shouldReceive('findByInvoiceId')
        ->atLeast()->once()
        ->andReturn([]);
    $em->shouldReceive('persist')
        ->atLeast()->once();
    $em->shouldReceive('flush')
        ->atLeast()->once();

    $di = container();
    $di['em'] = $em;
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

    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class);

    $invoiceModel = createEntity(Invoice::class);

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
    $invoiceModel = createEntity(Invoice::class, ['client_id' => 1]);

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
    $systemService->shouldReceive('reserveNextNumericParamValue')
        ->once()
        ->with('invoice_starting_number')
        ->andReturn(1);
    // Only reached by the fallback path now that the counter is claimed atomically.
    $systemService->shouldReceive('setParamValue')
        ->zeroOrMoreTimes();

    $serviceTaxMock = Mockery::mock(ServiceTax::class);
    $serviceTaxMock->shouldReceive('getTaxRateForClient');

    $di = container();
    $di['em']->shouldReceive('persist')->atLeast()->once();
    $di['em']->shouldReceive('flush')->atLeast()->once();
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
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $steps = [];
    $serviceMock->shouldReceive('tryPayWithCredits')
        ->once()
        ->andReturnUsing(function () use (&$steps): bool {
            $steps[] = 'credits';

            return true;
        });

    $data['use_credits'] = true;

    $invoiceModel = createEntity(Invoice::class);

    $eventDispatcher = new class($steps) {
        public function __construct(private array &$steps)
        {
        }

        public function dispatch(FOSSBilling\Events\Event $event): FOSSBilling\Events\Event
        {
            $this->steps[] = $event;

            return $event;
        }
    };

    $di = container();
    $di['em']->shouldReceive('persist')->atLeast()->once();
    $di['em']->shouldReceive('flush')->atLeast()->once();
    $di['event_dispatcher'] = $eventDispatcher;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    $result = $serviceMock->approveInvoice($invoiceModel, $data);
    expect($result)->toBeTrue()
        ->and($steps)->toHaveCount(3)
        ->and($steps[0])->toBeInstanceOf(BeforeAdminInvoiceApproveEvent::class)
        ->and($steps[0]->invoiceId)->toBe((int) $invoiceModel->getId())
        ->and($steps[1])->toBe('credits')
        ->and($steps[2])->toBeInstanceOf(AfterAdminInvoiceApproveEvent::class)
        ->and($steps[2]->invoiceId)->toBe((int) $invoiceModel->getId());
});

test('typed invoice approval listener emails an unpaid invoice and extends its link', function (): void {
    $invoice = createEntity(Invoice::class, ['id' => 14]);
    $invoiceData = ['id' => 14, 'total' => 25.0, 'status' => Invoice::STATUS_UNPAID, 'client' => ['id' => 8]];

    $invoiceRepository = Mockery::mock(InvoiceRepository::class);
    $invoiceRepository->shouldReceive('find')->once()->with(14)->andReturn($invoice);
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->once()->with(Invoice::class)->andReturn($invoiceRepository);

    $emailService = Mockery::mock(EmailService::class);
    $emailService->shouldReceive('sendTemplate')->once()->with(Mockery::on(static fn (array $email): bool => $email['to_client'] === 8
        && $email['code'] === 'mod_invoice_created'
        && $email['invoice'] === $invoiceData));

    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('toApiArray')->once()->with($invoice, true, null, true)->andReturn($invoiceData);
    $service->shouldReceive('withBillingRecipient')->once()->andReturnUsing(static fn (array $email): array => $email);
    $service->shouldReceive('getInvoicePdfAttachment')->once()->with($invoice)->andReturnNull();
    $service->shouldReceive('extendInvoiceHashLifetime')->once()->with($invoice);

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(moduleService(['email' => $emailService]));
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $service->sendApprovedInvoiceEmail(new AfterAdminInvoiceApproveEvent(14));
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

    $invoiceModel = createEntity(Invoice::class);

    $result = $serviceMock->getTotalWithTax($invoiceModel);
    expect($result)->toBeFloat();
    expect($result)->toBe($expected);
});

test('pays a zero-total invoice without recording a balance transaction', function (): void {
    $invoice = createEntity(Invoice::class);

    $invoice->id = 10;
    $invoice->client_id = 20;
    $invoice->approved = 1;
    $invoice->status = Invoice::STATUS_UNPAID;

    $balanceService = Mockery::mock(Box\Mod\Client\ServiceBalance::class);
    $balanceService->shouldReceive('getClientBalanceForUpdate')->once()->with(20)->andReturn(0.0);

    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getTotalWithTax')->once()->with($invoice)->andReturn(0.0);
    $service->shouldReceive('markAsPaid')->once()->with($invoice, false, false, true)->andReturn(true);

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Invoice::class)->andReturn(invoiceLockingRepository());
    $di['em']->shouldNotReceive('flush');
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $balanceService);
    $service->setDi($di);

    expect($service->tryPayWithCredits($invoice))->toBeTrue();
});

test('records a balance transaction for a one-cent invoice', function (): void {
    $invoice = createEntity(Invoice::class);

    $invoice->id = 10;
    $invoice->nr = '2024-001';
    $invoice->client_id = 20;
    $invoice->approved = 1;
    $invoice->status = Invoice::STATUS_UNPAID;

    $balanceService = Mockery::mock(Box\Mod\Client\ServiceBalance::class);
    $balanceService->shouldReceive('getClientBalanceForUpdate')->once()->with(20)->andReturn(0.01);

    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getTotalWithTax')->once()->with($invoice)->andReturn(0.01);
    $service->shouldReceive('markAsPaid')->once()->with($invoice, false, false, true)->andReturn(true);

    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 20]);

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Invoice::class)->andReturn(invoiceLockingRepository());
    $di['em']->shouldReceive('getReference')->with(Box\Mod\Client\Entity\Client::class, 20)->andReturn($client);
    $di['em']->shouldReceive('persist')->once()->with(
        Mockery::on(fn (ClientBalance $balance): bool => $balance->getClient()?->getId() === 20
            && $balance->getType() === 'invoice'
            && $balance->getRelId() === '10'
            && $balance->getAmount() === '-0.01')
    );
    $di['em']->shouldReceive('flush')->once();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $balanceService);
    $service->setDi($di);

    expect($service->tryPayWithCredits($invoice))->toBeTrue();
});

test('pays an invoice with credits and records a balance transaction', function (): void {
    $invoice = createEntity(Invoice::class);

    $invoice->id = 10;
    $invoice->nr = '2024-001';
    $invoice->client_id = 20;
    $invoice->approved = 1;
    $invoice->status = Invoice::STATUS_UNPAID;

    $balanceService = Mockery::mock(Box\Mod\Client\ServiceBalance::class);
    $balanceService->shouldReceive('getClientBalanceForUpdate')->once()->with(20)->andReturn(100.0);

    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getTotalWithTax')->once()->with($invoice)->andReturn(50.0);
    $service->shouldReceive('markAsPaid')->once()->with($invoice, false, false, true)->andReturn(true);

    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 20]);

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Invoice::class)->andReturn(invoiceLockingRepository());
    $di['em']->shouldReceive('getReference')->with(Box\Mod\Client\Entity\Client::class, 20)->andReturn($client);
    $di['em']->shouldReceive('persist')->once()->with(
        Mockery::on(fn (ClientBalance $balance): bool => $balance->getClient()?->getId() === 20
            && $balance->getType() === 'invoice'
            && $balance->getRelId() === '10'
            && $balance->getDescription() === 'Payment for invoice #2024-001 using account credit.'
            && $balance->getAmount() === '-50')
    );
    $di['em']->shouldReceive('flush')->once();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $balanceService);
    $service->setDi($di);

    expect($service->tryPayWithCredits($invoice))->toBeTrue();
});

test('locks the invoice before checking the client balance', function (): void {
    $invoice = createEntity(Invoice::class);
    $invoice->id = 10;
    $invoice->client_id = 20;
    $invoice->approved = 1;
    $invoice->status = Invoice::STATUS_UNPAID;

    $di = container();
    // The invoice row is the first serialization point; only then is the client balance locked.
    $di['em']->getRepository(Invoice::class)->shouldReceive('lockAndGetState')->with(10)
        ->globally()->ordered()->andReturn(['status' => Invoice::STATUS_UNPAID, 'approved' => true]);

    $balanceService = Mockery::mock(Box\Mod\Client\ServiceBalance::class);
    // The unlocked read must not be used on a path that deducts from the balance.
    $balanceService->shouldNotReceive('getClientBalance');
    $balanceService->shouldReceive('getClientBalanceForUpdate')->once()->with(20)->globally()->ordered()->andReturn(100.0);

    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getTotalWithTax')->once()->with($invoice)->andReturn(50.0);
    $service->shouldReceive('markAsPaid')->once()->with($invoice, false, false, true)->andReturn(true);

    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $balanceService);
    $service->setDi($di);

    expect($service->tryPayWithCredits($invoice))->toBeTrue();
});

test('does not deduct credits when the invoice was paid concurrently before the lock was acquired', function (): void {
    $invoice = createEntity(Invoice::class);
    $invoice->id = 10;
    $invoice->client_id = 20;
    $invoice->approved = 1;
    $invoice->status = Invoice::STATUS_UNPAID;

    $balanceService = Mockery::mock(Box\Mod\Client\ServiceBalance::class);
    $balanceService->shouldNotReceive('getClientBalanceForUpdate');

    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldNotReceive('markAsPaid');

    $di = container();
    // Stand in for another request having paid this invoice while we waited on the lock.
    $di['em']->getRepository(Invoice::class)->shouldReceive('lockAndGetState')->with(10)
        ->andReturn(['status' => Invoice::STATUS_PAID, 'approved' => true]);
    $di['em']->shouldNotReceive('persist');
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $balanceService);
    $service->setDi($di);

    expect($service->tryPayWithCredits($invoice))->toBeFalse();
});

test('does not deduct credits when the locked balance is insufficient', function (): void {
    $invoice = createEntity(Invoice::class);
    $invoice->id = 10;
    $invoice->client_id = 20;
    $invoice->approved = 1;
    $invoice->status = Invoice::STATUS_UNPAID;

    $balanceService = Mockery::mock(Box\Mod\Client\ServiceBalance::class);
    // Another request spent the credit first, so the locked read sees the reduced balance.
    $balanceService->shouldReceive('getClientBalanceForUpdate')->once()->with(20)->andReturn(10.0);

    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getTotalWithTax')->once()->with($invoice)->andReturn(50.0);
    $service->shouldNotReceive('markAsPaid');

    $di = container();
    $di['em']->getRepository(Invoice::class)->shouldReceive('lockAndGetState')->with(10)
        ->andReturn(['status' => Invoice::STATUS_UNPAID, 'approved' => true]);
    $di['em']->shouldNotReceive('persist');
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $balanceService);
    $service->setDi($di);

    expect($service->tryPayWithCredits($invoice))->toBeFalse();
});

test('pays a fully funded invoice despite floating-point rounding dust', function (): void {
    $invoice = createEntity(Invoice::class);
    $invoice->id = 10;
    $invoice->nr = '2024-001';
    $invoice->client_id = 20;
    $invoice->approved = 1;
    $invoice->status = Invoice::STATUS_UNPAID;

    $balanceService = Mockery::mock(Box\Mod\Client\ServiceBalance::class);
    // 0.30 and 0.1 * 3 are equal at two-decimal scale but not as raw floats.
    $balanceService->shouldReceive('getClientBalanceForUpdate')->once()->with(20)->andReturn(0.30);

    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getTotalWithTax')->once()->with($invoice)->andReturn(0.1 * 3);
    $service->shouldReceive('markAsPaid')->once()->with($invoice, false, false, true)->andReturn(true);

    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 20]);

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Invoice::class)->andReturn(invoiceLockingRepository());
    $di['em']->shouldReceive('getReference')->with(Box\Mod\Client\Entity\Client::class, 20)->andReturn($client);
    $di['em']->shouldReceive('persist')->once()->with(
        Mockery::on(fn (ClientBalance $balance): bool => $balance->getClient()?->getId() === 20
            && $balance->getType() === 'invoice'
            && $balance->getRelId() === '10'
            && round((float) $balance->getAmount(), 2) === -0.30)
    );
    $di['em']->shouldReceive('flush')->once();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $balanceService);
    $service->setDi($di);

    expect($service->tryPayWithCredits($invoice))->toBeTrue();
});

test('does not pay a one-cent invoice with a zero credit balance', function (): void {
    $invoice = createEntity(Invoice::class);
    $invoice->id = 10;
    $invoice->client_id = 20;
    $invoice->approved = 1;
    $invoice->status = Invoice::STATUS_UNPAID;

    $balanceService = Mockery::mock(Box\Mod\Client\ServiceBalance::class);
    $balanceService->shouldReceive('getClientBalanceForUpdate')->once()->with(20)->andReturn(0.0);

    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getTotalWithTax')->once()->with($invoice)->andReturn(0.01);
    $service->shouldNotReceive('markAsPaid');

    $di = container();
    $di['em']->getRepository(Invoice::class)->shouldReceive('lockAndGetState')->with(10)
        ->andReturn(['status' => Invoice::STATUS_UNPAID, 'approved' => true]);
    $di['em']->shouldNotReceive('persist');
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $balanceService);
    $service->setDi($di);

    expect($service->tryPayWithCredits($invoice))->toBeFalse();
});

test('gets total', function (): void {
    $service = new Service();
    $invoiceModel = createEntity(Invoice::class);

    $invoiceItemModel = createEntity(InvoiceItem::class, []);

    [$em, $invoiceItemRepo] = invoiceItemEmAndRepo();
    $invoiceItemRepo->shouldReceive('findByInvoiceId')
        ->atLeast()->once()
        ->andReturn([$invoiceItemModel]);

    $itemInvoiceServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $itemTotal = 10.0;
    $itemInvoiceServiceMock->shouldReceive('getTotal')
        ->atLeast()->once()
        ->andReturn($itemTotal);

    $di = container();
    $di['em'] = $em;
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
        ->atLeast()->once()
        ->andReturn($total);
    $serviceMock->shouldReceive('getTax')
        ->atLeast()->once()
        ->andReturn($tax);
    $serviceMock->shouldReceive('countIncome')
        ->once();
    $serviceMock->shouldReceive('addNote')
        ->times(3);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn(['id' => $newId, 'total' => -12.2]);
    $serviceMock->shouldReceive('getNextInvoiceNumber')
        ->once()
        ->andReturn(42);
    $serviceMock->shouldReceive('extendInvoiceHashLifetime')
        ->once();

    $invoiceModel = createEntity(Invoice::class, ['clientId' => 5]);
    $invoiceModel->setStatus(Invoice::STATUS_PAID);

    $invoiceModel->id = $newId;

    $invoiceItemModel = createEntity(InvoiceItem::class, []);

    $events = [];
    $eventDispatcher = new class($events) {
        public function __construct(private array &$events)
        {
        }

        public function dispatch(FOSSBilling\Events\Event $event): FOSSBilling\Events\Event
        {
            $this->events[] = $event;

            return $event;
        }
    };

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_refund_logic', 'manual')
        ->andReturn('negative_invoice');
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_number_padding')
        ->andReturn(5);
    $systemService->shouldReceive('getCompany')
        ->andReturn([]);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_series_paid')
        ->andReturn('FB-');
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_hash_lifetime_days', '90')
        ->andReturn(90);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_email_attach_pdf')
        ->andReturn(false);

    $emailService = Mockery::mock(EmailService::class);
    $emailService->shouldReceive('sendTemplate')
        ->once()
        ->withArgs(fn (array $email): bool => $email['code'] === 'mod_invoice_refunded'
            && isset($email['invoice'], $email['original_invoice']));

    [$em, $invoiceItemRepo] = invoiceItemEmAndRepo();
    $invoiceItemRepo->shouldReceive('findByInvoiceId')
        ->atLeast()->once()
        ->andReturn([$invoiceItemModel]);
    $invoiceRepo = Mockery::mock(InvoiceRepository::class);
    $invoiceRepo->shouldReceive('lockAndGetStatus')
        ->once()
        ->with($newId)
        ->andReturn(Invoice::STATUS_PAID);
    $invoiceRepo->shouldReceive('findBy')->andReturn([]);
    $em->shouldReceive('getRepository')->with(Invoice::class)->andReturn($invoiceRepo);
    $persisted = [];
    $em->shouldReceive('persist')
        ->atLeast()->once()
        ->andReturnUsing(function (object $entity) use ($newId, &$persisted): void {
            if ($entity instanceof Invoice && $entity->getId() === null) {
                setEntityId($entity, $newId);
            }
            $persisted[] = $entity;
        });
    $em->shouldReceive('flush')
        ->atLeast()->once();

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(moduleService([
        'system' => $systemService,
        'email' => $emailService,
    ]));
    $di['event_dispatcher'] = $eventDispatcher;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $result = $serviceMock->refundInvoice($invoiceModel, 'customNote');
    expect($result)->toBeInt()->toBe($newId);
    expect($invoiceModel->getStatus())->toBe(Invoice::STATUS_REFUNDED);
    expect($events)->toHaveCount(2)
        ->and($events[0])->toBeInstanceOf(BeforeAdminInvoiceRefundEvent::class)
        ->and($events[0]->invoiceId)->toBe($newId)
        ->and($events[1])->toBeInstanceOf(AfterAdminInvoiceRefundEvent::class)
        ->and($events[1]->invoiceId)->toBe($newId);

    $creditNote = null;
    foreach ($persisted as $entity) {
        if ($entity instanceof Invoice && $entity->getId() === $newId && $entity !== $invoiceModel) {
            $creditNote = $entity;
        }
    }
    expect($creditNote)->not->toBeNull();
    expect($creditNote->getCreditNoteForInvoiceId())->toBe($invoiceModel->getId());
    expect($creditNote->getNr())->toBe('42');
});

test('refunds invoice with credit note logic and reserved numbering', function (): void {
    $newId = 2;
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getTotal')->andReturn(20.0);
    $serviceMock->shouldReceive('getTax')->andReturn(0.0);
    $serviceMock->shouldReceive('countIncome')->once();
    $serviceMock->shouldReceive('addNote')->times(2);
    $serviceMock->shouldReceive('toApiArray')->andReturn(['id' => $newId, 'total' => -20.0]);
    $serviceMock->shouldReceive('extendInvoiceHashLifetime')->once();

    $invoiceModel = createEntity(Invoice::class, ['clientId' => 5]);
    $invoiceModel->setStatus(Invoice::STATUS_PAID);
    setEntityId($invoiceModel, 9);

    $invoiceItemModel = createEntity(InvoiceItem::class, []);

    $events = [];
    $eventDispatcher = new class($events) {
        public function __construct(private array &$events)
        {
        }

        public function dispatch(FOSSBilling\Events\Event $event): FOSSBilling\Events\Event
        {
            $this->events[] = $event;

            return $event;
        }
    };

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_refund_logic', 'manual')
        ->andReturn('credit_note');
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_number_padding')
        ->andReturn(5);
    $systemService->shouldReceive('getCompany')
        ->andReturn([]);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_hash_lifetime_days', '90')
        ->andReturn(90);
    $systemService->shouldReceive('reserveNextNumericParamValue')
        ->once()
        ->with('invoice_cn_starting_number', 1)
        ->andReturn(7);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_cn_series', 'CN-')
        ->andReturn('CN-');
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_email_attach_pdf')
        ->andReturn(false);

    $emailService = Mockery::mock(EmailService::class);
    $emailService->shouldReceive('sendTemplate')->once();

    [$em, $invoiceItemRepo] = invoiceItemEmAndRepo();
    $invoiceItemRepo->shouldReceive('findByInvoiceId')->andReturn([$invoiceItemModel]);
    $invoiceRepo = Mockery::mock(InvoiceRepository::class);
    $invoiceRepo->shouldReceive('lockAndGetStatus')
        ->once()
        ->with(9)
        ->andReturn(Invoice::STATUS_PAID);
    $invoiceRepo->shouldReceive('findBy')->andReturn([]);
    $em->shouldReceive('getRepository')->with(Invoice::class)->andReturn($invoiceRepo);
    $creditNote = null;
    $em->shouldReceive('persist')
        ->atLeast()->once()
        ->andReturnUsing(function (object $entity) use ($newId, &$creditNote): void {
            if ($entity instanceof Invoice && $entity->getId() === null) {
                setEntityId($entity, $newId);
            }
            if ($entity instanceof Invoice && $entity->getStatus() === Invoice::STATUS_REFUNDED && $entity->getId() === $newId) {
                $creditNote = $entity;
            }
        });
    $em->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(moduleService([
        'system' => $systemService,
        'email' => $emailService,
    ]));
    $di['event_dispatcher'] = $eventDispatcher;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    expect($serviceMock->refundInvoice($invoiceModel))->toBe($newId);
    expect($events)->toHaveCount(2)
        ->and($events[0])->toBeInstanceOf(BeforeAdminInvoiceRefundEvent::class)
        ->and($events[0]->invoiceId)->toBe(9)
        ->and($events[1])->toBeInstanceOf(AfterAdminInvoiceRefundEvent::class)
        ->and($events[1]->invoiceId)->toBe(9);
    expect($invoiceModel->getStatus())->toBe(Invoice::STATUS_REFUNDED);
    expect($creditNote)->not->toBeNull();
    expect($creditNote->getSerie())->toBe('CN-');
    expect($creditNote->getNr())->toBe('7');
    expect($creditNote->getCreditNoteForInvoiceId())->toBe($invoiceModel->getId());
});

test('refundInvoice keeps the refund successful when notification delivery fails', function (): void {
    $newId = 2;
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getTotal')->andReturn(20.0);
    $serviceMock->shouldReceive('getTax')->andReturn(0.0);
    $serviceMock->shouldReceive('countIncome')->once();
    $serviceMock->shouldReceive('addNote')->times(2);
    $serviceMock->shouldReceive('toApiArray')->andReturn(['id' => $newId, 'total' => -20.0]);

    $invoiceModel = createEntity(Invoice::class, ['clientId' => 5]);
    $invoiceModel->setStatus(Invoice::STATUS_PAID);
    setEntityId($invoiceModel, 9);

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_refund_logic', 'manual')
        ->andReturn('credit_note');
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_hash_lifetime_days', '90')
        ->andReturn(90);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_email_attach_pdf')
        ->andReturn(false);
    $systemService->shouldReceive('reserveNextNumericParamValue')
        ->once()
        ->with('invoice_cn_starting_number', 1)
        ->andReturn(7);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_cn_series', 'CN-')
        ->andReturn('CN-');

    $emailService = Mockery::mock(EmailService::class);
    $emailService->shouldReceive('sendTemplate')
        ->once()
        ->andThrow(new RuntimeException('queue failed'));

    [$em, $invoiceItemRepo] = invoiceItemEmAndRepo();
    $invoiceItemRepo->shouldReceive('findByInvoiceId')->andReturn([]);
    $invoiceRepo = Mockery::mock(InvoiceRepository::class);
    $invoiceRepo->shouldReceive('lockAndGetStatus')
        ->once()
        ->with(9)
        ->andReturn(Invoice::STATUS_PAID);
    $invoiceRepo->shouldReceive('findBy')->andReturn([]);
    $em->shouldReceive('getRepository')->with(Invoice::class)->andReturn($invoiceRepo);
    $em->shouldReceive('persist')
        ->atLeast()->once()
        ->andReturnUsing(function (object $entity) use ($newId): void {
            if ($entity instanceof Invoice && $entity->getId() === null) {
                setEntityId($entity, $newId);
            }
        });
    $em->shouldReceive('flush')->atLeast()->once();

    $events = [];
    $eventDispatcher = new class($events) {
        public function __construct(private array &$events)
        {
        }

        public function dispatch(FOSSBilling\Events\Event $event): FOSSBilling\Events\Event
        {
            $this->events[] = $event;

            return $event;
        }
    };
    $logger = new Tests\Helpers\TestLogger();

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(moduleService([
        'system' => $systemService,
        'email' => $emailService,
    ]));
    $di['event_dispatcher'] = $eventDispatcher;
    $di['logger'] = $logger;

    $serviceMock->setDi($di);
    expect($serviceMock->refundInvoice($invoiceModel))->toBe($newId)
        ->and($invoiceModel->getStatus())->toBe(Invoice::STATUS_REFUNDED);
    expect($events)->toHaveCount(2)
        ->and($events[0])->toBeInstanceOf(BeforeAdminInvoiceRefundEvent::class)
        ->and($events[0]->invoiceId)->toBe(9)
        ->and($events[1])->toBeInstanceOf(AfterAdminInvoiceRefundEvent::class)
        ->and($events[1]->invoiceId)->toBe(9);

    $emailErrors = array_filter(
        $logger->calls,
        static fn (array $call): bool => ($call['method'] ?? null) === 'error' && ($call['channel'] ?? null) === 'email'
    );
    expect($emailErrors)->not->toBeEmpty();
});

test('refundInvoice refuses invoices that are not paid', function (): void {
    foreach ([Invoice::STATUS_UNPAID, Invoice::STATUS_REFUNDED, Invoice::STATUS_CANCELED] as $status) {
        $service = new Service();
        $invoice = createEntity(Invoice::class, ['id' => 10]);
        $invoice->setStatus($status);

        $systemMock = Mockery::mock(SystemService::class);
        $systemMock->shouldReceive('getParamValue')
            ->with('invoice_refund_logic', 'manual')
            ->andReturn('credit_note');
        $systemMock->shouldReceive('getParamValue')
            ->with('invoice_number_padding')
            ->andReturn(5);
        $systemMock->shouldReceive('getCompany')
            ->andReturn([]);
        $systemMock->shouldReceive('getParamValue')
            ->with('invoice_hash_lifetime_days', '90')
            ->andReturn(90);
        $systemMock->shouldReceive('reserveNextNumericParamValue')
            ->once()
            ->with('invoice_cn_starting_number', 1)
            ->andReturn(1);

        $di = container();
        $invoiceRepo = $di['em']->getRepository(Invoice::class);
        $invoiceRepo->shouldReceive('lockAndGetStatus')
            ->once()
            ->with(10)
            ->andReturn($status);
        $di['mod_service'] = $di->protect(moduleService(['system' => $systemMock]));
        $service->setDi($di);

        expect(fn () => $service->refundInvoice($invoice))
            ->toThrow(FOSSBilling\InformationException::class, 'Only paid invoices can be refunded');
    }
});

test('updates an invoice', function (): void {
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

    $invoiceModel = createEntity(Invoice::class);

    $invoiceItemModel = createEntity(InvoiceItem::class, []);

    $eventDispatcher = new class {
        public array $events = [];

        public function dispatch(FOSSBilling\Events\Event $event): FOSSBilling\Events\Event
        {
            $this->events[] = $event;

            return $event;
        }
    };

    $itemInvoiceServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $itemInvoiceServiceMock->shouldReceive('addNew')
        ->atLeast()->once();
    $itemInvoiceServiceMock->shouldReceive('update')
        ->atLeast()->once();

    [$em, $invoiceItemRepo] = invoiceItemEmAndRepo();
    $invoiceItemRepo->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn($invoiceItemModel);
    $em->shouldReceive('persist')
        ->atLeast()->once();
    $em->shouldReceive('flush')
        ->atLeast()->once();

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $itemInvoiceServiceMock);
    $di['event_dispatcher'] = $eventDispatcher;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->setDi($di);

    $result = $serviceMock->updateInvoice($invoiceModel, $data);
    expect($result)->toBeTrue()
        ->and($eventDispatcher->events)->toHaveCount(2)
        ->and($eventDispatcher->events[0])->toBeInstanceOf(BeforeAdminInvoiceUpdateEvent::class)
        ->and($eventDispatcher->events[0]->invoiceId)->toBe((int) $invoiceModel->getId())
        ->and($eventDispatcher->events[0]->changedFields)->toContain('buyer_email', 'notes')
        ->and($eventDispatcher->events[1])->toBeInstanceOf(AfterAdminInvoiceUpdateEvent::class)
        ->and($eventDispatcher->events[1]->invoiceId)->toBe((int) $invoiceModel->getId());
});

test('removes an invoice', function (): void {
    $service = new Service();
    $invoiceModel = createEntity(Invoice::class);

    $invoiceItemModel = createEntity(InvoiceItem::class, []);

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('executeStatement')
        ->atLeast()->once();
    [$em, $invoiceItemRepo] = invoiceItemEmAndRepo();
    $invoiceItemRepo->shouldReceive('findByInvoiceId')
        ->atLeast()->once()
        ->andReturn([$invoiceItemModel]);
    $em->shouldReceive('remove')
        ->atLeast()->once();
    $em->shouldReceive('flush')
        ->atLeast()->once();

    // Regression coverage: transaction.invoice_id would be a real FK if MySQL ever adopted the
    // entity-metadata-driven schema generator - this cleanup used to be missing entirely, which
    // would make a real FK constraint reject the delete outright. Confirmed against a live
    // MariaDB container with FK enforcement during the unification scoping audit.
    $transactionRepo = Mockery::mock(TransactionRepository::class);
    $transactionRepo->shouldReceive('detachFromInvoice')->once()->with((int) $invoiceModel->getId());
    $em->shouldReceive('getRepository')->with(Transaction::class)->andReturn($transactionRepo);

    $di = container();
    $di['em'] = $em;
    $em->shouldReceive('getConnection')->andReturn($connection);

    $service->setDi($di);

    $result = $service->rmInvoice($invoiceModel);
    expect($result)->toBeTrue();
});

test('deletes invoice by admin', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('rmInvoice')
        ->once();

    $invoiceModel = createEntity(Invoice::class);

    $eventDispatcher = new class {
        public array $events = [];

        public function dispatch(FOSSBilling\Events\Event $event): FOSSBilling\Events\Event
        {
            $this->events[] = $event;

            return $event;
        }
    };

    $di = container();
    $di['event_dispatcher'] = $eventDispatcher;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    $result = $serviceMock->deleteInvoiceByAdmin($invoiceModel);
    expect($result)->toBeTrue()
        ->and($eventDispatcher->events)->toHaveCount(2)
        ->and($eventDispatcher->events[0])->toBeInstanceOf(BeforeAdminInvoiceDeleteEvent::class)
        ->and($eventDispatcher->events[0]->invoiceId)->toBe((int) $invoiceModel->getId())
        ->and($eventDispatcher->events[1])->toBeInstanceOf(AfterAdminInvoiceDeleteEvent::class)
        ->and($eventDispatcher->events[1]->invoiceId)->toBe((int) $invoiceModel->getId());
});

test('renews an invoice', function (): void {
    $newId = 2;
    $invoiceModel = createEntity(Invoice::class);

    $invoiceModel->id = $newId;

    $clientOrder = createEntity(Order::class);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('approveInvoice')
        ->once();
    $serviceMock->shouldReceive('generateForOrder')
        ->once()
        ->andReturn($invoiceModel);

    $eventDispatcher = new class {
        public array $events = [];

        public function dispatch(FOSSBilling\Events\Event $event): FOSSBilling\Events\Event
        {
            $this->events[] = $event;

            return $event;
        }
    };

    $di = container();
    $di['event_dispatcher'] = $eventDispatcher;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $result = $serviceMock->renewInvoice($clientOrder, []);
    expect($result)->toBeInt()->toBe($newId)
        ->and($eventDispatcher->events)->toHaveCount(2)
        ->and($eventDispatcher->events[0])->toBeInstanceOf(BeforeAdminGenerateRenewalInvoiceEvent::class)
        ->and($eventDispatcher->events[0]->orderId)->toBe((int) $clientOrder->getId())
        ->and($eventDispatcher->events[1])->toBeInstanceOf(AfterAdminGenerateRenewalInvoiceEvent::class)
        ->and($eventDispatcher->events[1]->orderId)->toBe((int) $clientOrder->getId())
        ->and($eventDispatcher->events[1]->invoiceId)->toBe($newId);
});

test('processes batch pay with credits', function (): void {
    $service = new Service();
    $invoiceModel = createEntity(Invoice::class);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('findAllUnpaid')
        ->atLeast()->once()
        ->andReturn([['id' => 1]]);
    $serviceMock->shouldReceive('tryPayWithCredits')
        ->atLeast()->once();

    $di = container();
    $invoiceRepo = $di['em']->getRepository(Invoice::class);
    $invoiceRepo->shouldReceive('findBy')
        ->andReturn([$invoiceModel]);
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $result = $serviceMock->doBatchPayWithCredits([]);
    expect($result)->toBeBool()->toBeTrue();
});

test('pays invoice with credits', function (): void {
    $service = new Service();
    $invoiceModel = createEntity(Invoice::class);

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
    $clientOrder = createEntity(Order::class, ['unpaidInvoiceId' => 2]);

    $invoiceModel = createEntity(Invoice::class);

    $invoiceModel->status = Invoice::STATUS_UNPAID;

    $di = container();
    $invoiceRepo = $di['em']->getRepository(Invoice::class);
    $invoiceRepo->shouldReceive('find')
        ->with(2)
        ->andReturn($invoiceModel);

    $service->setDi($di);
    $result = $service->generateForOrder($clientOrder);
    expect($result)->toBeInstanceOf(Invoice::class);
});

test('clears stale paid invoice reference when generating for order', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('setInvoiceDefaults')
        ->once();

    $clientOrder = createEntity(Order::class, [
        'client_id' => 1,
        'unpaidInvoiceId' => 2,
        'price' => 10,
        'quantity' => 1,
    ]);

    $paidInvoice = createEntity(Invoice::class);

    $paidInvoice->status = Invoice::STATUS_PAID;

    $newInvoice = createEntity(Invoice::class);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('unsetUnpaidInvoice')
        ->with($clientOrder)
        ->once();

    $invoiceItemServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $invoiceItemServiceMock->shouldReceive('generateFromOrder')
        ->with(Mockery::type(Invoice::class), $clientOrder, InvoiceItem::TASK_RENEW, 10, Mockery::type('array'), true)
        ->once();

    $di = container();
    $invoiceRepo = $di['em']->getRepository(Invoice::class);
    $invoiceRepo->shouldReceive('find')
        ->with(2)
        ->andReturn($paidInvoice);
    $di['em']->shouldReceive('persist')->atLeast()->once();
    $di['em']->shouldReceive('flush')->atLeast()->once();
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

    expect($result)->toBeInstanceOf(Invoice::class);
});

test('generates invoice for order', function (): void {
    $service = new Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('setInvoiceDefaults')
        ->once();

    $orderModel = createEntity(Order::class, ['client_id' => 1, 'price' => 10, 'promoRecurring' => true]);

    $invoiceModel = createEntity(Invoice::class);

    $invoiceItemServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $invoiceItemServiceMock->shouldReceive('generateFromOrder')
        ->atLeast()->once();

    $di = container();
    $di['em']->shouldReceive('persist')->atLeast()->once();
    $di['em']->shouldReceive('flush')->atLeast()->once();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $invoiceItemServiceMock);

    $serviceMock->setDi($di);
    $result = $serviceMock->generateForOrder($orderModel);
    expect($result)->toBeInstanceOf(Invoice::class);
});

test('generates invoice for active order using the order price, not the product price', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('setInvoiceDefaults')
        ->once();

    $orderModel = createEntity(Order::class, [
        'client_id' => 1,
        'status' => Order::STATUS_ACTIVE,
        'productId' => 5,
        'currency' => 'USD',
        'price' => 25,
        'quantity' => 1,
    ]);

    $invoiceModel = createEntity(Invoice::class);

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
        ->with(Mockery::type(Invoice::class), $orderModel, InvoiceItem::TASK_RENEW, 25, Mockery::on(fn ($line): bool => $line['price'] == 25 && $line['quantity'] === 1), true)
        ->once();

    $di = container();
    $di['em']->shouldReceive('persist')->atLeast()->once();
    $di['em']->shouldReceive('flush')->atLeast()->once();
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

test('generates domain renewal invoice with renewal title containing the domain', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('setInvoiceDefaults')
        ->once();

    $orderConfig = ['action' => 'register', 'register_sld' => 'example', 'register_tld' => '.com'];
    $orderModel = createEntity(Order::class, [
        'client_id' => 1,
        'status' => Order::STATUS_ACTIVE,
        'productId' => 5,
        'currency' => 'USD',
        'price' => 25,
        'quantity' => 1,
        'title' => 'Domain registration (example.com)',
        'config' => json_encode($orderConfig),
    ]);

    $product = Mockery::mock(Product::class)->makePartial();
    $product->shouldReceive('getType')->andReturn(ProductService::DOMAIN);

    $domainService = Mockery::mock(Box\Mod\Servicedomain\Service::class);
    $domainService->shouldReceive('getRenewalTitle')
        ->with($orderConfig)
        ->once()
        ->andReturn('Domain renewal (example.com)');

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('findProductById')
        ->with(5)
        ->once()
        ->andReturn($product);
    $productService->shouldReceive('getProductRenewalLineConfig')
        ->with($product, $orderConfig)
        ->once()
        ->andReturn(['price' => 12.0, 'quantity' => 1]);
    $productService->shouldReceive('getProductModuleService')
        ->with($product)
        ->once()
        ->andReturn($domainService);

    $currencyServiceMock = Mockery::mock(CurrencyService::class);
    $currencyRepository = Mockery::mock(CurrencyRepository::class);
    $currencyRepository->shouldReceive('getRateByCode')->with('USD')->andReturn(1.0);
    $currencyServiceMock->shouldReceive('getCurrencyRepository')->andReturn($currencyRepository);

    $invoiceItemServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $invoiceItemServiceMock->shouldReceive('generateFromOrder')
        ->with(Mockery::type(Invoice::class), $orderModel, InvoiceItem::TASK_RENEW, 12.0, Mockery::on(fn ($line): bool => ($line['title'] ?? null) === 'Domain renewal (example.com)'), true)
        ->once();

    $di = container();
    $di['em']->shouldReceive('persist')->atLeast()->once();
    $di['em']->shouldReceive('flush')->atLeast()->once();
    $di['mod_service'] = $di->protect(function (string $module, ?string $sub = null) use ($productService, $currencyServiceMock, $invoiceItemServiceMock): Mockery\MockInterface {
        if ($module === 'Product') {
            return $productService;
        }

        if ($module === 'Currency') {
            return $currencyServiceMock;
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

    $orderModel = createEntity(Order::class, [
        'client_id' => 1,
        'price' => 0,
        'quantity' => 1,
        'currency' => 'USD',
    ]);

    $invoiceModel = createEntity(Invoice::class);

    $invoiceItemServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $invoiceItemServiceMock->shouldReceive('generateFromOrder')
        ->atLeast()->once();

    $di = container();
    $di['em']->shouldReceive('persist')->atLeast()->once();
    $di['em']->shouldReceive('flush')->atLeast()->once();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $invoiceItemServiceMock);

    $serviceMock->setDi($di);
    $result = $serviceMock->generateForOrder($orderModel);
    expect($result)->toBeInstanceOf(Invoice::class);
});

test('throws exception when generating invoice for negative amount order', function (): void {
    $service = new Service();
    $clientOrder = createEntity(Order::class, ['price' => -1, 'quantity' => 1]);

    expect(fn (): Invoice => $service->generateForOrder($clientOrder))
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
    $clientOrder = createEntity(Order::class);

    $invoiceModel = createEntity(Invoice::class);

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
        ->andReturn([['id' => 1]]);

    $orderRepoMock = Mockery::mock(OrderRepository::class);
    $orderRepoMock->shouldReceive('findBy')
        ->atLeast()->once()
        ->andReturn([$clientOrder]);

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderService);
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $result = $serviceMock->generateInvoicesForExpiringOrders();
    expect($result)->toBeBool()->toBeTrue();
});

test('activates paid invoices in batch', function (): void {
    $service = new Service();
    $invoiceItemModel = createEntity(InvoiceItem::class, []);

    $itemInvoiceServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $itemInvoiceServiceMock->shouldReceive('executeTask')
        ->with($invoiceItemModel);
    $itemInvoiceServiceMock->shouldReceive('getAllNotExecutePaidItems')
        ->atLeast()->once()
        ->andReturn([['id' => 1]]);

    [$em, $invoiceItemRepo] = invoiceItemEmAndRepo();
    $invoiceItemRepo->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn($invoiceItemModel);

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('getDatabasePlatform')
        ->andReturn(Mockery::mock(Doctrine\DBAL\Platforms\MySQLPlatform::class));
    $connection->shouldReceive('transactional')
        ->once()
        ->andReturnUsing(fn (callable $func) => $func($connection));
    $connection->shouldReceive('fetchOne')
        ->with('SELECT status FROM invoice_item WHERE id = :id FOR UPDATE', ['id' => 1])
        ->andReturn(InvoiceItem::STATUS_PENDING_SETUP);
    $em->shouldReceive('getConnection')->andReturn($connection);

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $itemInvoiceServiceMock);
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);
    $result = $service->doBatchPaidInvoiceActivation();
    expect($result)->toBeBool()->toBeTrue();
});

test('handles exception during batch paid invoice activation', function (): void {
    $service = new Service();
    $invoiceItemModel = createEntity(InvoiceItem::class, []);

    $itemInvoiceServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $itemInvoiceServiceMock->shouldReceive('executeTask')
        ->with($invoiceItemModel)
        ->andThrow(new FOSSBilling\Exception('testing exception..'));
    $itemInvoiceServiceMock->shouldReceive('getAllNotExecutePaidItems')
        ->atLeast()->once()
        ->andReturn([['id' => 1]]);

    [$em, $invoiceItemRepo] = invoiceItemEmAndRepo();
    $invoiceItemRepo->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn($invoiceItemModel);

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('getDatabasePlatform')
        ->andReturn(Mockery::mock(Doctrine\DBAL\Platforms\MySQLPlatform::class));
    $connection->shouldReceive('transactional')
        ->once()
        ->andReturnUsing(fn (callable $func) => $func($connection));
    $connection->shouldReceive('fetchOne')
        ->with('SELECT status FROM invoice_item WHERE id = :id FOR UPDATE', ['id' => 1])
        ->andReturn(InvoiceItem::STATUS_PENDING_SETUP);
    $em->shouldReceive('getConnection')->andReturn($connection);
    // The exception did not close the EM, so recovery continues: clear and proceed.
    $em->shouldReceive('isOpen')->once()->andReturn(true);
    $em->shouldReceive('clear')->once();

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $itemInvoiceServiceMock);
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);
    $result = $service->doBatchPaidInvoiceActivation();
    expect($result)->toBeBool()->toBeTrue();
});

test('resets the EntityManager when it closes mid-batch so later consumers keep working', function (): void {
    // A flush failure inside executeTask closes the ORM EntityManager. The batch must
    // stop, and the closed manager must be replaced so the rest of the cron run can
    // keep writing (a later consumer reads from the replacement, not the dead EM).
    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $invoiceItemModel = createEntity(InvoiceItem::class, []);

    $itemInvoiceServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $itemInvoiceServiceMock->shouldReceive('getAllNotExecutePaidItems')
        ->once()
        ->andReturn([['id' => 1], ['id' => 2]]);
    // Only the first item is attempted; the batch breaks before the second.
    $itemInvoiceServiceMock->shouldReceive('executeTask')
        ->once()
        ->with($invoiceItemModel)
        ->andThrow(new Exception('flush failure closed the EM'));

    [$em, $invoiceItemRepo] = invoiceItemEmAndRepo();
    $invoiceItemRepo->shouldReceive('find')
        ->once()
        ->andReturn($invoiceItemModel);

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('getDatabasePlatform')
        ->andReturn(Mockery::mock(Doctrine\DBAL\Platforms\MySQLPlatform::class));
    $connection->shouldReceive('transactional')
        ->once()
        ->andReturnUsing(fn (callable $func) => $func($connection));
    $connection->shouldReceive('fetchOne')
        ->with('SELECT status FROM invoice_item WHERE id = :id FOR UPDATE', ['id' => 1])
        ->andReturn(InvoiceItem::STATUS_PENDING_SETUP);
    $em->shouldReceive('getConnection')->andReturn($connection);
    $em->shouldReceive('isOpen')->once()->andReturn(false);
    $em->shouldNotReceive('clear');

    $replacementEm = Mockery::mock(EntityManagerInterface::class);
    $replacementEm->shouldReceive('isOpen')->andReturn(true);

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $itemInvoiceServiceMock);
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->shouldReceive('resetEntityManager')->once()->andReturnUsing(function () use ($di, $replacementEm): void {
        unset($di['em']);
        $di['em'] = $replacementEm;
    });
    $service->setDi($di);

    expect($service->doBatchPaidInvoiceActivation())->toBeTrue();
    // The closed manager is gone; later cron consumers see the open replacement.
    expect($di['em'])->toBe($replacementEm);
    expect($di['em']->isOpen())->toBeTrue();
});

test('resetEntityManager invalidates both cached repositories so they re-resolve from the replacement', function (): void {
    // Exercises the real resetEntityManager() body (only the factory seam is mocked),
    // verifying that both lazily-cached repositories are dropped and re-resolve from
    // the replacement EntityManager rather than the closed one.
    $initialItemRepo = Mockery::mock(InvoiceItemRepository::class);
    $initialInvoiceRepo = Mockery::mock(InvoiceRepository::class);
    $initialEm = Mockery::mock(EntityManagerInterface::class);
    $initialEm->shouldReceive('getRepository')->with(InvoiceItem::class)->andReturn($initialItemRepo);
    $initialEm->shouldReceive('getRepository')->with(Invoice::class)->andReturn($initialInvoiceRepo);
    $initialEm->shouldReceive('getConnection')->once()->andReturn(Mockery::mock(Doctrine\DBAL\Connection::class));

    $replacementItemRepo = Mockery::mock(InvoiceItemRepository::class);
    $replacementInvoiceRepo = Mockery::mock(InvoiceRepository::class);
    $replacementEm = Mockery::mock(EntityManagerInterface::class);
    $replacementEm->shouldReceive('getRepository')->with(InvoiceItem::class)->andReturn($replacementItemRepo);
    $replacementEm->shouldReceive('getRepository')->with(Invoice::class)->andReturn($replacementInvoiceRepo);

    $di = container();
    $di['em'] = $initialEm;

    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('createEntityManager')->once()->with(Mockery::type(Doctrine\DBAL\Connection::class))->andReturn($replacementEm);
    $service->setDi($di);

    // Prime both caches from the initial EM.
    expect($service->getInvoiceItemRepository())->toBe($initialItemRepo);
    expect($service->getInvoiceRepository())->toBe($initialInvoiceRepo);

    // Trigger the real reset flow; only the factory seam is intercepted.
    (new ReflectionMethod(Service::class, 'resetEntityManager'))->invoke($service);

    // Both repositories now come from the replacement EntityManager.
    expect($service->getInvoiceItemRepository())->toBe($replacementItemRepo);
    expect($service->getInvoiceRepository())->toBe($replacementInvoiceRepo);
    expect($di['em'])->toBe($replacementEm);
});

test('skips invoice items already finalized by another process during batch activation', function (): void {
    $service = new Service();

    $itemInvoiceServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $itemInvoiceServiceMock->shouldReceive('getAllNotExecutePaidItems')
        ->atLeast()->once()
        ->andReturn([['id' => 1]]);
    $itemInvoiceServiceMock->shouldNotReceive('executeTask');

    [$em, $invoiceItemRepo] = invoiceItemEmAndRepo();
    $invoiceItemRepo->shouldNotReceive('find');

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('getDatabasePlatform')
        ->andReturn(Mockery::mock(Doctrine\DBAL\Platforms\MySQLPlatform::class));
    $connection->shouldReceive('transactional')
        ->once()
        ->andReturnUsing(fn (callable $func): mixed => $func($connection));
    $connection->shouldReceive('fetchOne')
        ->with('SELECT status FROM invoice_item WHERE id = :id FOR UPDATE', ['id' => 1])
        ->andReturn(InvoiceItem::STATUS_FAILED);
    $em->shouldReceive('getConnection')->andReturn($connection);

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $itemInvoiceServiceMock);
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);
    expect($service->doBatchPaidInvoiceActivation())->toBeTrue();
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

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('getDatabasePlatform')
        ->andReturn(Mockery::mock(Doctrine\DBAL\Platforms\MySQLPlatform::class));
    $connection->shouldReceive('fetchAllAssociative')
        ->twice()
        ->andReturn([['id' => 2, 'days_left' => 7]], []);

    $eventManagerMock = Mockery::mock('\\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->once()
        ->with(['event' => 'onBeforeAdminInvoiceSendReminders']);
    $eventManagerMock->shouldReceive('fire')
        ->once()
        ->with(['event' => 'onEventBeforeInvoiceIsDue', 'params' => ['id' => 2, 'days_left' => 7, 'reminder_intervals' => [7]]]);

    $di = container();
    $di['events_manager'] = $eventManagerMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $systemService);
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['em']->shouldReceive('getConnection')->andReturn($connection);

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

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('getDatabasePlatform')
        ->andReturn(Mockery::mock(Doctrine\DBAL\Platforms\MySQLPlatform::class));
    $connection->shouldReceive('fetchAllAssociative')
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
    $di['events_manager'] = $eventManagerMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $systemService);
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['em']->shouldReceive('getConnection')->andReturn($connection);

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

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('getDatabasePlatform')
        ->andReturn(Mockery::mock(Doctrine\DBAL\Platforms\MySQLPlatform::class));
    $connection->shouldReceive('fetchAllAssociative')
        ->atLeast()->once()
        ->andReturn([['id' => 1]]);

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->atLeast()->once();

    $di = container();
    $di['events_manager'] = $eventManagerMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $systemService);
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['em']->shouldReceive('getConnection')->andReturn($connection);

    $di['em']->shouldReceive('getConnection')->andReturn($connection);

    $service->setDi($di);

    $result = $service->doBatchInvokeDueEvent([]);
    expect($result)->toBeBool()->toBeTrue();
});

test('protects from sending reminders to paid invoices', function (): void {
    $service = new Service();
    $invoiceModel = createEntity(Invoice::class);

    $invoiceModel->status = Invoice::STATUS_PAID;

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
    $invoiceModel = createEntity(Invoice::class);

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->atLeast()->once();

    $di = container();
    $di['em']->shouldReceive('persist')->atLeast()->once();
    $di['em']->shouldReceive('flush')->atLeast()->once();
    $di['events_manager'] = $eventManagerMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    $result = $service->sendInvoiceReminder($invoiceModel);
    expect($result)->toBeBool()->toBeTrue();
});

test('counts invoices', function (): void {
    $service = new Service();
    $sqlResult = [
        ['status' => Invoice::STATUS_PAID,
            'counter' => 2],
    ];
    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('fetchAllAssociative')
        ->atLeast()->once()
        ->andReturn($sqlResult);

    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($connection);

    $service->setDi($di);
    $result = $service->counter();
    expect($result)->toBeArray();
});

test('throws exception when generating funds invoice without active order', function (): void {
    $service = new Service();
    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class);

    expect(fn (): Invoice => $service->generateFundsInvoice($clientModel, 10))
        ->toThrow(FOSSBilling\Exception::class, 'You must have at least one active order before you can add funds so you cannot proceed at the current time!');
});

test('throws exception when generating funds invoice while the feature is disabled', function (): void {
    $service = new Service();
    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class, ['currency' => 'EUR']);

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->once()
        ->with('funds_enabled', true)
        ->andReturn('0');

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $systemService);

    $service->setDi($di);

    expect(fn (): Invoice => $service->generateFundsInvoice($clientModel, 10))
        ->toThrow(FOSSBilling\Exception::class, 'Adding funds to the account balance is currently disabled');
});

test('throws exception when generating funds invoice below minimum amount', function (): void {
    $service = new Service();
    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class, ['currency' => 'EUR']);
    $fundsAmount = 2;

    $minAmount = 10;
    $maxAmount = 50;
    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')->with('funds_enabled', true)->andReturn(true);
    $systemService->shouldReceive('getParamValue')->with('funds_min_amount', null)->andReturn($minAmount);
    $systemService->shouldReceive('getParamValue')->with('funds_max_amount', null)->andReturn($maxAmount);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $systemService);

    $service->setDi($di);

    expect(fn (): Invoice => $service->generateFundsInvoice($clientModel, $fundsAmount))
        ->toThrow(FOSSBilling\Exception::class, 'Amount must be at least ' . $minAmount);
});

test('throws exception when generating funds invoice above maximum amount', function (): void {
    $service = new Service();
    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class, ['currency' => 'EUR']);
    $fundsAmount = 200;

    $minAmount = 10;
    $maxAmount = 50;
    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')->with('funds_enabled', true)->andReturn(true);
    $systemService->shouldReceive('getParamValue')->with('funds_min_amount', null)->andReturn($minAmount);
    $systemService->shouldReceive('getParamValue')->with('funds_max_amount', null)->andReturn($maxAmount);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $systemService);

    $service->setDi($di);

    expect(fn (): Invoice => $service->generateFundsInvoice($clientModel, $fundsAmount))
        ->toThrow(FOSSBilling\Exception::class, 'Amount cannot exceed ' . $maxAmount);
});

test('generates funds invoice', function (): void {
    $service = new Service();
    $invoiceModel = createEntity(Invoice::class);

    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class, ['currency' => 'EUR']);
    $fundsAmount = 20;

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('setInvoiceDefaults')
        ->atLeast()->once();

    $minAmount = 10;
    $maxAmount = 50;

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')->with('funds_enabled', true)->andReturn(true);
    $systemService->shouldReceive('getParamValue')->with('funds_min_amount', null)->andReturn($minAmount);
    $systemService->shouldReceive('getParamValue')->with('funds_max_amount', null)->andReturn($maxAmount);
    $systemService->shouldReceive('getParamValue')->with('invoice_auto_approval', true)->andReturn(true);

    $itemInvoiceServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $itemInvoiceServiceMock->shouldReceive('generateForAddFunds')
        ->atLeast()->once();

    $di = container();
    $di['em']->shouldReceive('persist')->atLeast()->once();
    $di['em']->shouldReceive('flush')->atLeast()->once();
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

test('isFundsEnabled defaults to true when the setting was never saved', function (): void {
    $service = new Service();

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->once()
        ->with('funds_enabled', true)
        ->andReturn(true);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $systemService);

    $service->setDi($di);

    expect($service->isFundsEnabled())->toBeTrue();
});

test('isFundsEnabled returns false when explicitly disabled', function (): void {
    $service = new Service();

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->once()
        ->with('funds_enabled', true)
        ->andReturn('0');

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $systemService);

    $service->setDi($di);

    expect($service->isFundsEnabled())->toBeFalse();
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

    $invoiceModel = createEntity(Invoice::class);

    $di = container();
    $invoiceRepo = $di['em']->getRepository(Invoice::class);
    $invoiceRepo->shouldReceive('findByHash')
        ->with('hashString')
        ->andReturn($invoiceModel);
    $gatewayRepo = $di['em']->getRepository(PayGateway::class);
    $gatewayRepo->shouldReceive('find')
        ->atLeast()->once()
        ->with(2)
        ->andReturn(null);

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

    $invoiceModel = createEntity(Invoice::class);

    $payGatewayModel = createEntity(PayGateway::class, ['id' => 2, 'enabled' => false]);

    $di = container();
    $invoiceRepo = $di['em']->getRepository(Invoice::class);
    $invoiceRepo->shouldReceive('findByHash')
        ->with('hashString')
        ->andReturn($invoiceModel);
    $gatewayRepo = $di['em']->getRepository(PayGateway::class);
    $gatewayRepo->shouldReceive('find')
        ->atLeast()->once()
        ->with(2)
        ->andReturn($payGatewayModel);

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

    $invoiceModel = createEntity(Invoice::class);

    $payGatewayModel = createEntity(PayGateway::class, ['id' => 2, 'enabled' => true]);

    $di = container();
    $invoiceRepo = $di['em']->getRepository(Invoice::class);
    $invoiceRepo->shouldReceive('findByHash')
        ->with('hashString')
        ->andReturn($invoiceModel);
    $gatewayRepo = $di['em']->getRepository(PayGateway::class);
    $gatewayRepo->shouldReceive('find')
        ->atLeast()->once()
        ->with(2)
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

    $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($payGatewayService, $subscribeService) {
        if ($sub == 'PayGateway') {
            return $payGatewayService;
        }
        if ($sub == 'Subscription') {
            return $subscribeService;
        }
    });
    $di['api_admin'] = new FOSSBilling\Api\Proxy(\Tests\Helpers\admin());
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

    $invoiceModel = createEntity(Invoice::class);

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
    $invoiceRepo = $di['em']->getRepository(Invoice::class);
    $invoiceRepo->shouldReceive('find')
        ->once()
        ->with(1)
        ->andReturn($invoiceModel);
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

    $invoiceModel = createEntity(Invoice::class);

    $di = container();
    $di['em']->shouldReceive('persist')->atLeast()->once();
    $di['em']->shouldReceive('flush')->atLeast()->once();
    $service->setDi($di);

    $result = $service->addNote($invoiceModel, $note);
    expect($result)->toBeTrue();
});

test('finds all unpaid invoices', function (): void {
    $service = new Service();
    $invoiceModel = createEntity(Invoice::class);

    $getAllResult = [
        [
            'id' => 1,
            'client_id' => 1,
            'serie' => 'BB',
            'nr' => '00',
        ],
    ];
    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('fetchAllAssociative')
        ->atLeast()->once()
        ->andReturn($getAllResult);

    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $service->setDi($di);

    $result = $service->findAllUnpaid();
    expect($result)->toBeArray();
});

test('finds all paid invoices', function (): void {
    $service = new Service();
    $invoiceModel = createEntity(Invoice::class);

    $di = container();
    $invoiceRepo = $di['em']->getRepository(Invoice::class);
    $invoiceRepo->shouldReceive('findPaid')
        ->andReturn([$invoiceModel]);
    $service->setDi($di);

    $result = $service->findAllPaid();
    expect($result)->toBeArray();
    expect($result[0])->toBeInstanceOf(Invoice::class);
});

test('gets unpaid invoices late for', function (): void {
    $service = new Service();
    $invoiceModel = createEntity(Invoice::class);

    $di = container();
    $invoiceRepo = $di['em']->getRepository(Invoice::class);
    $invoiceRepo->shouldReceive('findUnpaidApprovedNotRemindedBefore')
        ->andReturn([$invoiceModel]);
    $service->setDi($di);

    $result = $service->getUnpaidInvoicesLateFor();
    expect($result)->toBeArray();
    expect($result[0])->toBeInstanceOf(Invoice::class);
});

test('gets buyer', function (): void {
    $service = new Service();
    $invoiceModel = createEntity(Invoice::class);

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

    $modelInvoiceItem = createEntity(InvoiceItem::class, ['type' => InvoiceItem::TYPE_DEPOSIT]);

    $invoiceItems = [$modelInvoiceItem];

    [$em, $invoiceItemRepo] = invoiceItemEmAndRepo();
    $invoiceItemRepo->shouldReceive('findByInvoiceId')
        ->atLeast()->once()
        ->andReturn($invoiceItems);

    $di['em'] = $em;

    $modelInvoice = createEntity(Invoice::class);

    $service->setDi($di);
    $result = $service->isInvoiceTypeDeposit($modelInvoice);
    expect($result)->toBeTrue();
});

test('returns false when invoice type is not deposit', function (): void {
    $service = new Service();
    $di = container();

    $modelInvoiceItem = createEntity(InvoiceItem::class, ['type' => InvoiceItem::TYPE_ORDER]);

    $invoiceItems = [$modelInvoiceItem];

    [$em, $invoiceItemRepo] = invoiceItemEmAndRepo();
    $invoiceItemRepo->shouldReceive('findByInvoiceId')
        ->atLeast()->once()
        ->andReturn($invoiceItems);

    $di['em'] = $em;
    $service->setDi($di);

    $modelInvoice = createEntity(Invoice::class);

    $result = $service->isInvoiceTypeDeposit($modelInvoice);
    expect($result)->toBeFalse();
});

test('returns false when checking deposit with empty items', function (): void {
    $service = new Service();
    $di = container();

    $invoiceItems = [];

    [$em, $invoiceItemRepo] = invoiceItemEmAndRepo();
    $invoiceItemRepo->shouldReceive('findByInvoiceId')
        ->atLeast()->once()
        ->andReturn($invoiceItems);

    $di['em'] = $em;
    $service->setDi($di);

    $modelInvoice = createEntity(Invoice::class);

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
    $service = new Service();

    $subscription = createEntity(Subscription::class, ['id' => 7, 'relType' => 'invoice', 'relId' => 82]);

    $invoiceItem = createEntity(InvoiceItem::class, ['rel_id' => 82]);

    $originalOrder = createEntity(Order::class, ['status' => Order::STATUS_PENDING_SETUP]);

    $orderRepoMock = Mockery::mock(OrderRepository::class);
    $orderRepoMock->shouldReceive('find')
        ->with(82)
        ->andReturn($originalOrder);

    [$em, $invoiceItemRepo] = invoiceItemEmAndRepo();
    $invoiceItemRepo->shouldReceive('findOneByInvoiceIdAndType')
        ->with(82, InvoiceItem::TYPE_ORDER)
        ->andReturn($invoiceItem);
    $subscriptionRepo = Mockery::mock(SubscriptionRepository::class);
    $subscriptionRepo->shouldReceive('findOneBy')->once()->andReturn($subscription);
    $em->shouldReceive('getRepository')->with(Subscription::class)->andReturn($subscriptionRepo);
    $em->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);

    $di = container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->generateRenewalInvoiceForSubscriptionPayment('I-TEST123', 1);
    expect($result)->toBeNull();
});

test('generateRenewalInvoiceForSubscriptionPayment uses the original order and not a product_id lookup', function (): void {
    $subscription = createEntity(Subscription::class, ['id' => 7, 'relType' => 'invoice', 'relId' => 82]);

    $invoiceItem = createEntity(InvoiceItem::class, ['rel_id' => 82]);

    $originalOrder = createEntity(Order::class, [
        'status' => Order::STATUS_ACTIVE,
        'productId' => 1,
    ]);

    $renewalInvoice = createEntity(Invoice::class);

    $renewalInvoice->id = 99;

    $orderRepoMock = Mockery::mock(OrderRepository::class);
    $orderRepoMock->shouldReceive('find')
        ->with(82)
        ->andReturn($originalOrder);

    [$em, $invoiceItemRepo] = invoiceItemEmAndRepo();
    $invoiceItemRepo->shouldReceive('findOneByInvoiceIdAndType')
        ->with(82, InvoiceItem::TYPE_ORDER)
        ->andReturn($invoiceItem);
    $subscriptionRepo = Mockery::mock(SubscriptionRepository::class);
    $subscriptionRepo->shouldReceive('findOneBy')->once()->andReturn($subscription);
    $em->shouldReceive('getRepository')->with(Subscription::class)->andReturn($subscriptionRepo);
    $em->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('generateForOrder')
        ->with(Mockery::on(fn ($order): bool => $order === $originalOrder))
        ->once()
        ->andReturn($renewalInvoice);
    $serviceMock->shouldReceive('approveInvoice')
        ->once();

    $di = container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $result = $serviceMock->generateRenewalInvoiceForSubscriptionPayment('I-TEST123', 1);

    expect($result)->toBeInstanceOf(Invoice::class);
    expect($result->id)->toBe(99);
});

test('generateRenewalInvoiceForSubscriptionPayment still renews an order the batch-suspend cron already suspended', function (string $status): void {
    // A gateway's subscription-payment IPN can legitimately arrive after the
    // batch-suspend cron has already suspended the order for missing its
    // expiry, or after a prior renewal attempt left it failed_renew.
    $subscription = createEntity(Subscription::class, ['id' => 7, 'relType' => 'invoice', 'relId' => 82]);

    $invoiceItem = createEntity(InvoiceItem::class, ['rel_id' => 82]);

    $originalOrder = createEntity(Order::class, [
        'status' => $status,
        'productId' => 1,
    ]);

    $renewalInvoice = createEntity(Invoice::class);

    $renewalInvoice->id = 99;

    $orderRepoMock = Mockery::mock(OrderRepository::class);
    $orderRepoMock->shouldReceive('find')
        ->with(82)
        ->andReturn($originalOrder);

    [$em, $invoiceItemRepo] = invoiceItemEmAndRepo();
    $invoiceItemRepo->shouldReceive('findOneByInvoiceIdAndType')
        ->with(82, InvoiceItem::TYPE_ORDER)
        ->andReturn($invoiceItem);
    $subscriptionRepo = Mockery::mock(SubscriptionRepository::class);
    $subscriptionRepo->shouldReceive('findOneBy')->once()->andReturn($subscription);
    $em->shouldReceive('getRepository')->with(Subscription::class)->andReturn($subscriptionRepo);
    $em->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('generateForOrder')
        ->with(Mockery::on(fn ($order): bool => $order === $originalOrder))
        ->once()
        ->andReturn($renewalInvoice);
    $serviceMock->shouldReceive('approveInvoice')
        ->once();

    $di = container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $result = $serviceMock->generateRenewalInvoiceForSubscriptionPayment('I-TEST123', 1);

    expect($result)->toBeInstanceOf(Invoice::class);
    expect($result->id)->toBe(99);
})->with([
    'suspended' => Order::STATUS_SUSPENDED,
    'failed renew' => Order::STATUS_FAILED_RENEW,
]);

test('markAsPaid transitions a deposit invoice to paid status', function (): void {
    $service = new Service();

    $depositInvoice = createEntity(Invoice::class);

    $depositInvoice->id = 89;
    $depositInvoice->status = Invoice::STATUS_UNPAID;
    $depositInvoice->approved = true;
    $depositInvoice->currency = 'USD';

    $depositItem = createEntity(InvoiceItem::class, ['id' => 96, 'type' => InvoiceItem::TYPE_DEPOSIT, 'task' => 'void']);

    [$em, $invoiceItemRepo] = invoiceItemEmAndRepo();
    $invoiceItemRepo->shouldReceive('findByInvoiceId')
        ->andReturn([$depositItem]);
    $em->shouldReceive('persist')
        ->atLeast()->once();
    $em->shouldReceive('flush')
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

    $events = [];
    $eventDispatcher = new class($events) {
        public function __construct(private array &$events)
        {
        }

        public function dispatch(FOSSBilling\Events\Event $event): FOSSBilling\Events\Event
        {
            $this->events[] = $event;

            return $event;
        }
    };

    $di = container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $productService = Mockery::mock(ProductService::class)->shouldIgnoreMissing();
    $di['mod_service'] = $di->protect(fn ($name, $sub = '') => match ([$name, $sub]) {
        ['system', ''] => $systemService,
        ['currency', ''] => $currencyService,
        ['Invoice', 'InvoiceItem'] => $invoiceItemService,
        ['Product', ''], ['product', ''] => $productService,
        default => throw new RuntimeException("Unexpected service: {$name}/{$sub}"),
    });
    $di['event_dispatcher'] = $eventDispatcher;
    $service->setDi($di);

    $result = $service->markAsPaid($depositInvoice);

    expect($result)->toBeTrue();
    expect($depositInvoice->status)->toBe(Invoice::STATUS_PAID);
    expect($depositInvoice->paid_at)->not->toBeNull();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(AfterAdminInvoicePaymentReceivedEvent::class)
        ->and($events[0]->invoiceId)->toBe(89);
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

    $invoiceModel = createEntity(Invoice::class);

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

    $invoiceModel = createEntity(Invoice::class);

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

    $invoiceModel = createEntity(Invoice::class);

    $serviceMock->shouldReceive('toApiArray')
        ->once()
        ->andThrow(new Exception('boom'));

    $result = $serviceMock->getInvoicePdfAttachment($invoiceModel);

    expect($result)->toBeNull();
    $errors = array_filter($logger->calls, fn ($c): bool => $c['method'] === 'error');
    expect($errors)->not->toBeEmpty();
});

test('exportCSV strips hash from numeric-array headers', function (): void {
    $service = new Service();

    $capturedHeaders = null;
    $factoryMock = Mockery::mock();
    $factoryMock->shouldReceive('create')
        ->once()
        ->andReturnUsing(function (string $table, string $name, array $headers) use (&$capturedHeaders): Symfony\Component\HttpFoundation\Response {
            $capturedHeaders = $headers;

            return new Symfony\Component\HttpFoundation\Response();
        });

    $di = container();
    $di['csv_response_factory'] = $factoryMock;
    $service->setDi($di);

    $service->exportCSV(['hash', 'id', 'buyer_email']);

    expect($capturedHeaders)->not->toContain('hash')
        ->and($capturedHeaders)->toContain('id')
        ->and($capturedHeaders)->toContain('buyer_email');
});

test('exportCSV falls back to defaults when only hash is requested', function (): void {
    $service = new Service();

    $capturedHeaders = null;
    $factoryMock = Mockery::mock();
    $factoryMock->shouldReceive('create')
        ->once()
        ->andReturnUsing(function (string $table, string $name, array $headers) use (&$capturedHeaders): Symfony\Component\HttpFoundation\Response {
            $capturedHeaders = $headers;

            return new Symfony\Component\HttpFoundation\Response();
        });

    $di = container();
    $di['csv_response_factory'] = $factoryMock;
    $service->setDi($di);

    $service->exportCSV(['hash']);

    expect($capturedHeaders)->toContain('id')
        ->and($capturedHeaders)->toContain('buyer_email')
        ->and($capturedHeaders)->not->toContain('hash');
});

test('promoAddToInvoice applies a promo to an order on an unpaid invoice', function (): void {
    $service = new Service();

    $invoice = createEntity(Invoice::class, ['id' => 10, 'client_id' => 3, 'currency' => 'USD']);
    $invoice->setStatus(Invoice::STATUS_UNPAID);

    $order = createEntity(Order::class, [
        'id' => 20,
        'client_id' => 3,
        'product_id' => 5,
        'price' => 100.0,
        'quantity' => 1,
        'discount' => 0.0,
        'currency' => 'USD',
        'config' => json_encode(['period' => '1Y']),
        'unpaid_invoice_id' => 10,
    ]);

    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 3]);

    $promo = new Box\Mod\Product\Entity\Promo();
    $promoReflection = new ReflectionProperty($promo, 'id');
    $promoReflection->setValue($promo, 7);
    $promo->setCode('ADMIN10')->setRecurring(true);

    $product = new Product();
    $productReflection = new ReflectionProperty($product, 'id');
    $productReflection->setValue($product, 5);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('promoCanBeApplied')->once()->with($promo)->andReturn(true);
    $productService->shouldReceive('isPromoAvailableForClientGroup')->once()->with($promo, $client)->andReturn(true);
    $productService->shouldReceive('canClientUsePromo')->once()->with($client, $promo)->andReturn(true);
    $productService->shouldReceive('findProductById')->once()->with(5)->andReturn($product);
    $productService->shouldReceive('isPromoApplicableToProduct')->once()->andReturn(true);
    $productService->shouldReceive('getProductDiscount')->once()->andReturn(25.0);
    $productService->shouldReceive('clientHasActivePromoApplicationForUpdate')->once()->with($client, $promo)->andReturn(false);
    $productService->shouldReceive('usePromo')->once()->with($promo);
    $productService->shouldReceive('createPromoRedemption')
        ->once()
        ->with(
            $promo,
            $client,
            $order,
            $invoice,
            Box\Mod\Product\Entity\PromoRedemption::PHASE_CHECKOUT,
            25.0,
            'USD',
            Mockery::any(),
            Box\Mod\Product\Entity\PromoRedemption::STATUS_RESERVED
        )
        ->andReturn(1);

    $currencyRepository = Mockery::mock(CurrencyRepository::class);
    $currencyRepository->shouldReceive('getRateByCode')->once()->with('USD')->andReturn(1.0);
    $currencyService = Mockery::mock(CurrencyService::class);
    $currencyService->shouldReceive('getCurrencyRepository')->once()->andReturn($currencyRepository);

    $clientService = Mockery::mock(ClientService::class);
    $clientService->shouldReceive('isClientTaxable')->once()->with($client)->andReturn(false);

    $invoiceItemRepo = Mockery::mock(InvoiceItemRepository::class);
    $invoiceItemRepo->shouldReceive('findByInvoiceId')->once()->with(10)->andReturn([]);

    $em = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $em->shouldReceive('wrapInTransaction')->once()->andReturnUsing(fn (callable $callback): mixed => $callback());
    $em->shouldReceive('persist')->atLeast()->once();
    $em->shouldReceive('flush')->atLeast()->once();

    // Client lookup.
    $clientRepo = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepo->shouldReceive('find')->once()->with(3)->andReturn($client);
    $invoiceRepo = Mockery::mock(InvoiceRepository::class);
    $invoiceRepo->shouldReceive('lockAndGetStatus')->once()->with(10)->andReturn(Invoice::STATUS_UNPAID);
    $em->shouldReceive('getRepository')->andReturnUsing(
        fn (string $class) => match ($class) {
            Box\Mod\Client\Entity\Client::class => $clientRepo,
            Invoice::class => $invoiceRepo,
            default => $invoiceItemRepo,
        }
    );

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(moduleService([
        'product' => $productService,
        'currency' => $currencyService,
        'client' => $clientService,
    ]));
    $di['logger'] = new FOSSBilling\Logger();
    $service->setDi($di);

    $amount = $service->promoAddToInvoice($invoice, $promo, $order);

    expect($amount)->toEqual(25.0);
    expect((float) $order->getDiscount())->toEqual(25.0);
    expect($order->getPromoId())->toBe(7);
    expect($order->isPromoRecurring())->toBeTrue();
});

test('promoAddToInvoice refuses paid invoices', function (): void {
    $service = new Service();
    $service->setDi(container());

    $invoice = createEntity(Invoice::class, ['id' => 10]);
    $invoice->setStatus(Invoice::STATUS_PAID);
    $promo = new Box\Mod\Product\Entity\Promo();

    expect(fn () => $service->promoAddToInvoice($invoice, $promo))
        ->toThrow(FOSSBilling\InformationException::class, 'Promotions can only be applied to unpaid invoices');
});

test('promoRemoveFromInvoice removes a recorded promo', function (): void {
    $service = new Service();

    $invoice = createEntity(Invoice::class, ['id' => 10, 'client_id' => 3, 'currency' => 'USD']);
    $invoice->setStatus(Invoice::STATUS_UNPAID);

    $order = createEntity(Order::class, [
        'id' => 20,
        'price' => 100.0,
        'quantity' => 1,
        'discount' => 25.0,
        'currency' => 'USD',
        'promo_id' => 7,
        'promo_recurring' => true,
        'promo_used' => 1,
        'unpaid_invoice_id' => 10,
    ]);

    $promo = new Box\Mod\Product\Entity\Promo();
    $promoReflection = new ReflectionProperty($promo, 'id');
    $promoReflection->setValue($promo, 7);
    $promo->setCode('ADMIN10')->setRecurring(true);

    $redemption = new Box\Mod\Product\Entity\PromoRedemption();
    $redemption->setPromo($promo)
        ->setPhase(Box\Mod\Product\Entity\PromoRedemption::PHASE_CHECKOUT)
        ->setStatus(Box\Mod\Product\Entity\PromoRedemption::STATUS_RESERVED)
        ->setDiscountAmount(25.0);

    $discountLine = createEntity(InvoiceItem::class, ['id' => 30, 'price' => -25.0, 'unit' => 'discount', 'rel_id' => '20']);

    $redemptionRepo = Mockery::mock(Box\Mod\Product\Repository\PromoRedemptionRepository::class);
    $redemptionRepo->shouldReceive('findBy')->once()->andReturn([$redemption]);
    // No remaining applications after the release.
    $redemptionRepo->shouldReceive('findBy')->once()->andReturn([]);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('getPromoRedemptionRepository')->andReturn($redemptionRepo);
    $productService->shouldReceive('releaseCheckoutPromoRedemptions')
        ->once()
        ->with($order, $promo, 'admin_removed', $invoice)
        ->andReturn(1);

    $invoiceItemRepo = Mockery::mock(InvoiceItemRepository::class);
    $invoiceItemRepo->shouldReceive('findByInvoiceId')->andReturn([$discountLine]);

    $invoiceRepo = Mockery::mock(InvoiceRepository::class);
    $invoiceRepo->shouldReceive('lockAndGetStatus')->once()->with(10)->andReturn(Invoice::STATUS_UNPAID);

    $em = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $em->shouldReceive('wrapInTransaction')->once()->andReturnUsing(fn (callable $callback): mixed => $callback());
    $em->shouldReceive('persist')->atLeast()->once();
    $em->shouldReceive('remove')->once()->with($discountLine);
    $em->shouldReceive('flush')->atLeast()->once();
    $em->shouldReceive('getRepository')->with(InvoiceItem::class)->andReturn($invoiceItemRepo);
    $em->shouldReceive('getRepository')->with(Invoice::class)->andReturn($invoiceRepo);

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(moduleService(['product' => $productService]));
    $di['logger'] = new FOSSBilling\Logger();
    $service->setDi($di);

    $amount = $service->promoRemoveFromInvoice($invoice, $promo, $order);

    expect($amount)->toEqual(25.0);
    expect((float) $order->getDiscount())->toEqual(0.0);
    expect($order->getPromoId())->toBeNull();
});

test('promoRemoveFromInvoice throws when the promo is not applied', function (): void {
    $service = new Service();

    $invoice = createEntity(Invoice::class, ['id' => 10]);
    $invoice->setStatus(Invoice::STATUS_UNPAID);
    $order = createEntity(Order::class, ['id' => 20, 'unpaid_invoice_id' => 10]);
    $promo = new Box\Mod\Product\Entity\Promo();

    $redemptionRepo = Mockery::mock(Box\Mod\Product\Repository\PromoRedemptionRepository::class);
    $redemptionRepo->shouldReceive('findBy')->once()->andReturn([]);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('getPromoRedemptionRepository')->once()->andReturn($redemptionRepo);

    $em = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $em->shouldReceive('wrapInTransaction')->once()->andReturnUsing(fn (callable $callback): mixed => $callback());
    $invoiceItemRepo = Mockery::mock(InvoiceItemRepository::class)->shouldIgnoreMissing();
    $em->shouldReceive('getRepository')->with(InvoiceItem::class)->andReturn($invoiceItemRepo);

    $invoiceRepo = Mockery::mock(InvoiceRepository::class);
    $invoiceRepo->shouldReceive('lockAndGetStatus')->once()->with(10)->andReturn(Invoice::STATUS_UNPAID);
    $em->shouldReceive('getRepository')->with(Invoice::class)->andReturn($invoiceRepo);

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(moduleService(['product' => $productService]));
    $service->setDi($di);

    expect(fn () => $service->promoRemoveFromInvoice($invoice, $promo, $order))
        ->toThrow(FOSSBilling\InformationException::class, 'This promotion is not applied to the selected order');
});

test('promoAddToInvoice aborts when the invoice is paid concurrently', function (): void {
    $service = new Service();

    $invoice = createEntity(Invoice::class, ['id' => 10, 'client_id' => 3, 'currency' => 'USD']);
    $invoice->setStatus(Invoice::STATUS_UNPAID);
    $order = createEntity(Order::class, [
        'id' => 20,
        'product_id' => 5,
        'price' => 100.0,
        'quantity' => 1,
        'currency' => 'USD',
        'config' => json_encode([]),
        'unpaid_invoice_id' => 10,
    ]);
    $promo = new Box\Mod\Product\Entity\Promo();

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('promoCanBeApplied')->once()->andReturn(true);
    $productService->shouldReceive('isPromoAvailableForClientGroup')->once()->andReturn(true);
    $productService->shouldReceive('canClientUsePromo')->once()->andReturn(true);
    $productService->shouldReceive('findProductById')->once()->andReturn(new Product());
    $productService->shouldReceive('isPromoApplicableToProduct')->once()->andReturn(true);
    $productService->shouldReceive('getProductDiscount')->once()->andReturn(25.0);
    $productService->shouldNotReceive('usePromo');

    $currencyRepository = Mockery::mock(CurrencyRepository::class);
    $currencyRepository->shouldReceive('getRateByCode')->once()->with('USD')->andReturn(1.0);
    $currencyService = Mockery::mock(CurrencyService::class);
    $currencyService->shouldReceive('getCurrencyRepository')->once()->andReturn($currencyRepository);

    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 3]);
    $clientRepo = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepo->shouldReceive('find')->once()->with(3)->andReturn($client);
    $invoiceRepo = Mockery::mock(InvoiceRepository::class);
    // A payment committed after the pre-check: the locked status is paid.
    $invoiceRepo->shouldReceive('lockAndGetStatus')->once()->with(10)->andReturn(Invoice::STATUS_PAID);

    $em = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $em->shouldReceive('wrapInTransaction')->once()->andReturnUsing(fn (callable $callback): mixed => $callback());
    $em->shouldReceive('getRepository')->andReturnUsing(
        fn (string $class) => match ($class) {
            Box\Mod\Client\Entity\Client::class => $clientRepo,
            Invoice::class => $invoiceRepo,
            default => Mockery::mock(InvoiceItemRepository::class)->shouldIgnoreMissing(),
        }
    );

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(moduleService([
        'product' => $productService,
        'currency' => $currencyService,
    ]));
    $service->setDi($di);

    expect(fn () => $service->promoAddToInvoice($invoice, $promo, $order))
        ->toThrow(FOSSBilling\InformationException::class, 'Promotions can only be applied to unpaid invoices');
});

test('promoRemoveFromInvoice aborts when the invoice is paid concurrently', function (): void {
    $service = new Service();

    $invoice = createEntity(Invoice::class, ['id' => 10]);
    $invoice->setStatus(Invoice::STATUS_UNPAID);
    $order = createEntity(Order::class, ['id' => 20, 'unpaid_invoice_id' => 10]);
    $promo = new Box\Mod\Product\Entity\Promo();

    $redemptionRepo = Mockery::mock(Box\Mod\Product\Repository\PromoRedemptionRepository::class);
    // The lock aborts before any redemption rows are read.
    $redemptionRepo->shouldNotReceive('findBy');

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('getPromoRedemptionRepository')->andReturn($redemptionRepo);
    $productService->shouldNotReceive('releaseCheckoutPromoRedemptions');

    $invoiceRepo = Mockery::mock(InvoiceRepository::class);
    $invoiceRepo->shouldReceive('lockAndGetStatus')->once()->with(10)->andReturn(Invoice::STATUS_PAID);

    $em = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $em->shouldReceive('wrapInTransaction')->once()->andReturnUsing(fn (callable $callback): mixed => $callback());
    $em->shouldReceive('getRepository')->with(Invoice::class)->andReturn($invoiceRepo);

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(moduleService(['product' => $productService]));
    $service->setDi($di);

    expect(fn () => $service->promoRemoveFromInvoice($invoice, $promo, $order))
        ->toThrow(FOSSBilling\InformationException::class, 'Promotions can only be removed from unpaid invoices');
});

test('getInvoicePromoApplications groups redemptions by promo and order', function (): void {
    $service = new Service();

    $invoice = createEntity(Invoice::class, ['id' => 10, 'currency' => 'USD']);
    $invoice->setStatus(Invoice::STATUS_UNPAID);

    $promo = new Box\Mod\Product\Entity\Promo();
    $promoReflection = new ReflectionProperty($promo, 'id');
    $promoReflection->setValue($promo, 7);
    $promo->setCode('ADMIN10');

    $reserved = new Box\Mod\Product\Entity\PromoRedemption();
    $reserved->setPromo($promo)->setClientOrderId(20)
        ->setPhase(Box\Mod\Product\Entity\PromoRedemption::PHASE_CHECKOUT)
        ->setStatus(Box\Mod\Product\Entity\PromoRedemption::STATUS_RESERVED)
        ->setDiscountAmount(15.0);
    $committed = new Box\Mod\Product\Entity\PromoRedemption();
    $committed->setPromo($promo)->setClientOrderId(21)
        ->setPhase(Box\Mod\Product\Entity\PromoRedemption::PHASE_CHECKOUT)
        ->setStatus(Box\Mod\Product\Entity\PromoRedemption::STATUS_COMMITTED)
        ->setDiscountAmount(10.0);

    $redemptionRepo = Mockery::mock(Box\Mod\Product\Repository\PromoRedemptionRepository::class);
    $redemptionRepo->shouldReceive('findBy')->once()->andReturn([$reserved, $committed]);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('getPromoRedemptionRepository')->once()->andReturn($redemptionRepo);
    $productService->shouldReceive('findPromoById')->twice()->with(7)->andReturn($promo);
    $productService->shouldReceive('getPromoDiscountTitle')->twice()->andReturn('Promotional Code: ADMIN10');

    $di = container();
    $di['mod_service'] = $di->protect(moduleService(['product' => $productService]));
    $service->setDi($di);

    $result = $service->getInvoicePromoApplications($invoice);

    expect($result)->toHaveCount(2);
    expect($result[0])->toMatchArray([
        'promo_id' => 7,
        'code' => 'ADMIN10',
        'order_id' => 20,
        'discount_amount' => 15.0,
        'removable' => true,
    ]);
    expect($result[1])->toMatchArray(['order_id' => 21, 'removable' => false]);
});
test('refundInvoice supports partial line refunds and flips to refunded at full', function (): void {
    $line1 = createEntity(InvoiceItem::class, ['price' => 60.0, 'quantity' => 1, 'taxed' => false]);
    setEntityId($line1, 11);
    $line2 = createEntity(InvoiceItem::class, ['price' => 40.0, 'quantity' => 1, 'taxed' => false]);
    setEntityId($line2, 12);

    $invoiceModel = createEntity(Invoice::class, ['clientId' => 5]);
    $invoiceModel->setStatus(Invoice::STATUS_PAID);
    setEntityId($invoiceModel, 10);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getTotal')->andReturn(100.0, 40.0, 100.0, 60.0);
    $serviceMock->shouldReceive('getTax')->andReturn(0.0);
    $serviceMock->shouldReceive('countIncome')->twice();
    $serviceMock->shouldReceive('addNote')->times(4);
    $serviceMock->shouldReceive('toApiArray')->andReturn(['id' => 1, 'total' => -40.0]);
    $serviceMock->shouldReceive('extendInvoiceHashLifetime')->twice();

    $events = [];
    $eventDispatcher = new class($events) {
        public function __construct(private array &$events)
        {
        }

        public function dispatch(FOSSBilling\Events\Event $event): FOSSBilling\Events\Event
        {
            $this->events[] = $event;

            return $event;
        }
    };

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_refund_logic', 'manual')
        ->andReturn('credit_note');
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_hash_lifetime_days', '90')
        ->andReturn(90);
    $systemService->shouldReceive('reserveNextNumericParamValue')
        ->twice()
        ->with('invoice_cn_starting_number', 1)
        ->andReturn(7, 8);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_cn_series', 'CN-')
        ->andReturn('CN-');
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_number_padding')
        ->andReturn(5);
    $systemService->shouldReceive('getCompany')
        ->andReturn([]);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_hash_lifetime_days', '90')
        ->andReturn(90);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_email_attach_pdf')
        ->andReturn(false);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_hash_lifetime_days', '90')
        ->andReturn(90);

    $emailService = Mockery::mock(EmailService::class);
    $emailService->shouldReceive('sendTemplate')->twice();

    [$em, $invoiceItemRepo] = invoiceItemEmAndRepo();
    $invoiceItemRepo->shouldReceive('findByInvoiceId')->andReturn([$line1, $line2]);
    $newEntities = [];
    $em->shouldReceive('persist')->atLeast()->once()->andReturnUsing(
        function (object $entity) use ($invoiceModel, &$newEntities): void {
            if ($entity instanceof Invoice && $entity !== $invoiceModel && !in_array($entity, $newEntities, true)) {
                $newEntities[] = $entity;
                setEntityId($entity, 100 + count($newEntities));
            }
        }
    );
    $em->shouldReceive('flush')->atLeast()->once();

    $invoiceRepo = Mockery::mock(InvoiceRepository::class);
    $invoiceRepo->shouldReceive('lockAndGetStatus')->andReturn(Invoice::STATUS_PAID);
    $invoiceRepo->shouldReceive('findBy')->andReturn([]);
    $em->shouldReceive('getRepository')->with(Invoice::class)->andReturn($invoiceRepo);

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(moduleService([
        'system' => $systemService,
        'email' => $emailService,
    ]));
    $di['event_dispatcher'] = $eventDispatcher;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    expect($serviceMock->refundInvoice($invoiceModel, null, [12 => 1]))->toBeInt();
    expect($events)->toHaveCount(2)
        ->and($events[0])->toBeInstanceOf(BeforeAdminInvoiceRefundEvent::class)
        ->and($events[0]->invoiceId)->toBe(10)
        ->and($events[1])->toBeInstanceOf(AfterAdminInvoiceRefundEvent::class)
        ->and($events[1]->invoiceId)->toBe(10);
    expect($invoiceModel->getStatus())->toBe(Invoice::STATUS_PAID);
    expect((float) $invoiceModel->getRefund())->toEqual(40.0);
    expect($newEntities)->toHaveCount(1);
    expect($newEntities[0]->getCreditNoteForInvoiceId())->toBe(10);

    expect($serviceMock->refundInvoice($invoiceModel, null, [11 => 1]))->toBeInt();
    expect($events)->toHaveCount(4)
        ->and($events[2])->toBeInstanceOf(BeforeAdminInvoiceRefundEvent::class)
        ->and($events[2]->invoiceId)->toBe(10)
        ->and($events[3])->toBeInstanceOf(AfterAdminInvoiceRefundEvent::class)
        ->and($events[3]->invoiceId)->toBe(10);
    expect($invoiceModel->getStatus())->toBe(Invoice::STATUS_REFUNDED);
    expect((float) $invoiceModel->getRefund())->toEqual(100.0);
    expect($newEntities)->toHaveCount(2);
});

test('refundInvoice validates partial refund input', function (): void {
    $line = createEntity(InvoiceItem::class, ['price' => 60.0, 'quantity' => 2, 'taxed' => false]);
    setEntityId($line, 11);
    $discount = createEntity(InvoiceItem::class, ['price' => -10.0, 'quantity' => 1, 'taxed' => false]);
    setEntityId($discount, 12);

    $invoiceModel = createEntity(Invoice::class, ['clientId' => 5]);
    $invoiceModel->setStatus(Invoice::STATUS_PAID);
    setEntityId($invoiceModel, 10);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getTotal')->andReturn(110.0, 60.0);
    $serviceMock->shouldReceive('getTax')->andReturn(0.0);

    [$em, $invoiceItemRepo] = invoiceItemEmAndRepo();
    $invoiceItemRepo->shouldReceive('findByInvoiceId')->andReturn([$line, $discount]);
    $em->shouldNotReceive('persist', 'flush');

    $invoiceRepo = Mockery::mock(InvoiceRepository::class);
    $invoiceRepo->shouldReceive('lockAndGetStatus')->andReturn(Invoice::STATUS_PAID);
    $invoiceRepo->shouldReceive('findBy')->andReturn([]);
    $em->shouldReceive('getRepository')->with(Invoice::class)->andReturn($invoiceRepo);

    $clientRepo = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepo->shouldReceive('find')->andReturn(null);
    $em->shouldReceive('getRepository')->with(Box\Mod\Client\Entity\Client::class)->andReturn($clientRepo);

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_refund_logic', 'manual')
        ->andReturn('credit_note');
    $systemService->shouldReceive('reserveNextNumericParamValue')
        ->andReturn(7);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_cn_series', 'CN-')
        ->andReturn('CN-');
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_hash_lifetime_days', '90')
        ->andReturn(90);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_number_padding')
        ->andReturn(5);
    $systemService->shouldReceive('getCompany')
        ->andReturn([]);

    $events = [];
    $eventDispatcher = new class($events) {
        public function __construct(private array &$events)
        {
        }

        public function dispatch(FOSSBilling\Events\Event $event): FOSSBilling\Events\Event
        {
            $this->events[] = $event;

            return $event;
        }
    };

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(moduleService(['system' => $systemService]));
    $di['event_dispatcher'] = $eventDispatcher;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->refundInvoice($invoiceModel, null, [99 => 1]))
        ->toThrow(FOSSBilling\InformationException::class, 'was not found');
    expect(fn () => $serviceMock->refundInvoice($invoiceModel, null, [12 => 1]))
        ->toThrow(FOSSBilling\InformationException::class, 'Only charge lines');
    expect(fn () => $serviceMock->refundInvoice($invoiceModel, null, [11 => 5]))
        ->toThrow(FOSSBilling\InformationException::class, 'exceeds the remaining refundable quantity');
    expect(fn () => $serviceMock->refundInvoice($invoiceModel, null, [11 => 0]))
        ->toThrow(FOSSBilling\InformationException::class, 'No invoice lines selected');
    expect($events)->toHaveCount(4);
    foreach ($events as $event) {
        expect($event)->toBeInstanceOf(BeforeAdminInvoiceRefundEvent::class)
            ->and($event->invoiceId)->toBe(10);
    }
});

test('debitInvoice issues a payable debit note linked to the original', function (): void {
    $invoiceModel = createEntity(Invoice::class, ['clientId' => 5, 'currency' => 'USD']);
    $invoiceModel->setApproved(true);
    $invoiceModel->setStatus(Invoice::STATUS_UNPAID);
    setEntityId($invoiceModel, 10);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('countIncome')->once();
    $serviceMock->shouldReceive('addNote')->times(3);
    $serviceMock->shouldReceive('toApiArray')->andReturn(['id' => 1, 'total' => 25.0]);
    $serviceMock->shouldReceive('extendInvoiceHashLifetime')->once();

    $eventDispatcher = new class {
        public array $events = [];

        public function dispatch(FOSSBilling\Events\Event $event): FOSSBilling\Events\Event
        {
            $this->events[] = $event;

            return $event;
        }
    };

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_due_days')
        ->andReturn('5');
    $systemService->shouldReceive('reserveNextNumericParamValue')
        ->once()
        ->with('invoice_dn_starting_number', 1)
        ->andReturn(3);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_dn_series', 'DN-')
        ->andReturn('DN-');
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_hash_lifetime_days', '90')
        ->andReturn(90);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_number_padding')
        ->andReturn(5);
    $systemService->shouldReceive('getCompany')
        ->andReturn([]);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_email_attach_pdf')
        ->andReturn(false);

    $emailService = Mockery::mock(EmailService::class);
    $emailService->shouldReceive('sendTemplate')
        ->once()
        ->withArgs(fn (array $email): bool => $email['code'] === 'mod_invoice_debited'
            && isset($email['invoice'], $email['original_invoice']));

    $em = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $em->shouldReceive('wrapInTransaction')->andReturnUsing(fn (callable $callback): mixed => $callback());
    $debitNote = null;
    $em->shouldReceive('persist')->atLeast()->once()->andReturnUsing(
        function (object $entity) use ($invoiceModel, &$debitNote): void {
            if ($entity instanceof Invoice && $entity !== $invoiceModel) {
                $debitNote = $entity;
                setEntityId($entity, 55);
            }
        }
    );
    $em->shouldReceive('flush')->atLeast()->once();

    $invoiceRepo = Mockery::mock(InvoiceRepository::class);
    $invoiceRepo->shouldReceive('lockAndGetState')
        ->andReturn(['status' => Invoice::STATUS_UNPAID, 'approved' => true]);
    $em->shouldReceive('getRepository')->with(Invoice::class)->andReturn($invoiceRepo);

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(moduleService([
        'system' => $systemService,
        'email' => $emailService,
    ]));
    $di['event_dispatcher'] = $eventDispatcher;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $items = [
        ['title' => 'Undercharge correction', 'price' => 25, 'quantity' => 1, 'taxed' => false],
        ['title' => '', 'price' => 10, 'quantity' => 1],
    ];
    expect($serviceMock->debitInvoice($invoiceModel, $items, 'customNote'))->toBe(55);
    expect($invoiceModel->getStatus())->toBe(Invoice::STATUS_UNPAID);
    expect($eventDispatcher->events)->toHaveCount(2)
        ->and($eventDispatcher->events[0])->toBeInstanceOf(BeforeAdminInvoiceDebitEvent::class)
        ->and($eventDispatcher->events[0]->invoiceId)->toBe(10)
        ->and($eventDispatcher->events[1])->toBeInstanceOf(AfterAdminInvoiceDebitEvent::class)
        ->and($eventDispatcher->events[1]->invoiceId)->toBe(10)
        ->and($eventDispatcher->events[1]->debitNoteId)->toBe(55);

    expect($debitNote)->not->toBeNull();
    expect($debitNote->getStatus())->toBe(Invoice::STATUS_UNPAID);
    expect($debitNote->isApproved())->toBeTrue();
    expect($debitNote->getDebitNoteForInvoiceId())->toBe(10);
    expect($debitNote->getSerie())->toBe('DN-');
    expect($debitNote->getNr())->toBe('3');
});

test('debitInvoice refuses ineligible invoices and lines', function (): void {
    $cases = [
        'unapproved' => [false, Invoice::STATUS_UNPAID, null, [['title' => 'X', 'price' => 5]]],
        'canceled' => [true, Invoice::STATUS_CANCELED, null, [['title' => 'X', 'price' => 5]]],
        'refunded' => [true, Invoice::STATUS_REFUNDED, null, [['title' => 'X', 'price' => 5]]],
        'credit note' => [true, Invoice::STATUS_REFUNDED, 7, [['title' => 'X', 'price' => 5]]],
    ];

    foreach ($cases as [$approved, $status, $creditLink, $items]) {
        $service = new Service();
        $invoice = createEntity(Invoice::class, ['clientId' => 5]);
        $invoice->setApproved($approved);
        $invoice->setStatus($status);
        $invoice->setCreditNoteForInvoiceId($creditLink);
        setEntityId($invoice, 10);

        $di = container();
        $service->setDi($di);

        expect(fn () => $service->debitInvoice($invoice, $items))
            ->toThrow(FOSSBilling\InformationException::class, 'Only approved unpaid or paid invoices can be debited');
    }

    $service = new Service();
    $invoice = createEntity(Invoice::class, ['clientId' => 5]);
    $invoice->setApproved(true);
    $invoice->setStatus(Invoice::STATUS_UNPAID);
    setEntityId($invoice, 10);
    $di = container();
    $service->setDi($di);

    expect(fn () => $service->debitInvoice($invoice, []))
        ->toThrow(FOSSBilling\InformationException::class, 'No debit lines given');
    expect(fn () => $service->debitInvoice($invoice, [['title' => '   ', 'price' => 5]]))
        ->toThrow(FOSSBilling\InformationException::class, 'No debit lines given');
    expect(fn () => $service->debitInvoice($invoice, [['title' => 'X', 'price' => 0]]))
        ->toThrow(FOSSBilling\InformationException::class, 'positive amount');
});

test('isInvoiceEditable gates issued invoices by setting', function (): void {
    // [approved, status, setting, expected]
    $cases = [
        [false, Invoice::STATUS_UNPAID, false, true],
        [false, Invoice::STATUS_UNPAID, true, true],
        [true, Invoice::STATUS_UNPAID, false, false],
        [true, Invoice::STATUS_UNPAID, true, true],
        [true, Invoice::STATUS_PAID, true, false],
        [false, Invoice::STATUS_PAID, true, false],
        [true, Invoice::STATUS_REFUNDED, true, false],
        [true, Invoice::STATUS_CANCELED, true, false],
    ];

    foreach ($cases as [$approved, $status, $allow, $expected]) {
        $service = new Service();
        $invoice = createEntity(Invoice::class);
        $invoice->setApproved($approved);
        $invoice->setStatus($status);

        $systemMock = Mockery::mock(SystemService::class);
        $systemMock->shouldReceive('getParamValue')->with('invoice_allow_edit_unpaid', false)->andReturn($allow);

        $di = container();
        $di['mod_service'] = $di->protect(moduleService(['system' => $systemMock]));
        $service->setDi($di);

        expect($service->isInvoiceEditable($invoice))->toBe($expected);
    }
});

test('updateInvoice refuses to edit a locked invoice', function (): void {
    foreach ([Invoice::STATUS_UNPAID, Invoice::STATUS_PAID] as $status) {
        $service = new Service();
        $invoice = createEntity(Invoice::class, ['id' => 10]);
        $invoice->setApproved(true);
        $invoice->setStatus($status);

        $systemMock = Mockery::mock(SystemService::class);
        $systemMock->shouldReceive('getParamValue')->andReturn(false);

        $di = container();
        $di['mod_service'] = $di->protect(moduleService(['system' => $systemMock]));
        $service->setDi($di);

        expect(fn () => $service->updateInvoice($invoice, ['notes' => 'Edited']))
            ->toThrow(FOSSBilling\InformationException::class, 'can no longer be edited');
    }
});

test('updateInvoice resends an approved invoice after editing it', function (): void {
    $invoice = createEntity(Invoice::class, ['id' => 10, 'clientId' => 5]);
    $invoice->setApproved(true);
    $invoice->setStatus(Invoice::STATUS_UNPAID);

    $systemMock = Mockery::mock(SystemService::class);
    $systemMock->shouldReceive('getParamValue')->with('invoice_allow_edit_unpaid', false)->andReturn(true);

    $itemInvoiceServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $itemInvoiceServiceMock->shouldNotReceive('addNew', 'update');

    [$em] = invoiceItemEmAndRepo();
    $eventDispatcher = new class {
        public array $events = [];

        public function dispatch(FOSSBilling\Events\Event $event): FOSSBilling\Events\Event
        {
            $this->events[] = $event;

            return $event;
        }
    };

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(moduleService([
        'system' => $systemMock,
        'invoice:invoiceitem' => $itemInvoiceServiceMock,
    ]));
    $di['event_dispatcher'] = $eventDispatcher;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('resendUpdatedInvoice')->once()->with($invoice);
    $serviceMock->setDi($di);

    expect($serviceMock->updateInvoice($invoice, ['notes' => 'Edited']))->toBeTrue()
        ->and($eventDispatcher->events)->toHaveCount(2)
        ->and($eventDispatcher->events[0]->changedFields)->toBe(['notes'])
        ->and($eventDispatcher->events[1])->toBeInstanceOf(AfterAdminInvoiceUpdateEvent::class);
});

test('updateInvoice does not resend a draft invoice', function (): void {
    $invoice = createEntity(Invoice::class, ['id' => 10, 'clientId' => 5]);
    $invoice->setApproved(false);
    $invoice->setStatus(Invoice::STATUS_UNPAID);

    $itemInvoiceServiceMock = Mockery::mock(ServiceInvoiceItem::class);
    $itemInvoiceServiceMock->shouldNotReceive('addNew', 'update');

    [$em] = invoiceItemEmAndRepo();
    $eventDispatcher = new class {
        public array $events = [];

        public function dispatch(FOSSBilling\Events\Event $event): FOSSBilling\Events\Event
        {
            $this->events[] = $event;

            return $event;
        }
    };

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(moduleService(['invoice:invoiceitem' => $itemInvoiceServiceMock]));
    $di['event_dispatcher'] = $eventDispatcher;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldNotReceive('resendUpdatedInvoice');
    $serviceMock->setDi($di);

    expect($serviceMock->updateInvoice($invoice, ['notes' => 'Edited']))->toBeTrue()
        ->and($eventDispatcher->events)->toHaveCount(2)
        ->and($eventDispatcher->events[0]->changedFields)->toBe(['notes'])
        ->and($eventDispatcher->events[1])->toBeInstanceOf(AfterAdminInvoiceUpdateEvent::class);
});

test('deleteInvoiceByAdmin only deletes unapproved unpaid invoices', function (): void {
    $locked = [
        [true, Invoice::STATUS_UNPAID],
        [false, Invoice::STATUS_PAID],
        [true, Invoice::STATUS_PAID],
        [true, Invoice::STATUS_CANCELED],
    ];
    foreach ($locked as [$approved, $status]) {
        $service = new Service();
        $invoice = createEntity(Invoice::class, ['id' => 10]);
        $invoice->setApproved($approved);
        $invoice->setStatus($status);

        expect(fn () => $service->deleteInvoiceByAdmin($invoice))
            ->toThrow(FOSSBilling\InformationException::class, 'Only unapproved, unpaid invoices can be deleted');
    }

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $invoice = createEntity(Invoice::class, ['id' => 10]);
    $invoice->setApproved(false);
    $invoice->setStatus(Invoice::STATUS_UNPAID);

    $eventDispatcher = new class {
        public array $events = [];

        public function dispatch(FOSSBilling\Events\Event $event): FOSSBilling\Events\Event
        {
            $this->events[] = $event;

            return $event;
        }
    };

    $di = container();
    $di['event_dispatcher'] = $eventDispatcher;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);
    $serviceMock->shouldReceive('rmInvoice')->once()->with($invoice, true)->andReturn(true);

    expect($serviceMock->deleteInvoiceByAdmin($invoice))->toBeTrue()
        ->and($eventDispatcher->events)->toHaveCount(2)
        ->and($eventDispatcher->events[0])->toBeInstanceOf(BeforeAdminInvoiceDeleteEvent::class)
        ->and($eventDispatcher->events[1])->toBeInstanceOf(AfterAdminInvoiceDeleteEvent::class);
});

test('debitInvoice writes nothing when lines are invalid', function (): void {
    $invoiceModel = createEntity(Invoice::class, ['clientId' => 5]);
    $invoiceModel->setApproved(true);
    $invoiceModel->setStatus(Invoice::STATUS_UNPAID);
    setEntityId($invoiceModel, 10);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $eventDispatcher = new class {
        public array $events = [];

        public function dispatch(FOSSBilling\Events\Event $event): FOSSBilling\Events\Event
        {
            $this->events[] = $event;

            return $event;
        }
    };

    // Strict entity manager: any write attempt fails the test.
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldNotReceive('wrapInTransaction', 'persist', 'flush');

    $di = container();
    $di['em'] = $em;
    $di['event_dispatcher'] = $eventDispatcher;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->debitInvoice($invoiceModel, [['title' => 'X', 'price' => 0]]))
        ->toThrow(FOSSBilling\InformationException::class, 'positive amount');
    expect(fn () => $serviceMock->debitInvoice($invoiceModel, [['title' => '', 'price' => 5]]))
        ->toThrow(FOSSBilling\InformationException::class, 'No debit lines given');
    expect($eventDispatcher->events)->toHaveCount(2)
        ->and($eventDispatcher->events[0])->toBeInstanceOf(BeforeAdminInvoiceDebitEvent::class)
        ->and($eventDispatcher->events[1])->toBeInstanceOf(BeforeAdminInvoiceDebitEvent::class);
});

test('refundInvoice enforces per-line remaining quantities across partials', function (): void {
    $line = createEntity(InvoiceItem::class, ['price' => 40.0, 'quantity' => 1, 'taxed' => false]);
    setEntityId($line, 12);

    $invoiceModel = createEntity(Invoice::class, ['clientId' => 5]);
    $invoiceModel->setStatus(Invoice::STATUS_PAID);
    setEntityId($invoiceModel, 10);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getTotal')->andReturn(40.0);
    $serviceMock->shouldReceive('getTax')->andReturn(0.0);
    $serviceMock->shouldReceive('countIncome')->once();
    $serviceMock->shouldReceive('addNote')->twice();
    $serviceMock->shouldReceive('toApiArray')->andReturn(['id' => 1, 'total' => -40.0]);
    $serviceMock->shouldReceive('extendInvoiceHashLifetime')->once();

    $events = [];
    $eventDispatcher = new class($events) {
        public function __construct(private array &$events)
        {
        }

        public function dispatch(FOSSBilling\Events\Event $event): FOSSBilling\Events\Event
        {
            $this->events[] = $event;

            return $event;
        }
    };

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_refund_logic', 'manual')
        ->andReturn('credit_note');
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_hash_lifetime_days', '90')
        ->andReturn(90);
    $systemService->shouldReceive('reserveNextNumericParamValue')
        ->andReturn(7);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_cn_series', 'CN-')
        ->andReturn('CN-');
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_number_padding')
        ->andReturn(5);
    $systemService->shouldReceive('getCompany')
        ->andReturn([]);
    $systemService->shouldReceive('getParamValue')
        ->with('invoice_email_attach_pdf')
        ->andReturn(false);

    $emailService = Mockery::mock(EmailService::class);
    $emailService->shouldReceive('sendTemplate')->once();

    [$em, $invoiceItemRepo] = invoiceItemEmAndRepo();
    $invoiceItemRepo->shouldReceive('findByInvoiceId')->andReturn([$line]);
    $em->shouldReceive('persist')->atLeast()->once();
    $em->shouldReceive('flush')->atLeast()->once();

    $creditedItem = createEntity(InvoiceItem::class, ['quantity' => 1]);
    $creditedItem->setRefundedItemId(12);

    $invoiceRepo = Mockery::mock(InvoiceRepository::class);
    $invoiceRepo->shouldReceive('lockAndGetStatus')->andReturn(Invoice::STATUS_PAID);
    $invoiceRepo->shouldReceive('findBy')->andReturn([], [$creditedItem]);
    $em->shouldReceive('getRepository')->with(Invoice::class)->andReturn($invoiceRepo);

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(moduleService([
        'system' => $systemService,
        'email' => $emailService,
    ]));
    $di['event_dispatcher'] = $eventDispatcher;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    expect($serviceMock->refundInvoice($invoiceModel, null, [12 => 1]))->toBeInt();
    expect($events)->toHaveCount(2)
        ->and($events[0])->toBeInstanceOf(BeforeAdminInvoiceRefundEvent::class)
        ->and($events[0]->invoiceId)->toBe(10)
        ->and($events[1])->toBeInstanceOf(AfterAdminInvoiceRefundEvent::class)
        ->and($events[1]->invoiceId)->toBe(10);
    expect(fn () => $serviceMock->refundInvoice($invoiceModel, null, [12 => 1]))
        ->toThrow(FOSSBilling\InformationException::class, 'remaining');
    expect($events)->toHaveCount(3)
        ->and($events[2])->toBeInstanceOf(BeforeAdminInvoiceRefundEvent::class)
        ->and($events[2]->invoiceId)->toBe(10);
});
