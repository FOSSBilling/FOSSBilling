<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Tests\Support\ClientThemeRenderer;
use Tests\Support\PermissiveStub;

require_once PATH_TESTS . '/Support/ClientThemeRenderer.php';

test('dashboard supplies its profile to the real shell and links to outstanding actions', function (array $overrides, array $expected, array $absent): void {
    $profile = ['id' => 14, 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'client@example.test', 'company' => 'Acme', 'balance' => 99.5, 'currency' => 'USD'];
    $dashboard = array_replace_recursive([
        'profile' => $profile,
        'tickets' => ['on_hold' => 1], 'invoices' => ['unpaid' => 0], 'orders' => ['needs_review' => 0],
    ], $overrides);
    $client = new readonly class($dashboard) {
        public function __construct(private array $dashboard)
        {
        }

        public function __isset(string $name): bool
        {
            return true;
        }

        public function __get(string $name): mixed
        {
            return match ($name) {
                'index_get_dashboard' => $this->dashboard,
                'support_notification_get_list' => ['unread_count' => 0, 'latest_id' => 0, 'list' => []],
                default => new PermissiveStub(),
            };
        }

        public function profile_get(): never
        {
            throw new LogicException('The shell should reuse the dashboard profile.');
        }
    };
    $html = ClientThemeRenderer::render([
        'client' => $client, 'request' => ['ajax' => false],
        'settings' => ['showcase_enabled' => false, 'sidebar_balance_enabled' => true],
    ], 'mod_index_dashboard.html.twig');
    expect($html)->toContain('$99.50');
    foreach ($expected as $text) {
        expect($html)->toContain($text);
    }
    foreach ($absent as $text) {
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
        ['1 invoice awaiting payment', '1 service to review', '1 ticket awaiting your reply', 'href="/order/service?review=1"', 'href="/support?status=on_hold"'],
        ['No Unpaid Invoices', 'No Services Need Review', 'No Replies Needed'],
    ],
    'multiple actions' => [
        ['invoices' => ['unpaid' => 3], 'orders' => ['needs_review' => 2], 'tickets' => ['on_hold' => 4]],
        ['3 invoices awaiting payment', '2 services to review', '4 tickets awaiting your reply'],
        ['No Unpaid Invoices', 'No Services Need Review', 'No Replies Needed'],
    ],
]);
