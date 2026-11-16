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
use Box\Mod\Invoice\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;
use function Tests\Helpers\moduleService;

if (!defined('SYSTEM_URL')) {
    define('SYSTEM_URL', 'https://billing.example/');
}

function autopayAdapter(array $config = []): Payment_Adapter_PayPalAutoPay
{
    return new Payment_Adapter_PayPalAutoPay([
        'test_mode' => true,
        'test_api_username' => 'user',
        'test_api_password' => 'pass',
        'test_api_signature' => 'sig',
        'gateway_id' => 5,
        'prefill_email' => '0',
    ] + $config);
}

function autopayNvpResponder(array $bodies): Mockery\MockInterface
{
    $http = Mockery::mock(Symfony\Contracts\HttpClient\HttpClientInterface::class);
    $http->shouldReceive('request')->andReturnUsing(function (string $method, string $url, array $options = []) use (&$bodies): object {
        $next = array_shift($bodies);
        $response = Mockery::mock(Symfony\Contracts\HttpClient\ResponseInterface::class);
        if ($next instanceof Throwable) {
            $response->shouldReceive('getContent')->andThrow($next);
        } else {
            $response->shouldReceive('getContent')->andReturn($next);
        }

        return $response;
    });

    return $http;
}

function autopayDi(array $services = [], ?EntityManagerInterface $em = null): Pimple\Container
{
    $di = container();
    $di['em'] = $em ?? Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $di['mod_service'] = $di->protect(moduleService($services));

    return $di;
}

describe('configuration', function (): void {
    test('requires API details for its mode', function (): void {
        expect(fn () => new Payment_Adapter_PayPalAutoPay(['test_mode' => true, 'gateway_id' => 5]))
            ->toThrow(Payment_Exception::class, 'not fully configured');
    });

    test('exposes its identity and options', function (): void {
        $adapter = autopayAdapter(['pay_saved' => '0']);

        expect($adapter->gatewayId())->toBe(5)
            ->and($adapter->testMode())->toBeTrue()
            ->and($adapter->hasApiKeys())->toBeTrue()
            ->and($adapter->option('pay_saved'))->toBeFalse()
            ->and($adapter->option('pay_once'))->toBeTrue()
            ->and($adapter->option('missing_key', false))->toBeFalse();
    });

    test('connection test validates the currency and the PayPal account', function (): void {
        $currency = Mockery::mock(Box\Mod\Currency\Service::class);
        $currency->shouldReceive('getBaseCurrencyRate')->once()->with('EUR')->andReturn(1.0);

        $adapter = autopayAdapter(['convert_to' => 'EUR']);
        $adapter->setDi(autopayDi(['currency' => $currency, 'invoice:savedcards' => Mockery::mock(Box\Mod\Invoice\ServiceSavedCards::class)->shouldIgnoreMissing()], null));
        $adapter->getDi()['http_client'] = autopayNvpResponder(['ACK=Success&VERSION=119.0']);

        // No exception: the details work and the account may make agreements.
        $adapter->testConnection();
        expect(true)->toBeTrue();
    });

    test('connection test refuses an unknown convert-to currency', function (): void {
        $currency = Mockery::mock(Box\Mod\Currency\Service::class);
        $currency->shouldReceive('getBaseCurrencyRate')->once()->andThrow(new FOSSBilling\Core\Exception\BaseException('unknown'));

        $adapter = autopayAdapter(['convert_to' => 'XX']);
        $adapter->setDi(autopayDi(['currency' => $currency]));

        expect(fn () => $adapter->testConnection())
            ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'not one of the currencies');
    });

    test('connection test refuses an account without reference transactions', function (): void {
        $adapter = autopayAdapter();
        $adapter->setDi(autopayDi());
        $adapter->getDi()['http_client'] = autopayNvpResponder(['ACK=Failure&L_ERRORCODE0=11452&L_LONGMESSAGE0=Reference Transactions not enabled']);

        expect(fn () => $adapter->testConnection())
            ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Reference Transactions');
    });
});

