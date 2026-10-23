<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

// cspell:ignore guesthash

use Tests\Support\ClientThemeRenderer;
use Twig\Environment;
use Twig\TwigFilter;
use Twig\TwigFunction;

function clientTicketTemplates(): Environment
{
    $twig = ClientThemeRenderer::environment([
        'layout_default.html.twig' => '{% block content %}{% endblock %}{% block js %}{% endblock %}',
        'macro_functions.html.twig' => '{% macro status_badge(status, type) %}{{ status }}{% endmacro %}{% macro markdown_quote(text) %}{{ text }}{% endmacro %}',
        'partial_pagination.html.twig' => '',
    ]);
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

        public function __construct(private readonly bool $empty)
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
    $html = clientTicketTemplates()->render('mod_support_tickets.html.twig', [
        'client' => $client,
        'guest' => ['support_kb_suggestions_enabled' => false],
        'request' => ['status' => $status],
    ]);
    expect($html)->toContain('status=' . $expectedStatus . '" aria-current="page"', $expectedText)
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
    $html = clientTicketTemplates()->render('mod_support_ticket.html.twig', [
        'client' => true,
        'request' => [],
        'ticket' => [
            'id' => 5, 'subject' => 'A <conversation>', 'status' => $status, 'hash' => 'guesthash',
            'author' => ['role' => $role], 'helpdesk' => ['name' => 'Billing', 'can_reopen' => $reopen],
            'messages' => [
                ['id' => 8, 'content' => 'Original message', 'created_at' => '2026-10-20 11:00:00', 'author' => ['name' => 'Client', 'role' => 'client']],
                ['id' => 9, 'content' => 'Support reply', 'created_at' => '2026-10-20 12:00:00', 'author' => ['name' => '<Agent>', 'role' => 'admin']],
            ],
        ],
    ]);
    expect($html)->toContain('id="message-9"', 'href="#message-9"', 'A &lt;conversation&gt;', '&lt;Agent&gt;');
    expect(str_contains($html, 'id="ticket-reply-form"'))->toBe($canReply)
        ->and(str_contains($html, 'data-client-ticket-read="5"'))->toBe($role !== 'guest')
        ->and(str_contains($html, 'data-through-message-id="9"'))->toBe($role !== 'guest');
    if ($canReply) {
        expect($html)->toContain('data-unsaved-changes', 'for="ticket-reply-text"', $status === 'closed' ? 'Send Reply and Reopen' : 'Send Reply');
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

test('closed guest history offers a new ticket without client-only controls', function (): void {
    $html = clientTicketTemplates()->render('mod_support_ticket.html.twig', [
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
