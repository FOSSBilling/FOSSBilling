<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\Transaction;
use Doctrine\ORM\EntityManagerInterface;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;
use function Tests\Helpers\moduleService;

if (!defined('SYSTEM_URL')) {
    define('SYSTEM_URL', 'https://billing.example/');
}

function paypalEmailAdapter(array $config = []): Payment_Adapter_PayPalEmail
{
    return new Payment_Adapter_PayPalEmail(array_merge([
        'email' => 'pay@example.com',
        'test_mode' => true,
        'test_api_client_id' => 'cid',
        'test_api_secret' => 'csecret',
        'gateway_id' => 6,
        'vault' => '1',
    ], $config));
}

function paypalRest(array $calls): Mockery\MockInterface
{
    $http = Mockery::mock(Symfony\Contracts\HttpClient\HttpClientInterface::class);
    $http->shouldReceive('request')->andReturnUsing(function () use (&$calls): object {
        [$status, $body] = array_shift($calls);
        if ($body instanceof Throwable) {
            throw $body;
        }
        $response = Mockery::mock(Symfony\Contracts\HttpClient\ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn($status);
        $response->shouldReceive('toArray')->with(false)->andReturn($body);

        return $response;
    });

    return $http;
}

function paypalDi(array $services = [], ?EntityManagerInterface $em = null): Pimple\Container
{
    $di = container();
    $di['em'] = $em ?? Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $di['mod_service'] = $di->protect(moduleService($services));

    return $di;
}

describe('REST maintenance', function (): void {
    test('knows when the REST keys are set', function (): void {
        expect(paypalEmailAdapter()->hasApiKeys())->toBeTrue()
            ->and(paypalEmailAdapter(['test_api_client_id' => ''])->hasApiKeys())->toBeFalse();
    });

    test('connection test accepts working keys', function (): void {
        $adapter = paypalEmailAdapter();
        $adapter->setDi(paypalDi());
        $adapter->getDi()['http_client'] = paypalRest([
            [200, ['access_token' => 'tok', 'token_type' => 'Bearer']],
        ]);

        $adapter->testConnection();
        expect(true)->toBeTrue();
    });

    test('connection test rejects bad keys', function (): void {
        $adapter = paypalEmailAdapter();
        $adapter->setDi(paypalDi());
        $adapter->getDi()['http_client'] = paypalRest([
            [401, ['error' => 'invalid_client', 'error_description' => 'Bad credentials']],
        ]);

        expect(fn () => $adapter->testConnection())
            ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'did not accept');
    });

    test('cancels a subscription at PayPal', function (): void {
        $adapter = paypalEmailAdapter();
        $adapter->setDi(paypalDi());
        $adapter->getDi()['http_client'] = paypalRest([
            [200, ['access_token' => 'tok']],
            [204, []],
        ]);

        $adapter->cancelSubscription('I-1');
        expect(true)->toBeTrue();
    });

    test('an already ended subscription counts as cancelled', function (): void {
        $adapter = paypalEmailAdapter();
        $adapter->setDi(paypalDi());
        $adapter->getDi()['http_client'] = paypalRest([
            [200, ['access_token' => 'tok']],
            [422, ['name' => 'UNPROCESSABLE_ENTITY']],
            [200, ['status' => 'CANCELLED']],
        ]);

        $adapter->cancelSubscription('I-1');
        expect(true)->toBeTrue();
    });

    test('a live subscription that refuses stays an error', function (): void {
        $adapter = paypalEmailAdapter();
        $adapter->setDi(paypalDi());
        $adapter->getDi()['http_client'] = paypalRest([
            [200, ['access_token' => 'tok']],
            [422, ['name' => 'UNPROCESSABLE_ENTITY']],
            [200, ['status' => 'ACTIVE']],
        ]);

        expect(fn () => $adapter->cancelSubscription('I-1'))
            ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'did not cancel');
    });

    test('refunds a capture through PayPal', function (): void {
        $adapter = paypalEmailAdapter();
        $adapter->setDi(paypalDi());
        $adapter->getDi()['http_client'] = paypalRest([
            [200, ['access_token' => 'tok']],
            [201, ['id' => 'R-1']],
        ]);

        expect($adapter->refundPayment('C-1', 25.00, 'USD', ['attempt' => str_repeat('a', 32)]))->toBe('R-1');
    });

    test('reads the PayPal balances', function (): void {
        $adapter = paypalEmailAdapter();
        $adapter->setDi(paypalDi());
        $adapter->getDi()['http_client'] = paypalRest([
            [200, ['access_token' => 'tok']],
            [200, ['balances' => [['currency' => 'USD', 'total_balance' => ['value' => '10.00']]]]],
        ]);

        expect($adapter->balances())->toBe([['currency' => 'USD', 'amount' => 10.0]]);
    });

    test('vaulting needs the setting and the keys', function (): void {
        expect(paypalEmailAdapter()->canVault())->toBeTrue()
            ->and(paypalEmailAdapter(['vault' => '0'])->canVault())->toBeFalse()
            ->and(paypalEmailAdapter(['test_api_client_id' => ''])->canVault())->toBeFalse();
    });
});

