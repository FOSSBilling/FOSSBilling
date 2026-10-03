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
            throw new FOSSBilling\InformationException('You do not have permission to perform this action', [], 403);
        }
    };

    $app = Mockery::mock(Box_App::class);
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

    $app = Mockery::mock(Box_App::class);
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

    $app = Mockery::mock(Box_App::class);
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
            throw new FOSSBilling\InformationException('denied', [], 403);
        }
    };

    $request = new Symfony\Component\HttpFoundation\Request(['allow_subscription' => '1']);

    $app = Mockery::mock(Box_App::class);
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
            throw new FOSSBilling\InformationException('This invoice is already paid', [], 400);
        }
    };

    $di['api_guest'] = $alreadyPaid;

    expect(fn () => $controller->get_banklink($app, 'abc', 2))
        ->toThrow(FOSSBilling\InformationException::class, 'already paid');
});

test('pdf download returns the response, redirects on denial, and rejects garbage', function (): void {
    $ok = new class {
        public function invoice_pdf(array $data): Symfony\Component\HttpFoundation\Response
        {
            return new Symfony\Component\HttpFoundation\Response('pdf-bytes');
        }
    };

    $app = Mockery::mock(Box_App::class);

    $di = container();
    $di['api_guest'] = $ok;

    $controller = new Client();
    $controller->setDi($di);

    expect($controller->get_pdf($app, 'abc')->getContent())->toBe('pdf-bytes');

    $denied = new class {
        public function invoice_pdf(array $data): Symfony\Component\HttpFoundation\Response
        {
            throw new FOSSBilling\InformationException('denied', [], 403);
        }
    };

    $app->shouldReceive('redirect')->once()->with('invoice')->andReturn(new RedirectResponse('/invoice'));
    $di['api_guest'] = $denied;

    expect($controller->get_pdf($app, 'abc'))->toBeInstanceOf(RedirectResponse::class);
});
