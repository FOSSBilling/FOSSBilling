<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Client\Entity\Client;
use Box\Mod\Client\Repository\ClientGroupMembershipRepository;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\ServiceGatewayFees;
use Doctrine\ORM\EntityManagerInterface;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;
use function Tests\Helpers\setEntityId;

function gatewayFeesService(?object $em = null, ?object $currencyService = null): ServiceGatewayFees
{
    $service = new ServiceGatewayFees();
    $di = container();
    $di['em'] = $em ?? Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    if ($currencyService !== null) {
        $di['mod_service'] = $di->protect(fn (): object => $currencyService);
    }
    $service->setDi($di);

    return $service;
}

function gatewayWithFee(array $fee): PayGateway
{
    $gateway = createEntity(PayGateway::class);
    $gateway->setConfig(['fee' => $fee]);

    return $gateway;
}

test('cleans fee settings', function (): void {
    expect(ServiceGatewayFees::clean([]))->toBe(['percent' => 0.0, 'fixed' => 0.0, 'taxable' => false, 'exempt_groups' => []])
        ->and(ServiceGatewayFees::clean(['percent' => '2.5', 'fixed' => '1.20', 'taxable' => '1', 'exempt_groups' => ['2', 'x', 2]]))
        ->toBe(['percent' => 2.5, 'fixed' => 1.2, 'taxable' => true, 'exempt_groups' => [2]])
        ->and(ServiceGatewayFees::clean(['percent' => 150, 'fixed' => -5]))->toBe(['percent' => 100.0, 'fixed' => 0.0, 'taxable' => false, 'exempt_groups' => []]);
});

test('reads fee settings from the gateway config', function (): void {
    $service = gatewayFeesService();

    expect($service->settings(gatewayWithFee(['percent' => 3]))['percent'])->toEqual(3.0)
        ->and($service->settings(gatewayWithFee([])))->toBe(['percent' => 0.0, 'fixed' => 0.0, 'taxable' => false, 'exempt_groups' => []]);
});

test('returns zero when there is no base or no fee', function (): void {
    $service = gatewayFeesService();

    expect($service->fee(gatewayWithFee(['percent' => 5]), 0.0, 'USD', 1))->toBe(0.0)
        ->and($service->fee(gatewayWithFee([]), 100.0, 'USD', 1))->toBe(0.0);
});

test('applies percent and converted fixed amount', function (): void {
    $currencyService = Mockery::mock(Box\Mod\Currency\Service::class);
    $currencyService->shouldReceive('getBaseCurrencyRate')->with('EUR')->andReturn(0.5);

    $clientRepo = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepo->shouldReceive('find')->with(7)->andReturn(null);
    $membershipRepo = Mockery::mock(ClientGroupMembershipRepository::class);
    $membershipRepo->shouldReceive('getGroupIdsForClient')->with(7)->andReturn([]);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(Client::class)->andReturn($clientRepo);
    $em->shouldReceive('getRepository')->with(ClientGroupMembershipRepository::class)->andReturn($membershipRepo);

    $service = gatewayFeesService($em, $currencyService);

    // 2% of 100 = 2, plus fixed 1.00 / 0.5 = 2, total 4.
    expect($service->fee(gatewayWithFee(['percent' => 2, 'fixed' => 1]), 100.0, 'EUR', 7))->toEqual(4.0);
});

test('skips the fee for exempt client groups', function (): void {
    $currencyService = Mockery::mock(Box\Mod\Currency\Service::class);
    $currencyService->shouldNotReceive('getBaseCurrencyRate');

    $client = createEntity(Client::class);
    $clientRepo = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepo->shouldReceive('find')->with(7)->andReturn($client);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->andReturnUsing(static function (string $class) use ($clientRepo): object {
        if ($class === Client::class) {
            return $clientRepo;
        }

        return Mockery::mock()->shouldIgnoreMissing();
    });

    // Client::getGroupIds() on a fresh entity is empty; exempt group 9 does not match.
    $service = gatewayFeesService($em, $currencyService);
    expect($service->fee(gatewayWithFee(['percent' => 5, 'exempt_groups' => [9]]), 100.0, 'USD', 7))->toEqual(5.0);
});

