<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceCardCharge;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\PayGatewayCustomer;
use Box\Mod\Invoice\Repository\PayGatewayCustomerRepository;
use Box\Mod\Invoice\Repository\PayGatewayRepository;
use Box\Mod\Invoice\ServiceSavedCards;
use Doctrine\ORM\EntityManagerInterface;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;
use function Tests\Helpers\moduleService;

function savedCardsService(?EntityManagerInterface $em = null, array $services = []): ServiceSavedCards
{
    $service = new ServiceSavedCards();
    $di = container();
    $di['em'] = $em ?? Mockery::mock(EntityManagerInterface::class);
    $di['mod_service'] = $di->protect(moduleService($services));
    $service->setDi($di);

    return $service;
}

describe('settings', function (): void {
    test('returns defaults when nothing is configured', function (): void {
        $extension = Mockery::mock();
        $extension->shouldReceive('getConfig')->once()->with('mod_invoice')->andReturn([]);
        $service = savedCardsService(services: ['extension' => $extension]);

        expect($service->settings())->toBe(['days_before' => 0, 'every' => 7, 'retries' => 2]);
    });

    test('reads the configured schedule', function (): void {
        $extension = Mockery::mock();
        $extension->shouldReceive('getConfig')->once()->with('mod_invoice')->andReturn([
            'card_days_before' => '3',
            'card_retry_every' => '5',
            'card_retry_count' => '1',
        ]);
        $service = savedCardsService(services: ['extension' => $extension]);

        expect($service->settings())->toBe(['days_before' => 3, 'every' => 5, 'retries' => 1]);
    });

    test('clamps out of range values', function (): void {
        $extension = Mockery::mock();
        $extension->shouldReceive('getConfig')->once()->with('mod_invoice')->andReturn([
            'card_days_before' => '99',
            'card_retry_every' => '0',
            'card_retry_count' => '99',
        ]);
        $service = savedCardsService(services: ['extension' => $extension]);

        expect($service->settings())->toBe(['days_before' => 30, 'every' => 1, 'retries' => 5]);
    });

    test('a legacy disabled card_retries means no retries', function (): void {
        $extension = Mockery::mock();
        $extension->shouldReceive('getConfig')->once()->with('mod_invoice')->andReturn(['card_retries' => '0']);
        $service = savedCardsService(services: ['extension' => $extension]);

        expect($service->settings()['retries'])->toBe(0);
    });

    test('saves a valid schedule', function (): void {
        $extension = Mockery::mock();
        $extension->shouldReceive('getConfig')->once()->with('mod_invoice')->andReturn(['company_name' => ' kept ']);
        $extension->shouldReceive('setConfig')->once()->with([
            'company_name' => ' kept ',
            'card_days_before' => '3',
            'card_retry_every' => '5',
            'card_retry_count' => '1',
            'ext' => 'mod_invoice',
        ])->andReturn(true);
        $service = savedCardsService(services: ['extension' => $extension]);

        $service->saveSettings(['card_days_before' => '3', 'card_retry_every' => '5', 'card_retry_count' => '1']);
        expect(true)->toBeTrue();
    });

    test('rejects out of range values', function (): void {
        $extension = Mockery::mock();
        $extension->shouldReceive('getConfig')->byDefault()->andReturn([]);
        $extension->shouldNotReceive('setConfig');
        $service = savedCardsService(services: ['extension' => $extension]);

        expect(fn () => $service->saveSettings(['card_days_before' => '31']))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Days before')
            ->and(fn () => $service->saveSettings(['card_retry_every' => '0']))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Days between')
            ->and(fn () => $service->saveSettings(['card_retry_count' => '6']))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Retries');
    });
});

