<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Tests\Support\StrictTemplateRenderer;

function sortableThRequest(array $overrides = []): array
{
    return array_replace([
        'page' => 1,
        'per_page' => 25,
    ], $overrides);
}

test('sortable th partial renders an inactive header with the muted selector affordance', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_THEMES . '/default/admin/html/partial_sortable_th.html.twig',
        [
            'request' => sortableThRequest(['page' => 3]),
            'label' => 'TLD',
            'sort_key' => 'tld',
            'url' => 'servicedomain',
        ],
        emailMode: false,
    );

    expect($html)->toContain('<th')
        ->toContain('#selector')
        ->not->toContain('aria-sort')
        ->not->toContain('chevron-');
});

test('sortable th partial marks an ascending column with the up indicator', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_THEMES . '/default/admin/html/partial_sortable_th.html.twig',
        [
            'request' => sortableThRequest(['sort' => 'tld', 'direction' => 'ASC']),
            'label' => 'TLD',
            'sort_key' => 'tld',
            'url' => 'servicedomain',
            'hash' => '#tab-tlds',
        ],
        emailMode: false,
    );

    expect($html)->toContain('aria-sort="ascending"')
        ->toContain('#chevron-up')
        ->not->toContain('#chevron-down')
        ->not->toContain('#selector')
        ->toContain('href="#tab-tlds"');
});

test('sortable th partial marks a descending column with the down indicator', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_THEMES . '/default/admin/html/partial_sortable_th.html.twig',
        [
            'request' => sortableThRequest(['sort' => 'tld', 'direction' => 'desc']),
            'label' => 'TLD',
            'sort_key' => 'tld',
            'url' => 'servicedomain',
        ],
        emailMode: false,
    );

    expect($html)->toContain('aria-sort="descending"')
        ->toContain('#chevron-down')
        ->not->toContain('#chevron-up')
        ->not->toContain('#selector');
});

test('sortable th partial only activates the column matching the sort key', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_THEMES . '/default/admin/html/partial_sortable_th.html.twig',
        [
            'request' => sortableThRequest(['sort' => 'price_registration', 'direction' => 'ASC']),
            'label' => 'TLD',
            'sort_key' => 'tld',
            'url' => 'servicedomain',
        ],
        emailMode: false,
    );

    expect($html)->not->toContain('aria-sort')
        ->not->toContain('chevron-')
        ->toContain('#selector');
});

test('sortable th partial supports custom parameter names for multi-list pages', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_THEMES . '/default/admin/html/partial_sortable_th.html.twig',
        [
            'request' => sortableThRequest(['sort' => 'title', 'registrar_sort' => 'title', 'registrar_direction' => 'ASC']),
            'label' => 'Title',
            'sort_key' => 'title',
            'url' => 'servicedomain',
            'hash' => '#tab-registrars',
            'sort_param' => 'registrar_sort',
            'direction_param' => 'registrar_direction',
            'page_param' => 'registrar_page',
        ],
        emailMode: false,
    );

    expect($html)->toContain('aria-sort="ascending"')
        ->toContain('#chevron-up')
        ->not->toContain('#selector')
        ->toContain('href="#tab-registrars"');
});

test('sortable th partial ignores the default sort param when custom names are used', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_THEMES . '/default/admin/html/partial_sortable_th.html.twig',
        [
            'request' => sortableThRequest(['sort' => 'title', 'direction' => 'ASC']),
            'label' => 'Title',
            'sort_key' => 'title',
            'url' => 'servicedomain',
            'sort_param' => 'registrar_sort',
            'direction_param' => 'registrar_direction',
            'page_param' => 'registrar_page',
        ],
        emailMode: false,
    );

    expect($html)->not->toContain('aria-sort')
        ->not->toContain('chevron-')
        ->toContain('#selector');
});

test('sortable th partial passes through extra header classes', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_THEMES . '/default/admin/html/partial_sortable_th.html.twig',
        [
            'request' => sortableThRequest(),
            'label' => 'TLD',
            'sort_key' => 'tld',
            'url' => 'servicedomain',
            'th_class' => 'w-1',
        ],
        emailMode: false,
    );

    expect($html)->toContain('<th class="w-1">');
});

test('client theme sortable th partial renders the same header', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_THEMES . '/default/client/html/partial_sortable_th.html.twig',
        [
            'request' => sortableThRequest(['sort' => 'title', 'direction' => 'DESC']),
            'label' => 'Title',
            'sort_key' => 'title',
            'url' => 'order',
        ],
    );

    expect($html)->toContain('<th')
        ->toContain('>Title<')
        ->toContain('aria-sort="descending"')
        ->toContain('#chevron-down');
});
