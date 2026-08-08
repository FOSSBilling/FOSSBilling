<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use FOSSBilling\Extension\Contract\Payment\Checkout;
use FOSSBilling\Extension\Contract\Payment\CheckoutRequest;
use FOSSBilling\Extension\Contract\Payment\CheckoutUrls;
use FOSSBilling\Extension\Contract\Payment\Context;
use FOSSBilling\Extension\Contract\Payment\ContextAware;
use FOSSBilling\Extension\Contract\Payment\Gateway;
use FOSSBilling\Extension\Contract\Payment\InvoiceView;
use FOSSBilling\Extension\Contract\Payment\SupportsSubscriptions;
use FOSSBilling\Extension\Gateway\Custom\Custom;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A Context test double, not a mocked container — Custom (like every
 * Gateway-contract adapter) is only allowed to ask FOSSBilling for what the
 * Context interface declares.
 */
final class CustomTestContext implements Context
{
    /** @var array{template: string, vars: array<string, mixed>}|null */
    public ?array $lastRender = null;

    public function logger(): LoggerInterface
    {
        return new NullLogger();
    }

    public function httpClient(): HttpClientInterface
    {
        throw new LogicException('Custom does not make outbound HTTP calls.');
    }

    public function url(string $path): string
    {
        return 'https://example.com' . $path;
    }

    public function renderTemplate(string $template, array $vars): string
    {
        $this->lastRender = ['template' => $template, 'vars' => $vars];

        return 'rendered: ' . $template;
    }
}

function customInvoiceRequest(bool $subscription = false): CheckoutRequest
{
    return new CheckoutRequest(
        invoice: new InvoiceView(
            id: 1,
            clientId: 2,
            gatewayId: 3,
            currency: 'USD',
            hash: 'hashString',
            approved: true,
            serie: 'INV-',
            nr: 1001,
            total: 42.5,
            subtotal: 40.0,
            tax: 2.5,
        ),
        subscription: $subscription,
        urls: new CheckoutUrls('https://example.com/ok', 'https://example.com/cancel', 'https://example.com/ipn.php'),
        testMode: false,
    );
}

test('implements the Gateway contract and declares subscription support with zero container access', function (): void {
    $adapter = new Custom(['single' => 'Pay us', 'recurrent' => 'Subscribe']);

    expect($adapter)->toBeInstanceOf(Gateway::class)
        ->and($adapter)->toBeInstanceOf(SupportsSubscriptions::class)
        ->and($adapter)->toBeInstanceOf(ContextAware::class)
        ->and($adapter)->not->toBeInstanceOf(FOSSBilling\InjectionAwareInterface::class);
});

test('checkout renders the single-payment template for a one-time payment', function (): void {
    $context = new CustomTestContext();
    $adapter = new Custom(['single' => 'Wire :amount to us', 'recurrent' => 'Subscribe here']);
    $adapter->setContext($context);

    $checkout = $adapter->checkout(customInvoiceRequest(subscription: false));

    expect($checkout)->toBeInstanceOf(Checkout\Html::class)
        ->and($checkout->html)->toBe('rendered: Wire :amount to us')
        ->and($context->lastRender['template'])->toBe('Wire :amount to us')
        ->and($context->lastRender['vars']['invoice']['total'])->toBe(42.5)
        ->and($context->lastRender['vars']['invoice']['hash'])->toBe('hashString');
});

test('checkout renders the subscription template when the request is a subscription', function (): void {
    $context = new CustomTestContext();
    $adapter = new Custom(['single' => 'Wire us', 'recurrent' => 'Subscribe here']);
    $adapter->setContext($context);

    $checkout = $adapter->checkout(customInvoiceRequest(subscription: true));

    expect($checkout)->toBeInstanceOf(Checkout\Html::class)
        ->and($checkout->html)->toBe('rendered: Subscribe here');
});

test('checkout returns a placeholder message when its template is not configured', function (): void {
    $context = new CustomTestContext();
    $adapter = new Custom([]);
    $adapter->setContext($context);

    $checkout = $adapter->checkout(customInvoiceRequest());

    expect($checkout)->toBeInstanceOf(Checkout\Html::class)
        ->and($checkout->html)->toBe('"Custom" payment adapter is not fully configured.')
        ->and($context->lastRender)->toBeNull();
});

test('checkout returns the raw template text when no context was set', function (): void {
    $adapter = new Custom(['single' => 'Pay us directly']);

    $checkout = $adapter->checkout(customInvoiceRequest());

    expect($checkout)->toBeInstanceOf(Checkout\Html::class)
        ->and($checkout->html)->toBe('Pay us directly');
});
