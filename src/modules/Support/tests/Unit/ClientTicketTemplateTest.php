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

function clientTicketTemplates(): Environment
{
    $fs = new Filesystem();
    $twig = new Environment(new ArrayLoader([
        'partial_card_heading.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/client/html/partial_card_heading.html.twig'),
        'partial_card_heading_shared.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/shared/html/partial_card_heading_shared.html.twig'),
        'partial_page_heading.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/client/html/partial_page_heading.html.twig'),
        'partial_page_heading_shared.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/shared/html/partial_page_heading_shared.html.twig'),
        'partial_client_empty.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/client/html/partial_client_empty.html.twig'),
        'partial_empty_state.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/shared/html/partial_empty_state.html.twig'),
        'list' => $fs->readFile(PATH_MODS . '/Support/templates/client/mod_support_tickets.html.twig'),
        'conversation' => $fs->readFile(PATH_MODS . '/Support/templates/client/mod_support_ticket.html.twig'),
        'partial_support_ticket_status.html.twig' => $fs->readFile(PATH_MODS . '/Support/templates/client/partial_support_ticket_status.html.twig'),
        'layout_default.html.twig' => '{% block content %}{% endblock %}{% block js %}{% endblock %}',
        'macro_functions.html.twig' => '{% macro status_badge(status, type) %}{{ status }}{% endmacro %}{% macro markdown_quote(text) %}{{ text }}{% endmacro %}',
        'partial_pagination.html.twig' => '',
    ]), ['autoescape' => 'html']);
    $twig->addFilter(new TwigFilter('trans', static fn (string $text, array $params = []): string => strtr($text, $params)));
    $twig->addFilter(new TwigFilter('url', static fn (string $path, array $query = []): string => '/' . $path . ($query ? '?' . http_build_query($query) : '')));
    $twig->addFilter(new TwigFilter('api_url', static fn (string $path, array $query = [], ?string $role = null): string => '/api/' . ($role ?? 'client') . '/' . $path . ($query ? '?' . http_build_query($query) : '')));
    foreach (['format_date', 'format_datetime', 'timeago', 'markdown_to_html', 'public_asset_url', 'stylesheet_tag'] as $filter) {
        $twig->addFilter(new TwigFilter($filter, static fn (?string $text): string => $text ?? ''));
    }
    $twig->addFunction(new TwigFunction('wysiwyg', static fn (): string => ''));
    $twig->addFunction(new TwigFunction('avatar', static fn (): string => ''));
    $twig->addFunction(new TwigFunction('fb_api_form', static fn (array $config): string => 'data-callback="' . $config['callback'] . '"', ['is_safe' => ['html']]));
    $twig->addFunction(new TwigFunction('fb_api_link', static fn (array $config): string => 'href="' . htmlspecialchars($config['href'], ENT_QUOTES) . '" data-fb-api="' . htmlspecialchars(json_encode($config), ENT_QUOTES) . '"', ['is_safe' => ['html']]));

    return $twig;
}

test('ticket views distinguish replies from support updates and preserve explicit filters', function (?string $status, bool $empty, string $expectedStatus, string $expectedText): void {
    $client = new class($empty) {
        public array $queries = [];

        public function __construct(private bool $empty)
        {
        }

        public function support_ticket_get_list(array $data): array
        {
            $this->queries[] = $data;
            $status = $data['status'] ?? 'open';
            $list = $this->empty ? [] : [[
                'id' => 5, 'subject' => 'A <full> conversation subject', 'status' => $status,
                'helpdesk' => ['name' => 'Billing'], 'updated_at' => '2026-10-20 12:00:00',
            ]];

            return ['total' => count($list), 'list' => $list];
        }

        public function support_helpdesk_get_pairs(): array
        {
            return [1 => 'Billing'];
        }
    };
    $html = clientTicketTemplates()->render('list', [
        'client' => $client,
        'guest' => ['support_kb_suggestions_enabled' => false],
        'request' => ['status' => $status],
    ]);
    expect($html)->toContain('status=' . $expectedStatus . '" aria-current="page"', $expectedText)
        ->and($html)->toContain('aria-labelledby="open-ticket-title"', 'id="support-helpdesk-id"', 'action="/api/client/support/ticket_create"', 'onTicketCreated')
        ->and($client->queries[0])->toMatchArray(['sort' => 'updated_at', 'direction' => 'DESC']);
    if (!$empty) {
        expect($html)->toContain('A &lt;full&gt; conversation subject');
        if ($expectedStatus !== 'on_hold') {
            expect($html)->not->toContain('/support/ticket/5#reply-to');
        }
    }
})->with([
    'default reply view' => [null, false, 'on_hold', 'Reply to Ticket #5'],
    'support pending' => ['open', false, 'open', 'View Conversation'],
    'closed history' => ['closed', false, 'closed', 'View Conversation'],
    'all history' => ['all', false, 'all', 'View Conversation'],
    'invalid filter' => ['unexpected', false, 'on_hold', 'Reply to Ticket #5'],
    'no replies' => ['on_hold', true, 'on_hold', 'No Tickets Need Your Reply'],
    'no tickets' => [null, true, 'all', 'You Have No Support Tickets Yet'],
]);