describe('tryDate', function (): void {
    test('computes the day of each try', function (): void {
        // Due 2026-01-10, charged 2 days before, retried every 5 days.
        $settings = ['days_before' => 2, 'every' => 5, 'retries' => 2];

        expect(ServiceSavedCards::tryDate('2026-01-10 12:00:00', 0, $settings))->toBe('2026-01-08')
            ->and(ServiceSavedCards::tryDate('2026-01-10 12:00:00', 1, $settings))->toBe('2026-01-13')
            ->and(ServiceSavedCards::tryDate('2026-01-10 12:00:00', 2, $settings))->toBe('2026-01-18');
    });
});

describe('gateway resolution', function (): void {
    test('has no adapters without an enabled Stripe gateway', function (): void {
        $repo = Mockery::mock(PayGatewayRepository::class);
        $repo->shouldReceive('findEnabledByGateway')->twice()->with('Stripe')->andReturn(null);
        $repo->shouldReceive('findEnabledByGateway')->once()->with('PayPalAutoPay')->andReturn(null);

        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn($repo);
        $service = savedCardsService($em);

        expect($service->stripe())->toBeNull()
            ->and($service->adapters())->toBe([]);
    });

    test('resolves the enabled Stripe gateway adapter', function (): void {
        $gateway = createEntity(PayGateway::class, ['id' => 4, 'gateway' => 'Stripe']);
        $repo = Mockery::mock(PayGatewayRepository::class);
        $repo->shouldReceive('findEnabledByGateway')->twice()->with('Stripe')->andReturn($gateway);
        $repo->shouldReceive('findEnabledByGateway')->once()->with('PayPalAutoPay')->andReturn(null);

        $adapter = Mockery::mock(Payment_Adapter_Stripe::class);
        $payGatewayService = Mockery::mock(Box\Mod\Invoice\ServicePayGateway::class);
        $payGatewayService->shouldReceive('getPaymentAdapter')->twice()->with($gateway)->andReturn($adapter);

        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn($repo);
        $service = savedCardsService($em, ['invoice:paygateway' => $payGatewayService]);

        expect($service->stripe())->toBe($adapter)
            ->and($service->adapters())->toBe([$adapter]);
    });
});

