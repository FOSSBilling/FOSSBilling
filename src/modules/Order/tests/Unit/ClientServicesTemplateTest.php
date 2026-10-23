<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

// cspell:ignore demohash

use Tests\Support\ClientThemeRenderer;
use Twig\TwigFilter;
use Twig\TwigFunction;

test('service views show the appropriate payment, renewal and management actions', function (?string $review, bool $empty, array $expected, array $absent): void {
    $twig = ClientThemeRenderer::environment([
        'layout_default.html.twig' => '{% block content %}{% endblock %}',
        'macro_functions.html.twig' => '{% macro status_badge(status, type) %}{{ status }}{% endmacro %}',
        'partial_pagination.html.twig' => '',
    ]);
    $twig->addFilter(new TwigFilter('api_url', static fn (string $path, array $query = []): string => '/api/client/' . $path . '?' . http_build_query($query)));
    $twig->addFilter(new TwigFilter('format_date', static fn (?string $date): string => $date ?? ''));
    $twig->addFilter(new TwigFilter('period_title', static fn (string $period): string => $period));
    $twig->addFilter(new TwigFilter('format_currency', static fn (mixed $amount, string $currency): string => $amount . ' ' . $currency));
    $twig->addFunction(new TwigFunction('fb_api_link', static fn (array $config): string => 'href="' . htmlspecialchars($config['href']) . '"', ['is_safe' => ['html']]));
    $client = new readonly class($empty) {
        public function __construct(private bool $empty)
        {
        }

        public function order_get_list(array $data): array
        {
            $base = ['currency' => 'USD', 'total' => 12, 'period' => '1M', 'expires_at' => '2026-10-25', 'meta' => []];
            $orders = [
                $base + ['id' => 1, 'title' => 'Payment service', 'status' => 'pending_setup', 'unpaid_invoice_hash' => 'demohash'],
                $base + ['id' => 2, 'title' => 'Renewal service', 'status' => 'active', 'renewal_due' => true],
                $base + ['id' => 3, 'title' => 'Problem service', 'status' => 'failed_renew'],
            ];
            if (empty($data['review'])) {
                $orders[] = $base + ['id' => 4, 'title' => 'Healthy <service>', 'status' => 'active'];
            }
            if ($this->empty) {
                $orders = [];
            }

            return ['total' => count($orders), 'list' => $orders];
        }
    };
    $html = $twig->render('mod_order_list.html.twig', ['client' => $client, 'request' => ['review' => $review]]);
    foreach ($expected as $text) {
        expect($html)->toContain($text);
    }
    foreach ($absent as $text) {
        expect($html)->not->toContain($text);
    }
    $doc = new DOMDocument();
    @$doc->loadHTML($html);
    $xpath = new DOMXPath($doc);
    $activeView = $xpath->query('//nav//a[@aria-current="page"]');
    expect($activeView)->toHaveCount(1);
    $attention = $review === '1' || ($review === null && !$empty);
    expect($activeView->item(0)->getAttribute('href'))->toBe('/order/service?review=' . ($attention ? '1' : '0'));
    if (!$empty) {
        expect($html)->toContain('href="/invoice/demohash"', 'order_id=2', 'Renewal Needs Support', 'Manage Payment service');
        expect($xpath->query('//tr[.//a[@href="/order/service/manage/3"]]//a[contains(@href,"renewal_invoice")]')->length)->toBe(0);
    }
})->with([
    'attention by default' => [null, false, ['Pay Invoice', 'Renew Now'], ['Healthy &lt;service&gt;']],
    'explicit all services' => ['0', false, ['Healthy &lt;service&gt;', 'Pay Invoice'], []],
    'dashboard review link' => ['1', false, ['Pay Invoice'], ['Healthy &lt;service&gt;']],
    'empty attention view' => ['1', true, ['No Services Need Attention', 'View All Services'], ['Renew Now', 'Pay Invoice']],
    'empty account' => [null, true, ['You Have No Services Yet', 'Browse Products and Services'], ['Renew Now', 'Pay Invoice']],
]);
