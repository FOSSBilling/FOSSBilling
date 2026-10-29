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

function customApprovalAdapter(object $di): Payment_Adapter_Custom
{
    $adapter = new Payment_Adapter_Custom(['single' => 'Pay instructions', 'recurrent' => 'Subscription instructions']);
    $adapter->setDi($di);

    return $adapter;
}

function customApprovalTransaction(?Invoice $invoice = null, ?PayGateway $gateway = null): Transaction
{
    $gateway ??= createEntity(PayGateway::class, ['id' => 1]);
    $gateway->setGateway('Custom');
    $gateway->setName('Custom');

    $invoice ??= createEntity(Invoice::class, ['id' => 11, 'client_id' => 5, 'currency' => 'USD']);

    $tx = createEntity(Transaction::class, ['id' => 42]);
    $tx->setGateway($gateway);
    $tx->setInvoice($invoice);

    return $tx;
}

function customApprovalDi(Transaction $tx, float $total = 25.0): Pimple\Container
{
    $txRepo = Mockery::mock(Box\Mod\Invoice\Repository\TransactionRepository::class);
    $txRepo->shouldReceive('find')->with(42)->andReturn($tx);
    $txRepo->shouldReceive('find')->with(404)->andReturn(null);

    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(Transaction::class)->andReturn($txRepo);
    $em->shouldReceive('persist')->byDefault();
    $em->shouldReceive('flush')->byDefault();

    $clientService = Mockery::mock();
    $clientService->shouldReceive('get')->byDefault()->andReturn(['id' => 5]);
    $clientService->shouldReceive('addFunds')->byDefault()->andReturn(true);

    $invoiceService = Mockery::mock();
    $invoiceService->shouldReceive('getTotalWithTax')->byDefault()->andReturn($total);
    $invoiceService->shouldReceive('markAsPaid')->byDefault()->andReturn(true);

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(static function (string $name) use ($clientService, $invoiceService): object {
        if (strtolower($name) === 'client') {
            return $clientService;
        }

        return $invoiceService;
    });

    return $di;
}

test('custom gateway settles through manual approval only', function (): void {
    expect(Payment_Adapter_Custom::requiresManualApproval())->toBeTrue()
        ->and(method_exists(Payment_Adapter_Custom::class, 'processTransaction'))->toBeFalse()
        ->and(method_exists(Payment_Adapter_Custom::class, 'approveTransaction'))->toBeTrue();
});

test('approveTransaction credits the client and marks the invoice paid', function (): void {
    $tx = customApprovalTransaction();
    $di = customApprovalDi($tx);

    $apiAdmin = (new ReflectionClass(FOSSBilling\Core\Api\Proxy::class))->newInstanceWithoutConstructor();

    expect(customApprovalAdapter($di)->approveTransaction($apiAdmin, 42, 1))->toBeTrue()
        ->and($tx->getStatus())->toBe(Transaction::STATUS_PROCESSED)
        ->and($tx->getAmount())->toBe('25')
        ->and($tx->getCurrency())->toBe('USD');
});

test('approveTransaction throws for an unknown transaction', function (): void {
    $tx = customApprovalTransaction();
    $di = customApprovalDi($tx);

    $apiAdmin = (new ReflectionClass(FOSSBilling\Core\Api\Proxy::class))->newInstanceWithoutConstructor();

    try {
        customApprovalAdapter($di)->approveTransaction($apiAdmin, 404, 1);
        expect(false)->toBeTrue('unknown transactions must throw');
    } catch (Payment_Exception $e) {
        expect($e->getCode())->toBe(7010);
    }
});

test('approveTransaction throws when the transaction has no invoice', function (): void {
    $gateway = createEntity(PayGateway::class, ['id' => 1]);
    $gateway->setGateway('Custom');
    $tx = createEntity(Transaction::class, ['id' => 42]);
    $tx->setGateway($gateway);
    $tx->setInvoice(null);
    $di = customApprovalDi($tx);

    $apiAdmin = (new ReflectionClass(FOSSBilling\Core\Api\Proxy::class))->newInstanceWithoutConstructor();

    expect(fn (): mixed => customApprovalAdapter($di)->approveTransaction($apiAdmin, 42, 1))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Invoice not found');
});

test('approveTransaction throws when the transaction has no gateway', function (): void {
    $invoice = createEntity(Invoice::class, ['id' => 11, 'client_id' => 5, 'currency' => 'USD']);
    $tx = createEntity(Transaction::class, ['id' => 42]);
    $tx->setGateway(null);
    $tx->setInvoice($invoice);
    $di = customApprovalDi($tx);

    $apiAdmin = (new ReflectionClass(FOSSBilling\Core\Api\Proxy::class))->newInstanceWithoutConstructor();

    try {
        customApprovalAdapter($di)->approveTransaction($apiAdmin, 42, 1);
        expect(false)->toBeTrue('gateway-less transactions must throw');
    } catch (Payment_Exception $e) {
        expect($e->getCode())->toBe(7011);
    }
});
