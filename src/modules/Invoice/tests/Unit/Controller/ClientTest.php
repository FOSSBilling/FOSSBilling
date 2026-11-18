<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\Controller\Client;
use Symfony\Component\HttpFoundation\RedirectResponse;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

test('invoice browser controller redirects denied invoice access to invoice list', function (): void {
    $api = new class {
        public function invoice_get(array $data): array
        {
            throw new FOSSBilling\Core\Exception\InformationException('You do not have permission to perform this action', [], 403);
        }
    };

    $app = Mockery::mock(FOSSBilling\Core\Http\App::class);
    $app->shouldReceive('redirect')
        ->once()
        ->with('invoice')
        ->andReturn(new RedirectResponse('/invoice'));

    $di = container();
    $di['api_guest'] = $api;

    $controller = new Client();
    $controller->setDi($di);

    $response = $controller->get_invoice($app, str_repeat('a', 32));

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->headers->get('Location'))->toBe('/invoice');
});

test('invoice print and thank-you pages render an accessible invoice', function (): void {
    $api = new class {
        public function invoice_get(array $data): array
        {
            return ['id' => 7, 'hash' => $data['hash']];
        }
    };

    $app = Mockery::mock(FOSSBilling\Core\Http\App::class);
    $app->shouldReceive('render')
        ->once()
        ->with('mod_invoice_print', ['invoice' => ['id' => 7, 'hash' => 'abc']])
        ->andReturn('print-html');
    $app->shouldReceive('render')
        ->once()
        ->with('mod_invoice_thankyou', ['invoice' => ['id' => 7, 'hash' => 'abc']])
        ->andReturn('thankyou-html');

    $di = container();
    $di['api_guest'] = $api;

    $controller = new Client();
    $controller->setDi($di);

    expect($controller->get_invoice_print($app, 'abc'))->toBe('print-html')
        ->and($controller->get_thankyoupage($app, 'abc'))->toBe('thankyou-html');
});

test('banklink renders the payment form for a payable invoice', function (): void {
    $api = new class {
        public function invoice_get(array $data): array
        {
            return ['id' => 7];
        }

        public function invoice_payment(array $data): array
        {
            return ['type' => 'html', 'result' => '<form></form>'];
        }
    };

    $request = new Symfony\Component\HttpFoundation\Request(['allow_subscription' => '1']);

    $app = Mockery::mock(FOSSBilling\Core\Http\App::class);
    $app->shouldReceive('getRequest')->andReturn($request);
    $app->shouldReceive('render')
        ->once()
        ->with('mod_invoice_banklink', Mockery::type('array'))
        ->andReturn('banklink-html');

    $di = container();
    $di['api_guest'] = $api;

    $controller = new Client();
    $controller->setDi($di);

    expect($controller->get_banklink($app, 'abc', 2))->toBe('banklink-html');
});

test('banklink redirects when payment is denied and rethrows other failures', function (): void {
    $denied = new class {
        public function invoice_get(array $data): array
        {
            return ['id' => 7];
        }

        public function invoice_payment(array $data): array
        {
            throw new FOSSBilling\Core\Exception\InformationException('denied', [], 403);
        }
    };

    $request = new Symfony\Component\HttpFoundation\Request(['allow_subscription' => '1']);

    $app = Mockery::mock(FOSSBilling\Core\Http\App::class);
    $app->shouldReceive('getRequest')->andReturn($request);
    $app->shouldReceive('redirect')->once()->with('invoice')->andReturn(new RedirectResponse('/invoice'));

    $di = container();
    $di['api_guest'] = $denied;

    $controller = new Client();
    $controller->setDi($di);

    expect($controller->get_banklink($app, 'abc', 2))->toBeInstanceOf(RedirectResponse::class);

    // A non-access failure (e.g. the already-paid guard) is not a redirect:
    // only code 403 means "not yours".
    $alreadyPaid = new class {
        public function invoice_get(array $data): array
        {
            return ['id' => 7];
        }

        public function invoice_payment(array $data): array
        {
            throw new FOSSBilling\Core\Exception\InformationException('This invoice is already paid', [], 400);
        }
    };

    $di['api_guest'] = $alreadyPaid;

    expect(fn () => $controller->get_banklink($app, 'abc', 2))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'already paid');
});

test('pdf download returns the response, redirects on denial, and rejects garbage', function (): void {
    $ok = new class {
        public function invoice_pdf(array $data): Symfony\Component\HttpFoundation\Response
        {
            return new Symfony\Component\HttpFoundation\Response('pdf-bytes');
        }
    };

    $app = Mockery::mock(FOSSBilling\Core\Http\App::class);

    $di = container();
    $di['api_guest'] = $ok;

    $controller = new Client();
    $controller->setDi($di);

    expect($controller->get_pdf($app, 'abc')->getContent())->toBe('pdf-bytes');

    $denied = new class {
        public function invoice_pdf(array $data): Symfony\Component\HttpFoundation\Response
        {
            throw new FOSSBilling\Core\Exception\InformationException('denied', [], 403);
        }
    };

    $app->shouldReceive('redirect')->once()->with('invoice')->andReturn(new RedirectResponse('/invoice'));
    $di['api_guest'] = $denied;

    expect($controller->get_pdf($app, 'abc'))->toBeInstanceOf(RedirectResponse::class);
});

require_once dirname(__DIR__, 2) . '/Fixtures/GatewayReceiptContext.php';

