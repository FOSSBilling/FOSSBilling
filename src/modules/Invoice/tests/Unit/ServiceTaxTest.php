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
use Box\Mod\Invoice\ServiceInvoiceItem;
use Box\Mod\Invoice\ServiceTax;

use function Tests\Helpers\container;

test('gets dependency injection container', function (): void {
    $service = new ServiceTax();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toBe($di);
});

test('gets tax rate for client by country and state', function (): void {
    $service = new ServiceTax();
    $taxRateExpected = 0.21;
    $clientModel = new Model_Client();
    $clientModel->loadBean(new Tests\Helpers\DummyBean());

    $clientServiceMock = Mockery::mock(ClientService::class);
    $clientServiceMock->shouldReceive('isClientTaxable')
        ->atLeast()->once()
        ->andReturn(true);

    $taxEntity = new Box\Mod\Invoice\Entity\Tax();
    $taxEntity->setTaxrate((string) $taxRateExpected);
    $taxEntity->setName('Test Tax');

    $taxRepoMock = Mockery::mock(Box\Mod\Invoice\Repository\TaxRepository::class);
    $taxRepoMock->shouldReceive('findByCountryAndState')
        ->atLeast()->once()
        ->andReturn($taxEntity);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\Tax::class)
        ->andReturn($taxRepoMock);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $clientServiceMock);
    $di['em'] = $emMock;
    $service->setDi($di);

    $result = $service->getTaxRateForClient($clientModel);
    expect($result)->toBeFloat();
    expect($result)->toBe($taxRateExpected);
});

test('gets tax rate for client by country', function (): void {
    $service = new ServiceTax();
    $taxRateExpected = 0.21;
    $clientModel = new Model_Client();
    $clientModel->loadBean(new Tests\Helpers\DummyBean());

    $clientServiceMock = Mockery::mock(ClientService::class);
    $clientServiceMock->shouldReceive('isClientTaxable')
        ->atLeast()->once()
        ->andReturn(true);

    $taxEntity = new Box\Mod\Invoice\Entity\Tax();
    $taxEntity->setTaxrate((string) $taxRateExpected);
    $taxEntity->setName('Test Tax');

    $taxRepoMock = Mockery::mock(Box\Mod\Invoice\Repository\TaxRepository::class);
    $taxRepoMock->shouldReceive('findByCountryAndState')->andReturn(null);
    $taxRepoMock->shouldReceive('findByCountry')
        ->atLeast()->once()
        ->andReturn($taxEntity);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\Tax::class)
        ->andReturn($taxRepoMock);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $clientServiceMock);
    $di['em'] = $emMock;
    $service->setDi($di);

    $result = $service->getTaxRateForClient($clientModel);
    expect($result)->toBeFloat();
    expect($result)->toBe($taxRateExpected);
});

test('gets tax rate for client', function (): void {
    $service = new ServiceTax();
    $taxRateExpected = 0.21;
    $clientModel = new Model_Client();
    $clientModel->loadBean(new Tests\Helpers\DummyBean());

    $clientServiceMock = Mockery::mock(ClientService::class);
    $clientServiceMock->shouldReceive('isClientTaxable')
        ->atLeast()->once()
        ->andReturn(true);

    $taxEntity = new Box\Mod\Invoice\Entity\Tax();
    $taxEntity->setTaxrate((string) $taxRateExpected);
    $taxEntity->setName('Test Tax');

    $taxRepoMock = Mockery::mock(Box\Mod\Invoice\Repository\TaxRepository::class);
    $taxRepoMock->shouldReceive('findByCountryAndState')->andReturn(null);
    $taxRepoMock->shouldReceive('findByCountry')->andReturn(null);
    $taxRepoMock->shouldReceive('findGlobal')
        ->atLeast()->once()
        ->andReturn($taxEntity);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\Tax::class)
        ->andReturn($taxRepoMock);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $clientServiceMock);
    $di['em'] = $emMock;
    $service->setDi($di);

    $result = $service->getTaxRateForClient($clientModel);
    expect($result)->toBeFloat();
    expect($result)->toBe($taxRateExpected);
});