test('conversations respect reopening rules and keep notification message anchors', function (string $role, string $status, bool $reopen, bool $canReply): void {
    $html = clientTicketTemplates()->render('conversation', [
        'client' => true,
        'request' => [],
        'ticket' => [
            'id' => 5, 'subject' => 'A conversation', 'status' => $status, 'hash' => 'guesthash',
            'author' => ['role' => $role], 'helpdesk' => ['name' => 'Billing', 'can_reopen' => $reopen],
            'messages' => [['id' => 9, 'content' => 'Support reply', 'created_at' => '2026-10-20 12:00:00', 'author' => ['name' => 'Agent', 'role' => 'admin']]],
        ],
    ]);
    expect($html)->toContain('id="message-9"', '<h1 class="card-title mb-2">A conversation</h1>');
    expect(str_contains($html, 'id="ticket-reply-form"'))->toBe($canReply)
        ->and(str_contains($html, 'data-client-ticket-read="5"'))->toBe($role !== 'guest');
    if ($canReply) {
        expect($html)->toContain('for="ticket-reply-text"', $status === 'closed' ? 'Send Reply and Reopen' : 'Send Reply');
    }
    if ($status !== 'closed') {
        expect($html)->toContain($role === 'guest' ? '/api/guest/support/ticket_close?hash=guesthash' : '/api/client/support/ticket_close?id=5');
    }
})->with([
    'client reply' => ['client', 'on_hold', false, true],
    'client reopen' => ['client', 'closed', true, true],
    'client cannot reopen' => ['client', 'closed', false, false],
    'guest reply' => ['guest', 'open', false, true],
    'guest cannot reopen' => ['guest', 'closed', true, false],
]);

test('ticket history presents authors, timestamps and stable links in conversation order', function (): void {
    $html = clientTicketTemplates()->render('conversation', [
        'client' => true, 'request' => [],
        'ticket' => [
            'id' => 5, 'subject' => '<Ticket>', 'status' => 'on_hold', 'updated_at' => '2026-10-20 18:00:00',
            'author' => ['role' => 'client'], 'helpdesk' => ['name' => 'Billing', 'can_reopen' => true],
            'messages' => [
                ['id' => 9, 'content' => 'Original message', 'created_at' => '2026-10-20 12:00:00', 'author' => ['name' => '<Client>', 'role' => 'client']],
                ['id' => 10, 'content' => 'Staff response', 'created_at' => '2026-10-20 18:00:00', 'author' => ['name' => 'Agent', 'role' => 'admin']],
            ],
        ],
    ]);
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    expect($html)->toContain('&lt;Ticket&gt;', '&lt;Client&gt;', 'Support has replied.', 'Last Updated', '2026-10-20 18:00:00', 'href="#message-10"', 'data-through-message-id="10"', '/api/client/support/ticket_reply', 'onTicketReplySent')
        ->and($html)->toContain('Keep Ticket Open', 'conversation will remain in your ticket history', 'Closing Ticket')
        ->and($xpath->query('//form[@id="ticket-reply-form" and @data-unsaved-changes]'))->toHaveCount(1)
        ->and($xpath->query('//ol/li[contains(@class,"client-ticket-message")]')->length)->toBe(2)
        ->and($xpath->query('//*[@id="ticket-latest"]/ancestor::li[1]')->item(0)->getAttribute('id'))->toBe('message-10')
        ->and(strpos($html, 'Original message'))->toBeLessThan(strpos($html, 'Staff response'));
});

test('closed guest history offers a new ticket without client-only controls', function (): void {
    $html = clientTicketTemplates()->render('conversation', [
        'client' => null, 'request' => [],
        'ticket' => [
            'id' => 5, 'hash' => 'guesthash', 'subject' => 'Closed conversation', 'status' => 'closed',
            'author' => ['role' => 'guest'], 'helpdesk' => ['name' => 'General', 'can_reopen' => true],
            'messages' => [], 'rel_type' => 'order', 'rel_id' => 42,
        ],
    ]);
    expect($html)->toContain('cannot be reopened', 'No messages are available yet.', 'Open a New Ticket', '/support/contact-us')
        ->not->toContain('ticket-reply-form', 'id="ticket-close"', 'data-client-ticket-read', '/order/service/manage/42', 'class="btn btn-outline-secondary btn-sm btn-quote-reply"');
});