test('invoice rendering retains mandatory fees when the public quote budget is exhausted', function (): void {
    [, $em, $client, $gateway] = receiptTestContext();
    $gateway->setEnabled(true)->setAllowSingle(true)->setAcceptedCurrencies(['USD'])->setConfig(['fee' => ['percent' => 2.5]]);
    $invoice = (new Box\Mod\Invoice\Entity\Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus('unpaid')->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new Box\Mod\Invoice\Entity\InvoiceItem())->setInvoice($invoice)->setType('custom')->setTask('void')->setPrice(100)->setQuantity(1));
    $em->flush();
    $billing = gatewayDepositTestService($em);
    $di = $billing->getDi();
    $gateways = new Box\Mod\Invoice\ServicePayGateway();
    $gateways->setDi($di);
    $subscriptions = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptions->shouldReceive('isSubscribable')->andReturn(false);
    $services = $di['mod_service'];
    unset($di['mod_service']);
    $di['mod_service'] = $di->protect(fn (string $module, string $sub = ''): object => match ($sub) {
        'PayGateway' => $gateways,
        'Subscription' => $subscriptions,
        default => $services($module, $sub),
    });
    $di['rate_limit_cache'] = new Symfony\Component\Cache\Adapter\ArrayAdapter();
    $di['request'] = new Symfony\Component\HttpFoundation\Request(server: ['REMOTE_ADDR' => '192.0.2.55']);
    $limiter = new class extends FOSSBilling\Core\Security\RateLimiter {
        protected function getConfig(): array
        {
            return self::getDefaultConfig();
        }
    };
    $limiter->setDi($di);
    $di['rate_limiter'] = $limiter;
    for ($i = 0; $i < 10; ++$i) {
        $limiter->consumeOrThrow('invoice_gateway_fees_ip', '192.0.2.55');
    }
    $endpoint = new Box\Mod\Invoice\Api\Guest();
    $endpoint->setDi($di);
    $endpoint->setIp('192.0.2.55');
    expect(fn () => $endpoint->gateway_fees(['hash' => str_repeat('a', 32)]))->toThrow(FOSSBilling\Core\Security\RateLimitException::class);
    $data = ['id' => $invoice->getId(), 'hash' => str_repeat('a', 32), 'status' => 'unpaid'];
    $api = new readonly class($data, $endpoint) {
        public function __construct(private array $data, private Box\Mod\Invoice\Api\Guest $endpoint)
        {
        }

        public function invoice_get(array $data): array
        {
            return $this->data;
        }

        public function invoice_gateway_fees(array $data): array
        {
            return $this->endpoint->gateway_fees($data);
        }
    };
    $di['api_guest'] = $api;
    $app = Mockery::mock(FOSSBilling\Core\Http\App::class);
    $app->shouldReceive('render')->once()->with('mod_invoice_invoice', ['invoice' => $data, 'gateway_fees' => [$gateway->getId() => ['fee' => 2.5, 'total' => 102.5]]])->andReturn('invoice with actual fees');
    $controller = new Client();
    $controller->setDi($di);
    expect($controller->get_invoice($app, $data['hash']))->toBe('invoice with actual fees');
    // Internal display calculation leaves the public endpoint's limits intact.
    expect(fn () => $endpoint->gateway_fees(['hash' => $data['hash']]))->toThrow(FOSSBilling\Core\Security\RateLimitException::class);
});

test('invoice controller preserves readable invoices when display fee calculation fails', function (Throwable $failure): void {
    $data = ['id' => 7, 'hash' => 'example', 'status' => 'unpaid'];
    $api = new readonly class($data) {
        public function __construct(private array $data)
        {
        }

        public function invoice_get(array $data): array
        {
            return $this->data;
        }
    };
    $di = container();
    $di['api_guest'] = $api;
    $di['em']->shouldReceive('find')->with(Box\Mod\Invoice\Entity\Invoice::class, 7)->andReturn(new Box\Mod\Invoice\Entity\Invoice());
    $fees = Mockery::mock(Box\Mod\Invoice\ServiceGatewayFees::class);
    $fees->shouldReceive('quote')->once()->andThrow($failure);
    $di['mod_service'] = $di->protect(fn (): object => $fees);
    $app = Mockery::mock(FOSSBilling\Core\Http\App::class);
    $app->shouldReceive('render')->once()->with('mod_invoice_invoice', ['invoice' => $data, 'gateway_fees' => null])->andReturn('readable invoice');
    $controller = new Client();
    $controller->setDi($di);
    expect($controller->get_invoice($app, 'example'))->toBe('readable invoice');
})->with([fn () => new RuntimeException('quote failed'), fn () => new Error('quote interrupted')]);

test('payment methods page renders the saved cards', function (): void {
    $api = new class {
        public function invoice_card_list(): array
        {
            return ['available' => true, 'bank' => false, 'cards' => [], 'paypal' => null, 'autopay' => null];
        }
    };

    $app = Mockery::mock(FOSSBilling\Core\Http\App::class);
    $app->shouldReceive('render')
        ->once()
        ->with('mod_invoice_payment_methods', ['cards' => ['available' => true, 'bank' => false, 'cards' => [], 'paypal' => null, 'autopay' => null]])
        ->andReturn('rendered');

    $di = container();
    $di['api_client'] = $api;
    $di['is_client_logged'] = true;

    $controller = new Client();
    $controller->setDi($di);

    expect($controller->get_payment_methods($app))->toBe('rendered');
});

