<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use FOSSBilling\Paginator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

function renderThemePagination(string $theme, array $context = []): string
{
    $twig = new Environment(new FilesystemLoader([
        PATH_THEMES . '/default/' . $theme . '/html',
        PATH_THEMES . '/default/shared/html',
    ]), ['strict_variables' => true, 'autoescape' => 'html']);
    $twig->addFilter(new TwigFilter('trans', static fn (string $text, array $params = []): string => strtr($text, $params)));
    $twig->addFilter(new TwigFilter('url', static fn (string $path, array $query): string => $path . '?' . http_build_query($query)));
    $guest = new class {
        public string $system_current_url = '/email?search=paid';

        public function system_paginator(array $params): array
        {
            return (new Paginator($params['total'], $params[$params['page_param']], $params['per_page']))->toArray();
        }
    };

    return $twig->render('partial_pagination.html.twig', array_replace([
        'app_area' => $theme, 'guest' => $guest, 'request' => ['page' => 5],
        'list' => ['total' => 500, 'pages' => 20, 'per_page' => 25], 'url' => '/history',
    ], $context));
}

function paginationDocument(string $html): DOMXPath
{
    $document = new DOMDocument();
    @$document->loadHTML('<!doctype html><meta charset="utf-8">' . $html);

    return new DOMXPath($document);
}

test('theme pagers retain filters other tab parameters and anchors with real URLs', function (string $theme): void {
    $html = renderThemePagination($theme, [
        'page_param' => 'email_page', 'per_page_param' => 'email_per_page', 'hash' => '#tab-emails',
        'request' => ['email_page' => 5, 'email_per_page' => 25, 'order_page' => 3, 'search' => '<billing & renewal>', 'status' => ['paid', 'unpaid'], 'unused' => null],
    ]);
    $xpath = paginationDocument($html);
    expect($xpath->query('//span[@aria-current="page"]'))->toHaveCount(1);
    expect(trim($xpath->query('//span[@aria-current="page"]')->item(0)->textContent))->toBe('5');
    foreach ($xpath->query('//a') as $link) {
        $href = $link->getAttribute('href');
        parse_str(parse_url($href, PHP_URL_QUERY), $query);
        expect($query)->toHaveKeys(['email_page', 'email_per_page', 'order_page', 'search', 'status'])
            ->and($query['order_page'])->toBe('3')
            ->and($query['search'])->toBe('<billing & renewal>')
            ->and($query['status'])->toBe(['paid', 'unpaid'])
            ->and($query)->not->toHaveKey('unused')
            ->and(parse_url($href, PHP_URL_FRAGMENT))->toBe('tab-emails');
    }
    expect($html)->not->toContain('<billing');
})->with(['client', 'admin']);

test('both themes bound invalid page input before rendering controls', function (mixed $raw, int $expected): void {
    foreach (['client', 'admin'] as $theme) {
        $xpath = paginationDocument(renderThemePagination($theme, ['request' => ['page' => $raw]]));
        expect(trim($xpath->query('//span[@aria-current="page"]')->item(0)->textContent))->toBe((string) $expected);
        foreach ($xpath->query('//ul[contains(@class,"pagination")]//a') as $link) {
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
            expect((int) $query['page'])->toBeBetween(1, 20);
        }
    }
})->with([
    [0, 1], [-2, 1], ['invalid', 1], ['2.5', 1], [['page' => 3], 1], [999, 20], ['999999999999999999999999', 20],
]);

test('shared controls label arrows hide decorative icons and keep current and disabled pages out of links', function (string $theme): void {
    $xpath = paginationDocument(renderThemePagination($theme));
    expect($xpath->query('//a[@rel="prev" and @aria-label="Previous"]'))->toHaveCount(1)
        ->and($xpath->query('//a[@rel="next" and @aria-label="Next"]'))->toHaveCount(1)
        ->and($xpath->query('//svg[not(@aria-hidden="true")]'))->toHaveCount(0)
        ->and($xpath->query('//li[contains(@class,"active")]//a'))->toHaveCount(0)
        ->and($xpath->query('//li[contains(@class,"disabled")]//a'))->toHaveCount(0)
        ->and($xpath->query('//ul[contains(@class,"pagination")]/text()[normalize-space(.)!=""]'))->toHaveCount(0);
})->with(['client', 'admin']);

