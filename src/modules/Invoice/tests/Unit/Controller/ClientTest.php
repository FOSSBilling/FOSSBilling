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