describe('saved methods', function (): void {
    test('saved returns null when the client has no saved method', function (): void {
        $repo = Mockery::mock(PayGatewayCustomerRepository::class);
        $repo->shouldReceive('findSaved')->once()->with(4, false, 7)->andReturn(null);

        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('getRepository')->with(PayGatewayCustomer::class)->andReturn($repo);
        $service = savedCardsService($em);

        expect($service->saved(4, false, 7))->toBeNull();
    });

    test('saved returns the stored method', function (): void {
        $row = createEntity(PayGatewayCustomer::class);
        $row->setSavedCards(true);
        $row->setDefaultPaymentMethod('pm_123');
        $row->setDefaultCard('Visa •••• 4242');
        $row->setAutoPayAt(new DateTime('2026-01-05 10:00:00'));

        $repo = Mockery::mock(PayGatewayCustomerRepository::class);
        $repo->shouldReceive('findSaved')->once()->with(4, true, 7)->andReturn($row);

        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('getRepository')->with(PayGatewayCustomer::class)->andReturn($repo);
        $service = savedCardsService($em);

        expect($service->saved(4, true, 7))->toBe([
            'payment_method' => 'pm_123',
            'card' => 'Visa •••• 4242',
            'auto_pay_at' => '2026-01-05 10:00:00',
        ]);
    });

    test('remember keeps the saved method on a new row', function (): void {
        $repo = Mockery::mock(PayGatewayCustomerRepository::class);
        $repo->shouldReceive('findOneByGatewayAndClient')->once()->with(4, 7, false)->andReturn(null);

        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('getRepository')->with(PayGatewayCustomer::class)->andReturn($repo);
        $em->shouldReceive('getReference')->once()->with(PayGateway::class, 4)->andReturn(createEntity(PayGateway::class, ['id' => 4]));
        $em->shouldReceive('persist')->once()->with(Mockery::on(function (PayGatewayCustomer $row): bool {
            return $row->getClientId() === 7
                && $row->isTestMode() === false
                && $row->getExternalCustomerId() === 'cus_1'
                && $row->hasSavedCards()
                && $row->getDefaultPaymentMethod() === 'pm_1'
                && $row->getDefaultCard() === 'Visa •••• 4242'
                && $row->getAutoPayAt() instanceof DateTime;
        }));
        $em->shouldReceive('flush')->once();
        $service = savedCardsService($em);

        $service->remember(4, false, 7, 'cus_1', 'pm_1', 'Visa •••• 4242');
        expect(true)->toBeTrue();
    });

    test('remember updates the existing row instead of adding one', function (): void {
        $existing = createEntity(PayGatewayCustomer::class, ['client_id' => 7]);
        $repo = Mockery::mock(PayGatewayCustomerRepository::class);
        $repo->shouldReceive('findOneByGatewayAndClient')->once()->with(4, 7, true)->andReturn($existing);

        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('getRepository')->with(PayGatewayCustomer::class)->andReturn($repo);
        $em->shouldNotReceive('persist');
        $em->shouldReceive('flush')->once();
        $service = savedCardsService($em);

        $service->remember(4, true, 7, 'cus_2', 'pm_2', 'Mastercard •••• 5555');

        expect($existing->getDefaultPaymentMethod())->toBe('pm_2')
            ->and($existing->getDefaultCard())->toBe('Mastercard •••• 5555');
    });

    test('choose marks the saved method as the one that pays', function (): void {
        $row = createEntity(PayGatewayCustomer::class);
        $repo = Mockery::mock(PayGatewayCustomerRepository::class);
        $repo->shouldReceive('findSaved')->once()->with(4, false, 7)->andReturn($row);

        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('getRepository')->with(PayGatewayCustomer::class)->andReturn($repo);
        $em->shouldReceive('flush')->once();
        $service = savedCardsService($em);

        $service->choose(4, false, 7);

        expect($row->getAutoPayAt())->toBeInstanceOf(DateTime::class);
    });

    test('choose does nothing without a saved method', function (): void {
        $repo = Mockery::mock(PayGatewayCustomerRepository::class);
        $repo->shouldReceive('findSaved')->once()->with(4, false, 7)->andReturn(null);

        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('getRepository')->with(PayGatewayCustomer::class)->andReturn($repo);
        $em->shouldNotReceive('flush');
        $service = savedCardsService($em);

        $service->choose(4, false, 7);
        expect(true)->toBeTrue();
    });
});

describe('holds', function (): void {
    test('hold creates a held row when the invoice was never tried', function (): void {
        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('find')->once()->with(InvoiceCardCharge::class, 9)->andReturn(null);
        $em->shouldReceive('persist')->once()->with(Mockery::on(function (InvoiceCardCharge $charge): bool {
            return $charge->getInvoiceId() === 9
                && $charge->getAttempts() === InvoiceCardCharge::HELD
                && $charge->getLastAttemptAt() instanceof DateTime
                && $charge->getLastError() === 'Payment pi_1 is not completed yet.';
        }));
        $em->shouldReceive('flush')->once();
        $service = savedCardsService($em);

        $service->hold(9, 'Payment pi_1 is not completed yet.');
        expect(true)->toBeTrue();
    });

    test('hold upgrades an existing row', function (): void {
        $charge = new InvoiceCardCharge();
        $charge->setInvoiceId(9);
        $charge->setAttempts(2);

        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('find')->once()->with(InvoiceCardCharge::class, 9)->andReturn($charge);
        $em->shouldNotReceive('persist');
        $em->shouldReceive('flush')->once();
        $service = savedCardsService($em);

        $service->hold(9, 'No answer');

        expect($charge->getAttempts())->toBe(InvoiceCardCharge::HELD)
            ->and($charge->isHeld())->toBeTrue();
    });
});