describe('vault lifecycle', function (): void {
    test('starts saving an account with a setup token', function (): void {
        $adapter = paypalEmailAdapter();
        $adapter->setDi(paypalDi());
        $adapter->getDi()['http_client'] = paypalRest([
            [200, ['access_token' => 'tok']],
            [201, ['id' => 'ST-1', 'links' => [['rel' => 'approve', 'href' => 'https://paypal.example/approve']]]],
        ]);

        expect($adapter->vaultSetup('https://billing.example/return', 'https://billing.example/cancel'))->toBe(['id' => 'ST-1', 'url' => 'https://paypal.example/approve']);
    });

    test('keeps the approved account as the saved method', function (): void {
        $savedCards = Mockery::mock(Box\Mod\Invoice\ServiceSavedCards::class);
        $savedCards->shouldReceive('saved')->once()->with(6, true, 9)->andReturn(null);
        $savedCards->shouldReceive('remember')->once()->with(6, true, 9, 'CUS-1', 'T-1', 'PayPal buyer@example.com');

        $adapter = paypalEmailAdapter();
        $adapter->setDi(paypalDi(['invoice:savedcards' => $savedCards]));
        $adapter->getDi()['http_client'] = paypalRest([
            [200, ['access_token' => 'tok']],
            [201, ['id' => 'T-1', 'customer' => ['id' => 'CUS-1'], 'payment_source' => ['paypal' => ['email_address' => 'buyer@example.com']]]],
        ]);

        $adapter->vaultSaved(9, 'ST-1');
        expect(true)->toBeTrue();
    });

    test('replaces the previous account and deletes its token', function (): void {
        $savedCards = Mockery::mock(Box\Mod\Invoice\ServiceSavedCards::class);
        $savedCards->shouldReceive('saved')->once()->andReturn(['payment_method' => 'T-old', 'card' => 'x', 'auto_pay_at' => 'x']);
        $savedCards->shouldReceive('remember')->once();

        $adapter = paypalEmailAdapter();
        $adapter->setDi(paypalDi(['invoice:savedcards' => $savedCards]));
        $adapter->getDi()['http_client'] = paypalRest([
            [200, ['access_token' => 'tok']],
            [201, ['id' => 'T-new', 'customer' => ['id' => 'CUS-1'], 'payment_source' => ['paypal' => ['email_address' => 'buyer@example.com']]]],
            [204, []],
        ]);

        $adapter->vaultSaved(9, 'ST-1');
        expect(true)->toBeTrue();
    });

    test('removes the saved account here and at PayPal', function (): void {
        $savedCards = Mockery::mock(Box\Mod\Invoice\ServiceSavedCards::class);
        $savedCards->shouldReceive('saved')->once()->with(6, true, 9)->andReturn(['payment_method' => 'T-1', 'card' => 'PayPal x', 'auto_pay_at' => 'x']);
        $savedCards->shouldReceive('forget')->once()->with(6, true, 'T-1');

        $adapter = paypalEmailAdapter();
        $adapter->setDi(paypalDi(['invoice:savedcards' => $savedCards]));
        $adapter->getDi()['http_client'] = paypalRest([
            [200, ['access_token' => 'tok']],
            [204, []],
        ]);

        $adapter->vaultRemove(9);
        expect(true)->toBeTrue();
    });

    test('charges the saved account and returns the capture id', function (): void {
        $savedCards = Mockery::mock(Box\Mod\Invoice\ServiceSavedCards::class);
        $savedCards->shouldReceive('saved')->once()->with(6, true, 9)->andReturn(['payment_method' => 'T-1', 'card' => 'PayPal x', 'auto_pay_at' => 'x']);

        $invoiceService = Mockery::mock(Box\Mod\Invoice\Service::class);
        $invoiceService->shouldReceive('getBalanceDue')->once()->andReturn(25.00);
        $invoiceService->shouldReceive('toApiArray')->once()->andReturn(['serie_nr' => 'INV-00060', 'currency' => 'USD']);

        $adapter = paypalEmailAdapter();
        $adapter->setDi(paypalDi(['invoice:savedcards' => $savedCards, 'invoice' => $invoiceService]));
        $adapter->getDi()['http_client'] = paypalRest([
            [200, ['access_token' => 'tok']],
            [201, ['purchase_units' => [['payments' => ['captures' => [['id' => 'C-1', 'status' => 'COMPLETED']]]]]]],
        ]);

        $invoice = createEntity(Invoice::class, ['id' => 60, 'client_id' => 9, 'currency' => 'USD']);

        expect($adapter->chargeSavedCard($invoice, 'attempt_2'))->toBe('C-1');
    });

    test('a declined vault charge reports the refusal', function (): void {
        $savedCards = Mockery::mock(Box\Mod\Invoice\ServiceSavedCards::class);
        $savedCards->shouldReceive('saved')->once()->andReturn(['payment_method' => 'T-1', 'card' => 'PayPal x', 'auto_pay_at' => 'x']);

        $invoiceService = Mockery::mock(Box\Mod\Invoice\Service::class);
        $invoiceService->shouldReceive('getBalanceDue')->once()->andReturn(25.00);
        $invoiceService->shouldReceive('toApiArray')->once()->andReturn(['serie_nr' => 'INV-00060', 'currency' => 'USD']);

        $adapter = paypalEmailAdapter();
        $adapter->setDi(paypalDi(['invoice:savedcards' => $savedCards, 'invoice' => $invoiceService]));
        $adapter->getDi()['http_client'] = paypalRest([
            [200, ['access_token' => 'tok']],
            [201, ['purchase_units' => [['payments' => ['captures' => [['id' => 'C-1', 'status' => 'DECLINED']]]]]]],
        ]);

        $invoice = createEntity(Invoice::class, ['id' => 60, 'client_id' => 9, 'currency' => 'USD']);

        expect(fn () => $adapter->chargeSavedCard($invoice, 'attempt_1'))
            ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'refused');
    });
});

