<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * @param array<string, mixed> $contextOverrides
 *
 * @return array{0: string, 1: object}
 */
function renderOrderbuttonCheckoutForBalance(array $contextOverrides = []): array
{
    $template = file_get_contents(PATH_MODS . '/Orderbutton/templates/client/mod_orderbutton_checkout.html.twig');
    if ($template === false) {
        throw new RuntimeException('Unable to load the orderbutton checkout template.');
    }

    $twig = new Environment(new ArrayLoader(['checkout' => $template]), ['strict_variables' => true]);
    foreach (['trans', 'period_title', 'url'] as $filterName) {
        $twig->addFilter(new TwigFilter($filterName, static fn (string $value): string => $value));
    }
    $twig->addFilter(new TwigFilter(
        'api_url',
        static fn (string $value, ?array $query = null, ?string $role = null): string => '/' . $value,
    ));
    $twig->addFilter(new TwigFilter(
        'format_currency',
        static fn (mixed $amount, string $currency): string => $currency . ' ' . number_format((float) $amount, 2, '.', ''),
    ));
    $twig->addFunction(new TwigFunction('fb_api_link', static fn (array $options = []): string => ''));
    $twig->addFunction(new TwigFunction('fb_api_form', static fn (array $options = []): string => ''));

    $client = new class {
        public bool $client_is_taxable = false;
        public int $balanceLookups = 0;

        public function client_balance_get_total(): float
        {
            ++$this->balanceLookups;

            return 20.0;
        }
    };

    $context = array_replace([
        'client' => $client,
        'request' => ['checkout' => true, 'show_custom_form_values' => false, 'promocode' => ''],
        'settings' => [],
        'cart' => [],
        'guest' => [
            'cart_get' => [
                'items' => [[
                    'id' => 1,
                    'title' => 'Test product',
                    'quantity' => 1,
                    'discount_price' => 0,
                    'total' => 10,
                    'setup_price' => 0,
                    'discount_setup' => 0,
                    'form_id' => null,
                ]],
                'promocode' => '',
                'discount' => 0,
                'subtotal' => 10,
                'total' => 10,
                'subscribable' => false,
                'currency' => ['code' => 'EUR', 'conversion_rate' => 2],
            ],
            // The checkout total must continue using cart.currency, not this separate API result.
            'cart_get_currency' => ['code' => 'USD', 'conversion_rate' => 99],
            'invoice_gateways' => [],
        ],
    ], $contextOverrides);

    return [$twig->render('checkout', $context), $client];
}

test('checkout uses a defined zero profile balance without calling the balance API', function (): void {
    [$html, $client] = renderOrderbuttonCheckoutForBalance(['profile' => ['balance' => 0]]);

    expect($html)->toContain('Choose a payment method to continue with your order.')
        ->and($html)->not->toContain('Total amount will be deducted from account balance')
        ->and($client->balanceLookups)->toBe(0)
        ->and($html)->toContain('EUR 20.00');
});

test('checkout uses sufficient profile balance without calling the balance API', function (): void {
    [$html, $client] = renderOrderbuttonCheckoutForBalance(['profile' => ['balance' => 20]]);

    expect($html)->toContain('Total amount will be deducted from account balance')
        ->and($html)->not->toContain('Choose a payment method to continue with your order.')
        ->and($client->balanceLookups)->toBe(0)
        ->and($html)->toContain('EUR 20.00');
});

test('checkout falls back to the balance API when profile is missing', function (): void {
    [$html, $client] = renderOrderbuttonCheckoutForBalance();

    expect($html)->toContain('Total amount will be deducted from account balance')
        ->and($client->balanceLookups)->toBe(1)
        ->and($html)->toContain('EUR 20.00');
});

test('guest checkout never requests a client balance', function (): void {
    [$html] = renderOrderbuttonCheckoutForBalance(['client' => null, 'profile' => null]);

    expect($html)->toContain('You must first login / create an account before you can checkout.')
        ->and($html)->not->toContain('Total amount will be deducted from account balance')
        ->and($html)->toContain('EUR 20.00');
});
