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
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Invoice\Entity\Tax;
use Box\Mod\Invoice\Repository\InvoiceItemRepository;
use Box\Mod\Invoice\Repository\TaxRepository;
use Box\Mod\Invoice\ServiceInvoiceItem;
use Box\Mod\Invoice\ServiceTax;
use Doctrine\ORM\EntityManagerInterface;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;
use function Tests\Helpers\setEntityId;

function taxService(TaxRepository $taxRepository, ?EntityManagerInterface $em = null): ServiceTax
{
    $service = new ServiceTax();
    $di = container();
    $em ??= Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(Tax::class)->andReturn($taxRepository);
    $di['em'] = $em;
    $service->setDi($di);

    return $service;
}

test('gets dependency injection container', function (): void {
    $taxRepo = Mockery::mock(TaxRepository::class);
    $service = taxService($taxRepo);

    expect($service->getDi())->toBeInstanceOf(Pimple\Container::class)
        ->and($service->getTaxRepository())->toBe($taxRepo);
});

test('gets tax rate for client by country and state', function (): void {
    $taxEntity = createEntity(Tax::class, ['taxrate' => '0.21', 'name' => 'VAT']);

    $taxRepo = Mockery::mock(TaxRepository::class);
    $taxRepo->shouldReceive('findOneByStateAndCountry')->once()->andReturn($taxEntity);

    $clientServiceMock = Mockery::mock(ClientService::class);
    $clientServiceMock->shouldReceive('isClientTaxable')->andReturn(true);

    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class);

    $service = taxService($taxRepo);
    $service->getDi()['mod_service'] = $service->getDi()->protect(fn (): Mockery\MockInterface => $clientServiceMock);

    $title = null;
    $result = $service->getTaxRateForClient($clientModel, $title);

    expect($result)->toBe('0.21')
        ->and($title)->toBe('VAT');
});

test('gets tax rate for client by country', function (): void {
    $taxEntity = createEntity(Tax::class, ['taxrate' => '0.21', 'name' => 'VAT']);

    $taxRepo = Mockery::mock(TaxRepository::class);
    $taxRepo->shouldReceive('findOneByStateAndCountry')->once()->andReturn(null);
    $taxRepo->shouldReceive('findOneByCountry')->once()->andReturn($taxEntity);

    $clientServiceMock = Mockery::mock(ClientService::class);
    $clientServiceMock->shouldReceive('isClientTaxable')->andReturn(true);

    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class);

    $service = taxService($taxRepo);
    $service->getDi()['mod_service'] = $service->getDi()->protect(fn (): Mockery\MockInterface => $clientServiceMock);

    $result = $service->getTaxRateForClient($clientModel);

    expect($result)->toBe('0.21');
});

test('gets tax rate for client from global rule', function (): void {
    $taxEntity = createEntity(Tax::class, ['taxrate' => '0.21', 'name' => 'Global VAT']);

    $taxRepo = Mockery::mock(TaxRepository::class);
    $taxRepo->shouldReceive('findOneByStateAndCountry')->once()->andReturn(null);
    $taxRepo->shouldReceive('findOneByCountry')->once()->andReturn(null);
    $taxRepo->shouldReceive('findGlobalRate')->once()->andReturn($taxEntity);

    $clientServiceMock = Mockery::mock(ClientService::class);
    $clientServiceMock->shouldReceive('isClientTaxable')->andReturn(true);

    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class);

    $service = taxService($taxRepo);
    $service->getDi()['mod_service'] = $service->getDi()->protect(fn (): Mockery\MockInterface => $clientServiceMock);

    $result = $service->getTaxRateForClient($clientModel);

    expect($result)->toBe('0.21');
});

test('returns zero tax rate when tax not found', function (): void {
    $taxRepo = Mockery::mock(TaxRepository::class);
    $taxRepo->shouldReceive('findOneByStateAndCountry')->andReturn(null);
    $taxRepo->shouldReceive('findOneByCountry')->andReturn(null);
    $taxRepo->shouldReceive('findGlobalRate')->andReturn(null);

    $clientServiceMock = Mockery::mock(ClientService::class);
    $clientServiceMock->shouldReceive('isClientTaxable')->andReturn(true);

    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class);

    $service = taxService($taxRepo);
    $service->getDi()['mod_service'] = $service->getDi()->protect(fn (): Mockery\MockInterface => $clientServiceMock);

    expect($service->getTaxRateForClient($clientModel))->toBeInt()->toBe(0);
});

