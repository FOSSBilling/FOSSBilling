<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

test('client dashboard profile is available to its inherited layout', function (array $overrides, array $expected, array $absent): void {
    $dashboardTemplate = file_get_contents(PATH_MODS . '/Index/templates/client/mod_index_dashboard.html.twig');
    expect($dashboardTemplate)->toBeString();

    $layoutTemplate = <<<'TWIG'
        {% if client and profile is not defined %}
            {% set profile = client.profile_get %}
        {% endif %}
        <header>{{ profile.balance }}</header>
        {% block content %}{% endblock %}
        TWIG;

    $loader = new ArrayLoader([
        'mod_index_dashboard.html.twig' => $dashboardTemplate,
        'partial_notice.html.twig' => (new Symfony\Component\Filesystem\Filesystem())->readFile(PATH_THEMES . '/default/client/html/partial_notice.html.twig'),
        'partial_notice_shared.html.twig' => (new Symfony\Component\Filesystem\Filesystem())->readFile(PATH_THEMES . '/default/shared/html/partial_notice_shared.html.twig'),
        'layout_default.html.twig' => $layoutTemplate,
        'layout_blank.html.twig' => $layoutTemplate,
        'macro_functions.html.twig' => '{% macro status_badge(status, type) %}{{ status }}{% endmacro %}',
    ]);
    $twig = new Environment($loader, ['cache' => false]);
    $identityFilter = static fn (?string $text): string => $text ?? '';
    $twig->addFilter(new TwigFilter('trans', static fn (string $text, array $params = []): string => strtr($text, $params)));
    foreach (['markdown_to_html', 'truncate', 'format_date', 'timeago'] as $filterName) {
        $twig->addFilter(new TwigFilter($filterName, $identityFilter));
    }
    $twig->addFilter(new TwigFilter('url', static fn (string $path, ?array $query = null): string => $path . ($query ? '?' . http_build_query($query) : '')));
    $twig->addFilter(new TwigFilter('format_currency', static fn (int|float|string $amount, ?string $currency = null): string => $amount . ' ' . $currency));
    $twig->addFunction(new TwigFunction('render_widgets', static fn (string $name, array $context = []): string => ''));

    $client = new class($overrides) {
        public function __construct(private readonly array $overrides)
        {
        }
        public int $dashboardCalls = 0;
        public int $profileCalls = 0;

        public function index_get_dashboard(): array
        {
            ++$this->dashboardCalls;

            return array_replace_recursive([
                'profile' => [
                    'id' => 14,
                    'email' => 'client@example.test',
                    'balance' => 42,
                    'currency' => 'USD',
                ],
                'tickets' => ['total' => 1, 'open' => 0, 'on_hold' => 1],
                'invoices' => ['total' => 0, 'paid' => 0, 'unpaid' => 0],
                'orders' => ['total' => 0, 'active' => 0, 'expiring' => 0, 'needs_review' => 0],
                'recent_orders' => [],
                'recent_tickets' => [['id' => 9, 'subject' => 'A support conversation', 'status' => 'on_hold', 'updated_at' => '2026-10-20 12:00:00']],
            ], $this->overrides);
        }

        public function profile_get(): array
        {
            ++$this->profileCalls;

            return ['balance' => 42];
        }
    };

    $html = $twig->render('mod_index_dashboard.html.twig', [
        'client' => $client,
        'guest' => [],
        'request' => ['ajax' => false],
        'settings' => ['showcase_enabled' => false],
    ]);

    expect($client->dashboardCalls)->toBe(1)
        ->and($client->profileCalls)->toBe(0)
        ->and($html)->toContain('<header>42</header>', '<h1 class="mb-4">Dashboard</h1>')
        ->and($html)->not->toContain('A support conversation')
        ->and($html)->not->toContain('Recent Orders')
        ->and($html)->not->toContain('Support Tickets')
        ->and($html)->not->toContain('was replied to')
        ->and($html)->not->toContain('Client ID')
        ->and($html)->not->toContain('href="client/profile"');
    foreach ($expected as $text) {
        expect($html)->toContain($text);
    }
    foreach (array_merge($absent, ['New Order', 'New Ticket', '>Total<', '>Paid<', '>Active<']) as $text) {
        expect($html)->not->toContain($text);
    }
})->with([
    'nothing to do' => [
        ['tickets' => ['on_hold' => 0]],
        ['No Unpaid Invoices', 'No Services Need Review', 'No Replies Needed', 'View Invoices', 'View Services', 'View Tickets'],
        ['Review Unpaid Invoices', 'Review Services', 'Reply to Support'],
    ],
    'one action in each area' => [
        ['invoices' => ['unpaid' => 1], 'orders' => ['needs_review' => 1], 'tickets' => ['on_hold' => 1]],
        ['1 invoice awaiting payment', '1 service to review', '1 ticket awaiting your reply', 'href="order/service?review=1"', 'href="support?status=on_hold"'],
        ['No Unpaid Invoices', 'No Services Need Review', 'No Replies Needed'],
    ],
    'multiple actions' => [
        ['invoices' => ['unpaid' => 3], 'orders' => ['needs_review' => 2], 'tickets' => ['on_hold' => 4]],
        ['3 invoices awaiting payment', '2 services to review', '4 tickets awaiting your reply'],
        ['No Unpaid Invoices', 'No Services Need Review', 'No Replies Needed'],
    ],
]);