test('invoice autopay page starts paying with a billing agreement', function (bool $once): void {
    $invoice = createEntity(Box\Mod\Invoice\Entity\Invoice::class, ['id' => 60, 'client_id' => 7, 'hash' => str_repeat('a', 32)]);
    $invoiceRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceRepository::class);
    $invoiceRepo->shouldReceive('findOneBy')->once()->with(['hash' => str_repeat('a', 32), 'status' => Box\Mod\Invoice\Entity\Invoice::STATUS_UNPAID])->andReturn($invoice);

    $adapter = Mockery::mock(Payment_Adapter_PayPalAutoPay::class);
    if ($once) {
        $adapter->shouldReceive('option')->once()->with('pay_once')->andReturn(true);
    }
    $adapter->shouldReceive('checkout')->once()->with($invoice, !$once, 'https://billing.example/invoice/paypal-autopay-paid', 'https://billing.example/invoice/' . str_repeat('a', 32))->andReturn(['token' => 'EC-1', 'url' => 'https://paypal.example/EC-1']);

    $savedCards = Mockery::mock(Box\Mod\Invoice\ServiceSavedCards::class);
    $savedCards->shouldReceive('autopay')->once()->andReturn($adapter);

    $cache = Mockery::mock(Psr\SimpleCache\CacheInterface::class);
    $cache->shouldReceive('getItem')->once()->andReturn($item = Mockery::mock(Psr\Cache\CacheItemInterface::class));
    $item->shouldReceive('set')->once();
    $item->shouldReceive('expiresAfter')->once();
    $cache->shouldReceive('save')->once()->with($item);

    $app = Mockery::mock(FOSSBilling\Core\Http\App::class);
    $request = Symfony\Component\HttpFoundation\Request::create('/invoice/paypal-autopay/' . str_repeat('a', 32) . ($once ? '?once=1' : ''));
    $app->shouldReceive('getRequest')->andReturn($request);
    $app->shouldReceive('redirectUrl')->once()->with('https://paypal.example/EC-1')->andReturn(new RedirectResponse('https://paypal.example/EC-1'));

    $url = new FOSSBilling\Core\Url();
    $url->setBaseUri('https://billing.example/');

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\Invoice\Entity\Invoice::class)->andReturn($invoiceRepo);
    $invoiceService = Mockery::mock(Box\Mod\Invoice\Service::class);
    if ($once) {
        $invoiceService->shouldReceive('checkInvoiceAuth')->once()->with($invoice, Box\Mod\Invoice\InvoiceOperation::PAYMENT);
    }
    $di['mod_service'] = $di->protect(Tests\Helpers\moduleService(['invoice:savedcards' => $savedCards, 'invoice' => $invoiceService]));
    $di['url'] = $url;
    $di['cache'] = $cache;
    $di['request'] = $request;
    $di['is_client_logged'] = true;
    $di['loggedin_client'] = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 7]);
    $di['cookie_queue'] = Mockery::mock(FOSSBilling\Core\Cookie\CookieQueue::class);
    $di['cookie_queue']->shouldReceive('queue')->once();

    $controller = new Client();
    $controller->setDi($di);

    $response = $controller->get_invoice_autopay($app, str_repeat('a', 32));

    expect($response)->toBeInstanceOf(RedirectResponse::class);
})->with([false, true]);

test('invoice autopay return records the approved payment', function (): void {
    $invoice = createEntity(Box\Mod\Invoice\Entity\Invoice::class, ['id' => 60, 'client_id' => 7, 'hash' => str_repeat('a', 32), 'status' => Box\Mod\Invoice\Entity\Invoice::STATUS_UNPAID]);
    $invoiceRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceRepository::class);
    $invoiceRepo->shouldReceive('find')->once()->with(60)->andReturn($invoice);

    $adapter = Mockery::mock(Payment_Adapter_PayPalAutoPay::class);
    $adapter->shouldReceive('gatewayId')->andReturn(5);
    $adapter->shouldReceive('checkoutPaid')->once()->with($invoice, 'EC-1')->andReturn('P-2');

    $savedCards = Mockery::mock(Box\Mod\Invoice\ServiceSavedCards::class);
    $savedCards->shouldReceive('autopay')->once()->andReturn($adapter);
    $savedCards->shouldReceive('hold')->once();

    $txService = Mockery::mock(Box\Mod\Invoice\ServiceTransaction::class);
    $txService->shouldReceive('createAndProcess')->once()->with([
        'invoice_id' => 60,
        'gateway_id' => 5,
        'source' => 'paypal_autopay_return',
        'get' => ['paypal_txn' => 'P-2', 'invoice_id' => 60],
    ])->andReturn(70);

    $cookie = new Symfony\Component\HttpFoundation\Cookie('paypal_return', 'bind', time() + 100);
    $request = Symfony\Component\HttpFoundation\Request::create('/invoice/paypal-autopay-paid?token=EC-1', cookies: ['paypal_return' => 'bind', session_name() => 'x']);

    $connection = paypalReturnDatabase();

    $cacheItem = Mockery::mock(Psr\Cache\CacheItemInterface::class);
    $cacheItem->shouldReceive('isHit')->andReturn(true);
    $cacheItem->shouldReceive('get')->andReturn(['invoice' => 60, 'bind' => hash('sha256', 'bind')]);
    $cache = Mockery::mock(Psr\SimpleCache\CacheInterface::class);
    $cache->shouldReceive('getItem')->andReturn($cacheItem);
    $cache->shouldReceive('deleteItem')->once();

    $app = Mockery::mock(FOSSBilling\Core\Http\App::class);
    $app->shouldReceive('getRequest')->andReturn($request);

    $url = new FOSSBilling\Core\Url();
    $url->setBaseUri('https://billing.example/');

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\Invoice\Entity\Invoice::class)->andReturn($invoiceRepo);
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $di['mod_service'] = $di->protect(Tests\Helpers\moduleService(['invoice:savedcards' => $savedCards, 'invoice:transaction' => $txService]));
    $di['url'] = $url;
    $di['cache'] = $cache;
    $di['request'] = $request;

    $controller = new Client();
    $controller->setDi($di);

    $response = $controller->get_invoice_autopay_paid($app);

    expect($response)->toBeInstanceOf(Symfony\Component\HttpFoundation\Response::class);
});