describe('runDue', function (): void {
    test('does nothing without a gateway that charges saved methods', function (): void {
        $repo = Mockery::mock(PayGatewayRepository::class);
        $repo->shouldReceive('findEnabledByGateway')->once()->with('Stripe')->andReturn(null);
        $repo->shouldReceive('findEnabledByGateway')->once()->with('PayPalAutoPay')->andReturn(null);

        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn($repo);
        $em->shouldNotReceive('getConnection');
        $service = savedCardsService($em);

        $service->runDue();
        expect(true)->toBeTrue();
    });

    test('charges nothing when no invoice try is due', function (): void {
        $gateway = createEntity(PayGateway::class, ['id' => 4, 'gateway' => 'Stripe']);
        $repo = Mockery::mock(PayGatewayRepository::class);
        $repo->shouldReceive('findEnabledByGateway')->with('Stripe')->andReturn($gateway);
        $repo->shouldReceive('findEnabledByGateway')->with('PayPalAutoPay')->andReturn(null);

        $adapter = Mockery::mock(Payment_Adapter_Stripe::class);
        $adapter->shouldReceive('gatewayId')->byDefault()->andReturn(4);
        $adapter->shouldReceive('testMode')->byDefault()->andReturn(false);
        $adapter->shouldNotReceive('savedCard');
        $adapter->shouldNotReceive('chargeSavedCard');
        $payGatewayService = Mockery::mock(Box\Mod\Invoice\ServicePayGateway::class);
        $payGatewayService->shouldReceive('getPaymentAdapter')->with($gateway)->andReturn($adapter);

        $extension = Mockery::mock();
        $extension->shouldReceive('getConfig')->with('mod_invoice')->andReturn([]);

        $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
        $connection->shouldReceive('quoteSingleIdentifier')->byDefault()->andReturn('"transaction"');
        $connection->shouldReceive('fetchAllAssociative')->once()->andReturn([]);

        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn($repo);
        $em->shouldReceive('getConnection')->byDefault()->andReturn($connection);
        $em->shouldNotReceive('find');
        $service = savedCardsService($em, [
            'extension' => $extension,
            'invoice:paygateway' => $payGatewayService,
        ]);

        $service->runDue();
        expect(true)->toBeTrue();
    });
});

describe('charge', function (): void {
    test('refuses an invoice that is no longer unpaid without touching the gateway', function (): void {
        $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
        $connection->shouldReceive('getDatabasePlatform')->andReturn(new Doctrine\DBAL\Platforms\MySQLPlatform());
        $connection->shouldReceive('fetchOne')->once()->with('SELECT GET_LOCK(:name, :timeout)', Mockery::any())->andReturn(1);
        $connection->shouldReceive('fetchAssociative')->once()->andReturn(['status' => 'paid', 'gateway_id' => null]);
        $connection->shouldReceive('executeStatement')->once()->with('SELECT RELEASE_LOCK(:name)', Mockery::any());

        $invoice = createEntity(Invoice::class, ['id' => 11]);
        $adapter = Mockery::mock(Payment_Adapter_Stripe::class);
        $adapter->shouldNotReceive('chargeSavedCard');

        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('getConnection')->byDefault()->andReturn($connection);
        $service = savedCardsService($em);

        expect($service->charge($invoice, $adapter, false))->toBe(['paid' => false, 'error' => 'This invoice is already paid or cancelled.']);
    });

    test('refuses an invoice whose payment is still clearing', function (): void {
        $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
        $connection->shouldReceive('getDatabasePlatform')->andReturn(new Doctrine\DBAL\Platforms\MySQLPlatform());
        $connection->shouldReceive('quoteSingleIdentifier')->byDefault()->andReturn('"transaction"');
        $connection->shouldReceive('fetchOne')->once()->with('SELECT GET_LOCK(:name, :timeout)', Mockery::any())->andReturn(1);
        $connection->shouldReceive('fetchAssociative')->twice()->andReturn(
            ['status' => 'unpaid', 'gateway_id' => null],
            ['amount' => '10.00', 'currency' => 'USD', 'created_at' => '2026-01-01 10:00:00']
        );
        $connection->shouldReceive('executeStatement')->once()->with('SELECT RELEASE_LOCK(:name)', Mockery::any());

        $invoice = createEntity(Invoice::class, ['id' => 12]);
        $adapter = Mockery::mock(Payment_Adapter_Stripe::class);
        $adapter->shouldNotReceive('chargeSavedCard');

        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('getConnection')->byDefault()->andReturn($connection);
        $service = savedCardsService($em);

        expect($service->charge($invoice, $adapter, false))->toBe(['paid' => false, 'error' => 'A payment for this invoice is still clearing.']);
    });
});