describe('REST capture recording', function (): void {
    test('routes a vault return payment through the capture reader', function (): void {
        $api = Mockery::mock();
        $api->shouldReceive('invoice_transaction_update')->andReturnNull();

        $invoice = createEntity(Invoice::class, ['id' => 60, 'client_id' => 9, 'currency' => 'USD', 'issued' => true, 'status' => Invoice::STATUS_UNPAID]);
        $invoiceRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceRepository::class);
        $invoiceRepo->shouldReceive('find')->once()->with(60)->andReturn($invoice);

        $invoiceService = Mockery::mock(Box\Mod\Invoice\Service::class);
        $invoiceService->shouldReceive('settleClearingPayment')->once()->with($invoice, Mockery::type(Transaction::class), Mockery::type(Transaction::class), 'PayPal');

        $txRepo = Mockery::mock(Box\Mod\Invoice\Repository\TransactionRepository::class);
        $txRepo->shouldReceive('findOneBy')->once()->andReturn(null);
        $txRepo->shouldReceive('find')->once()->with(70)->andReturn(createEntity(Transaction::class, ['id' => 70]));

        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('getRepository')->with(Invoice::class)->andReturn($invoiceRepo);
        $em->shouldReceive('getRepository')->with(Transaction::class)->andReturn($txRepo);
        $em->shouldReceive('getReference')->with(PayGateway::class, 6)->andReturn(createEntity(PayGateway::class, ['id' => 6]));
        $em->shouldReceive('getConnection')->andReturn(Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));

        $adapter = paypalEmailAdapter();
        $adapter->setDi(paypalDi(['invoice' => $invoiceService], $em));
        $adapter->getDi()['http_client'] = paypalRest([
            [200, ['access_token' => 'tok']],
            [200, ['id' => 'C-9', 'status' => 'COMPLETED', 'custom_id' => 'fb-invoice-60', 'amount' => ['value' => '25.00', 'currency_code' => 'USD']]],
        ]);

        $adapter->processTransaction($api, 70, ['source' => 'saved_card', 'get' => ['paypal_capture' => 'C-9']], 6);

        expect(true)->toBeTrue();
    });

    test('an incomplete capture waits instead of paying', function (): void {
        $api = Mockery::mock();
        $updates = [];
        $api->shouldReceive('invoice_transaction_update')->twice()->andReturnUsing(function (array $data) use (&$updates): bool {
            $updates[] = $data;

            return true;
        });

        $invoice = createEntity(Invoice::class, ['id' => 60, 'currency' => 'USD']);
        $invoiceRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceRepository::class);
        $invoiceRepo->shouldReceive('find')->once()->with(60)->andReturn($invoice);

        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('getRepository')->with(Invoice::class)->andReturn($invoiceRepo);
        $txRepo = Mockery::mock(Box\Mod\Invoice\Repository\TransactionRepository::class);
        $txRepo->shouldReceive('findOneBy')->once()->andReturn(null);
        $em->shouldReceive('getRepository')->with(Transaction::class)->andReturn($txRepo);
        $em->shouldReceive('getReference')->with(PayGateway::class, 6)->andReturn(createEntity(PayGateway::class, ['id' => 6]));
        $em->shouldReceive('getConnection')->andReturn(Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));

        $adapter = paypalEmailAdapter();
        $adapter->setDi(paypalDi([], $em));
        $adapter->getDi()['http_client'] = paypalRest([
            [200, ['access_token' => 'tok']],
            [200, ['id' => 'C-9', 'status' => 'PENDING', 'custom_id' => 'fb-invoice-60', 'amount' => ['value' => '25.00', 'currency_code' => 'USD']]],
        ]);

        $adapter->processTransaction($api, 70, ['source' => 'saved_card', 'get' => ['paypal_capture' => 'C-9']], 6);

        expect($updates[0]['txn_status'])->toBe('processing')
            ->and($updates[1]['status'])->toBe(Transaction::STATUS_RECEIVED);
    });

    test('a stale pending response cannot recreate a clearing marker after the capture is settled', function (): void {
        $invoice = createEntity(Invoice::class, ['id' => 60, 'currency' => 'USD']);
        $invoiceRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceRepository::class);
        $invoiceRepo->shouldReceive('find')->with(60)->andReturn($invoice);
        $txRepo = Mockery::mock(Box\Mod\Invoice\Repository\TransactionRepository::class);
        $txRepo->shouldReceive('findOneBy')->once()->andReturn(createEntity(Transaction::class, ['id' => 69]));
        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('getRepository')->with(Invoice::class)->andReturn($invoiceRepo);
        $em->shouldReceive('getRepository')->with(Transaction::class)->andReturn($txRepo);
        $em->shouldReceive('getReference')->with(PayGateway::class, 6)->andReturn(createEntity(PayGateway::class, ['id' => 6]));
        $em->shouldReceive('getConnection')->andReturn(Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));
        $updates = [];
        $api = Mockery::mock();
        $api->shouldReceive('invoice_transaction_update')->once()->andReturnUsing(function (array $data) use (&$updates): bool {
            $updates[] = $data;

            return true;
        });
        $adapter = paypalEmailAdapter();
        $adapter->setDi(paypalDi([], $em));
        $adapter->getDi()['http_client'] = paypalRest([
            [200, ['access_token' => 'tok']],
            [200, ['id' => 'C-9', 'status' => 'PENDING', 'custom_id' => 'fb-invoice-60', 'amount' => ['value' => '25.00', 'currency_code' => 'USD']]],
        ]);
        $adapter->processTransaction($api, 70, ['source' => 'saved_card', 'get' => ['paypal_capture' => 'C-9']], 6);
        expect($updates[0]['status'])->toBe(Transaction::STATUS_ERROR)
            ->and($updates[0]['txn_status'])->toBe('Completed');
    });
});