test('payment methods setup starts saving a PayPal account', function (): void {
    $adapter = Mockery::mock(Payment_Adapter_PayPalEmail::class);
    $adapter->shouldReceive('vaultSetup')->once()->with('https://billing.example/payment-methods/paypal/saved', 'https://billing.example/payment-methods')->andReturn(['id' => 'ST-1', 'url' => 'https://paypal.example/approve']);

    $savedCards = Mockery::mock(Box\Mod\Invoice\ServiceSavedCards::class);
    $savedCards->shouldReceive('paypal')->once()->andReturn($adapter);

    $cacheItem = Mockery::mock(Psr\Cache\CacheItemInterface::class);
    $cacheItem->shouldReceive('set')->once();
    $cacheItem->shouldReceive('expiresAfter')->once();
    $cache = Mockery::mock(Psr\SimpleCache\CacheInterface::class);
    $cache->shouldReceive('getItem')->once()->andReturn($cacheItem);
    $cache->shouldReceive('save')->once()->with($cacheItem);

    $app = Mockery::mock(FOSSBilling\Core\Http\App::class);
    $app->shouldReceive('getRequest')->andReturn(Symfony\Component\HttpFoundation\Request::create('/payment-methods/paypal'));
    $app->shouldReceive('redirectUrl')->once()->with('https://paypal.example/approve')->andReturn(new RedirectResponse('https://paypal.example/approve'));

    $url = new FOSSBilling\Core\Url();
    $url->setBaseUri('https://billing.example/');

    $di = container();
    $di['mod_service'] = $di->protect(Tests\Helpers\moduleService(['invoice:savedcards' => $savedCards]));
    $di['url'] = $url;
    $di['cache'] = $cache;
    $di['request'] = Symfony\Component\HttpFoundation\Request::create('/payment-methods/paypal');
    $di['is_client_logged'] = true;
    $di['loggedin_client'] = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 7]);
    $di['cookie_queue'] = Mockery::mock(FOSSBilling\Core\Cookie\CookieQueue::class);
    $di['cookie_queue']->shouldReceive('queue')->once();

    $controller = new Client();
    $controller->setDi($di);

    expect($controller->get_paypal_save($app))->toBeInstanceOf(RedirectResponse::class);
});

test('paying and saving a PayPal account starts an order for the invoice', function (): void {
    $invoice = createEntity(Box\Mod\Invoice\Entity\Invoice::class, ['id' => 60, 'client_id' => 7, 'hash' => str_repeat('a', 32), 'status' => Box\Mod\Invoice\Entity\Invoice::STATUS_UNPAID]);
    $invoiceRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceRepository::class);
    $invoiceRepo->shouldReceive('findOneBy')->once()->andReturn($invoice);

    $adapter = Mockery::mock(Payment_Adapter_PayPalEmail::class);
    $adapter->shouldReceive('vaultOrder')->once()->andReturn(['id' => 'O-1', 'url' => 'https://paypal.example/checkout']);

    $savedCards = Mockery::mock(Box\Mod\Invoice\ServiceSavedCards::class);
    $savedCards->shouldReceive('paypal')->once()->andReturn($adapter);

    $cacheItem = Mockery::mock(Psr\Cache\CacheItemInterface::class);
    $cacheItem->shouldReceive('set')->once();
    $cacheItem->shouldReceive('expiresAfter')->once();
    $cache = Mockery::mock(Psr\SimpleCache\CacheInterface::class);
    $cache->shouldReceive('getItem')->once()->andReturn($cacheItem);
    $cache->shouldReceive('save')->once()->with($cacheItem);

    $app = Mockery::mock(FOSSBilling\Core\Http\App::class);
    $app->shouldReceive('getRequest')->andReturn(Symfony\Component\HttpFoundation\Request::create('/invoice/paypal-save/' . str_repeat('a', 32)));
    $app->shouldReceive('redirectUrl')->once()->with('https://paypal.example/checkout')->andReturn(new RedirectResponse('https://paypal.example/checkout'));

    $url = new FOSSBilling\Core\Url();
    $url->setBaseUri('https://billing.example/');

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\Invoice\Entity\Invoice::class)->andReturn($invoiceRepo);
    $di['mod_service'] = $di->protect(Tests\Helpers\moduleService(['invoice:savedcards' => $savedCards]));
    $di['url'] = $url;
    $di['cache'] = $cache;
    $di['request'] = Symfony\Component\HttpFoundation\Request::create('/x');
    $di['is_client_logged'] = true;
    $di['loggedin_client'] = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 7]);
    $di['cookie_queue'] = Mockery::mock(FOSSBilling\Core\Cookie\CookieQueue::class);
    $di['cookie_queue']->shouldReceive('queue')->once();

    $controller = new Client();
    $controller->setDi($di);

    expect($controller->get_invoice_paypal_save($app, str_repeat('a', 32))->getTargetUrl())->toBe('https://paypal.example/checkout');
});

