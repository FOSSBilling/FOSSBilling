<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

function searchPartialTwig(string $theme): Environment
{
    $twig = new Environment(new FilesystemLoader([
        PATH_THEMES . '/default/' . $theme . '/html', PATH_THEMES . '/default/shared/html',
    ]), ['strict_variables' => true, 'autoescape' => 'html']);
    $twig->addFilter(new TwigFilter('trans', static fn (string $text): string => $text));

    return $twig;
}

test('client search keeps visible labels routes parameter names and escaped values', function (string $name): void {
    $html = searchPartialTwig('client')->render('partial_client_search.html.twig', [
        'search_id' => 'help-search', 'search_label' => 'Search Help', 'search_name' => $name,
        'search_value' => '"><script>test</script>', 'search_placeholder' => 'Find <answers>',
        'search_action' => '/support/kb', 'search_clear_url' => '/support/kb',
    ]);
    expect($html)->toContain('role="search"', 'aria-label="Search Help"', 'method="get"', 'action="/support/kb"',
        '<label class="form-label" for="help-search">Search Help</label>', 'name="' . $name . '"',
        'id="help-search"', 'enterkeyhint="search"', '&lt;script&gt;', 'Find &lt;answers&gt;', 'href="/support/kb"')
        ->not->toContain('<script>', 'data-fb-api', 'search-filter-toggle');
})->with(['search', 'q']);

test('blank client search does not show a clear action', function (): void {
    $html = searchPartialTwig('client')->render('partial_client_search.html.twig', [
        'search_id' => 'search', 'search_label' => 'Search', 'search_action' => '/news', 'search_clear_url' => '/news',
    ]);
    expect($html)->not->toContain('href=', 'type="hidden"');
});

test('compact admin search preserves allowlisted filters and explicit extension fields without duplicate search', function (): void {
    $html = searchPartialTwig('admin')->render('partial_search_action.html.twig', [
        'request' => ['search' => 'demo', 'status' => 'active', 'page' => 8, 'per_page' => 50, 'unrelated' => 'discard'],
        'search_preserve_fields' => ['status', 'per_page'],
        'search_hidden_fields' => ['extension' => 'custom', 'status' => 'suspended', 'search' => 'old'],
        'has_filter_panel' => true, 'filter_panel_id' => 'custom-filters', 'filter_panel_open' => true,
    ]);
    expect($html)->toContain('role="search"', 'value="demo"', 'name="status" value="suspended"', 'name="per_page" value="50"',
        'name="extension" value="custom"', 'data-bs-target="#custom-filters"', 'aria-controls="custom-filters"',
        'aria-expanded="true"', 'aria-label="Search Settings"', 'aria-hidden="true"')
        ->not->toContain('name="page"', 'unrelated', 'value="old"');
    expect(substr_count($html, 'name="search"'))->toBe(1);
});

test('admin toolbar variants share a single GET form and preserve trusted action markup', function (bool $actions): void {
    $html = searchPartialTwig('admin')->render('partial_search.html.twig', [
        'request' => ['search' => 'test'], 'search_toolbar_actions' => $actions ? '<a href="/new">New</a>' : '',
        'search_hidden_fields' => ['type' => 'email'],
    ]);
    expect(substr_count($html, '<form'))->toBe(1);
    expect($html)->toContain('name="type" value="email"', 'name="search"', 'value="test"');
    expect(str_contains($html, '<a href="/new">New</a>'))->toBe($actions);
})->with([true, false]);

test('main admin searches retain active filters while starting a new result page', function (string $module, string $template, string $endpoint): void {
    $html = (new Tests\Support\StrictTemplateRenderer())->renderTemplate(PATH_MODS . '/' . $module . '/templates/admin/' . $template . '.html.twig', [
        'request' => new Tests\Support\PermissiveStub(['search' => 'demo', 'status' => 'active', 'page' => 4, 'per_page' => 25, 'date_from' => '2026-10-01', 'sort' => 'id', 'direction' => 'DESC']),
        'admin' => new Tests\Support\PermissiveStub([
            $endpoint => ['list' => [], 'total' => 0, 'pages' => 1, 'per_page' => 25],
            'client_get_statuses' => ['active' => 0, 'suspended' => 0, 'canceled' => 0],
        ]),
    ]);
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    $fields = [];
    foreach ($xpath->query('//form[contains(@class,"fb-search-form")]//input') as $field) {
        $fields[$field->getAttribute('name')] = $field->getAttribute('value');
    }
    expect($fields)->toMatchArray(['search' => 'demo', 'status' => 'active', 'date_from' => '2026-10-01', 'sort' => 'id', 'direction' => 'DESC', 'per_page' => '25']);
    expect($fields)->not->toHaveKey('page');
})->with([
    ['Invoice', 'mod_invoice_index', 'invoice_get_list'], ['Order', 'mod_order_index', 'order_get_list'],
    ['Support', 'mod_support_tickets', 'support_ticket_get_list'], ['Client', 'mod_client_index', 'client_get_list'],
]);
