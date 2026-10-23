<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Tests\Support\PermissiveStub;
use Tests\Support\StrictTemplateRenderer;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

function emptyStateTwig(string $theme): Environment
{
    $twig = new Environment(new FilesystemLoader([
        PATH_THEMES . '/default/' . $theme . '/html', PATH_THEMES . '/default/shared/html',
    ]), ['autoescape' => 'html', 'strict_variables' => true]);
    $twig->addFilter(new TwigFilter('trans', static fn (string $text): string => $text));
    $twig->addFilter(new TwigFilter('url', static fn (string $path, array $query = []): string => '/' . $path . ($query ? '?' . http_build_query($query) : '')));
    $twig->addFilter(new TwigFilter('markdown_to_html', static fn (string $text): string => $text));

    return $twig;
}

test('admin empty list recovery distinguishes first-use filtered and out-of-range results', function (int $total, bool $filtered, string $title, ?string $action): void {
    $html = emptyStateTwig('admin')->render('partial_admin_list_empty.html.twig', [
        'empty_list' => ['total' => $total], 'empty_list_url' => 'invoice',
        'empty_title' => 'No Invoices Yet', 'empty_description' => 'Invoices will appear here when they are created.',
        'request_has_filters' => $filtered, 'page_param' => 'invoice_page',
        'request' => ['invoice_page' => 999, 'status' => 'paid', 'per_page' => 25],
    ]);
    expect($html)->toContain($title);
    if ($action === null) {
        expect($html)->not->toContain('empty-action');
    } else {
        expect($html)->toContain($action);
    }
    if ($total > 0) {
        expect($html)->toContain('invoice_page=1', 'status=paid', 'per_page=25')->not->toContain('invoice_page=999');
    } elseif ($filtered) {
        expect($html)->toContain('href="/invoice"')->not->toContain('status=paid');
    }
})->with([
    [0, false, 'No Invoices Yet', null],
    [0, true, 'No Matching Results', 'Clear Filters'],
    [50, false, 'No Results on This Page', 'Back to First Page'],
    [50, true, 'No Results on This Page', 'Back to First Page'],
]);

test('main admin lists keep empty recovery outside responsive tables', function (string $module, string $template, string $endpoint, string $title, bool $filtered, int $total): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(PATH_MODS . '/' . $module . '/templates/admin/' . $template . '.html.twig', [
        'request_has_filters' => $filtered,
        'admin' => new PermissiveStub([
            $endpoint => ['list' => [], 'total' => $total, 'pages' => $total > 0 ? 2 : 1, 'per_page' => 25],
            'client_get_statuses' => ['active' => 0, 'suspended' => 0, 'canceled' => 0],
        ]),
    ]);
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    expect($html)->toContain($total > 0 ? 'No Results on This Page' : ($filtered ? 'No Matching Results' : $title));
    expect($xpath->query('//*[@data-fb-empty-state]'))->toHaveCount(1);
    expect($xpath->query('//*[@data-fb-empty-state]/ancestor::*[contains(concat(" ", normalize-space(@class), " "), " table-responsive ")]'))->toHaveCount(0);
})->with([
    ['Invoice', 'mod_invoice_index', 'invoice_get_list', 'No Invoices Yet', false, 0],
    ['Order', 'mod_order_index', 'order_get_list', 'No Orders Yet', true, 0],
    ['Support', 'mod_support_tickets', 'support_ticket_get_list', 'No Support Tickets Yet', false, 50],
    ['Client', 'mod_client_index', 'client_get_list', 'No Clients Yet', false, 0],
]);