test('invoice vault return records the approved order', function (): void {
    $invoice = createEntity(Box\Mod\Invoice\Entity\Invoice::class, ['id' => 60, 'client_id' => 7, 'hash' => str_repeat('a', 32), 'status' => Box\Mod\Invoice\Entity\Invoice::STATUS_UNPAID]);
    $invoiceRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceRepository::class);
    $invoiceRepo->shouldReceive('find')->once()->with(60)->andReturn($invoice);

    $adapter = Mockery::mock(Payment_Adapter_PayPalEmail::class);
    $adapter->shouldReceive('gatewayId')->andReturn(6);
    $adapter->shouldReceive('vaultCapture')->once()->with($invoice, 'O-1')->andReturn('C-1');

    $savedCards = Mockery::mock(Box\Mod\Invoice\ServiceSavedCards::class);
    $savedCards->shouldReceive('paypal')->once()->andReturn($adapter);
    $savedCards->shouldReceive('hold')->once();

    $txService = Mockery::mock(Box\Mod\Invoice\ServiceTransaction::class);
    $txService->shouldReceive('createAndProcess')->once()->with([
        'invoice_id' => 60,
        'gateway_id' => 6,
        'source' => 'paypal_return',
        'get' => ['paypal_capture' => 'C-1', 'invoice_id' => 60],
    ])->andReturn(70);

    $request = Symfony\Component\HttpFoundation\Request::create('/invoice/paypal-paid?token=O-1', cookies: ['paypal_return' => 'bind', session_name() => 'x']);

    $connection = paypalReturnDatabase();

    $cacheItem = Mockery::mock(Psr\Cache\CacheItemInterface::class);
    $cacheItem->shouldReceive('isHit')->andReturn(true);
    $cacheItem->shouldReceive('get')->andReturn(['invoice' => 60, 'bind' => hash('sha256', 'bind')]);
    $cache = Mockery::mock(Psr\SimpleCache\CacheInterface::class);
    $cache->shouldReceive('getItem')->andReturn($cacheItem);
    $cache->shouldReceive('deleteItem')->once();

    $app = Mockery::mock(FOSSBilling\Core\Http\App::class);
    $app->shouldReceive('getRequest')->andReturn($request);

    $url = new FOSSBilling\Core\Url();
    $url->setBaseUri('https://billing.example/');

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\Invoice\Entity\Invoice::class)->andReturn($invoiceRepo);
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $di['mod_service'] = $di->protect(Tests\Helpers\moduleService(['invoice:savedcards' => $savedCards, 'invoice:transaction' => $txService]));
    $di['url'] = $url;
    $di['cache'] = $cache;
    $di['request'] = $request;

    $controller = new Client();
    $controller->setDi($di);

    expect($controller->get_invoice_paypal_paid($app))->toBeInstanceOf(Symfony\Component\HttpFoundation\Response::class);
});

test('invoice vault return waits when another payment is clearing', function (): void {
    $invoice = createEntity(Box\Mod\Invoice\Entity\Invoice::class, ['id' => 60, 'client_id' => 7, 'hash' => str_repeat('a', 32), 'status' => Box\Mod\Invoice\Entity\Invoice::STATUS_UNPAID]);
    $invoiceRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceRepository::class);
    $invoiceRepo->shouldReceive('find')->once()->with(60)->andReturn($invoice);

    $adapter = Mockery::mock(Payment_Adapter_PayPalEmail::class);
    $adapter->shouldNotReceive('vaultCapture');

    $savedCards = Mockery::mock(Box\Mod\Invoice\ServiceSavedCards::class);
    $savedCards->shouldReceive('paypal')->once()->andReturn($adapter);
    $savedCards->shouldNotReceive('hold');

    $txService = Mockery::mock(Box\Mod\Invoice\ServiceTransaction::class);
    $txService->shouldNotReceive('createAndProcess');

    $request = Symfony\Component\HttpFoundation\Request::create('/invoice/paypal-paid?token=O-1', cookies: ['paypal_return' => 'bind', session_name() => 'x']);

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('getDatabasePlatform')->andReturn(new Doctrine\DBAL\Platforms\MySQLPlatform());
    $connection->shouldReceive('isTransactionActive')->byDefault()->andReturn(false);
    $connection->shouldReceive('fetchOne')->andReturn(1, 'unpaid');
    $connection->shouldReceive('quoteSingleIdentifier')->with('transaction')->andReturn('"transaction"');
    $connection->shouldReceive('fetchAssociative')->andReturn(['amount' => '10.00', 'currency' => 'USD', 'created_at' => '2026-01-01']);
    $connection->shouldReceive('executeStatement')->andReturn(1);

    $cacheItem = Mockery::mock(Psr\Cache\CacheItemInterface::class);
    $cacheItem->shouldReceive('isHit')->andReturn(true);
    $cacheItem->shouldReceive('get')->andReturn(['invoice' => 60, 'bind' => hash('sha256', 'bind')]);
    $cache = Mockery::mock(Psr\SimpleCache\CacheInterface::class);
    $cache->shouldReceive('getItem')->andReturn($cacheItem);
    $cache->shouldReceive('deleteItem')->once();

    $app = Mockery::mock(FOSSBilling\Core\Http\App::class);
    $app->shouldReceive('getRequest')->andReturn($request);

    $url = new FOSSBilling\Core\Url();
    $url->setBaseUri('https://billing.example/');

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\Invoice\Entity\Invoice::class)->andReturn($invoiceRepo);
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $di['mod_service'] = $di->protect(Tests\Helpers\moduleService(['invoice:savedcards' => $savedCards, 'invoice:transaction' => $txService]));
    $di['url'] = $url;
    $di['cache'] = $cache;
    $di['request'] = $request;

    $controller = new Client();
    $controller->setDi($di);

    expect($controller->get_invoice_paypal_paid($app))->toBeInstanceOf(Symfony\Component\HttpFoundation\Response::class);
});