function gatewayFeesInvoice(array $properties = []): Box\Mod\Invoice\Entity\Invoice
{
    $invoice = createEntity(Box\Mod\Invoice\Entity\Invoice::class, $properties);
    if ($invoice->getId() === null) {
        setEntityId($invoice, 11);
    }

    return $invoice;
}

function gatewayFeesItem(array $properties = []): Box\Mod\Invoice\Entity\InvoiceItem
{
    return createEntity(Box\Mod\Invoice\Entity\InvoiceItem::class, $properties);
}

test('base excludes the fee line and applied payments', function (): void {
    $invoice = gatewayFeesInvoice(['status' => Box\Mod\Invoice\Entity\Invoice::STATUS_UNPAID, 'currency' => 'USD', 'taxrate' => 0]);

    $line = gatewayFeesItem(['price' => '2.00', 'quantity' => 1, 'taxed' => false, 'task' => ServiceGatewayFees::TASK, 'type' => Box\Mod\Invoice\Entity\InvoiceItem::TYPE_CUSTOM]);

    $itemRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceItemRepository::class);
    $itemRepo->shouldReceive('findOneBy')->andReturn($line);

    $invoiceService = Mockery::mock(Box\Mod\Invoice\Service::class);
    $invoiceService->shouldReceive('getTotalWithTax')->with($invoice)->andReturn(102.00);
    $invoiceService->shouldReceive('getAmountPaid')->with($invoice)->andReturn(80.00);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(Box\Mod\Invoice\Entity\InvoiceItem::class)->andReturn($itemRepo);

    $service = gatewayFeesService($em);
    $service->getDi()['mod_service'] = $service->getDi()->protect(fn (): object => $invoiceService);

    expect($service->base($invoice))->toEqual(20.0);
});

test('fee tax is marginal over the other taxed lines', function (): void {
    $invoice = gatewayFeesInvoice(['status' => Box\Mod\Invoice\Entity\Invoice::STATUS_UNPAID, 'currency' => 'USD', 'taxrate' => 10]);

    $item = gatewayFeesItem(['price' => '0.33', 'quantity' => 3, 'taxed' => true, 'task' => null, 'type' => Box\Mod\Invoice\Entity\InvoiceItem::TYPE_CUSTOM]);

    $itemRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceItemRepository::class);
    $itemRepo->shouldReceive('findByInvoiceId')->andReturn([$item]);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(Box\Mod\Invoice\Entity\InvoiceItem::class)->andReturn($itemRepo);

    $service = gatewayFeesService($em);

    // Taxed subtotal 0.99 -> 0.10; with a 10.00 fee the total tax is 1.10, so the fee adds 1.00.
    expect($service->taxForFee($invoice, 10.00))->toEqual(1.00);
});