describe('saved agreements', function (): void {
    test('reads the saved agreement through the saved-cards service', function (): void {
        $saved = ['payment_method' => 'B-123', 'card' => 'PayPal buyer@example.com', 'auto_pay_at' => '2026-01-05 10:00:00'];
        $savedCards = Mockery::mock(Box\Mod\Invoice\ServiceSavedCards::class);
        $savedCards->shouldReceive('saved')->once()->with(5, true, 9)->andReturn($saved);

        $adapter = autopayAdapter();
        $adapter->setDi(autopayDi(['invoice:savedcards' => $savedCards]));

        expect($adapter->savedCard(9))->toBe($saved);
    });

    test('charging without an agreement refuses', function (): void {
        $savedCards = Mockery::mock(Box\Mod\Invoice\ServiceSavedCards::class);
        $savedCards->shouldReceive('saved')->once()->andReturn(null);

        $adapter = autopayAdapter();
        $adapter->setDi(autopayDi(['invoice:savedcards' => $savedCards]));

        $invoice = createEntity(Invoice::class, ['id' => 60, 'client_id' => 9, 'currency' => 'USD']);

        expect(fn () => $adapter->chargeSavedCard($invoice, 'attempt_1'))
            ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'no PayPal billing agreement');
    });

    test('charging the agreement returns the transaction id', function (): void {
        $savedCards = Mockery::mock(Box\Mod\Invoice\ServiceSavedCards::class);
        $savedCards->shouldReceive('saved')->once()->andReturn(['payment_method' => 'B-123', 'card' => 'PayPal buyer@example.com', 'auto_pay_at' => 'x']);

        $invoiceService = Mockery::mock(Box\Mod\Invoice\Service::class);
        $invoiceService->shouldReceive('getBalanceDue')->once()->with(Mockery::type(Invoice::class))->andReturn(25.00);

        $adapter = autopayAdapter();
        $adapter->setDi(autopayDi(['invoice:savedcards' => $savedCards, 'invoice' => $invoiceService, 'system' => Mockery::mock()->shouldIgnoreMissing()]));
        $adapter->getDi()['http_client'] = autopayNvpResponder(['ACK=Success&TRANSACTIONID=P-1']);

        $invoice = createEntity(Invoice::class, ['id' => 60, 'client_id' => 9, 'currency' => 'USD', 'serie' => 'INV-', 'nr' => '00060']);

        expect($adapter->chargeSavedCard($invoice, str_repeat('a', 32)))->toBe('P-1');
        $adapter->getDi()['http_client']->shouldHaveReceived('request')->with('POST', Mockery::any(), Mockery::on(function (array $options): bool {
            expect(strlen($options['body']['MSGSUBID']))->toBe(38);

            return true;
        }))->once();
    });

    test('a cancelled agreement is forgotten so it is never tried again', function (): void {
        $savedCards = Mockery::mock(Box\Mod\Invoice\ServiceSavedCards::class);
        $savedCards->shouldReceive('saved')->once()->andReturn(['payment_method' => 'B-gone', 'card' => 'PayPal x@y.zz', 'auto_pay_at' => 'x']);

        $invoiceService = Mockery::mock(Box\Mod\Invoice\Service::class);
        $invoiceService->shouldReceive('getBalanceDue')->once()->andReturn(25.00);

        $savedCards->shouldReceive('forget')->once()->with(5, true, 'B-gone');

        $adapter = autopayAdapter();
        $adapter->setDi(autopayDi(['invoice:savedcards' => $savedCards, 'invoice' => $invoiceService, 'system' => Mockery::mock()->shouldIgnoreMissing()]));
        $adapter->getDi()['http_client'] = autopayNvpResponder(['ACK=Failure&L_ERRORCODE0=10201&L_LONGMESSAGE0=Billing agreement cancelled']);

        $invoice = createEntity(Invoice::class, ['id' => 60, 'client_id' => 9, 'currency' => 'USD', 'serie' => 'INV-', 'nr' => '00060']);

        expect(fn () => $adapter->chargeSavedCard($invoice, 'attempt_1'))
            ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'refused');
    });

    test('removing the agreement cancels it at PayPal and forgets it', function (): void {
        $savedCards = Mockery::mock(Box\Mod\Invoice\ServiceSavedCards::class);
        $savedCards->shouldReceive('saved')->once()->with(5, true, 9)->andReturn(['payment_method' => 'B-1', 'card' => 'PayPal x@y.zz', 'auto_pay_at' => 'x']);

        $savedCards->shouldReceive('forget')->once()->with(5, true, 'B-1');

        $adapter = autopayAdapter();
        $adapter->setDi(autopayDi(['invoice:savedcards' => $savedCards]));
        $adapter->getDi()['http_client'] = autopayNvpResponder(['ACK=Success']);

        $adapter->agreementRemove(9);
        expect(true)->toBeTrue();
    });

    test('refunds a payment through PayPal', function (): void {
        $adapter = autopayAdapter();
        $adapter->setDi(autopayDi());
        $adapter->getDi()['http_client'] = autopayNvpResponder([
            'ACK=Success&CURRENCYCODE=USD&AMT=25.00',
            'ACK=Success&REFUNDTRANSACTIONID=R-1',
        ]);

        expect($adapter->refundPayment('P-1', 25.00, 'USD'))->toBe('R-1');
    });

    test('reads the account balances', function (): void {
        $adapter = autopayAdapter();
        $adapter->setDi(autopayDi());
        $adapter->getDi()['http_client'] = autopayNvpResponder(['ACK=Success&L_AMT0=10.00&L_CURRENCYCODE0=USD&L_AMT1=5.00&L_CURRENCYCODE1=EUR']);

        expect($adapter->balances())->toBe([
            ['currency' => 'USD', 'amount' => 10.0],
            ['currency' => 'EUR', 'amount' => 5.0],
        ]);
    });
});