test('PayPal recovery lookup is read only and binds the capture to its invoice', function (string $status, string $expected): void {
    $adapter = paypalEmailAdapter();
    $di = paypalDi();
    $http = paypalRest([
        [200, ['access_token' => 'tok']],
        [200, ['id' => 'cap_recovery', 'custom_id' => 'fb-invoice-21', 'status' => $status, 'amount' => ['value' => '12.50', 'currency_code' => 'USD']]],
    ]);
    $di['http_client'] = $http;
    $adapter->setDi($di);
    $result = $adapter->inspectPayment('cap_recovery', 21);
    expect($result['state'])->toBe($expected)->and($result['amount'])->toBe(12.5);
    $http->shouldHaveReceived('request')->with('GET', Mockery::pattern('#/v2/payments/captures/cap_recovery$#'), Mockery::any())->once();
})->with([['COMPLETED', 'settled'], ['PENDING', 'pending'], ['DECLINED', 'failed'], ['UNRECOGNIZED', 'unknown']]);

test('PayPal recovery refuses a capture for another invoice', function (): void {
    $adapter = paypalEmailAdapter();
    $di = paypalDi();
    $di['http_client'] = paypalRest([[200, ['access_token' => 'tok']], [200, ['id' => 'cap_other', 'custom_id' => 'fb-invoice-22']]]);
    $adapter->setDi($di);
    expect(fn () => $adapter->inspectPayment('cap_other', 21))->toThrow(Payment_Exception::class);
});