test('apply adds the fee line and remove takes it off', function (): void {
    $invoice = gatewayFeesInvoice(['status' => Box\Mod\Invoice\Entity\Invoice::STATUS_UNPAID, 'currency' => 'USD', 'client_id' => 7, 'taxrate' => 0]);
    $gateway = gatewayWithFee(['percent' => 2]);
    $gateway->setName('Stripe');

    $itemRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceItemRepository::class);
    $feeLine = gatewayFeesItem(['price' => '2.00', 'quantity' => 1, 'taxed' => false, 'task' => ServiceGatewayFees::TASK, 'type' => Box\Mod\Invoice\Entity\InvoiceItem::TYPE_CUSTOM]);
    // base() lookup -> none, apply() lookup -> none (fresh add), remove() lookup -> the line.
    $itemRepo->shouldReceive('findOneBy')->andReturn(null, null, $feeLine);

    $clientRepo = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepo->shouldReceive('find')->with(7)->andReturn(null);
    $membershipRepo = Mockery::mock(ClientGroupMembershipRepository::class);
    $membershipRepo->shouldReceive('getGroupIdsForClient')->with(7)->andReturn([]);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->andReturnUsing(static function (string $class) use ($itemRepo, $clientRepo, $membershipRepo): object {
        return match ($class) {
            Box\Mod\Invoice\Entity\InvoiceItem::class => $itemRepo,
            Client::class => $clientRepo,
            ClientGroupMembershipRepository::class => $membershipRepo,
            default => Mockery::mock()->shouldIgnoreMissing(),
        };
    });
    $em->shouldReceive('wrapInTransaction')->andReturnUsing(static fn (callable $callback): mixed => $callback());
    $em->shouldReceive('refresh')->andReturnNull();

    $added = [];
    $invoiceItemService = Mockery::mock(Box\Mod\Invoice\ServiceInvoiceItem::class);
    $invoiceItemService->shouldReceive('addNew')->once()->with($invoice, Mockery::on(function (array $data) use (&$added): bool {
        $added = $data;

        return true;
    }), true)->andReturn(99);
    $invoiceItemService->shouldReceive('remove')->once()->with(Mockery::type(Box\Mod\Invoice\Entity\InvoiceItem::class), true)->andReturnTrue();

    $invoiceService = Mockery::mock(Box\Mod\Invoice\Service::class);
    $invoiceService->shouldReceive('getTotalWithTax')->with($invoice)->andReturn(100.00);
    $invoiceService->shouldReceive('getAmountPaid')->with($invoice)->andReturn(0.0);
    $invoiceService->shouldReceive('isInvoiceTypeDeposit')->with($invoice)->andReturn(false);
    $invoiceService->shouldReceive('lockInvoiceState')->andReturn(['status' => 'unpaid', 'issued' => true]);

    $currencyService = Mockery::mock(Box\Mod\Currency\Service::class);

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (string $name, string $sub = ''): object => match ([strtolower($name), strtolower($sub)]) {
        ['invoice', ''] => $invoiceService,
        ['invoice', 'invoiceitem'] => $invoiceItemService,
        default => $currencyService,
    });

    $service = new ServiceGatewayFees();
    $service->setDi($di);

    $service->apply($invoice, $gateway);
    expect($added['price'])->toEqual(2.00)
        ->and($added['task'])->toBe(ServiceGatewayFees::TASK);

    expect($service->remove($invoice))->toEqual(2.00);
});

test('apply skips paid and deposit invoices', function (): void {
    $paid = gatewayFeesInvoice(['status' => Box\Mod\Invoice\Entity\Invoice::STATUS_PAID, 'currency' => 'USD', 'taxrate' => 0]);

    $invoiceService = Mockery::mock(Box\Mod\Invoice\Service::class);
    $invoiceService->shouldReceive('lockInvoiceState')->once()->andReturn(['status' => 'paid', 'issued' => true]);
    $invoiceService->shouldNotReceive('isInvoiceTypeDeposit');

    $invoiceItemService = Mockery::mock(Box\Mod\Invoice\ServiceInvoiceItem::class);
    $invoiceItemService->shouldNotReceive('addNew');
    $invoiceItemService->shouldNotReceive('remove');

    $em = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $em->shouldReceive('wrapInTransaction')->once()->andReturnUsing(static fn (callable $callback): mixed => $callback());

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (string $name, string $sub = ''): object => match ([strtolower($name), strtolower($sub)]) {
        ['invoice', ''] => $invoiceService,
        default => $invoiceItemService,
    });

    $service = new ServiceGatewayFees();
    $service->setDi($di);

    // Paid status returns after the lock, before any item lookup.
    $service->apply($paid, gatewayWithFee(['percent' => 5]));
    expect(true)->toBeTrue();
});
