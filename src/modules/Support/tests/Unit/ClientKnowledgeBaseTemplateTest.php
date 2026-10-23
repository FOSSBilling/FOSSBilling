<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Tests\Support\PermissiveStub;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;

function clientKnowledgeBaseTemplates(): Environment
{
    $templates = [
        'partial_client_search.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/client/html/partial_client_search.html.twig'),
        'partial_search_form_shared.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/shared/html/partial_search_form_shared.html.twig'),
        'partial_client_empty.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/client/html/partial_client_empty.html.twig'),
        'partial_empty_state.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/shared/html/partial_empty_state.html.twig'),
        'layout_default.html.twig' => '{% block head %}{% endblock %}{% block content %}{% endblock %}',
        'partial_pagination.html.twig' => '',
    ];
    foreach (['mod_support_kb_index', 'mod_support_kb_category', 'mod_support_kb_article', 'partial_support_kb_search', 'partial_support_kb_help', 'partial_support_kb_articles'] as $name) {
        $templates[$name . '.html.twig'] = (new Filesystem())->readFile(PATH_MODS . '/Support/templates/client/' . $name . '.html.twig');
    }
    $twig = new Environment(new ArrayLoader($templates), ['autoescape' => 'html', 'strict_variables' => true]);
    $twig->addFilter(new TwigFilter('trans', static fn (string $text, array $params = []): string => strtr($text, $params)));
    $twig->addFilter(new TwigFilter('url', static fn (string $path, array $query = []): string => '/' . $path . ($query ? '?' . http_build_query($query) : '')));
    $twig->addFilter(new TwigFilter('format_date', static fn (string $date): string => substr($date, 0, 10)));
    foreach (['markdown_to_html', 'public_asset_url', 'stylesheet_tag', 'truncate'] as $filter) {
        $twig->addFilter(new TwigFilter($filter, static fn (?string $text): string => $text ?? ''));
    }

    return $twig;
}

test('knowledge base search shows article matches without a false category empty state', function (): void {
    $guest = new class {
        public array $query = [];

        public function support_kb_article_get_list(array $data): array
        {
            $this->query = $data;

            return ['list' => [['title' => 'Pay an <invoice>', 'slug' => 'pay', 'category' => ['slug' => 'billing', 'title' => 'Billing']]]];
        }
    };
    $html = clientKnowledgeBaseTemplates()->render('mod_support_kb_index.html.twig', ['guest' => $guest, 'client' => true, 'request' => ['ajax' => false, 'q' => ' invoice ', 'page' => 2]]);
    expect($guest->query)->toBe(['search' => 'invoice', 'per_page' => 10, 'page' => 2])
        ->and($html)->toContain('name="q"', 'value="invoice"', 'Search Results', 'Pay an &lt;invoice&gt;', 'Billing', '/support/kb/billing/pay?q=invoice&amp;page=2', '/support?ticket=1')
        ->and($html)->not->toContain('No Matching Articles', 'No results found', 'Matching categories');
});

test('knowledge base browsing paginates categories with active article counts', function (): void {
    $guest = new class {
        public array $query = [];

        public function support_kb_category_get_list(array $data): array
        {
            $this->query = $data;

            return ['list' => [['slug' => 'billing', 'title' => 'Billing', 'description' => 'Invoices & payments', 'articles' => [['title' => 'Pay an invoice']]]]];
        }
    };
    $html = clientKnowledgeBaseTemplates()->render('mod_support_kb_index.html.twig', ['guest' => $guest, 'client' => true, 'request' => ['ajax' => false, 'q' => ' ', 'page' => 2]]);
    expect($guest->query)->toBe(['per_page' => 12, 'page' => 2])
        ->and($html)->toContain('Browse by Category', 'Invoices &amp; payments', '1 article', 'href="/support/kb/billing"');
});

test('knowledge base category reads a paginated article list rather than embedding every article', function (): void {
    $guest = new class {
        public array $query = [];

        public function support_kb_article_get_list(array $data): array
        {
            $this->query = $data;

            return ['list' => [['title' => 'Current article', 'slug' => 'current', 'category' => ['slug' => 'billing']]]];
        }
    };
    $html = clientKnowledgeBaseTemplates()->render('mod_support_kb_category.html.twig', [
        'category' => ['id' => 7, 'slug' => 'billing', 'title' => 'Billing', 'description' => 'Payments', 'articles' => [['title' => 'Do not render unpaginated articles']]],
        'guest' => $guest, 'client' => true, 'request' => ['ajax' => false, 'page' => 2],
    ]);
    expect($guest->query)->toBe(['kb_article_category_id' => 7, 'per_page' => 10, 'page' => 2])
        ->and($html)->toContain('Current article', '/support/kb/billing/current?q=&amp;page=2')
        ->and($html)->not->toContain('Do not render unpaginated articles');
});

test('knowledge base has helpful empty states and respects guest support settings', function (string $query, bool $guestTickets, string $message, string $support): void {
    $html = clientKnowledgeBaseTemplates()->render('mod_support_kb_index.html.twig', [
        'guest' => new PermissiveStub(['support_kb_article_get_list' => ['list' => [], 'total' => 0], 'support_kb_category_get_list' => ['list' => [], 'total' => 0], 'support_guest_tickets_enabled' => $guestTickets]),
        'client' => false, 'request' => ['ajax' => false, 'q' => $query],
    ]);
    expect($html)->toContain($message, $support)->and($html)->not->toContain('/support?ticket=1');
})->with([
    'no matches, guest tickets enabled' => ['unknown', true, 'No Matching Articles', '/support/contact-us'],
    'no categories, guest tickets disabled' => ['', false, 'No Help Articles Yet', '/login'],
]);

test('knowledge base reader preserves result context and excludes itself from related articles', function (bool $viewsEnabled, string $query, string $returnUrl): void {
    $article = ['id' => 3, 'title' => 'Pay an invoice', 'slug' => 'pay', 'content' => 'Payment Instructions', 'updated_at' => '2026-10-20', 'views' => 12, 'category' => ['id' => 7, 'slug' => 'billing', 'title' => 'Billing']];
    $guest = new PermissiveStub([
        'support_kb_article_get_list' => ['list' => [$article, array_replace($article, ['id' => 4, 'slug' => 'refund', 'title' => 'Refund questions'])]],
        'support_kb_article_views_enabled' => $viewsEnabled,
    ]);
    $html = clientKnowledgeBaseTemplates()->render('mod_support_kb_article.html.twig', ['article' => $article, 'guest' => $guest, 'client' => true, 'request' => ['ajax' => false, 'q' => $query, 'page' => 2]]);
    expect($html)->toContain($returnUrl, '<h1 class="h2 mb-3">Pay an invoice</h1>', 'Payment Instructions', 'Related Articles', 'Refund questions', '2026-10-20', '/support?ticket=1')
        ->and($html)->not->toContain('/billing/pay');
    if ($viewsEnabled) {
        expect($html)->toContain('12 views');
    } else {
        expect($html)->not->toContain('12 views');
    }
})->with([
    'search, views enabled' => [true, 'invoice', '/support/kb?q=invoice&amp;page=2'],
    'category, views disabled' => [false, '', '/support/kb/billing?page=2'],
]);