describe('charge success', function (): void {
    test('a taken charge records the payment and returns paid', function (): void {
        $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
        $connection->shouldReceive('getDatabasePlatform')->andReturn(new Doctrine\DBAL\Platforms\MySQLPlatform());
        $connection->shouldReceive('quoteSingleIdentifier')->byDefault()->andReturn('"transaction"');
        $connection->shouldReceive('fetchOne')->twice()->andReturn(1, 'paid');
        $connection->shouldReceive('fetchAssociative')->twice()->andReturn(
            ['status' => 'unpaid', 'gateway_id' => null],
            false
        );
        $connection->shouldReceive('executeStatement')->once()->with('SELECT RELEASE_LOCK(:name)', Mockery::any());

        $invoice = createEntity(Invoice::class, ['id' => 21, 'client_id' => 7]);
        $gateway = createEntity(PayGateway::class, ['id' => 5, 'gateway' => 'Stripe']);

        $adapter = Mockery::mock(Payment_Adapter_Stripe::class);
        $adapter->shouldReceive('gatewayId')->byDefault()->andReturn(5);
        $adapter->shouldReceive('savedCard')->once()->with(7)->andReturn(['payment_method' => 'pm_1', 'card' => 'Visa •••• 4242', 'auto_pay_at' => '2026-01-05 10:00:00']);
        $adapter->shouldReceive('chargeSavedCard')->once()->with($invoice, 'attempt_1')->andReturn('pi_ok');

        $feesService = Mockery::mock(Box\Mod\Invoice\ServiceGatewayFees::class);
        $feesService->shouldReceive('apply')->once()->with($invoice, $gateway);

        $transactionService = Mockery::mock(Box\Mod\Invoice\ServiceTransaction::class);
        $transactionService->shouldReceive('createAndProcess')->once()->with([
            'invoice_id' => 21,
            'gateway_id' => 5,
            'source' => 'saved_card',
            'get' => ['payment_intent' => 'pi_ok', 'invoice_id' => 21],
        ])->andReturn(44);

        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('getConnection')->byDefault()->andReturn($connection);
        $em->shouldReceive('find')->with(InvoiceCardCharge::class, 21)->times(3)->andReturn(null);
        $em->shouldReceive('find')->with(PayGateway::class, 5)->once()->andReturn($gateway);
        $em->shouldReceive('persist')->twice()->with(Mockery::type(InvoiceCardCharge::class));
        $em->shouldReceive('flush')->twice();
        $service = savedCardsService($em, [
            'invoice:transaction' => $transactionService,
            'invoice:gatewayfees' => $feesService,
        ]);

        expect($service->charge($invoice, $adapter, false))->toBe(['paid' => true, 'error' => null]);
    });
});