test('returns zero tax rate when tax not found', function (): void {
    $service = new ServiceTax();
    $clientModel = new Model_Client();
    $clientModel->loadBean(new Tests\Helpers\DummyBean());

    $clientServiceMock = Mockery::mock(ClientService::class);
    $clientServiceMock->shouldReceive('isClientTaxable')
        ->atLeast()->once()
        ->andReturn(true);

    $taxRepoMock = Mockery::mock(Box\Mod\Invoice\Repository\TaxRepository::class);
    $taxRepoMock->shouldReceive('findByCountryAndState')->andReturn(null);
    $taxRepoMock->shouldReceive('findByCountry')->andReturn(null);
    $taxRepoMock->shouldReceive('findGlobal')->andReturn(null);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\Tax::class)
        ->andReturn($taxRepoMock);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $clientServiceMock);
    $di['em'] = $emMock;
    $service->setDi($di);

    $taxRateExpected = 0;
    $result = $service->getTaxRateForClient($clientModel);
    expect($result)->toBeInt();
    expect($result)->toBe($taxRateExpected);
});

test('returns zero tax rate when client is not taxable', function (): void {
    $service = new ServiceTax();
    $clientModel = new Model_Client();
    $clientModel->loadBean(new Tests\Helpers\DummyBean());

    $clientServiceMock = Mockery::mock(ClientService::class);
    $clientServiceMock->shouldReceive('isClientTaxable')
        ->atLeast()->once()
        ->andReturn(false);

    $taxModel = new Model_Tax();
    $taxModel->loadBean(new Tests\Helpers\DummyBean());

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $clientServiceMock);
    $service->setDi($di);

    $taxRateExpected = 0;
    $result = $service->getTaxRateForClient($clientModel);
    expect($result)->toBeInt();
    expect($result)->toBe($taxRateExpected);
});

test('returns zero tax when tax rate is zero', function (): void {
    $service = new ServiceTax();
    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
    $invoiceModel->taxrate = 0;

    $result = $service->getTax($invoiceModel);
    expect($result)->toBeInt();
    expect($result)->toBe(0);
});

test('gets tax', function (): void {
    $service = new ServiceTax();
    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
    $invoiceModel->taxrate = 15;

    $invoiceItemModel = new Model_InvoiceItem();
    $invoiceItemModel->loadBean(new Tests\Helpers\DummyBean());
    $invoiceItemModel->quantity = 1;

    $invoiceItemRepoMock = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceItemRepository::class);
    $invoiceItemRepoMock->shouldReceive('findByInvoiceId')
        ->atLeast()->once()
        ->andReturn([$invoiceItemModel]);

    $invoiceItemService = Mockery::mock(ServiceInvoiceItem::class);
    $invoiceItemService->shouldReceive('getTax')
        ->atLeast()->once()
        ->andReturn(21);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\InvoiceItem::class)
        ->andReturn($invoiceItemRepoMock);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $invoiceItemService);
    $di['em'] = $emMock;

    $service->setDi($di);
    $result = $service->getTax($invoiceModel);
    expect($result)->toBeInt();
});

test('deletes a tax', function (): void {
    $service = new ServiceTax();
    $taxModel = new Model_Tax();
    $taxModel->loadBean(new Tests\Helpers\DummyBean());

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->delete($taxModel);
    expect($result)->toBeTrue();
});

test('creates a tax', function (): void {
    $service = new ServiceTax();

    $di = container();
    $di['em'] = Tests\Helpers\entityManagerWithIds($di);
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $data = [
        'name' => 'tax',
        'taxrate' => '0.18',
    ];
    $result = $service->create($data);
    expect($result)->toBeInt()->toBe(1);
});

test('updates a tax', function (): void {
    $service = new ServiceTax();
    $taxModel = new Model_Tax();
    $taxModel->loadBean(new Tests\Helpers\DummyBean());

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $data = [
        'name' => 'tax',
        'taxrate' => '0.18',
    ];
    $result = $service->update($taxModel, $data);
    expect($result)->toBeBool()->toBeTrue();
});

test('gets search query', function (): void {
    $service = new ServiceTax();
    $result = $service->getSearchQuery([]);
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();
    expect($result[1])->toBe([]);
});

test('converts to api array', function (): void {
    $service = new ServiceTax();
    $taxModel = new Model_Tax();
    $taxModel->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('toArray')
        ->with($taxModel)
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->toApiArray($taxModel);
    expect($result)->toBeArray();
});