describe('checkout and agreement setup', function (): void {
    test('starts paying with an agreement', function (): void {
        $invoiceService = Mockery::mock(Box\Mod\Invoice\Service::class);
        $invoiceService->shouldReceive('getBalanceDue')->once()->andReturn(25.00);

        $adapter = autopayAdapter();
        $adapter->setDi(autopayDi(['invoice' => $invoiceService, 'system' => Mockery::mock()->shouldIgnoreMissing()]));
        $adapter->getDi()['http_client'] = autopayNvpResponder(['ACK=Success&TOKEN=EC-1']);

        $invoice = createEntity(Invoice::class, ['id' => 60, 'client_id' => 9, 'currency' => 'USD', 'serie' => 'INV-', 'nr' => '00060', 'buyer_email' => 'buyer@example.com']);

        $result = $adapter->checkout($invoice, true, 'https://billing.example/return', 'https://billing.example/cancel');

        expect($result['token'])->toBe('EC-1')
            ->and($result['url'])->toContain('EC-1');
    });

    test('takes the approved payment and keeps its agreement', function (): void {
        $savedCards = Mockery::mock(Box\Mod\Invoice\ServiceSavedCards::class);
        $savedCards->shouldReceive('saved')->once()->andReturn(null);
        $savedCards->shouldReceive('remember')->once()->with(5, true, 9, '', 'B-new', 'PayPal buyer@example.com');

        $adapter = autopayAdapter();
        $adapter->setDi(autopayDi(['invoice:savedcards' => $savedCards], $em));
        $adapter->getDi()['http_client'] = autopayNvpResponder([
            'ACK=Success&PAYMENTREQUEST_0_CUSTOM=fb-invoice-60&PAYERID=PAYER1&PAYMENTREQUEST_0_AMT=25.00&PAYMENTREQUEST_0_CURRENCYCODE=USD&EMAIL=buyer@example.com',
            'ACK=Success&PAYMENTINFO_0_TRANSACTIONID=P-2&BILLINGAGREEMENTID=B-new',
        ]);

        $invoice = createEntity(Invoice::class, ['id' => 60, 'client_id' => 9, 'currency' => 'USD']);

        expect($adapter->checkoutPaid($invoice, 'EC-1'))->toBe('P-2');
    });

    test('keeps a setup-only agreement as the saved method', function (): void {
        $savedCards = Mockery::mock(Box\Mod\Invoice\ServiceSavedCards::class);
        $savedCards->shouldReceive('saved')->once()->andReturn(null);
        $savedCards->shouldReceive('remember')->once()->with(5, true, 9, '', 'B-setup', 'PayPal buyer@example.com');

        $adapter = autopayAdapter();
        $adapter->setDi(autopayDi(['invoice:savedcards' => $savedCards], $em));
        $adapter->getDi()['http_client'] = autopayNvpResponder([
            'ACK=Success&PAYMENTREQUEST_0_CUSTOM=fb-client-9&EMAIL=buyer@example.com',
            'ACK=Success&BILLINGAGREEMENTID=B-setup',
        ]);

        $adapter->agreementSaved(9, 'EC-2');
        expect(true)->toBeTrue();
    });
});

