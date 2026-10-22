<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use FOSSBilling\Twig\Markdown\FOSSBillingMarkdown;
use Symfony\Component\Filesystem\Filesystem;
use Tests\Support\PermissiveStub;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;

function clientNewsTemplates(): Environment
{
    $twig = new Environment(new ArrayLoader([
        'partial_client_search.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/client/html/partial_client_search.html.twig'),
        'partial_search_form_shared.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/shared/html/partial_search_form_shared.html.twig'),
        'partial_page_heading.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/client/html/partial_page_heading.html.twig'),
        'partial_page_heading_shared.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/shared/html/partial_page_heading_shared.html.twig'),
        'partial_card_heading_shared.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/shared/html/partial_card_heading_shared.html.twig'),
        'partial_client_empty.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/client/html/partial_client_empty.html.twig'),
        'partial_empty_state.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/shared/html/partial_empty_state.html.twig'),
        'list' => (new Filesystem())->readFile(PATH_MODS . '/News/templates/client/mod_news_index.html.twig'),
        'post' => (new Filesystem())->readFile(PATH_MODS . '/News/templates/client/mod_news_post.html.twig'),
        'layout_default.html.twig' => '{% block head %}{% endblock %}{% block content %}{% endblock %}',
        'partial_pagination.html.twig' => '{{ url }}?search={{ request.search|default(\'\') }}&page={{ request.page|default(1) }}',
    ]), ['autoescape' => 'html', 'strict_variables' => true]);
    $twig->addFilter(new TwigFilter('trans', static fn (string $text, array $params = []): string => strtr($text, $params)));
    $twig->addFilter(new TwigFilter('url', static fn (string $path, array $query = []): string => '/' . $path . ($query ? '?' . http_build_query($query) : '')));
    $twig->addFilter(new TwigFilter('format_date', static fn (string $date): string => substr($date, 0, 10)));
    $twig->addFilter(new TwigFilter('truncate', static fn (string $text, int $length): string => mb_strlen($text) > $length ? mb_substr($text, 0, $length) . '...' : $text));
    $twig->addFilter(new TwigFilter('public_asset_url', static fn (string $path): string => '/public/assets/' . $path));
    $twig->addFilter(new TwigFilter('stylesheet_tag', static fn (string $path): string => '<link rel="stylesheet" href="' . $path . '">', ['is_safe' => ['html']]));
    $di = new Pimple\Container();
    $di['mod_service'] = $di->protect(static fn () => new class {
        public function getDefaultMarkdownAttributes(?bool $isAdmin): array
        {
            return [];
        }
    });
    $markdown = new FOSSBillingMarkdown($di);
    $twig->addFilter(new TwigFilter('markdown_to_html', $markdown->convert(...), ['is_safe' => ['html']]));

    return $twig;
}

test('news search paginates history and keeps summaries readable without losing result context', function (): void {
    $guest = new class {
        public array $query = [];

        public function news_get_list(array $data): array
        {
            $this->query = $data;

            return ['list' => [[
                'title' => 'A maintenance <update>', 'slug' => 'maintenance-update', 'section' => 'Hosting',
                'published_at' => '2026-10-18', 'created_at' => '2026-10-17',
                'excerpt' => '**Short** summary with [details](https://example.test).', 'description' => 'Unused description', 'content' => 'Unused full content',
            ]]];
        }
    };
    $html = clientNewsTemplates()->render('list', ['guest' => $guest, 'request' => ['ajax' => false, 'search' => ' maintenance ', 'page' => 2]]);
    expect($guest->query)->toBe(['page' => 2, 'per_page' => 5, 'search' => 'maintenance', 'sort' => 'created_at', 'direction' => 'DESC'])
        ->and($html)->toContain('A maintenance &lt;update&gt;', 'Hosting', '2026-10-18', 'Short summary with details.', '/news/maintenance-update?search=maintenance&amp;page=2', 'name="search"', 'value="maintenance"', 'news?search= maintenance &page=2')
        ->and($html)->not->toContain('Unused description', 'Unused full content', '<strong>', '<p><p>');
});

test('news list provides useful empty states', function (string $search, int $total, string $expected): void {
    $html = clientNewsTemplates()->render('list', [
        'guest' => new PermissiveStub(['news_get_list' => ['list' => [], 'total' => $total]]),
        'request' => ['ajax' => false, 'search' => $search],
    ]);
    expect($html)->toContain($expected);
    if ($search) {
        expect($html)->toContain('>Clear</a>');
    }
})->with([
    'no posts' => ['', 0, 'No Announcements Yet'],
    'no matches' => ['unknown', 0, 'No Matching Announcements'],
    'empty page' => ['maintenance', 5, 'Back to First Page'],
]);

test('news article keeps markdown formatting, publication context and return navigation', function (?string $published, string $updated, array $author, bool $showUpdate): void {
    $html = clientNewsTemplates()->render('post', [
        'post' => [
            'title' => 'Maintenance <notice>', 'section' => 'Hosting', 'published_at' => $published, 'created_at' => '2026-10-17', 'updated_at' => $updated,
            'author' => $author, 'image' => '/notice.png', 'content' => "## What to expect\n\n- Short interruption\n- No action needed\n\n[Contact Support](/support)",
        ],
        'request' => ['ajax' => false, 'search' => 'maintenance', 'page' => 2],
    ]);
    expect($html)->toContain('<h1 class="h2 mb-3">Maintenance &lt;notice&gt;</h1>', '/public/assets/css/markdown.css', 'Published', '<h2>What to expect</h2>', '<li>Short interruption</li>', 'href="/support"', 'class="img-fluid rounded mb-4"', '/news?search=maintenance&amp;page=2')
        ->and($html)->toContain($published ?? '2026-10-17');
    if ($showUpdate) {
        expect($html)->toContain('Updated');
    } else {
        expect($html)->not->toContain('Updated');
    }
    if ($author) {
        expect($html)->toContain('By Support');
    } else {
        expect($html)->not->toContain('By ');
    }
})->with([
    'updated publication' => ['2026-10-18', '2026-10-20', ['name' => 'Support'], true],
    'legacy publication without author' => [null, '2026-10-17', [], false],
]);
