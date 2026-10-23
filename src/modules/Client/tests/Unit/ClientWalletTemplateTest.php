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
use Twig\TwigFunction;

function clientWalletTemplate(): Environment
{
    $twig = new Environment(new ArrayLoader([
        'partial_client_empty.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/client/html/partial_client_empty.html.twig'),
        'partial_empty_state.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/shared/html/partial_empty_state.html.twig'),
        'wallet' => (new Filesystem())->readFile(PATH_MODS . '/Client/templates/client/mod_client_balance.html.twig'),
        'layout_default.html.twig' => '{% block content %}{% endblock %}{% block js %}{% endblock %}',
        'layout_blank.html.twig' => '{% block content %}{% endblock %}{% block js %}{% endblock %}',
        'partial_pagination.html.twig' => '',
    ]), ['autoescape' => 'html', 'strict_variables' => true]);
    $twig->addFilter(new TwigFilter('trans', static fn (string $text): string => $text));
    $twig->addFilter(new TwigFilter('url', static fn (string $path): string => '/' . $path));
    $twig->addFilter(new TwigFilter('api_url', static fn (string $path): string => '/api/client/' . $path));
    $twig->addFilter(new TwigFilter('format_date', static fn (?string $date): string => $date ?? ''));
    $twig->addFilter(new TwigFilter('format_currency', static fn (mixed $amount, string $currency): string => $amount . ' ' . $currency));
    $twig->addFunction(new TwigFunction('fb_api_form', static fn (array $options): string => 'data-callback="' . $options['callback'] . '"', ['is_safe' => ['html']]));

    return $twig;
}

test('wallet retains available balance, funding actions and signed history', function (): void {
    $client = new class {
        public array $query = [];

        public function profile_get(): array
        {
            return ['balance' => 6, 'currency' => 'USD'];
        }

        public function client_balance_get_list(array $data): array
        {
            $this->query = $data;

            return ['list' => [
                ['description' => 'Payment <invoice>', 'amount' => '-29.00', 'currency' => 'USD', 'created_at' => '2026-10-20'],
                ['description' => 'Account credit', 'amount' => '40.00', 'currency' => 'EUR', 'created_at' => '2026-10-19'],
            ]];
        }
    };
    $html = clientWalletTemplate()->render('wallet', [
        'client' => $client, 'guest' => ['invoice_funds_enabled' => true], 'request' => ['ajax' => false, 'page' => 2],
    ]);
    expect($client->query)->toBe(['per_page' => 10, 'page' => 2, 'sort' => 'id', 'direction' => 'DESC'])
        ->and($html)->toContain('Available Balance', '6 USD', 'Amount to Add', '(USD)', 'Continue to Payment', 'Funds become available once payment is received.')
        ->and($html)->toContain('action="/api/client/invoice/funds_invoice"', 'data-callback="onAfterInvoiceCreated"', 'min="0.01"', 'step="0.01"', "window.location = '/invoice/' + hash")
        ->and($html)->toContain('Payment &lt;invoice&gt;', '-29.00 USD', '+40.00 EUR', 'Credit', 'Debit')
        ->and($html)->not->toContain('<tfoot>');
    expect(strpos($html, 'Available Balance'))->toBeLessThan(strpos($html, 'Transaction History'));
});

test('wallet handles unavailable funding and empty history without hiding balance', function (bool $enabled, ?string $currency, float $balance, bool $ajax, string $message, bool $hasForm): void {
    $html = clientWalletTemplate()->render('wallet', [
        'client' => new Tests\Support\PermissiveStub([
            'profile_get' => ['balance' => $balance, 'currency' => $currency],
            'client_balance_get_list' => ['list' => []],
        ]),
        'guest' => ['invoice_funds_enabled' => $enabled], 'default_currency' => 'USD', 'request' => ['ajax' => $ajax],
    ]);
    expect($html)->toContain('Available Balance', $balance . ' USD', $message, 'No Transactions Yet');
    if ($hasForm) {
        expect($html)->toContain('<form', 'for="wallet-amount"', 'aria-describedby="wallet-funds-help"');
    } else {
        expect($html)->not->toContain('<form');
    }
})->with([
    'zero balance' => [true, 'USD', 0.0, false, 'Continue to Payment', true],
    'negative balance' => [true, 'USD', -5.0, false, 'text-danger', true],
    'funding disabled' => [false, 'USD', 6.0, false, 'Adding funds is currently unavailable.', false],
    'no currency yet' => [true, null, 0.0, false, 'You need an active order', false],
    'ajax layout' => [true, 'USD', 6.0, true, 'Continue to Payment', true],
]);