test('invoice autopay start waits while another payment is clearing', function (): void {
    $invoice = createEntity(Box\Mod\Invoice\Entity\Invoice::class, ['id' => 60, 'client_id' => 7, 'hash' => str_repeat('a', 32), 'status' => Box\Mod\Invoice\Entity\Invoice::STATUS_UNPAID]);
    $invoiceRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceRepository::class);
    $invoiceRepo->shouldReceive('findOneBy')->once()->andReturn($invoice);

    $adapter = Mockery::mock(Payment_Adapter_PayPalAutoPay::class);
    $adapter->shouldNotReceive('checkout');

    $savedCards = Mockery::mock(Box\Mod\Invoice\ServiceSavedCards::class);
    $savedCards->shouldReceive('autopay')->once()->andReturn($adapter);

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('quoteSingleIdentifier')->with('transaction')->andReturn('"transaction"');
    $connection->shouldReceive('fetchAssociative')->once()->andReturn(['amount' => '10.00', 'currency' => 'USD', 'created_at' => '2026-01-01 10:00:00']);

    $app = Mockery::mock(FOSSBilling\Core\Http\App::class);
    $app->shouldReceive('getRequest')->andReturn(Symfony\Component\HttpFoundation\Request::create('/invoice/paypal-autopay/' . str_repeat('a', 32)));
    $app->shouldReceive('redirect')->once()->with('invoice/' . str_repeat('a', 32))->andReturn(new RedirectResponse('/invoice/' . str_repeat('a', 32)));

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\Invoice\Entity\Invoice::class)->andReturn($invoiceRepo);
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $di['mod_service'] = $di->protect(Tests\Helpers\moduleService(['invoice:savedcards' => $savedCards]));
    $di['is_client_logged'] = true;
    $di['loggedin_client'] = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 7]);

    $controller = new Client();
    $controller->setDi($di);

    expect($controller->get_invoice_autopay($app, str_repeat('a', 32))->getTargetUrl())->toBe('/invoice/' . str_repeat('a', 32));
});

test('pay and save waits while another payment is clearing', function (): void {
    $invoice = createEntity(Box\Mod\Invoice\Entity\Invoice::class, ['id' => 60, 'client_id' => 7, 'hash' => str_repeat('a', 32), 'status' => Box\Mod\Invoice\Entity\Invoice::STATUS_UNPAID]);
    $invoiceRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceRepository::class);
    $invoiceRepo->shouldReceive('findOneBy')->once()->andReturn($invoice);

    $adapter = Mockery::mock(Payment_Adapter_PayPalEmail::class);
    $adapter->shouldNotReceive('vaultOrder');

    $savedCards = Mockery::mock(Box\Mod\Invoice\ServiceSavedCards::class);
    $savedCards->shouldReceive('paypal')->once()->andReturn($adapter);

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('quoteSingleIdentifier')->with('transaction')->andReturn('"transaction"');
    $connection->shouldReceive('fetchAssociative')->once()->andReturn(['amount' => '10.00', 'currency' => 'USD', 'created_at' => '2026-01-01 10:00:00']);

    $app = Mockery::mock(FOSSBilling\Core\Http\App::class);
    $app->shouldReceive('redirect')->once()->with('invoice/' . str_repeat('a', 32))->andReturn(new RedirectResponse('/invoice/' . str_repeat('a', 32)));

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\Invoice\Entity\Invoice::class)->andReturn($invoiceRepo);
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $di['mod_service'] = $di->protect(Tests\Helpers\moduleService(['invoice:savedcards' => $savedCards]));
    $di['is_client_logged'] = true;
    $di['loggedin_client'] = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 7]);

    $controller = new Client();
    $controller->setDi($di);

    expect($controller->get_invoice_paypal_save($app, str_repeat('a', 32))->getTargetUrl())->toBe('/invoice/' . str_repeat('a', 32));
});

test('one-time PayPal checkout applies invoice access policy before contacting PayPal', function (bool $allowed): void {
    $hash = str_repeat('a', 32);
    $invoice = createEntity(Box\Mod\Invoice\Entity\Invoice::class, ['id' => 60, 'client_id' => 7, 'hash' => $hash, 'status' => Box\Mod\Invoice\Entity\Invoice::STATUS_UNPAID]);
    $repo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceRepository::class);
    $repo->shouldReceive('findOneBy')->once()->andReturn($invoice);
    $adapter = Mockery::mock(Payment_Adapter_PayPalAutoPay::class);
    $adapter->shouldReceive('option')->once()->with('pay_once')->andReturn(true);
    $adapter->shouldNotReceive('checkout');
    $savedCards = Mockery::mock(Box\Mod\Invoice\ServiceSavedCards::class);
    $savedCards->shouldReceive('autopay')->once()->andReturn($adapter);
    $service = Mockery::mock(Box\Mod\Invoice\Service::class);
    $auth = $service->shouldReceive('checkInvoiceAuth')->once()->with($invoice, Box\Mod\Invoice\InvoiceOperation::PAYMENT);
    if (!$allowed) {
        $auth->andThrow(new FOSSBilling\Core\Exception\InformationException('Access denied', [], 403));
    }
    $request = Symfony\Component\HttpFoundation\Request::create('/invoice/paypal-autopay/' . $hash . '?once=1');
    $app = Mockery::mock(FOSSBilling\Core\Http\App::class);
    $app->shouldReceive('getRequest')->andReturn($request);
    $destination = $allowed ? 'invoice/' . $hash : 'invoice';
    $app->shouldReceive('redirect')->once()->with($destination)->andReturn(new RedirectResponse('/' . $destination));
    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\Invoice\Entity\Invoice::class)->andReturn($repo);
    if ($allowed) {
        $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
        $connection->shouldReceive('quoteSingleIdentifier')->with('transaction')->andReturn('"transaction"');
        $connection->shouldReceive('fetchAssociative')->once()->andReturn(['amount' => '10.00', 'currency' => 'USD', 'created_at' => '2026-01-01 10:00:00']);
        $di['em']->shouldReceive('getConnection')->andReturn($connection);
    }
    $di['mod_service'] = $di->protect(Tests\Helpers\moduleService(['invoice:savedcards' => $savedCards, 'invoice' => $service]));
    $controller = new Client();
    $controller->setDi($di);
    expect($controller->get_invoice_autopay($app, $hash)->getTargetUrl())->toBe('/' . $destination);
})->with([false, true]);

