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
use Twig\TwigFunction;

function clientEmailTemplates(): Environment
{
    $twig = new Environment(new ArrayLoader([
        'partial_client_search.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/client/html/partial_client_search.html.twig'),
        'partial_search_form_shared.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/shared/html/partial_search_form_shared.html.twig'),
        'partial_client_empty.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/client/html/partial_client_empty.html.twig'),
        'partial_empty_state.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/shared/html/partial_empty_state.html.twig'),
        'list' => (new Filesystem())->readFile(PATH_MODS . '/Email/templates/client/mod_email_index.html.twig'),
        'message' => (new Filesystem())->readFile(PATH_MODS . '/Email/templates/client/mod_email_email.html.twig'),
        'layout_default.html.twig' => '{% block content %}{% endblock %}',
        'partial_pagination.html.twig' => '{{ url }}?search={{ request.search }}&page={{ request.page }}',
    ]), ['strict_variables' => true, 'autoescape' => 'html']);
    $twig->addFilter(new TwigFilter('trans', static fn (string $text, array $params = []): string => strtr($text, $params)));
    $twig->addFilter(new TwigFilter('url', static fn (string $path, array $query = []): string => '/' . $path . ($query ? '?' . http_build_query($query) : '')));
    $twig->addFilter(new TwigFilter('api_url', static fn (string $path, array $query = []): string => '/api/client/' . $path . '?' . http_build_query($query)));
    $twig->addFilter(new TwigFilter('format_datetime', static fn (string $date): string => $date));
    $twig->addFunction(new TwigFunction('fb_api_link', static fn (array $options): string => 'data-fb-api="' . htmlspecialchars(json_encode($options), ENT_QUOTES) . '"', ['is_safe' => ['html']]));

    return $twig;
}

test('client email search covers history with full subjects and retains search when opening messages', function (): void {
    $client = new class {
        public array $query = [];

        public function email_get_list(array $data): array
        {
            $this->query = $data;

            return ['list' => [['id' => 7, 'subject' => 'An invoice <subject> longer than the old truncated subject limit', 'sender' => 'Billing <billing@example.test>', 'created_at' => '2026-10-20 12:30']]];
        }
    };
    $html = clientEmailTemplates()->render('list', ['client' => $client, 'request' => ['ajax' => false, 'search' => ' invoice ', 'page' => 2]]);
    expect($client->query)->toBe(['per_page' => 10, 'page' => 2, 'search' => 'invoice', 'sort' => 'created_at', 'direction' => 'DESC'])
        ->and($html)->toContain('method="get"', 'name="search"', 'value="invoice"', 'An invoice &lt;subject&gt; longer than the old truncated subject limit', 'aria-label="Read Email: An invoice &lt;subject&gt;', '/email/7?search=invoice&amp;page=2', 'email?search= invoice &page=2')
        ->and($html)->not->toContain('<iframe', 'email/resend', 'email/delete');
});

test('client email history distinguishes no emails from no search matches', function (string $search, string $message): void {
    $html = clientEmailTemplates()->render('list', [
        'client' => new PermissiveStub(['email_get_list' => ['list' => []]]),
        'request' => ['ajax' => false, 'search' => $search],
    ]);
    expect($html)->toContain($message)->and($html)->not->toContain('<table');
    if ($search) {
        expect($html)->toContain('>Clear</a>');
    }
})->with([
    'no history' => ['', 'No Emails Yet'],
    'no matches' => ['unknown', 'No Matching Emails'],
]);

test('client email reader isolates html and preserves labelled actions and search context', function (): void {
    $html = clientEmailTemplates()->render('message', [
        'email' => ['id' => 7, 'subject' => 'Invoice "review" <today>', 'sender' => 'Billing', 'recipients' => 'client@example.test', 'created_at' => '2026-10-20', 'content_html' => '<style>body{display:none}</style><p>Invoice</p><script>alert(1)</script>'],
        'request' => ['ajax' => false, 'search' => 'invoice', 'page' => 2],
    ]);
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $frame = $document->getElementsByTagName('iframe')->item(0);
    $xpath = new DOMXPath($document);
    $delete = $xpath->query('//a[contains(@href, "email/delete")]')->item(0);
    $confirmation = json_decode($delete->getAttribute('data-fb-api'), true);
    expect($confirmation['modal']['content'])->toContain('Invoice "review" <today>')
        ->and($confirmation['modal']['button'])->toBe('Delete Email')
        ->and($confirmation['modal']['buttonColor'])->toBe('danger')
        ->and($confirmation['modal']['cancelButton'])->toBe('Keep Email')
        ->and($confirmation['redirect'])->toBe('/email?search=invoice&page=2');
    expect($frame->getAttribute('sandbox'))->toBe('allow-popups')
        ->and($frame->getAttribute('srcdoc'))->toContain('<p>Invoice</p>', '<script>alert(1)</script>')
        ->and($frame->getAttribute('title'))->toBe('Message: Invoice "review" <today>')
        ->and($document->getElementsByTagName('script')->length)->toBe(0)
        ->and($html)->toContain('Back to Emails', '/email?search=invoice&amp;page=2', 'Resend Email', 'Delete Email', 'This cannot be undone.', 'Keep Email', 'Deleting Email', 'Email resent', '/api/client/email/resend?id=7');
});

test('client email reader supports plain text and missing subjects', function (): void {
    $html = clientEmailTemplates()->render('message', [
        'email' => ['id' => 7, 'subject' => '', 'sender' => 'Billing', 'recipients' => 'client@example.test', 'created_at' => '2026-10-20', 'content_html' => null, 'content_text' => "Hello <script>\nYour invoice is ready."],
        'request' => ['ajax' => false],
    ]);
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $content = $document->getElementsByTagName('iframe')->item(0)->getAttribute('srcdoc');
    expect($content)->toContain('Hello &lt;script&gt;', "\nYour invoice is ready.", 'name="color-scheme" content="light dark"', 'color: CanvasText')
        ->and($html)->toContain('No Subject');
});

test('client email history recovers when a page becomes empty', function (): void {
    $html = clientEmailTemplates()->render('list', [
        'client' => new PermissiveStub(['email_get_list' => ['list' => [], 'total' => 10]]),
        'request' => ['ajax' => false, 'search' => 'invoice', 'page' => 2],
    ]);
    expect($html)->toContain('No Messages on This Page', 'href="/email?search=invoice"', 'Back to First Page')
        ->and($html)->not->toContain('No Matching Emails', 'No Emails Yet');
});
