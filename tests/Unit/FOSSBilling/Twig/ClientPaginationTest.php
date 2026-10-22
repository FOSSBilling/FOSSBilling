<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

function renderClientPagination(mixed $page, int $pages = 500): string
{
    $twig = new Environment(new FilesystemLoader([
        PATH_THEMES . '/default/client/html',
        PATH_THEMES . '/default/shared/html',
    ]), ['strict_variables' => true, 'autoescape' => 'html']);
    $twig->addFilter(new TwigFilter('trans', static fn (string $text, array $params = []): string => strtr($text, $params)));
    $twig->addFilter(new TwigFilter('url', static fn (string $path, array $query): string => '/' . $path . '?' . http_build_query($query)));

    return $twig->render('partial_pagination.html.twig', [
        'guest' => ['system_current_url' => '/email'], 'list' => ['pages' => $pages], 'url' => 'email', 'page_param' => 'history_page', 'hash' => '#history',
        'request' => ['history_page' => $page, 'search' => 'invoice', 'status' => 'paid'],
    ]);
}

test('client pagination is bounded and retains filters custom page keys and anchors', function (): void {
    $html = renderClientPagination(250);
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//li')->length)->toBe(7)
        ->and($html)->toContain('Page 250 of 500', 'aria-current="page">250', 'search=invoice', 'status=paid', 'history_page=249#history', 'history_page=251#history', 'rel="prev"', 'rel="next"');
    expect($xpath->query('//a[normalize-space(.)="1"]')->length)->toBe(0);
});

test('pagination edges do not link to unavailable pages', function (mixed $rawPage, int $expectedPage, string $disabled): void {
    $html = renderClientPagination($rawPage, 3);
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    expect($html)->toContain('Page ' . $expectedPage . ' of 3', 'aria-disabled="true" aria-label="' . $disabled . '"')
        ->and($xpath->query('//li[contains(@class,"disabled")]//a')->length)->toBe(0);
    foreach ($document->getElementsByTagName('a') as $link) {
        parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
        expect((int) $query['history_page'])->toBeBetween(1, 3);
    }
})->with([
    'first' => [1, 1, 'Previous'],
    'last' => [3, 3, 'Next'],
    'zero' => [0, 1, 'Previous'],
    'out of range' => [8, 3, 'Next'],
    'non numeric' => ['invalid', 1, 'Previous'],
]);

test('pagination is hidden for a single page', function (): void {
    expect(renderClientPagination(1, 1))->toBe('');
});