test('returns zero tax rate when client is not taxable', function (): void {
    $taxRepo = Mockery::mock(TaxRepository::class);
    $taxRepo->shouldNotReceive('findOneByStateAndCountry');

    $clientServiceMock = Mockery::mock(ClientService::class);
    $clientServiceMock->shouldReceive('isClientTaxable')->andReturn(false);

    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class);

    $service = taxService($taxRepo);
    $service->getDi()['mod_service'] = $service->getDi()->protect(fn (): Mockery\MockInterface => $clientServiceMock);

    expect($service->getTaxRateForClient($clientModel))->toBeInt()->toBe(0);
});

test('returns zero tax when invoice tax rate is zero', function (): void {
    $taxRepo = Mockery::mock(TaxRepository::class);
    $service = taxService($taxRepo);

    $invoiceModel = createEntity(Invoice::class);

    $invoiceModel->setTaxrate(0);

    expect($service->getTax($invoiceModel))->toBeInt()->toBe(0);
});

test('gets tax for an invoice', function (): void {
    $invoiceModel = createEntity(Invoice::class);

    $invoiceModel->setTaxrate(15);

    $invoiceItem = createEntity(InvoiceItem::class, ['quantity' => 1]);

    $invoiceItemRepo = Mockery::mock(InvoiceItemRepository::class);
    $invoiceItemRepo->shouldReceive('findByInvoiceId')->andReturn([$invoiceItem]);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(InvoiceItem::class)->andReturn($invoiceItemRepo);

    $taxRepo = Mockery::mock(TaxRepository::class);
    $service = taxService($taxRepo, $em);

    $invoiceItemService = Mockery::mock(ServiceInvoiceItem::class);
    $invoiceItemService->shouldReceive('getTax')->andReturn(21);

    $di = $service->getDi();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $invoiceItemService);

    expect($service->getTax($invoiceModel))->toBeInt();
});

test('deletes a tax', function (): void {
    $taxEntity = createEntity(Tax::class, ['id' => 1, 'name' => 'VAT']);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('remove')->with($taxEntity)->once();
    $em->shouldReceive('flush')->once();

    $taxRepo = Mockery::mock(TaxRepository::class);
    $service = taxService($taxRepo, $em);
    $service->getDi()['logger'] = new Tests\Helpers\TestLogger();

    expect($service->delete($taxEntity))->toBeTrue();
});

test('creates a tax', function (): void {
    $newId = 2;

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('persist')
        ->once()
        ->withArgs(function (Tax $tax) use ($newId): bool {
            setEntityId($tax, $newId);

            return true;
        });
    $em->shouldReceive('flush')->once();

    $taxRepo = Mockery::mock(TaxRepository::class);
    $service = taxService($taxRepo, $em);
    $service->getDi()['logger'] = new Tests\Helpers\TestLogger();

    $data = [
        'name' => 'tax',
        'taxrate' => '0.18',
    ];

    expect($service->create($data))->toBeInt()->toBe($newId);
});

test('updates a tax', function (): void {
    $taxEntity = createEntity(Tax::class, ['id' => 1, 'name' => 'tax', 'taxrate' => '0.10']);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('flush')->once();

    $taxRepo = Mockery::mock(TaxRepository::class);
    $service = taxService($taxRepo, $em);
    $service->getDi()['logger'] = new Tests\Helpers\TestLogger();

    $data = [
        'name' => 'tax',
        'taxrate' => '0.18',
    ];

    expect($service->update($taxEntity, $data))->toBeBool()->toBeTrue();
    expect($taxEntity->getTaxrate())->toBe('0.18');
});

test('converts tax to api array', function (): void {
    $taxEntity = createEntity(Tax::class, [
        'id' => 1,
        'level' => 1,
        'name' => 'VAT',
        'country' => 'US',
        'state' => 'CA',
        'taxrate' => '8.25',
    ]);

    $taxRepo = Mockery::mock(TaxRepository::class);
    $service = taxService($taxRepo);

    $result = $service->toApiArray($taxEntity);

    expect($result)->toBeArray()
        ->and($result['id'])->toBe(1)
        ->and($result['name'])->toBe('VAT')
        ->and($result['taxrate'])->toBe('8.25');
});
