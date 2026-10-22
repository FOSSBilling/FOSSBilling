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

test('service views show the appropriate payment, renewal and management actions', function (?string $review, bool $empty, array $expected, array $absent): void {
    $fs = new Filesystem();
    $twig = new Environment(new ArrayLoader([
        'partial_client_empty.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/client/html/partial_client_empty.html.twig'),
        'partial_empty_state.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/shared/html/partial_empty_state.html.twig'),
        'services' => $fs->readFile(PATH_MODS . '/Order/templates/client/mod_order_list.html.twig'),
        'partial_order_attention.html.twig' => $fs->readFile(PATH_MODS . '/Order/templates/client/partial_order_attention.html.twig'),
        'layout_default.html.twig' => '{% block content %}{% endblock %}',
        'macro_functions.html.twig' => '{% macro status_badge(status, type) %}{{ status }}{% endmacro %}',
        'partial_pagination.html.twig' => '',
    ]), ['autoescape' => 'html']);
    $twig->addFilter(new TwigFilter('trans', static fn (string $text, array $params = []): string => strtr($text, $params)));
    $twig->addFilter(new TwigFilter('url', static fn (string $path, array $query = []): string => '/' . $path . ($query ? '?' . http_build_query($query) : '')));
    $twig->addFilter(new TwigFilter('api_url', static fn (string $path, array $query = []): string => '/api/client/' . $path . '?' . http_build_query($query)));
    $twig->addFilter(new TwigFilter('format_date', static fn (?string $date): string => $date ?? ''));
    $twig->addFilter(new TwigFilter('period_title', static fn (string $period): string => $period));
    $twig->addFilter(new TwigFilter('format_currency', static fn (mixed $amount, string $currency): string => $amount . ' ' . $currency));
    $twig->addFunction(new TwigFunction('fb_api_link', static fn (array $config): string => 'href="' . htmlspecialchars($config['href']) . '"', ['is_safe' => ['html']]));
    $client = new class($empty) {
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
    $html = $twig->render('services', ['client' => $client, 'request' => ['review' => $review]]);
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

test('service request dialogs use labelled unique fields and the shared API form handler', function (): void {
    $twig = new Environment(new ArrayLoader([
        'partial_card_heading.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/client/html/partial_card_heading.html.twig'),
        'partial_card_heading_shared.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/shared/html/partial_card_heading_shared.html.twig'),
        'manage' => file_get_contents(PATH_MODS . '/Order/templates/client/mod_order_manage.html.twig'),
        'layout_default.html.twig' => '{% block content %}{% endblock %}{% block js %}{% endblock %}',
        'macro_functions.html.twig' => '{% macro status_badge(status, type) %}{{ status }}{% endmacro %}',
        'partial_order_attention.html.twig' => '',
    ]), ['autoescape' => 'html']);
    $twig->addFilter(new TwigFilter('trans', static fn (string $text): string => $text));
    $twig->addFilter(new TwigFilter('url', static fn (string $text): string => '/' . $text));
    $twig->addFilter(new TwigFilter('api_url', static fn (string $text, array $query = []): string => '/api/client/' . $text));
    foreach (['format_currency', 'format_date', 'period_title', 'daysleft', 'markdown_to_html'] as $filter) {
        $twig->addFilter(new TwigFilter($filter, static fn (mixed $value): string => (string) $value));
    }
    $twig->addFunction(new TwigFunction('render_widgets', static fn (): string => ''));
    $twig->addFunction(new TwigFunction('wysiwyg', static fn (): string => ''));
    $twig->addFunction(new TwigFunction('fb_api_link', static fn (): string => ''));
    $twig->addFunction(new TwigFunction('fb_api_form', static fn (): string => 'method="post" data-fb-api="{}"', ['is_safe' => ['html']]));
    $html = $twig->render('manage', [
        'order' => ['id' => 6, 'title' => 'Example service', 'status' => 'pending_setup', 'total' => 5, 'currency' => 'USD'],
        'request' => [],
        'client' => new Tests\Support\PermissiveStub(['order_addons' => [], 'order_upgradables' => [7 => 'Larger plan'], 'support_helpdesk_get_pairs' => [1 => 'Support']]),
    ]);
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//h1')->length)->toBe(1)
        ->and($xpath->query('//form[@data-fb-api and @action="/api/client/support/ticket_create"]')->length)->toBe(3)
        ->and($html)->toContain('editor-textarea', 'name="rel_task" value="cancel"', 'name="rel_task" value="upgrade"', 'onServiceTicketCreated');
    $ids = [];
    foreach ($xpath->query('//*[@id]') as $element) {
        $id = $element->getAttribute('id');
        expect($ids)->not->toContain($id);
        $ids[] = $id;
    }
    foreach ($xpath->query('//label[@for]') as $label) {
        expect($document->getElementById($label->getAttribute('for')))->not->toBeNull();
    }
    foreach ($xpath->query('//*[@aria-labelledby]') as $dialog) {
        expect($document->getElementById($dialog->getAttribute('aria-labelledby')))->not->toBeNull();
    }
});
