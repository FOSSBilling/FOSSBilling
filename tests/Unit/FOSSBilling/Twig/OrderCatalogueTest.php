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

function orderCatalogueTwig(array $templates): Environment
{
    $twig = new Environment(new ArrayLoader($templates), ['autoescape' => 'html', 'strict_variables' => true]);
    $twig->addFilter(new TwigFilter('trans', static fn (string $text): string => $text));
    $twig->addFilter(new TwigFilter('format_currency', static fn (mixed $amount, string $currency): string => $currency . ' ' . number_format((float) $amount, 2, '.', '')));
    $twig->addFilter(new TwigFilter('mod_asset_url', static fn (string $path, string $module): string => $path));

    return $twig;
}

test('catalogue pricing shows the billing basis and setup fee in the cart currency', function (array $product, array $expected, array $absent): void {
    $twig = orderCatalogueTwig(['price' => (new Filesystem())->readFile(PATH_MODS . '/Orderbutton/templates/client/partial_orderbutton_price.html.twig')]);
    $html = $twig->render('price', ['product' => $product, 'cart_currency' => ['code' => 'EUR', 'conversion_rate' => 2]]);
    foreach ($expected as $text) {
        expect($html)->toContain($text);
    }
    foreach ($absent as $text) {
        expect($html)->not->toContain($text);
    }
})->with([
    'one-time price with setup' => [
        ['type' => 'custom', 'pricing' => ['type' => 'once', 'once' => ['price' => 10, 'setup' => 5]]],
        ['EUR 20.00', 'One-Time Payment', 'Setup Fee', 'EUR 10.00'], ['Free'],
    ],
    'first available recurring cycle' => [
        ['type' => 'hosting', 'pricing' => ['type' => 'recurrent', 'recurrent' => [
            '1M' => ['enabled' => false, 'price' => 1, 'setup' => 0, 'title' => 'Every month'],
            '1Y' => ['enabled' => true, 'price' => 100, 'setup' => 5, 'title' => 'Every year'],
        ]]],
        ['EUR 200.00', 'Every year', 'EUR 10.00'], ['Every month', 'From', 'Other billing cycles available'],
    ],
    'free product' => [['type' => 'custom', 'pricing' => ['type' => 'free']], ['Free'], ['Setup Fee', 'One-Time Payment']],
    'domain pricing' => [['type' => 'domain', 'price_starting_from' => 12, 'pricing' => []], ['From EUR 24.00', 'renewal terms'], ['Free']],
    'no available cycle' => [['type' => 'custom', 'pricing' => ['type' => 'recurrent', 'recurrent' => []]], ['Pricing Unavailable'], ['Free', 'EUR 0.00']],
]);

test('ordering opens the appropriate stage and keeps payment hidden until checkout completes', function (bool $product, bool $checkout, bool $items, string $stage): void {
    $templates = ['flow' => (new Filesystem())->readFile(PATH_MODS . '/Orderbutton/templates/client/mod_orderbutton_content.html.twig')];
    foreach (['choose_product', 'product_configuration', 'checkout'] as $part) {
        $templates['mod_orderbutton_' . $part . '.html.twig'] = '<div data-stage="{{ order_stage }}"></div>';
    }
    $templates['mod_orderbutton_js.html.twig'] = '';
    $html = orderCatalogueTwig($templates)->render('flow', [
        'product' => $product ? ['id' => 1] : null,
        'client' => true,
        'request' => ['product' => null, 'checkout' => $checkout],
        'guest' => new class($items) {
            public function __construct(private bool $items)
            {
            }

            public function cart_get(): array
            {
                return ['items' => $this->items ? [['id' => 1]] : []];
            }

            public function extension_is_on(array $data): bool
            {
                return false;
            }
        },
    ]);
    expect($html)->toContain('data-stage="' . $stage . '"', 'id="payment-step" hidden');
})->with([
    'catalogue' => [false, false, false, 'browse'],
    'product configuration' => [true, false, false, 'configure'],
    'cart review' => [false, true, true, 'checkout'],
    'empty checkout returns to catalogue' => [false, true, false, 'browse'],
    'checkout takes precedence over product' => [true, true, true, 'checkout'],
]);

test('embedded ordering accepts legacy and current product parameters without overriding a supplied product', function (array $request, ?array $product, ?int $expectedLookup, int $expectedProduct): void {
    $templates = ['flow' => (new Filesystem())->readFile(PATH_MODS . '/Orderbutton/templates/client/mod_orderbutton_content.html.twig')];
    foreach (['choose_product', 'product_configuration', 'checkout'] as $part) {
        $templates['mod_orderbutton_' . $part . '.html.twig'] = '<div data-product="{{ product.id }}" data-stage="{{ order_stage }}"></div>';
    }
    $templates['mod_orderbutton_js.html.twig'] = '';
    $guest = new class {
        public array $lookups = [];

        public function product_get(array $data): array
        {
            $this->lookups[] = $data['id'];

            return $data;
        }

        public function cart_get(): array
        {
            return ['items' => []];
        }

        public function extension_is_on(array $data): bool
        {
            return false;
        }
    };
    $html = orderCatalogueTwig($templates)->render('flow', [
        'product' => $product, 'request' => $request + ['checkout' => false], 'client' => true, 'guest' => $guest,
    ]);
    expect($guest->lookups)->toBe($expectedLookup === null ? [] : [$expectedLookup])
        ->and($html)->toContain('data-product="' . $expectedProduct . '" data-stage="configure"');
})->with([
    'legacy widget order parameter' => [['order' => 6], null, 6, 6],
    'current product parameter' => [['product' => 7], null, 7, 7],
    'current parameter takes precedence' => [['product' => 7, 'order' => 6], null, 7, 7],
    'controller-supplied product takes precedence' => [['product' => 7, 'order' => 6], ['id' => 8], null, 8],
]);