test('PayPal returns leave approved payments uncaptured while a saved charge is ambiguous', function (bool $vault): void {
    $invoice = createEntity(Box\Mod\Invoice\Entity\Invoice::class, ['id' => 60, 'client_id' => 7, 'hash' => str_repeat('a', 32), 'status' => 'unpaid']);
    $db = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $db->executeStatement('CREATE TABLE invoice (id INTEGER PRIMARY KEY, status TEXT)');
    $db->insert('invoice', ['id' => 60, 'status' => 'unpaid']);
    $db->executeStatement('CREATE TABLE "transaction" (id INTEGER PRIMARY KEY, invoice_id INTEGER, status TEXT, txn_status TEXT, amount TEXT, currency TEXT, created_at TEXT)');
    $db->executeStatement('CREATE TABLE invoice_gateway_charge_attempt (id TEXT, invoice_id INTEGER, state TEXT, created_at TEXT)');
    // The provider timeout left no transaction or bank-debit row to detect.
    $db->insert('invoice_gateway_charge_attempt', ['id' => 'ambiguous', 'invoice_id' => 60, 'state' => 'pending', 'created_at' => '2026-01-01']);
    $repo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceRepository::class);
    $repo->shouldReceive('find')->once()->with(60)->andReturn($invoice);
    $adapter = Mockery::mock($vault ? Payment_Adapter_PayPalEmail::class : Payment_Adapter_PayPalAutoPay::class);
    $adapter->shouldNotReceive($vault ? 'vaultCapture' : 'checkoutPaid');
    $saved = Mockery::mock(Box\Mod\Invoice\ServiceSavedCards::class);
    $saved->shouldReceive($vault ? 'paypal' : 'autopay')->once()->andReturn($adapter);
    $saved->shouldNotReceive('hold');
    $transactions = Mockery::mock(Box\Mod\Invoice\ServiceTransaction::class);
    $transactions->shouldNotReceive('createAndProcess');
    $request = Symfony\Component\HttpFoundation\Request::create('/return?token=approved', cookies: ['paypal_return' => 'bind', session_name() => 'x']);
    $item = Mockery::mock(Psr\Cache\CacheItemInterface::class);
    $item->shouldReceive('isHit')->andReturn(true);
    $item->shouldReceive('get')->andReturn(['invoice' => 60, 'bind' => hash('sha256', 'bind')]);
    $cache = Mockery::mock(Psr\SimpleCache\CacheInterface::class);
    $cache->shouldReceive('getItem')->andReturn($item);
    $cache->shouldReceive('deleteItem')->once();
    $url = new FOSSBilling\Core\Url();
    $url->setBaseUri('https://billing.example/');
    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\Invoice\Entity\Invoice::class)->andReturn($repo);
    $di['em']->shouldReceive('getConnection')->andReturn($db);
    $di['mod_service'] = $di->protect(Tests\Helpers\moduleService(['invoice:savedcards' => $saved, 'invoice:transaction' => $transactions]));
    $di['cache'] = $cache;
    $di['request'] = $request;
    $di['url'] = $url;
    $controller = new Client();
    $controller->setDi($di);
    $app = Mockery::mock(FOSSBilling\Core\Http\App::class);
    $app->shouldReceive('getRequest')->andReturn($request);
    $method = $vault ? 'get_invoice_paypal_paid' : 'get_invoice_autopay_paid';
    $response = $controller->$method($app);
    expect($response->getContent())->toContain('https://billing.example/invoice/' . str_repeat('a', 32))
        ->and($db->fetchOne('SELECT state FROM invoice_gateway_charge_attempt'))->toBe('pending')
        ->and($db->fetchOne('SELECT COUNT(*) FROM "transaction"'))->toBe(0);
    $db->close();
})->with([false, true]);

function paypalReturnDatabase(): Doctrine\DBAL\Connection
{
    $db = Tests\Helpers\fencedConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $em = FOSSBilling\Core\Doctrine\EntityManagerFactory::create($db);
    (new Doctrine\ORM\Tools\SchemaTool($em))->createSchema([$em->getClassMetadata(Box\Mod\Invoice\Entity\GatewayChargeAttempt::class)]);
    $db->executeStatement('CREATE TABLE invoice (id INTEGER PRIMARY KEY, status TEXT)');
    $db->insert('invoice', ['id' => 60, 'status' => 'unpaid']);
    $db->executeStatement('CREATE TABLE "transaction" (id INTEGER PRIMARY KEY, invoice_id INTEGER, gateway_id INTEGER, txn_id TEXT, status TEXT, txn_status TEXT, amount TEXT, currency TEXT, created_at TEXT)');

    return $db;
}

