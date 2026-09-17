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
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\Transaction;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

test('declares support for one-time payments only', function (): void {
    $config = Payment_Adapter_ClientBalance::getConfig();

    expect($config)
        ->toHaveKey('supports_one_time_payments', true)
        ->toHaveKey('supports_subscriptions', false);
});

test('processes a transaction when the invoice and callback gateways match', function (): void {
    $gateway = createEntity(PayGateway::class, ['id' => 5]);
    $invoice = createEntity(Invoice::class, ['id' => 10, 'client_id' => 42]);
    $invoice->setGateway($gateway);

    $transactionRepository = Mockery::mock();
    $transactionRepository->shouldReceive('find')->once()->with(20)->andReturn(null);
    $invoiceRepository = Mockery::mock();
    $invoiceRepository->shouldReceive('find')->once()->with(10)->andReturn($invoice);

    $entityManager = Mockery::mock();
    $entityManager->shouldReceive('getRepository')->with(Transaction::class)->andReturn($transactionRepository);
    $entityManager->shouldReceive('getRepository')->with(Invoice::class)->andReturn($invoiceRepository);

    $invoiceService = Mockery::mock();
    $invoiceService->shouldReceive('isInvoiceTypeDeposit')->once()->with($invoice)->andReturnFalse();
    $invoiceService->shouldReceive('payInvoiceWithCredits')->once()->with($invoice);
    $invoiceService->shouldReceive('doBatchPayWithCredits')->once()->with(['client_id' => 42]);

    $loggedInClient = Mockery::mock();
    $loggedInClient->shouldReceive('getId')->once()->andReturn(42);

    $auth = Mockery::mock();
    $auth->shouldReceive('isClientLoggedIn')->once()->andReturnTrue();

    $di = container();
    $di['em'] = $entityManager;
    $di['auth'] = $auth;
    $di['loggedin_client'] = $loggedInClient;
    $di['mod_service'] = $di->protect(fn (string $name): object => $invoiceService);

    $adapter = new Payment_Adapter_ClientBalance();
    $adapter->setDi($di);

    expect($adapter->processTransaction(null, 20, ['get' => ['invoice_id' => 10]], 5))->toBeTrue();
});