describe('payment notices', function (): void {
    test('records a completed payment read back from PayPal', function (): void {
        $invoice = createEntity(Invoice::class, ['id' => 60, 'client_id' => 9, 'currency' => 'USD', 'issued' => true, 'status' => Invoice::STATUS_UNPAID]);

        $invoiceService = Mockery::mock(Box\Mod\Invoice\Service::class);
        $invoiceService->shouldReceive('getBalanceDue')->once()->with($invoice)->andReturn(25.00);
        $invoiceService->shouldReceive('validatePaymentAmount')->once()->with(25.00, 25.00);
        $invoiceService->shouldReceive('isInvoiceTypeDeposit')->once()->with($invoice)->andReturn(false);
        $invoiceService->shouldReceive('issueInvoice')->never();
        $invoiceService->shouldReceive('markAsPaid')->never();

        $api = Mockery::mock();
        $api->shouldReceive('invoice_transaction_update')->andReturnNull();
        $api->shouldReceive('client_balance_add_funds')->once()->with([
            'id' => 9, 'amount' => 25.0, 'description' => 'PayPal transaction P-9', 'type' => 'PayPal', 'rel_id' => 'P-9',
        ]);
        $api->shouldReceive('invoice_pay_with_credits')->once()->with(['id' => 60]);

        $txRepo = Mockery::mock(TransactionRepository::class);
        $txRepo->shouldReceive('findBy')->andReturn([]);

        $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
        $connection->shouldReceive('getDatabasePlatform')->andReturn(new Doctrine\DBAL\Platforms\MySQLPlatform());
        $connection->shouldReceive('quoteSingleIdentifier')->with('transaction')->andReturn('"transaction"');
        $connection->shouldReceive('fetchOne')->andReturn(1, false);
        $connection->shouldReceive('executeStatement')->andReturn(1);

        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('getConnection')->andReturn($connection);
        $em->shouldReceive('getRepository')->with(Invoice::class)->andReturn($invoiceRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceRepository::class));
        $invoiceRepo->shouldReceive('find')->once()->with(60)->andReturn($invoice);

        $adapter = autopayAdapter();
        $adapter->setDi(autopayDi(['invoice' => $invoiceService], $em));
        $adapter->getDi()['http_client'] = autopayNvpResponder([
            'ACK=Success&AMT=25.00&CURRENCYCODE=USD&PAYMENTSTATUS=Completed&CUSTOM=fb-invoice-60',
        ]);

        $adapter->processTransaction($api, 70, ['source' => 'saved_card', 'get' => ['paypal_txn' => 'P-9']], 5);

        expect(true)->toBeTrue();
    });

    test('a cancelled agreement is removed without touching invoices', function (): void {
        $savedCards = Mockery::mock(Box\Mod\Invoice\ServiceSavedCards::class);
        $savedCards->shouldReceive('forget')->once()->with(5, true, 'B-1');

        $api = Mockery::mock();
        $api->shouldReceive('invoice_transaction_update')->once();

        $adapter = autopayAdapter();
        $adapter->setDi(autopayDi(['invoice:savedcards' => $savedCards]));

        $adapter->processTransaction($api, 70, [
            'source' => 'ipn',
            'http_raw_post_data' => 'txn_type=mp_cancel&mp_id=B-1',
            'post' => ['txn_type' => 'mp_cancel', 'mp_id' => 'B-1'],
        ], 5);
        expect(true)->toBeTrue();
    });

    test('a reversal of an unknown payment is left for staff', function (): void {
        $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
        $connection->shouldReceive('quoteSingleIdentifier')->with('transaction')->andReturn('"transaction"');
        $connection->shouldReceive('fetchAssociative')->once()->andReturn(false);

        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('getConnection')->andReturn($connection);

        $api = Mockery::mock();
        $api->shouldReceive('invoice_transaction_update')->once();

        $adapter = autopayAdapter();
        $adapter->setDi(autopayDi([], $em));

        $adapter->processTransaction($api, 70, [
            'source' => 'ipn',
            'http_raw_post_data' => 'payment_status=Reversed&parent_txn_id=P-old&txn_id=R-1&mc_gross=25.00&mc_currency=USD',
            'post' => ['payment_status' => 'Reversed', 'parent_txn_id' => 'P-old', 'txn_id' => 'R-1', 'mc_gross' => '25.00', 'mc_currency' => 'USD'],
        ], 5);
        expect(true)->toBeTrue();
    });

    test('a partial reversal books a custom-line credit note for the exact amount', function (): void {
        $invoice = createEntity(Invoice::class, ['id' => 60, 'client_id' => 9, 'currency' => 'USD', 'issued' => true, 'status' => Invoice::STATUS_PAID]);

        $invoiceService = Mockery::mock(Box\Mod\Invoice\Service::class);
        $invoiceService->shouldReceive('getTotalWithTax')->once()->with($invoice)->andReturn(100.00);
        $invoiceService->shouldReceive('refundInvoice')->once()->with(
            $invoice,
            'PayPal chargeback R-1 of payment P-9.',
            null,
            ['gateway_refund' => false, 'gateway_returned' => true, 'custom_line' => ['title' => 'Partial chargeback R-1 of payment P-9.', 'amount' => 25.0]]
        )->andReturn(101);
        $invoiceService->shouldReceive('addNote')->once()->with($invoice, Mockery::pattern('/custom-line/'));

        $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
        $connection->shouldReceive('quoteSingleIdentifier')->with('transaction')->andReturn('"transaction"');
        $connection->shouldReceive('fetchAssociative')->once()->andReturn(['invoice_id' => 60, 'amount' => 100.0]);

        $invoiceRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceRepository::class);
        $invoiceRepo->shouldReceive('find')->once()->with(60)->andReturn($invoice);
        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('getConnection')->andReturn($connection);
        $em->shouldReceive('getRepository')->with(Invoice::class)->andReturn($invoiceRepo);

        $captured = null;
        $api = Mockery::mock();
        $api->shouldReceive('invoice_transaction_update')->once()->andReturnUsing(function (array $data) use (&$captured): void {
            $captured = $data;
        });

        $adapter = autopayAdapter();
        $adapter->setDi(autopayDi(['invoice' => $invoiceService], $em));

        $adapter->processTransaction($api, 70, [
            'source' => 'ipn',
            'http_raw_post_data' => 'payment_status=Reversed&parent_txn_id=P-9&txn_id=R-1&mc_gross=25.00&mc_currency=USD',
            'post' => ['payment_status' => 'Reversed', 'parent_txn_id' => 'P-9', 'txn_id' => 'R-1', 'mc_gross' => '25.00', 'mc_currency' => 'USD'],
        ], 5);
        expect($captured['note'] ?? '')->toContain('custom-line');
    });

    test('a failed partial reversal falls back to staff settlement', function (): void {
        $invoice = createEntity(Invoice::class, ['id' => 60, 'client_id' => 9, 'currency' => 'USD', 'issued' => true, 'status' => Invoice::STATUS_PAID]);

        $invoiceService = Mockery::mock(Box\Mod\Invoice\Service::class);
        $invoiceService->shouldReceive('getTotalWithTax')->once()->with($invoice)->andReturn(100.00);
        $invoiceService->shouldReceive('refundInvoice')->once()->andThrow(new FOSSBilling\Core\Exception\InformationException('No remaining refundable amount on this invoice'));
        $invoiceService->shouldReceive('addNote')->once()->with($invoice, 'PayPal chargeback R-1 of payment P-9. No remaining refundable amount on this invoice');

        $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
        $connection->shouldReceive('quoteSingleIdentifier')->with('transaction')->andReturn('"transaction"');
        $connection->shouldReceive('fetchAssociative')->once()->andReturn(['invoice_id' => 60, 'amount' => 100.0]);

        $invoiceRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceRepository::class);
        $invoiceRepo->shouldReceive('find')->once()->with(60)->andReturn($invoice);
        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('getConnection')->andReturn($connection);
        $em->shouldReceive('getRepository')->with(Invoice::class)->andReturn($invoiceRepo);

        $captured = null;
        $api = Mockery::mock();
        $api->shouldReceive('invoice_transaction_update')->once()->andReturnUsing(function (array $data) use (&$captured): void {
            $captured = $data;
        });

        $adapter = autopayAdapter();
        $adapter->setDi(autopayDi(['invoice' => $invoiceService], $em));

        $adapter->processTransaction($api, 70, [
            'source' => 'ipn',
            'http_raw_post_data' => 'payment_status=Reversed&parent_txn_id=P-9&txn_id=R-1&mc_gross=25.00&mc_currency=USD',
            'post' => ['payment_status' => 'Reversed', 'parent_txn_id' => 'P-9', 'txn_id' => 'R-1', 'mc_gross' => '25.00', 'mc_currency' => 'USD'],
        ], 5);
        expect($captured['note'] ?? '')->toContain('Not refunded here');
    });

    test('a full reversal refunds the invoice and moves renewals back', function (): void {
        $invoice = createEntity(Invoice::class, ['id' => 60, 'client_id' => 9, 'currency' => 'USD', 'issued' => true, 'status' => Invoice::STATUS_PAID]);
        $text = 'PayPal chargeback R-1 of payment P-9.';

        $invoiceService = Mockery::mock(Box\Mod\Invoice\Service::class);
        $invoiceService->shouldReceive('getTotalWithTax')->once()->with($invoice)->andReturn(100.00);
        $invoiceService->shouldReceive('refundInvoice')->once()->with($invoice, $text, null, ['gateway_refund' => false, 'gateway_returned' => true])->andReturn(101);
        $invoiceService->shouldReceive('addNote')->once()->with($invoice, $text);

        $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
        $connection->shouldReceive('quoteSingleIdentifier')->with('transaction')->andReturn('"transaction"');
        $connection->shouldReceive('fetchAssociative')->once()->andReturn(['invoice_id' => 60, 'amount' => 100.0]);
        $connection->shouldReceive('fetchAllAssociative')->once()->andReturn([]);

        $invoiceRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceRepository::class);
        $invoiceRepo->shouldReceive('find')->once()->with(60)->andReturn($invoice);
        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('getConnection')->andReturn($connection);
        $em->shouldReceive('getRepository')->with(Invoice::class)->andReturn($invoiceRepo);

        $captured = null;
        $api = Mockery::mock();
        $api->shouldReceive('invoice_transaction_update')->once()->andReturnUsing(function (array $data) use (&$captured): void {
            $captured = $data;
        });

        $adapter = autopayAdapter();
        $adapter->setDi(autopayDi(['invoice' => $invoiceService], $em));

        $adapter->processTransaction($api, 70, [
            'source' => 'ipn',
            'http_raw_post_data' => 'payment_status=Reversed&parent_txn_id=P-9&txn_id=R-1&mc_gross=100.00&mc_currency=USD',
            'post' => ['payment_status' => 'Reversed', 'parent_txn_id' => 'P-9', 'txn_id' => 'R-1', 'mc_gross' => '100.00', 'mc_currency' => 'USD'],
        ], 5);
        expect($captured['note'] ?? '')->toBe($text);
    });

    test('a notice that is not genuine is refused', function (): void {
        $adapter = autopayAdapter();
        $adapter->setDi(autopayDi());
        $adapter->getDi()['http_client'] = autopayNvpResponder(['INVALID']);

        expect(fn () => withAppEnv('prod', fn () => $adapter->processTransaction(Mockery::mock(), 70, ['source' => 'ipn', 'post' => ['txn_id' => 'x'], 'http_raw_post_data' => 'txn_id=x'], 5)))
            ->toThrow(Payment_Exception::class, 'not genuine');
    });
});