describe('runDue charges', function (): void {
    test('charges an invoice whose try is due today', function (): void {
        $today = date('Y-m-d H:i:s');
        $gateway = createEntity(PayGateway::class, ['id' => 5, 'gateway' => 'Stripe']);
        $repo = Mockery::mock(PayGatewayRepository::class);
        $repo->shouldReceive('findEnabledByGateway')->with('Stripe')->andReturn($gateway);
        $repo->shouldReceive('findEnabledByGateway')->with('PayPalAutoPay')->andReturn(null);

        $adapter = Mockery::mock(Payment_Adapter_Stripe::class);
        $adapter->shouldReceive('gatewayId')->byDefault()->andReturn(5);
        $adapter->shouldReceive('testMode')->byDefault()->andReturn(false);
        $adapter->shouldReceive('savedCard')->with(7)->andReturn(['payment_method' => 'pm_1', 'card' => 'Visa •••• 4242', 'auto_pay_at' => '2026-01-05 10:00:00']);
        $adapter->shouldReceive('chargeSavedCard')->once()->andReturn('pi_ok');
        $payGatewayService = Mockery::mock(Box\Mod\Invoice\ServicePayGateway::class);
        $payGatewayService->shouldReceive('getPaymentAdapter')->with($gateway)->andReturn($adapter);

        $extension = Mockery::mock();
        $extension->shouldReceive('getConfig')->with('mod_invoice')->andReturn([]);

        $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
        $connection->shouldReceive('getDatabasePlatform')->andReturn(new Doctrine\DBAL\Platforms\MySQLPlatform());
        $connection->shouldReceive('quoteSingleIdentifier')->byDefault()->andReturn('"transaction"');
        $connection->shouldReceive('fetchAllAssociative')->once()->andReturn([
            ['id' => 21, 'client_id' => 7, 'due_at' => $today, 'attempts' => 0],
        ]);
        // NamedLock acquire, then the final invoice status read.
        $connection->shouldReceive('fetchOne')->twice()->andReturn(1, 'paid');
        $connection->shouldReceive('fetchAssociative')->twice()->andReturn(['status' => 'unpaid', 'gateway_id' => 5], false);
        $connection->shouldReceive('executeStatement')->byDefault();

        $invoice = createEntity(Invoice::class, ['id' => 21, 'client_id' => 7]);

        $feesService = Mockery::mock(Box\Mod\Invoice\ServiceGatewayFees::class);
        $feesService->shouldReceive('apply')->once();

        $transactionService = Mockery::mock(Box\Mod\Invoice\ServiceTransaction::class);
        $transactionService->shouldReceive('createAndProcess')->once()->andReturn(44);

        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn($repo);
        $em->shouldReceive('getConnection')->byDefault()->andReturn($connection);
        $em->shouldReceive('find')->with(Invoice::class, 21)->andReturn($invoice);
        $em->shouldReceive('find')->with(InvoiceCardCharge::class, 21)->byDefault()->andReturn(null);
        $em->shouldReceive('find')->with(PayGateway::class, 5)->andReturn($gateway);
        $em->shouldReceive('persist')->byDefault()->with(Mockery::type(InvoiceCardCharge::class));
        $em->shouldReceive('flush')->byDefault();
        $service = savedCardsService($em, [
            'extension' => $extension,
            'invoice:paygateway' => $payGatewayService,
            'invoice:transaction' => $transactionService,
            'invoice:gatewayfees' => $feesService,
        ]);

        $service->runDue();
        expect(true)->toBeTrue();
    });
});

describe('autopay gateway resolution', function (): void {
    test('resolves the enabled PayPal Auto-Pay gateway adapter', function (): void {
        $gateway = createEntity(PayGateway::class, ['id' => 5, 'gateway' => 'PayPalAutoPay']);
        $repo = Mockery::mock(PayGatewayRepository::class);
        $repo->shouldReceive('findEnabledByGateway')->once()->with('PayPalAutoPay')->andReturn($gateway);

        $adapter = Mockery::mock(Payment_Adapter_PayPalAutoPay::class);
        $payGatewayService = Mockery::mock(Box\Mod\Invoice\ServicePayGateway::class);
        $payGatewayService->shouldReceive('getPaymentAdapter')->once()->with($gateway)->andReturn($adapter);

        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn($repo);
        $service = savedCardsService($em, ['invoice:paygateway' => $payGatewayService]);

        expect($service->autopay())->toBe($adapter);
    });

    test('returns null without an enabled PayPal Auto-Pay gateway', function (): void {
        $repo = Mockery::mock(PayGatewayRepository::class);
        $repo->shouldReceive('findEnabledByGateway')->once()->with('PayPalAutoPay')->andReturn(null);

        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn($repo);
        $service = savedCardsService($em);

        expect($service->autopay())->toBeNull();
    });
});
