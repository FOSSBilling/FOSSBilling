<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;

function renderClientPaymentPage(string $page, array $overrides = []): string
{
    $fs = new Filesystem();
    $twig = new Environment(new ArrayLoader([
        'handoff' => $fs->readFile(PATH_MODS . '/Invoice/templates/client/mod_invoice_banklink.html.twig'),
        'outcome' => $fs->readFile(PATH_MODS . '/Invoice/templates/client/mod_invoice_thankyou.html.twig'),
        'layout_default.html.twig' => '{% block content %}{% endblock %}',
    ]), ['strict_variables' => true, 'autoescape' => 'html']);
    $twig->addFilter(new TwigFilter('trans', static fn (string $text): string => $text));
    $twig->addFilter(new TwigFilter('url', static fn (string $path, array $query = []): string => '/' . $path . ($query ? '?' . http_build_query($query) : '')));
    $twig->addFilter(new TwigFilter('format_currency', static fn (float $value, string $currency): string => $currency . ' ' . number_format($value, 2)));
    $twig->addFilter(new TwigFilter('format_datetime', static fn (string $date): string => $date));
    $invoice = array_replace(['id' => 5, 'serie_nr' => 'DEMO5', 'hash' => 'demo-hash', 'status' => 'unpaid', 'issued' => true, 'total' => 24, 'currency' => 'USD', 'lines' => []], $overrides['invoice'] ?? []);

    return $twig->render($page, array_replace(['request' => ['ajax' => false], 'client' => true], $overrides, ['invoice' => $invoice]));
}

test('gateway handoff keeps provider fields and a visible manual fallback', function (): void {
    $html = renderClientPaymentPage('handoff', ['payment' => ['type' => 'form', 'service_url' => 'https://provider.example/pay', 'result' => ['merchant' => 'demo', 'description' => 'A "quoted" <plan>', 'submit' => 'provider field']]]);
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    expect($dom->getElementById('payment_button')->hasAttribute('hidden'))->toBeFalse()
        ->and($dom->getElementById('gateway-payment-form')->getAttribute('action'))->toBe('https://provider.example/pay')
        ->and($xpath->query('//input[@name="description"]')->item(0)->getAttribute('value'))->toBe('A "quoted" <plan>')
        ->and($html)->toContain('USD 24.00', 'HTMLFormElement.prototype.submit.call', 'Check Invoice Status', '/invoice/demo-hash')
        ->not->toContain("style.display = 'none'", 'Payment Confirmed');
});

test('HTML gateways retain their instructions and a path to invoice status', function (): void {
    $html = renderClientPaymentPage('handoff', ['payment' => ['type' => 'html', 'result' => '<div data-provider-instructions>Bank transfer instructions</div>']]);
    expect($html)->toContain('data-provider-instructions', 'Bank transfer instructions', 'Check Invoice Status')
        ->not->toContain('gateway-payment-form', 'Payment Confirmed');
});

test('payment startup failures offer recovery without exposing gateway diagnostics', function (array $context): void {
    $html = renderClientPaymentPage('handoff', $context);
    expect($html)->toContain('Payment Could Not Be Started', 'Review Invoice and Payment Options', 'If you already authorized a payment', '/invoice/demo-hash')
        ->not->toContain('gateway-payment-form', 'Payment Confirmed');
})->with([
    [['payment_error' => true]],
    [['payment' => ['type' => 'html', 'result' => '']]],
    [['payment' => ['type' => 'unsupported']]],
    [['payment' => ['type' => 'form', 'service_url' => '', 'result' => []]]],
]);

test('confirmation reports the invoice state instead of trusting provider return parameters', function (array $invoice, string $heading, bool $pending): void {
    $html = renderClientPaymentPage('outcome', ['invoice' => $invoice, 'request' => ['ajax' => false, 'status' => 'ok', 'payment_status' => 'Completed']]);
    expect($html)->toContain($heading, '/invoice/demo-hash', 'USD 24.00')
        ->and(str_contains($html, 'Check Payment Status'))->toBe($pending);
    if (($invoice['status'] ?? 'unpaid') !== 'paid') {
        expect($html)->not->toContain('Payment Confirmed');
    }
})->with([
    [[], 'Payment Awaiting Confirmation', true],
    [['status' => 'paid', 'paid_at' => '2026-10-20 18:00:00'], 'Payment Confirmed', false],
    [['status' => 'refunded'], 'Invoice Refunded', false],
    [['status' => 'canceled'], 'Invoice Canceled', false],
    [['issued' => false], 'Invoice Not Issued', false],
    [['credit_note_for_invoice_id' => 3], 'Credit Note Issued', false],
]);

test('paid wallet deposits lead to wallet history instead of service management', function (): void {
    $html = renderClientPaymentPage('outcome', ['invoice' => ['status' => 'paid', 'lines' => [['type' => 'deposit']]]]);
    expect($html)->toContain('Payment Confirmed', 'View Wallet', '/client/balance')->not->toContain('View Services');
});

test('zero-value invoices do not wait for a payment confirmation', function (): void {
    $html = renderClientPaymentPage('outcome', ['invoice' => ['total' => 0]]);
    expect($html)->toContain('No Payment Due')->not->toContain('Payment Awaiting Confirmation', 'Check Payment Status');
});