describe('converted amounts without a local record', function (): void {
    test('a refund without a recorded payment goes back to the dashboard', function (): void {
        $adapter = autopayAdapter();
        $adapter->setDi(autopayDi());
        $adapter->getDi()['http_client'] = autopayNvpResponder([
            'ACK=Success&CURRENCYCODE=EUR&AMT=25.00',
        ]);
        $adapter->getDi()['em']->shouldReceive('getConnection')->andReturn($connection = Mockery::mock(Doctrine\DBAL\Connection::class));
        $connection->shouldReceive('quoteSingleIdentifier')->with('transaction')->andReturn('"transaction"');
        $connection->shouldReceive('fetchOne')->once()->andReturn(false);

        expect(fn () => $adapter->refundPayment('P-9', 20.00, 'USD'))
            ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'dashboard');
    });
});

test('a reversal with an unknown amount is left for staff', function (): void {
    $invoice = createEntity(Invoice::class, ['id' => 60, 'client_id' => 9, 'currency' => 'USD']);
    $invoiceRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceRepository::class);
    $invoiceRepo->shouldReceive('find')->once()->with(60)->andReturn($invoice);

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('quoteSingleIdentifier')->with('transaction')->andReturn('"transaction"');
    $connection->shouldReceive('fetchAssociative')->once()->andReturn(['invoice_id' => 60, 'amount' => '25.00']);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);
    $em->shouldReceive('getRepository')->with(Invoice::class)->andReturn($invoiceRepo);

    $api = Mockery::mock();
    $api->shouldReceive('invoice_transaction_update')->once()->with(Mockery::on(function (array $data): bool {
        return str_contains((string) ($data['note'] ?? ''), 'unknown, settle it by hand');
    }));

    $adapter = autopayAdapter();
    $adapter->setDi(autopayDi([], $em));
    $adapter->getDi()['http_client'] = autopayNvpResponder([
        'ACK=Success',
    ]);

    $adapter->processTransaction($api, 70, [
        'source' => 'ipn',
        'http_raw_post_data' => 'payment_status=Reversed&parent_txn_id=P-old&txn_id=R-1&mc_currency=EUR',
        'post' => ['payment_status' => 'Reversed', 'parent_txn_id' => 'P-old', 'txn_id' => 'R-1', 'mc_currency' => 'EUR'],
    ], 5);
    expect(true)->toBeTrue();
});

test('legacy PayPal recovery reads transaction details and rejects another invoice', function (string $custom, string $status, string $expected): void {
    $adapter = autopayAdapter();
    $di = autopayDi();
    $di['http_client'] = autopayNvpResponder(['ACK=Success&TRANSACTIONID=P-1&CUSTOM=' . $custom . '&PAYMENTSTATUS=' . $status . '&AMT=25.00&CURRENCYCODE=USD']);
    $adapter->setDi($di);
    if ($expected === 'reject') {
        expect(fn () => $adapter->inspectPayment('P-1', 21))->toThrow(Payment_Exception::class);
    } else {
        expect($adapter->inspectPayment('P-1', 21)['state'])->toBe($expected);
    }
})->with([['fb-invoice-21', 'Completed', 'settled'], ['fb-invoice-21', 'Pending', 'pending'], ['fb-invoice-21', 'Denied', 'failed'], ['fb-invoice-22', 'Completed', 'reject']]);