function paypalReturnJournalContext(bool $vault, ?Doctrine\DBAL\Connection $db = null): array
{
    $db ??= paypalReturnDatabase();
    $invoice = createEntity(Box\Mod\Invoice\Entity\Invoice::class, ['id' => 60, 'client_id' => 7, 'hash' => str_repeat('a', 32), 'status' => 'unpaid']);
    $repo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceRepository::class);
    $repo->shouldReceive('find')->with(60)->andReturn($invoice);
    $adapter = Mockery::mock($vault ? Payment_Adapter_PayPalEmail::class : Payment_Adapter_PayPalAutoPay::class);
    $adapter->shouldReceive('gatewayId')->andReturn(5);
    $saved = Mockery::mock(Box\Mod\Invoice\ServiceSavedCards::class);
    $saved->shouldReceive($vault ? 'paypal' : 'autopay')->andReturn($adapter);
    $saved->shouldReceive('hold')->byDefault();
    $transactions = Mockery::mock(Box\Mod\Invoice\ServiceTransaction::class);
    $request = Symfony\Component\HttpFoundation\Request::create('/return?token=approved', cookies: ['paypal_return' => 'bind', session_name() => 'x']);
    $item = Mockery::mock(Psr\Cache\CacheItemInterface::class);
    $item->shouldReceive('isHit')->andReturn(true);
    $item->shouldReceive('get')->andReturn(['invoice' => 60, 'bind' => hash('sha256', 'bind')]);
    $cache = Mockery::mock(Psr\SimpleCache\CacheInterface::class);
    $cache->shouldReceive('getItem')->andReturn($item);
    $cache->shouldReceive('deleteItem')->byDefault();
    $url = new FOSSBilling\Core\Url();
    $url->setBaseUri('https://billing.example/');
    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\Invoice\Entity\Invoice::class)->andReturn($repo);
    $di['em']->shouldReceive('getConnection')->andReturn($db);
    $di['em']->shouldReceive('close')->byDefault();
    $di['mod_service'] = $di->protect(Tests\Helpers\moduleService(['invoice:savedcards' => $saved, 'invoice:transaction' => $transactions]));
    $di['cache'] = $cache;
    $di['request'] = $request;
    $di['url'] = $url;
    $controller = new Client();
    $controller->setDi($di);
    $app = Mockery::mock(FOSSBilling\Core\Http\App::class);
    $app->shouldReceive('getRequest')->andReturn($request);
    $method = $vault ? 'get_invoice_paypal_paid' : 'get_invoice_autopay_paid';
    $capture = $vault ? 'vaultCapture' : 'checkoutPaid';

    return compact('db', 'invoice', 'adapter', 'transactions', 'controller', 'app', 'method', 'capture', 'request');
}

test('PayPal return captures keep a durable barrier through provider and settlement failures', function (bool $vault, string $outcome): void {
    $c = paypalReturnJournalContext($vault);
    $db = $c['db'];
    $c['adapter']->shouldReceive($c['capture'])->once()->andReturnUsing(function () use ($db, $outcome): string {
        $attempt = Box\Mod\Invoice\GatewayChargeAttempts::pending($db, 60);
        expect($attempt)->not->toBeNull()->and($attempt['payment_id'])->toBeNull()
            ->and($db->isTransactionActive())->toBeFalse()
            ->and(FOSSBilling\Core\Payment\ProcessingFence::claims())->not->toBeEmpty();
        if ($outcome === 'provider timeout') {
            throw new FOSSBilling\Core\Exception\InformationException('Provider timeout');
        }

        return 'PAYMENT-1';
    });
    if ($outcome === 'provider timeout') {
        $c['transactions']->shouldNotReceive('createAndProcess');
    } else {
        $c['transactions']->shouldReceive('createAndProcess')->once()->andReturnUsing(function () use ($db, $outcome): int {
            expect(Box\Mod\Invoice\GatewayChargeAttempts::pending($db, 60)['payment_id'])->toBe('PAYMENT-1')
                ->and(FOSSBilling\Core\Doctrine\NamedLock::acquire($db, 'fb:card-charge:60', 0))->toBeFalse();
            if ($outcome === 'settlement failure') {
                throw new RuntimeException('Settlement failed');
            }
            $db->insert($db->quoteSingleIdentifier('transaction'), ['id' => 70, 'invoice_id' => 60, 'gateway_id' => 5, 'txn_id' => 'PAYMENT-1', 'status' => $outcome === 'pending' ? 'received' : 'processed', 'txn_status' => $outcome === 'pending' ? 'processing' : 'completed']);
            if ($outcome === 'settled') {
                $db->update('invoice', ['status' => 'paid'], ['id' => 60]);
            }

            return 70;
        });
    }
    $callback = fn (): mixed => $c['controller']->{$c['method']}($c['app']);
    if (in_array($outcome, ['provider timeout', 'settlement failure'], true)) {
        expect($callback)->toThrow($outcome === 'provider timeout' ? FOSSBilling\Core\Exception\InformationException::class : RuntimeException::class);
    } else {
        expect($callback())->toBeInstanceOf(Symfony\Component\HttpFoundation\Response::class);
    }
    $attempt = $db->fetchAssociative('SELECT * FROM invoice_gateway_charge_attempt');
    expect($attempt['state'])->toBe($outcome === 'settled' ? 'settled' : 'pending')
        ->and($attempt['payment_id'])->toBe($outcome === 'provider timeout' ? null : 'PAYMENT-1');
    // Another approved checkout must never capture while the first is unresolved.
    $other = paypalReturnJournalContext($vault, $db);
    $other['request']->query->set('token', 'second-approved-checkout');
    $other['adapter']->shouldNotReceive($other['capture']);
    $other['transactions']->shouldNotReceive('createAndProcess');
    $other['controller']->{$other['method']}($other['app']);
    expect((int) $db->fetchOne('SELECT COUNT(*) FROM invoice_gateway_charge_attempt'))->toBe(1);
    $db->close();
})->with([false, true])->with(['provider timeout', 'settlement failure', 'pending', 'receipt only', 'settled']);

test('PayPal return capture requires an independently committed journal', function (bool $vault): void {
    $c = paypalReturnJournalContext($vault);
    $c['adapter']->shouldNotReceive($c['capture']);
    $c['transactions']->shouldNotReceive('createAndProcess');
    $c['db']->beginTransaction();

    try {
        expect(fn (): mixed => $c['controller']->{$c['method']}($c['app']))->toThrow(LogicException::class, 'inside a transaction');
        expect((int) $c['db']->fetchOne('SELECT COUNT(*) FROM invoice_gateway_charge_attempt'))->toBe(0);
    } finally {
        $c['db']->rollBack();
        $c['db']->close();
    }
})->with([false, true]);
