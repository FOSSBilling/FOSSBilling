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
        $repo->shouldReceive('findEnabledByGateway')->once()->with('PayPalEmail')->andReturn(null);

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
        $repo->shouldReceive('findEnabledByGateway')->once()->with('PayPalEmail')->andReturn(null);

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
        $row->setAutoPayAt(new DateTimeImmutable('2026-01-05 10:00:00'));

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
        $em->shouldReceive('persist')->once()->with(Mockery::on(fn (PayGatewayCustomer $row): bool => $row->getClientId() === 7
            && $row->isTestMode() === false
            && $row->getExternalCustomerId() === 'cus_1'
            && $row->hasSavedCards()
            && $row->getDefaultPaymentMethod() === 'pm_1'
            && $row->getDefaultCard() === 'Visa •••• 4242'
            && $row->getAutoPayAt() instanceof DateTimeImmutable));
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

        expect($row->getAutoPayAt())->toBeInstanceOf(DateTimeImmutable::class);
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
        $em->shouldReceive('persist')->once()->with(Mockery::on(fn (InvoiceCardCharge $charge): bool => $charge->getInvoiceId() === 9
            && $charge->getAttempts() === InvoiceCardCharge::HELD
            && $charge->getLastAttemptAt() instanceof DateTimeImmutable
            && $charge->getLastError() === 'Payment pi_1 is not completed yet.'));
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
        $repo->shouldReceive('findEnabledByGateway')->once()->with('PayPalEmail')->andReturn(null);

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
        $repo->shouldReceive('findEnabledByGateway')->with('PayPalEmail')->andReturn(null);

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
        $connection->shouldReceive('getConfiguration')->byDefault()->andReturn(Tests\Helpers\fencedConfiguration());
        $connection->shouldReceive('getTransactionNestingLevel')->byDefault()->andReturn(0);
        $connection->shouldReceive('fetchOne')->with('SELECT 1')->zeroOrMoreTimes()->andReturn(1);
        $connection->shouldReceive('isTransactionActive')->byDefault()->andReturn(false);
        $connection->shouldReceive('fetchAssociative')->with(Mockery::pattern('/SELECT \* FROM invoice_gateway_charge_attempt/'), Mockery::any())->byDefault()->andReturn(false);
        $connection->shouldReceive('insert')->with('invoice_gateway_charge_attempt', Mockery::any())->byDefault()->andReturn(1);
        $connection->shouldReceive('executeStatement')->with(Mockery::pattern('/UPDATE invoice_gateway_charge_attempt/'), Mockery::any())->zeroOrMoreTimes()->andReturn(1);
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
        $connection->shouldReceive('getConfiguration')->byDefault()->andReturn(Tests\Helpers\fencedConfiguration());
        $connection->shouldReceive('getTransactionNestingLevel')->byDefault()->andReturn(0);
        $connection->shouldReceive('fetchOne')->with('SELECT 1')->zeroOrMoreTimes()->andReturn(1);
        $connection->shouldReceive('isTransactionActive')->byDefault()->andReturn(false);
        $connection->shouldReceive('fetchAssociative')->with(Mockery::pattern('/SELECT \* FROM invoice_gateway_charge_attempt/'), Mockery::any())->byDefault()->andReturn(false);
        $connection->shouldReceive('insert')->with('invoice_gateway_charge_attempt', Mockery::any())->byDefault()->andReturn(1);
        $connection->shouldReceive('executeStatement')->with(Mockery::pattern('/UPDATE invoice_gateway_charge_attempt/'), Mockery::any())->zeroOrMoreTimes()->andReturn(1);
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
        $connection->shouldReceive('getConfiguration')->byDefault()->andReturn(Tests\Helpers\fencedConfiguration());
        $connection->shouldReceive('getTransactionNestingLevel')->byDefault()->andReturn(0);
        $connection->shouldReceive('fetchOne')->with('SELECT 1')->zeroOrMoreTimes()->andReturn(1);
        $connection->shouldReceive('isTransactionActive')->byDefault()->andReturn(false);
        $connection->shouldReceive('fetchAssociative')->with(Mockery::pattern('/SELECT \* FROM invoice_gateway_charge_attempt/'), Mockery::any())->byDefault()->andReturn(false);
        $connection->shouldReceive('insert')->with('invoice_gateway_charge_attempt', Mockery::any())->byDefault()->andReturn(1);
        $connection->shouldReceive('executeStatement')->with(Mockery::pattern('/UPDATE invoice_gateway_charge_attempt/'), Mockery::any())->zeroOrMoreTimes()->andReturn(1);
        $connection->shouldReceive('getDatabasePlatform')->andReturn(new Doctrine\DBAL\Platforms\MySQLPlatform());
        $connection->shouldReceive('quoteSingleIdentifier')->byDefault()->andReturn('"transaction"');
        $connection->shouldReceive('fetchOne')->once()->with('SELECT GET_LOCK(:name, :timeout)', Mockery::any())->andReturn(1);
        $connection->shouldReceive('fetchAssociative')->with(Mockery::pattern('/^(SELECT status, gateway_id, issued FROM invoice|SELECT .*transaction)/s'), Mockery::any())->twice()->andReturn(
            ['status' => 'unpaid', 'gateway_id' => null, 'issued' => true],
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
        $connection->shouldReceive('getConfiguration')->byDefault()->andReturn(Tests\Helpers\fencedConfiguration());
        $connection->shouldReceive('getTransactionNestingLevel')->byDefault()->andReturn(0);
        $connection->shouldReceive('fetchOne')->with('SELECT 1')->zeroOrMoreTimes()->andReturn(1);
        $connection->shouldReceive('isTransactionActive')->byDefault()->andReturn(false);
        $connection->shouldReceive('fetchAssociative')->with(Mockery::pattern('/SELECT \* FROM invoice_gateway_charge_attempt/'), Mockery::any())->byDefault()->andReturn(false);
        $connection->shouldReceive('insert')->with('invoice_gateway_charge_attempt', Mockery::any())->byDefault()->andReturn(1);
        $connection->shouldReceive('executeStatement')->with(Mockery::pattern('/UPDATE invoice_gateway_charge_attempt/'), Mockery::any())->zeroOrMoreTimes()->andReturn(1);
        $connection->shouldReceive('getDatabasePlatform')->andReturn(new Doctrine\DBAL\Platforms\MySQLPlatform());
        $connection->shouldReceive('quoteSingleIdentifier')->byDefault()->andReturn('"transaction"');
        $connection->shouldReceive('fetchOne')->times(3)->andReturn(1, 'paid', 44);
        $connection->shouldReceive('fetchAssociative')->with(Mockery::pattern('/^(SELECT status, gateway_id, issued FROM invoice|SELECT .*transaction)/s'), Mockery::any())->twice()->andReturn(
            ['status' => 'unpaid', 'gateway_id' => null, 'issued' => true],
            false
        );
        $connection->shouldReceive('executeStatement')->once()->with('SELECT RELEASE_LOCK(:name)', Mockery::any());

        $invoice = createEntity(Invoice::class, ['id' => 21, 'client_id' => 7]);
        $gateway = createEntity(PayGateway::class, ['id' => 5, 'gateway' => 'Stripe']);

        $adapter = Mockery::mock(Payment_Adapter_Stripe::class);
        $adapter->shouldReceive('gatewayId')->byDefault()->andReturn(5);
        $adapter->shouldReceive('savedCard')->once()->with(7)->andReturn(['payment_method' => 'pm_1', 'card' => 'Visa •••• 4242', 'auto_pay_at' => '2026-01-05 10:00:00']);
        $adapter->shouldReceive('chargeSavedCard')->once()->with($invoice, Mockery::pattern('/^[a-f0-9]{32}$/'))->andReturn('pi_ok');

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
        $repo->shouldReceive('findEnabledByGateway')->with('PayPalEmail')->andReturn(null);

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
        $connection->shouldReceive('getConfiguration')->byDefault()->andReturn(Tests\Helpers\fencedConfiguration());
        $connection->shouldReceive('getTransactionNestingLevel')->byDefault()->andReturn(0);
        $connection->shouldReceive('fetchOne')->with('SELECT 1')->zeroOrMoreTimes()->andReturn(1);
        $connection->shouldReceive('isTransactionActive')->byDefault()->andReturn(false);
        $connection->shouldReceive('fetchAssociative')->with(Mockery::pattern('/SELECT \* FROM invoice_gateway_charge_attempt/'), Mockery::any())->byDefault()->andReturn(false);
        $connection->shouldReceive('insert')->with('invoice_gateway_charge_attempt', Mockery::any())->byDefault()->andReturn(1);
        $connection->shouldReceive('executeStatement')->with(Mockery::pattern('/UPDATE invoice_gateway_charge_attempt/'), Mockery::any())->zeroOrMoreTimes()->andReturn(1);
        $connection->shouldReceive('getDatabasePlatform')->andReturn(new Doctrine\DBAL\Platforms\MySQLPlatform());
        $connection->shouldReceive('quoteSingleIdentifier')->byDefault()->andReturn('"transaction"');
        $connection->shouldReceive('fetchAllAssociative')->once()->andReturn([
            ['id' => 21, 'client_id' => 7, 'due_at' => $today, 'attempts' => 0],
        ]);
        // NamedLock acquire, then the final invoice status read.
        $connection->shouldReceive('fetchOne')->times(3)->andReturn(1, 'paid', 44);
        $connection->shouldReceive('fetchAssociative')->with(Mockery::pattern('/^(SELECT status, gateway_id, issued FROM invoice|SELECT .*transaction)/s'), Mockery::any())->twice()->andReturn(['status' => 'unpaid', 'gateway_id' => 5, 'issued' => true], false);
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

describe('paypal subscription teardown', function (): void {
    test('runDue cancels gateway subscriptions once a PayPal account is saved', function (): void {
        $subscription = createEntity(Box\Mod\Invoice\Entity\Subscription::class, ['id' => 5, 'sid' => 'I-1']);

        $adapter = Mockery::mock(Payment_Adapter_Stripe::class);
        $adapter->shouldReceive('gatewayId')->byDefault()->andReturn(4);
        $adapter->shouldReceive('testMode')->byDefault()->andReturn(false);
        $autopay = Mockery::mock(Payment_Adapter_PayPalAutoPay::class);
        $autopay->shouldReceive('gatewayId')->byDefault()->andReturn(5);
        $autopay->shouldReceive('testMode')->byDefault()->andReturn(false);
        $paypal = Mockery::mock(Payment_Adapter_PayPalEmail::class);
        $paypal->shouldReceive('canVault')->andReturn(true);
        $paypal->shouldReceive('gatewayId')->andReturn(6);
        $paypal->shouldReceive('testMode')->andReturn(false);

        $payGatewayService = Mockery::mock(Box\Mod\Invoice\ServicePayGateway::class);
        $payGatewayService->shouldReceive('getPaymentAdapter')->andReturn($adapter, $autopay, $paypal);

        $stripeGateway = createEntity(PayGateway::class, ['id' => 4, 'gateway' => 'Stripe']);
        $autopayGateway = createEntity(PayGateway::class, ['id' => 5, 'gateway' => 'PayPalAutoPay']);
        $paypalGateway = createEntity(PayGateway::class, ['id' => 6, 'gateway' => 'PayPalEmail']);
        $repo = Mockery::mock(PayGatewayRepository::class);
        $repo->shouldReceive('findEnabledByGateway')->with('Stripe')->andReturn($stripeGateway);
        $repo->shouldReceive('findEnabledByGateway')->with('PayPalAutoPay')->andReturn($autopayGateway);
        $repo->shouldReceive('findEnabledByGateway')->with('PayPalEmail')->andReturn($paypalGateway);

        $extension = Mockery::mock();
        $extension->shouldReceive('getConfig')->with('mod_invoice')->andReturn([]);

        $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
        $connection->shouldReceive('getConfiguration')->byDefault()->andReturn(Tests\Helpers\fencedConfiguration());
        $connection->shouldReceive('getTransactionNestingLevel')->byDefault()->andReturn(0);
        $connection->shouldReceive('fetchOne')->with('SELECT 1')->zeroOrMoreTimes()->andReturn(1);
        $connection->shouldReceive('isTransactionActive')->byDefault()->andReturn(false);
        $connection->shouldReceive('fetchAssociative')->with(Mockery::pattern('/SELECT \* FROM invoice_gateway_charge_attempt/'), Mockery::any())->byDefault()->andReturn(false);
        $connection->shouldReceive('insert')->with('invoice_gateway_charge_attempt', Mockery::any())->byDefault()->andReturn(1);
        $connection->shouldReceive('executeStatement')->with(Mockery::pattern('/UPDATE invoice_gateway_charge_attempt/'), Mockery::any())->zeroOrMoreTimes()->andReturn(1);
        $connection->shouldReceive('quoteSingleIdentifier')->byDefault()->andReturn('"transaction"');
        $connection->shouldReceive('fetchAllAssociative')->andReturn([]);
        $connection->shouldReceive('fetchFirstColumn')->once()->andReturn([5]);

        $subscriptionService = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
        $subscriptionService->shouldReceive('cancel')->once()->with($subscription);

        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn($repo);
        $em->shouldReceive('getConnection')->byDefault()->andReturn($connection);
        $em->shouldReceive('find')->with(Box\Mod\Invoice\Entity\Subscription::class, 5)->andReturn($subscription);
        $service = savedCardsService($em, [
            'extension' => $extension,
            'invoice:paygateway' => $payGatewayService,
            'invoice:subscription' => $subscriptionService,
        ]);

        $service->runDue();
        expect(true)->toBeTrue();
    });
});

test('automatic charge query runs against the invoice schema', function (): void {
    $db = Tests\Helpers\fencedConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    foreach ([
        'CREATE TABLE invoice (id INTEGER PRIMARY KEY, client_id INTEGER, due_at TEXT, status TEXT, gateway_id INTEGER, issued BOOLEAN)',
        'CREATE TABLE pay_gateway_customer (client_id INTEGER, pay_gateway_id INTEGER, test_mode BOOLEAN, saved_cards BOOLEAN, default_payment_method TEXT)',
        'CREATE TABLE invoice_card_charge (invoice_id INTEGER, attempts INTEGER, last_attempt_at TEXT)',
        'CREATE TABLE "transaction" (invoice_id INTEGER, status TEXT, txn_status TEXT, created_at TEXT)',
        'CREATE TABLE invoice_item (invoice_id INTEGER, type TEXT, rel_id TEXT)',
        'CREATE TABLE subscription (rel_type TEXT, rel_id INTEGER, status TEXT)',
    ] as $sql) {
        $db->executeStatement($sql);
    }
    $gateway = createEntity(PayGateway::class, ['id' => 4, 'gateway' => 'Stripe']);
    $repo = Mockery::mock(PayGatewayRepository::class);
    $repo->shouldReceive('findEnabledByGateway')->with('Stripe')->andReturn($gateway);
    $repo->shouldReceive('findEnabledByGateway')->with('PayPalAutoPay')->andReturn(null);
    $repo->shouldReceive('findEnabledByGateway')->with('PayPalEmail')->andReturn(null);
    $adapter = Mockery::mock(Payment_Adapter_Stripe::class);
    $adapter->shouldReceive('gatewayId')->andReturn(4);
    $adapter->shouldReceive('testMode')->andReturn(false);
    $payGateway = Mockery::mock(Box\Mod\Invoice\ServicePayGateway::class);
    $payGateway->shouldReceive('getPaymentAdapter')->with($gateway)->andReturn($adapter);
    $extension = Mockery::mock();
    $extension->shouldReceive('getConfig')->with('mod_invoice')->andReturn([]);
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn($repo);
    $em->shouldReceive('getConnection')->andReturn($db);
    savedCardsService($em, ['extension' => $extension, 'invoice:paygateway' => $payGateway])->runDue();
    expect(true)->toBeTrue();
});

test('queued automatic charges refresh retry limits and timing under the charge lock', function (string $scenario): void {
    $db = Tests\Helpers\fencedConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $em = FOSSBilling\Core\Doctrine\EntityManagerFactory::create($db);
    (new Doctrine\ORM\Tools\SchemaTool($em))->createSchema(array_map($em->getClassMetadata(...), [
        Invoice::class, PayGateway::class, InvoiceCardCharge::class, Box\Mod\Invoice\Entity\GatewayChargeAttempt::class, Box\Mod\Invoice\Entity\Transaction::class,
    ]));
    $invoice = (new Invoice())->setIssued(true)->setStatus('unpaid')->setClientId(7)->setCurrency('USD')->setDueAt(new DateTimeImmutable('-1 month'));
    $gateway = (new PayGateway())->setGateway('Stripe');
    $em->persist($invoice);
    $em->persist($gateway);
    $em->flush();
    $record = (new InvoiceCardCharge())->setInvoiceId($invoice->getId());
    $em->persist($record);
    $em->flush();
    // Both workers selected the invoice while no charge had been tried.
    $otherEm = new Doctrine\ORM\EntityManager($db, $em->getConfiguration());
    $staleRecord = $otherEm->find(InvoiceCardCharge::class, $invoice->getId());
    $otherInvoice = $otherEm->find(Invoice::class, $invoice->getId());
    $expected = $scenario === 'eligible retry' ? 2 : 1;
    $adapter = Mockery::mock(Payment_Adapter_Stripe::class);
    $adapter->shouldReceive('gatewayId')->andReturn($gateway->getId());
    $adapter->shouldReceive('savedCard')->times($expected)->andReturn(['card' => 'Saved card']);
    $adapter->shouldReceive('chargeSavedCard')->times($expected)->andThrow(new FOSSBilling\Core\Exception\InformationException('Declined'));
    $fees = Mockery::mock(Box\Mod\Invoice\ServiceGatewayFees::class);
    $fees->shouldReceive('apply')->times($expected);
    $extension = Mockery::mock();
    $extension->shouldReceive('getConfig')->with('mod_invoice')->andReturn(['card_retry_count' => $scenario === 'no retries' ? '0' : '2', 'card_retry_every' => '7']);
    $invoices = Mockery::mock(Box\Mod\Invoice\Service::class);
    $invoices->shouldReceive('invoiceHasDeletionHold')->andReturnFalse();
    $invoices->shouldReceive('toApiArray')->times($expected)->andReturn([]);
    $invoices->shouldReceive('withBillingRecipient')->times($expected)->andReturnUsing(fn (array $data): array => $data);
    $emails = Mockery::mock();
    $emails->shouldReceive('sendTemplate')->times($expected);
    $services = ['extension' => $extension, 'invoice:gatewayfees' => $fees, 'invoice' => $invoices, 'email' => $emails];
    $first = savedCardsService($em, $services);
    $second = savedCardsService($otherEm, $services);
    expect($first->charge($invoice, $adapter, false))->toBe(['paid' => false, 'error' => 'Declined']);
    expect($staleRecord->getAttempts())->toBe(0);
    if ($scenario !== 'retry interval') {
        $db->update('invoice_card_charge', ['last_attempt_at' => (new DateTimeImmutable('-8 days'))->format('Y-m-d H:i:s')], ['invoice_id' => $invoice->getId()]);
    }
    $result = $second->charge($otherInvoice, $adapter, false);
    expect($result)->toBe(['paid' => false, 'error' => $expected === 2 ? 'Declined' : null])
        ->and((int) $db->fetchOne('SELECT attempts FROM invoice_card_charge'))->toBe($expected)
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM invoice_gateway_charge_attempt'))->toBe($expected);
    $db->close();
})->with(['no retries', 'retry interval', 'eligible retry']);

test('cron recovers after saved payment settlement closes its entity manager', function (int $invoiceCount): void {
    $db = Tests\Helpers\fencedConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $em = FOSSBilling\Core\Doctrine\EntityManagerFactory::create($db);
    (new Doctrine\ORM\Tools\SchemaTool($em))->createSchema(array_map($em->getClassMetadata(...), [
        Invoice::class, PayGateway::class, PayGatewayCustomer::class, InvoiceCardCharge::class,
        Box\Mod\Invoice\Entity\GatewayChargeAttempt::class, Box\Mod\Invoice\Entity\Transaction::class,
        Box\Mod\Invoice\Entity\InvoiceItem::class, Box\Mod\Invoice\Entity\Subscription::class, Box\Mod\System\Entity\Setting::class,
    ]));
    $db->executeStatement('CREATE TABLE session (lifetime INTEGER, created_at INTEGER)');
    $gateway = (new PayGateway())->setGateway('Stripe');
    $em->persist($gateway);
    $em->flush();
    $ids = [];
    for ($client = 7; $client < 7 + $invoiceCount; ++$client) {
        $invoice = (new Invoice())->setIssued(true)->setClientId($client)->setStatus('unpaid')->setCurrency('USD')->setDueAt(new DateTimeImmutable('-1 month'));
        $em->persist($invoice);
        $em->persist((new PayGatewayCustomer())->setPayGateway($gateway)->setClientId($client)->setTestMode(false)->setSavedCards(true)->setExternalCustomerId('cus_' . $client)->setDefaultPaymentMethod('pm_' . $client)->setDefaultCard('Saved card')->setAutoPayAt(new DateTimeImmutable()));
        $em->flush();
        $ids[] = (int) $invoice->getId();
    }
    $di = container();
    $di['em'] = fn (): EntityManagerInterface => $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service = Mockery::mock(ServiceSavedCards::class)->makePartial();
    $service->setDi($di);
    $adapter = Mockery::mock(Payment_Adapter_Stripe::class);
    $adapter->shouldReceive('gatewayId')->andReturn((int) $gateway->getId());
    $adapter->shouldReceive('testMode')->andReturn(false);
    $adapter->shouldReceive('savedCard')->andReturnUsing(fn (int $client): ?array => $service->saved((int) $gateway->getId(), false, $client));
    $charged = [];
    $adapter->shouldReceive('chargeSavedCard')->times($invoiceCount)->andReturnUsing(function (Invoice $invoice) use (&$charged): string {
        $charged[] = (int) $invoice->getId();

        return 'pi_' . $invoice->getId();
    });
    $service->shouldReceive('adapters')->andReturn([$adapter]);
    $service->shouldReceive('paypal')->andReturn(null);
    $service->shouldReceive('settings')->andReturn(['days_before' => 0, 'every' => 7, 'retries' => 2]);
    $fees = Mockery::mock(Box\Mod\Invoice\ServiceGatewayFees::class);
    $fees->shouldReceive('apply')->times($invoiceCount);
    $transactions = Mockery::mock(Box\Mod\Invoice\ServiceTransaction::class);
    $transactions->shouldReceive('createAndProcess')->times($invoiceCount)->andReturnUsing(function (array $data) use ($di, $ids): int {
        $manager = $di['em'];
        $invoice = $manager->find(Invoice::class, $data['invoice_id']);
        $invoice->setStatus(Invoice::STATUS_PAID);
        if ($data['invoice_id'] === $ids[0]) {
            // The provider accepted payment, but local settlement fails with
            // dirty managed state. Recovery must never flush that state later.
            throw new RuntimeException('Payment details lookup timed out');
        }
        $tx = (new Box\Mod\Invoice\Entity\Transaction())->setInvoice($invoice)
            ->setGateway($manager->find(PayGateway::class, $data['gateway_id']))
            ->setTxnId($data['get']['payment_intent'])->setTxnStatus('succeeded')->setStatus('processed');
        $manager->persist($tx);
        $manager->flush();

        return (int) $tx->getId();
    });
    $di['mod_service'] = $di->protect(function (string $module, string $sub = '') use ($di, $fees, $transactions): object {
        if (strtolower($module) === 'staff') {
            return Mockery::mock(Box\Mod\Staff\Service::class)->shouldIgnoreMissing();
        }
        if (strtolower($module) === 'system') {
            $system = new Box\Mod\System\Service();
            $system->setDi($di);

            return $system;
        }

        if ($sub === '') {
            $invoices = new Box\Mod\Invoice\Service();
            $invoices->setDi($di);

            return $invoices;
        }

        return match ($sub) {
            'GatewayFees' => $fees, 'Transaction' => $transactions,
        };
    });
    $listenerManagers = [];
    $di['event_dispatcher'] = new FOSSBilling\Core\Events\EventDispatcher(fn (): array => ['invoice'], function () use ($di, &$listenerManagers): object {
        $boundManager = $di['em'];
        $listenerManagers[] = $boundManager;
        $listener = Mockery::mock(Box\Mod\Invoice\Service::class)->shouldIgnoreMissing();
        $listener->shouldReceive('healMissingJournalEntries')->zeroOrMoreTimes()->andReturnUsing(function () use ($boundManager): void {
            expect($boundManager->isOpen())->toBeTrue();
            $boundManager->persist((new Box\Mod\System\Entity\Setting())->setParam('after_cron_listener')->setValue('healthy'));
            $boundManager->flush();
        });

        return $listener;
    });
    $api = new class($service, $di) {
        public function __construct(private readonly ServiceSavedCards $saved, private Pimple\Container $di)
        {
        }

        public function __call(string $method, array $args): void
        {
            if ($method === 'invoice_batch_charge_saved_cards') {
                $this->saved->runDue();
            } elseif (in_array($method, ['order_batch_send_suspension_warnings', 'support_batch_ticket_auto_close', 'email_batch_sendmail'], true)) {
                $manager = $this->di['em'];
                expect($manager->isOpen())->toBeTrue();
                $manager->persist((new Box\Mod\System\Entity\Setting())->setParam($method)->setValue('healthy'));
                $manager->flush();
            }
        }
    };
    $finalization = Mockery::mock();
    $finalization->shouldReceive('withRuntimeLock')->andReturnUsing(fn (callable $work): mixed => $work());
    $finalization->shouldReceive('isRequired')->andReturnFalse();
    $finalization->shouldReceive('healSchemaDrift')->andReturnNull();
    $di['update_finalization'] = $finalization;
    $di['api_system'] = $api;
    $cron = new Box\Mod\Cron\Service();
    $cron->setDi($di);
    ob_start();

    try {
        expect($cron->runCrons())->toBeTrue()
            ->and($charged)->toBe($ids)
            ->and($em->isOpen())->toBeFalse()
            ->and($di['em']->isOpen())->toBeTrue()
            ->and($di['em'])->not->toBe($em)
            ->and($listenerManagers)->toHaveCount(2)
            ->and($listenerManagers[1])->toBe($di['em'])
            ->and($db->fetchOne('SELECT status FROM invoice WHERE id = ?', [$ids[0]]))->toBe('unpaid')
            ->and(Box\Mod\Invoice\GatewayChargeAttempts::pending($db, $ids[0])['payment_id'])->toBe('pi_' . $ids[0]);
        if ($invoiceCount > 1) {
            expect($db->fetchOne('SELECT status FROM invoice WHERE id = ?', [$ids[1]]))->toBe('paid')
                ->and(Box\Mod\Invoice\GatewayChargeAttempts::pending($db, $ids[1]))->toBeNull();
        }
        foreach (['order_batch_send_suspension_warnings', 'support_batch_ticket_auto_close', 'email_batch_sendmail', 'after_cron_listener'] as $param) {
            expect($db->fetchOne('SELECT value FROM setting WHERE param = ?', [$param]))->toBe('healthy');
        }
    } finally {
        ob_end_clean();
        $db->close();
    }
})->with(['failed last invoice' => 1, 'later invoice still settles' => 2]);

test('saved charges reject drafts and deletion holds before changing payment state', function (string $case, bool $byStaff, bool $byClient, ?int $typedId, string $reference): void {
    $db = Tests\Helpers\fencedConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $em = FOSSBilling\Core\Doctrine\EntityManagerFactory::create($db);
    (new Doctrine\ORM\Tools\SchemaTool($em))->createSchema(array_map($em->getClassMetadata(...), [
        Invoice::class, PayGateway::class, InvoiceCardCharge::class,
        Box\Mod\Invoice\Entity\GatewayChargeAttempt::class, Box\Mod\Invoice\Entity\Transaction::class,
        Box\Mod\Invoice\Entity\InvoiceItem::class,
    ]));
    $db->executeStatement('CREATE TABLE client_order_meta (client_order_id INTEGER, name TEXT, value TEXT)');
    $invoice = (new Invoice())->setIssued(true)->setStatus('unpaid')->setClientId(7)->setCurrency('USD');
    $gateway = (new PayGateway())->setGateway('Stripe');
    $em->persist($invoice);
    $em->persist($gateway);
    $em->flush();
    if ($case === 'draft') {
        // runDue can select an issued invoice before a later edit restores its draft state.
        $db->update('invoice', ['issued' => false], ['id' => $invoice->getId()]);
        expect($invoice->isIssued())->toBeTrue();
    } else {
        $db->insert('invoice_item', ['invoice_id' => $invoice->getId(), 'type' => 'order', 'order_id' => $typedId, 'rel_id' => $reference]);
        $db->insert('client_order_meta', ['client_order_id' => 7, 'name' => 'deletion_state', 'value' => $case]);
    }
    $adapter = Mockery::mock(Payment_Adapter_Stripe::class);
    $adapter->shouldReceive('gatewayId')->andReturn($gateway->getId());
    $adapter->shouldNotReceive('chargeSavedCard', 'savedCard');
    $fees = Mockery::mock(Box\Mod\Invoice\ServiceGatewayFees::class);
    $fees->shouldNotReceive('apply');
    $invoices = new Box\Mod\Invoice\Service();
    $invoices->setDi(savedCardsService($em)->getDi());
    $extension = Mockery::mock();
    $extension->shouldReceive('getConfig')->with('mod_invoice')->andReturn([]);
    $service = savedCardsService($em, ['invoice' => $invoices, 'invoice:gatewayfees' => $fees, 'extension' => $extension]);

    expect($service->charge($invoice, $adapter, $byStaff, $byClient))->toBe([
        'paid' => false,
        'error' => $case === 'draft' ? null : 'Order deletion is awaiting recovery. Retry deletion before billing or changing the service.',
    ])->and((int) $db->fetchOne('SELECT COUNT(*) FROM invoice_card_charge'))->toBe(0)
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM invoice_gateway_charge_attempt'))->toBe(0)
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM "transaction"'))->toBe(0);
    $db->close();
})->with([
    'draft selected before edit' => ['draft', false, false, null, '7'],
    'automatic charge after remote deletion' => ['service_deleted', false, false, 7, '99'],
    'staff charge during interrupted deletion' => ['started', true, false, 7, '99'],
    'client charge with legacy link' => ['service_deleted', true, true, null, '007 '],
]);

test('automatic selection excludes due drafts while retaining issued invoices', function (): void {
    $db = Tests\Helpers\fencedConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $em = FOSSBilling\Core\Doctrine\EntityManagerFactory::create($db);
    (new Doctrine\ORM\Tools\SchemaTool($em))->createSchema(array_map($em->getClassMetadata(...), [
        Invoice::class, PayGateway::class, PayGatewayCustomer::class, InvoiceCardCharge::class,
        Box\Mod\Invoice\Entity\Transaction::class, Box\Mod\Invoice\Entity\InvoiceItem::class, Box\Mod\Invoice\Entity\Subscription::class,
    ]));
    $gateway = (new PayGateway())->setGateway('Stripe');
    $em->persist($gateway);
    $em->flush();
    $draft = (new Invoice())->setStatus('unpaid')->setClientId(7)->setDueAt(new DateTimeImmutable('-1 month'));
    $issued = (new Invoice())->setIssued(true)->setStatus('unpaid')->setClientId(7)->setDueAt(new DateTimeImmutable('-1 month'));
    $em->persist($draft);
    $em->persist($issued);
    $em->persist((new PayGatewayCustomer())->setPayGateway($gateway)->setClientId(7)->setTestMode(false)->setSavedCards(true)->setExternalCustomerId('cus_7')->setDefaultPaymentMethod('pm_7'));
    $em->flush();
    $adapter = Mockery::mock(Payment_Adapter_Stripe::class);
    $adapter->shouldReceive('gatewayId')->andReturn($gateway->getId());
    $adapter->shouldReceive('testMode')->andReturnFalse();
    $service = Mockery::mock(ServiceSavedCards::class)->makePartial();
    $service->setDi(savedCardsService($em)->getDi());
    $service->shouldReceive('adapters')->andReturn([$adapter]);
    $service->shouldReceive('paypal')->andReturnNull();
    $service->shouldReceive('settings')->andReturn(['days_before' => 0, 'every' => 7, 'retries' => 2]);
    $service->shouldReceive('method')->once()->with(7)->andReturn(['adapter' => $adapter]);
    $service->shouldReceive('charge')->once()->with($issued, $adapter, false)->andReturn(['paid' => true, 'error' => null]);

    $service->runDue();
    $db->close();
});