test('client pagination keeps a compact window even for large histories', function (): void {
    $html = renderThemePagination('client', [
        'request' => ['page' => 250], 'list' => ['total' => 12500, 'pages' => 500, 'per_page' => 25],
    ]);
    $xpath = paginationDocument($html);
    expect($xpath->query('//ul[contains(@class,"pagination")]/li'))->toHaveCount(7)
        ->and($html)->toContain('Page 250 of 500')
        ->and($xpath->query('//a[normalize-space(.)="1"]'))->toHaveCount(0);
});

test('pagination edges disable unavailable directions without clickable links', function (string $theme, int $page, string $disabled): void {
    $xpath = paginationDocument(renderThemePagination($theme, ['request' => ['page' => $page]]));
    expect($xpath->query('//a[@aria-label="' . $disabled . '"]'))->toHaveCount(0)
        ->and($xpath->query('//li[contains(@class,"disabled")]//a'))->toHaveCount(0);
    if ($theme === 'client') {
        expect($xpath->query('//span[@aria-disabled="true" and @aria-label="' . $disabled . '"]'))->toHaveCount(1);
    }
})->with(['client', 'admin'])->with([[1, 'Previous'], [20, 'Next']]);

test('admin preserves its wider bounded range and valid ellipsis list items', function (): void {
    $xpath = paginationDocument(renderThemePagination('admin', [
        'request' => ['page' => 250], 'list' => ['total' => 12500, 'pages' => 500, 'per_page' => 25],
    ]));
    expect($xpath->query('//ul[contains(@class,"pagination")]/li'))->toHaveCount(13)
        ->and($xpath->query('//li[contains(@class,"disabled")]/span[@aria-hidden="true"]'))->toHaveCount(2)
        ->and($xpath->query('//a[normalize-space(.)="1"]'))->toHaveCount(1)
        ->and($xpath->query('//a[normalize-space(.)="500"]'))->toHaveCount(1);
});

test('admin record selector resets only its own page and honours custom options', function (): void {
    $xpath = paginationDocument(renderThemePagination('admin', [
        'page_param' => 'ticket_page', 'per_page_param' => 'ticket_per_page',
        'per_page_options' => [10, 25, 50], 'split_footer_controls' => true,
        'request' => ['ticket_page' => 5, 'ticket_per_page' => 40, 'order_page' => 7, 'search' => 'hosting'],
    ]));
    $links = $xpath->query('//a[contains(@class,"dropdown-item")]');
    expect($links)->toHaveCount(4);
    foreach ($links as $link) {
        parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
        expect($query['ticket_page'])->toBe('1')
            ->and($query['order_page'])->toBe('7')
            ->and($query['search'])->toBe('hosting')
            ->and((int) $query['ticket_per_page'])->toBeIn([10, 25, 40, 50]);
    }
    expect($xpath->query('//a[contains(@class,"dropdown-item") and contains(@class,"active")]'))->toHaveCount(1);
    expect($xpath->query('//nav[contains(@class,"ms-auto")]'))->toHaveCount(1);
});

test('record selector can be disabled explicitly and is never added to client pagination', function (): void {
    expect(renderThemePagination('admin', ['show_per_page_selector' => false]))->not->toContain('dropdown-toggle');
    expect(renderThemePagination('client'))->not->toContain('dropdown-toggle');
    expect(renderThemePagination('admin', ['app_area' => 'client']))->not->toContain('dropdown-toggle');
});

test('invalid admin page sizes fall back without introducing a zero records choice', function (mixed $raw): void {
    $xpath = paginationDocument(renderThemePagination('admin', ['request' => ['per_page' => $raw]]));
    $active = $xpath->query('//a[contains(@class,"dropdown-item") and contains(@class,"active")]');
    expect($active)->toHaveCount(1)->and(trim($active->item(0)->textContent))->toBe('25 records');
})->with([[0], [-1], ['invalid'], [['bad']]]);

test('pagination preserves the different missing URL contracts', function (): void {
    expect(trim(renderThemePagination('admin', ['url' => null])))->toBe('');
    expect(renderThemePagination('client', ['url' => null]))->toContain('href="/email?page=');
});

test('single page lists hide navigation while admin can still offer its record selector', function (): void {
    foreach (['client', 'admin'] as $theme) {
        $html = renderThemePagination($theme, ['list' => ['total' => 5, 'pages' => 1, 'per_page' => 25]]);
        expect(trim($html))->toBe('');
    }
    $html = renderThemePagination('admin', ['list' => ['total' => 20, 'pages' => 1, 'per_page' => 25]]);
    expect($html)->toContain('dropdown-toggle')->not->toContain('<nav');
});
