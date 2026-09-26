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

test('client dashboard profile is available to its inherited layout', function (): void {
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
        'layout_default.html.twig' => $layoutTemplate,
        'layout_blank.html.twig' => $layoutTemplate,
        'macro_functions.html.twig' => '{% macro status_badge(status, type) %}{{ status }}{% endmacro %}',
    ]);
    $twig = new Environment($loader, ['cache' => false]);
    $identityFilter = static fn (?string $text): string => $text ?? '';
    foreach (['trans', 'markdown_to_html', 'truncate', 'format_date', 'timeago'] as $filterName) {
        $twig->addFilter(new TwigFilter($filterName, $identityFilter));
    }
    $twig->addFilter(new TwigFilter('url', static fn (string $path, ?array $query = null): string => $path));
    $twig->addFilter(new TwigFilter('format_currency', static fn (int|float|string $amount, ?string $currency = null): string => $amount . ' ' . $currency));
    $twig->addFunction(new TwigFunction('render_widgets', static fn (string $name, array $context = []): string => ''));

    $client = new class {
        public int $dashboardCalls = 0;
        public int $profileCalls = 0;

        public function index_get_dashboard(): array
        {
            ++$this->dashboardCalls;

            return [
                'profile' => [
                    'id' => 14,
                    'email' => 'client@example.test',
                    'balance' => 42,
                    'currency' => 'USD',
                ],
                'tickets' => ['total' => 0, 'open' => 0, 'on_hold' => 0],
                'invoices' => ['total' => 0, 'paid' => 0, 'unpaid' => 0],
                'orders' => ['total' => 0, 'active' => 0, 'expiring' => 0],
                'recent_orders' => [],
                'recent_tickets' => [],
            ];
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
        ->and($html)->toContain('<header>42</header>')
        ->and($html)->toContain('42 USD');
});
