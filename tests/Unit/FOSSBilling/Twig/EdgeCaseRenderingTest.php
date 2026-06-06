<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Tests\Support\StrictTemplateRenderer;

/*
 * Render targeted templates with realistic edge-case data shapes to catch the
 * class of bug where a template accesses a nested key that is missing or empty
 * for some real-world data (e.g. a domain product's `pricing` array is keyed
 * by TLD rather than the standard `type`/`recurrent`/`once`/`free` keys).
 *
 * The full render-everything test in StrictVariablesTest.php uses
 * PermissiveStub for every variable, which masks these shape mismatches.
 * This test exercises specific real-world shapes that have caused production
 * errors.
 *
 * Each test asserts that the render does not throw. The render either
 * succeeds (template was defensive about the edge case) or throws a
 * Twig\Error\RuntimeError (template has the bug). There is no in-between.
 */

function edgeCaseRequest(array $overrides = []): array
{
    return array_replace([
        'ajax' => true,
        'show_custom_form_values' => false,
        'search' => '',
        'ip' => '',
        'admin_id' => null,
        'client_id' => null,
        'date_from' => null,
        'date_to' => null,
        'user_filter' => null,
        'min_priority' => null,
        'priority' => null,
        'page' => 1,
    ], $overrides);
}

function edgeCaseAdmin(array $methods = []): PermissiveCallableStub
{
    return new PermissiveCallableStub(array_replace([
        'support_helpdesk_get_pairs' => [1 => 'Helpdesk'],
        'support_canned_pairs' => [],
        'product_get_pairs' => [],
    ], $methods));
}

function edgeCaseGuest(array $methods = []): PermissiveCallableStub
{
    return new PermissiveCallableStub(array_replace([
        'system_periods' => ['1M' => 'Monthly', '1Y' => 'Yearly'],
        'cart_get_currency' => [
            'code' => 'USD',
            'conversion_rate' => 1.0,
        ],
        'system_template_exists' => false,
        'extension_is_on' => false,
    ], $methods));
}

function edgeCaseCartCurrency(): array
{
    return [
        'code' => 'USD',
        'conversion_rate' => 1.0,
    ];
}

test('orderbutton product configuration renders a domain product', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_MODS . '/Orderbutton/templates/client/mod_orderbutton_product_configuration.html.twig',
        [
            'app_area' => 'client',
            'request' => edgeCaseRequest(),
            'guest' => edgeCaseGuest(),
            'cart_currency' => edgeCaseCartCurrency(),
            'product' => [
                'id' => 1,
                'type' => 'domain',
                'title' => 'Domain Registration',
                'description' => 'Register a domain name',
                'pricing' => [
                    '.com' => ['price' => 10.0, 'enabled' => true, 'setup' => '0.00'],
                    '.net' => ['price' => 12.0, 'enabled' => true, 'setup' => '0.00'],
                ],
                'allow_quantity_select' => false,
                'form_id' => null,
            ],
        ],
    );

    expect($html)->toBeString();
});

test('orderbutton product configuration renders a hosting product', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_MODS . '/Orderbutton/templates/client/mod_orderbutton_product_configuration.html.twig',
        [
            'app_area' => 'client',
            'request' => edgeCaseRequest(),
            'guest' => edgeCaseGuest(),
            'cart_currency' => edgeCaseCartCurrency(),
            'product' => [
                'id' => 5,
                'type' => 'hosting',
                'title' => 'Test Hosting',
                'description' => 'A hosting product',
                'pricing' => [
                    'type' => 'recurrent',
                    'recurrent' => [
                        '1M' => ['price' => 9.99, 'enabled' => true, 'setup' => '0.00'],
                        '1Y' => ['price' => 99.99, 'enabled' => true, 'setup' => '0.00'],
                    ],
                ],
                'allow_quantity_select' => true,
                'quantity_in_stock' => 10,
                'stock_control' => true,
                'form_id' => null,
            ],
        ],
    );

    expect($html)->toBeString();
});

test('orderbutton product configuration renders a one-time payment product', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_MODS . '/Orderbutton/templates/client/mod_orderbutton_product_configuration.html.twig',
        [
            'app_area' => 'client',
            'request' => edgeCaseRequest(),
            'guest' => edgeCaseGuest(),
            'cart_currency' => edgeCaseCartCurrency(),
            'product' => [
                'id' => 2,
                'type' => 'custom',
                'title' => 'Security Suite',
                'description' => null,
                'pricing' => [
                    'type' => 'once',
                    'once' => ['price' => 49.99, 'setup' => '0.00'],
                ],
                'allow_quantity_select' => false,
                'form_id' => null,
            ],
        ],
    );

    expect($html)->toBeString();
});

test('orderbutton addons renders a product without addons key', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_MODS . '/Orderbutton/templates/client/mod_orderbutton_addons.html.twig',
        [
            'app_area' => 'client',
            'request' => edgeCaseRequest(),
            'guest' => edgeCaseGuest(),
            'cart_currency' => edgeCaseCartCurrency(),
            'product' => [
                'id' => 1,
                'title' => 'Domain Registration',
            ],
        ],
    );

    expect($html)->toBeString();
});

test('activity index renders a staff-only event (no client key)', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_MODS . '/Activity/templates/admin/mod_activity_index.html.twig',
        [
            'app_area' => 'admin',
            'request' => edgeCaseRequest(),
            'admin' => edgeCaseAdmin([
                'activity_log_get_list' => [
                    'list' => [
                        [
                            'id' => 1,
                            'priority' => 6,
                            'message' => 'Admin logged in',
                            'ip' => '1.2.3.4',
                            'created_at' => '2026-01-01 00:00:00',
                            'client_id' => null,
                            'admin_id' => 1,
                            'staff_id' => 1,
                            'staff' => ['id' => 1, 'name' => 'Admin', 'email' => 'admin@example.com'],
                        ],
                    ],
                    'pages' => 1,
                    'per_page' => 25,
                    'page' => 1,
                    'total' => 1,
                ],
            ]),
        ],
    );

    expect($html)->toBeString();
});

test('activity index renders a system event (no client, no staff)', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_MODS . '/Activity/templates/admin/mod_activity_index.html.twig',
        [
            'app_area' => 'admin',
            'request' => edgeCaseRequest(),
            'admin' => edgeCaseAdmin([
                'activity_log_get_list' => [
                    'list' => [
                        [
                            'id' => 1,
                            'priority' => 6,
                            'message' => 'System cron ran',
                            'ip' => '127.0.0.1',
                            'created_at' => '2026-01-01 00:00:00',
                            'client_id' => null,
                            'admin_id' => null,
                            'staff_id' => null,
                        ],
                    ],
                    'pages' => 1,
                    'per_page' => 25,
                    'page' => 1,
                    'total' => 1,
                ],
            ]),
        ],
    );

    expect($html)->toBeString();
});

test('activity index renders a mixed list of all three event shapes', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_MODS . '/Activity/templates/admin/mod_activity_index.html.twig',
        [
            'app_area' => 'admin',
            'request' => edgeCaseRequest(),
            'admin' => edgeCaseAdmin([
                'activity_log_get_list' => [
                    'list' => [
                        [
                            'id' => 1, 'priority' => 6, 'message' => 'Admin', 'ip' => '1.2.3.4',
                            'created_at' => '2026-01-01 00:00:00', 'client_id' => null,
                            'admin_id' => 1, 'staff_id' => 1,
                            'staff' => ['id' => 1, 'name' => 'Admin', 'email' => 'a@a.com'],
                        ],
                        [
                            'id' => 2, 'priority' => 6, 'message' => 'Client', 'ip' => '1.2.3.5',
                            'created_at' => '2026-01-01 00:00:01', 'client_id' => 1,
                            'admin_id' => null, 'staff_id' => null,
                            'client' => ['id' => 1, 'name' => 'C', 'email' => 'c@c.com'],
                        ],
                        [
                            'id' => 3, 'priority' => 6, 'message' => 'System', 'ip' => '127.0.0.1',
                            'created_at' => '2026-01-01 00:00:02', 'client_id' => null,
                            'admin_id' => null, 'staff_id' => null,
                        ],
                    ],
                    'pages' => 1,
                    'per_page' => 25,
                    'page' => 1,
                    'total' => 1,
                ],
            ]),
        ],
    );

    expect($html)->toBeString();
});

test('security iplookup renders with empty asn array', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_MODS . '/Security/templates/admin/mod_security_iplookup.html.twig',
        [
            'app_area' => 'admin',
            'request' => edgeCaseRequest(['ip' => '192.168.1.1']),
            'record' => [
                'ip' => ['address' => '192.168.1.1', 'type' => 'IPv4'],
                'country' => ['flag' => '', 'name' => 'Australia'],
                'asn' => [],
            ],
        ],
    );

    expect($html)->toBeString();
});

test('security iplookup renders with empty country array', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_MODS . '/Security/templates/admin/mod_security_iplookup.html.twig',
        [
            'app_area' => 'admin',
            'request' => edgeCaseRequest(['ip' => '0.0.0.0']),
            'record' => [
                'ip' => ['address' => '0.0.0.0', 'type' => 'IPv4'],
                'country' => [],
                'asn' => ['asnNumber' => 13335, 'asnOrg' => 'CLOUDFLARENET'],
            ],
        ],
    );

    expect($html)->toBeString();
});

test('security iplookup renders with no record (initial page load)', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_MODS . '/Security/templates/admin/mod_security_iplookup.html.twig',
        [
            'app_area' => 'admin',
            'request' => edgeCaseRequest(),
            'record' => null,
        ],
    );

    expect($html)->toBeString();
});

test('support admin ticket renders with no notes and no rel', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_MODS . '/Support/templates/admin/mod_support_ticket.html.twig',
        [
            'app_area' => 'admin',
            'request' => edgeCaseRequest(['id' => 1]),
            'admin' => edgeCaseAdmin(),
            'profile' => [
                'id' => 1,
                'name' => 'Test Admin',
                'email' => 'admin@example.com',
                'signature' => '-- Admin',
            ],
            'canned_delay_message' => '',
            'request_message' => null,
            'ticket' => [
                'id' => 1,
                'status' => 'open',
                'subject' => 'Test ticket',
                'priority' => 6,
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
                'support_helpdesk_id' => 1,
                'client_id' => 1,
                'rel_type' => null,
                'rel_id' => null,
                'rel_task' => null,
                'rel_new_value' => null,
                'rel_status' => null,
                'rel' => [
                    'id' => null,
                    'type' => null,
                    'task' => null,
                    'new_value' => null,
                    'status' => null,
                ],
                'notes' => [],
                'messages' => [],
                'replies' => 0,
                'first' => null,
                'helpdesk' => ['id' => 1, 'name' => 'Helpdesk', 'can_reopen' => true, 'signature' => ''],
                'client' => ['id' => 1, 'first_name' => 'Test', 'last_name' => 'Client'],
            ],
        ],
    );

    expect($html)->toBeString();
});

test('support admin ticket renders with a related order and a note', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_MODS . '/Support/templates/admin/mod_support_ticket.html.twig',
        [
            'app_area' => 'admin',
            'request' => edgeCaseRequest(['id' => 1]),
            'admin' => edgeCaseAdmin(),
            'profile' => [
                'id' => 1,
                'name' => 'Test Admin',
                'email' => 'admin@example.com',
                'signature' => '-- Admin',
            ],
            'canned_delay_message' => '',
            'request_message' => null,
            'ticket' => [
                'id' => 1,
                'status' => 'open',
                'subject' => 'Issue with order',
                'priority' => 6,
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
                'support_helpdesk_id' => 1,
                'client_id' => 1,
                'rel_type' => 'order',
                'rel_id' => 42,
                'rel_task' => null,
                'rel_new_value' => null,
                'rel_status' => null,
                'rel' => [
                    'id' => 42,
                    'type' => 'order',
                    'task' => null,
                    'new_value' => null,
                    'status' => null,
                ],
                'notes' => [
                    [
                        'id' => 1,
                        'admin_id' => 1,
                        'note' => 'Internal note',
                        'author' => ['name' => 'Admin', 'email' => 'admin@example.com'],
                    ],
                ],
                'messages' => [],
                'replies' => 0,
                'first' => null,
                'helpdesk' => ['id' => 1, 'name' => 'Helpdesk', 'can_reopen' => true, 'signature' => ''],
                'client' => ['id' => 1, 'first_name' => 'Test', 'last_name' => 'Client'],
            ],
        ],
    );

    expect($html)->toBeString();
});

test('support admin ticket renders with a pending task (rel.status=pending)', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_MODS . '/Support/templates/admin/mod_support_ticket.html.twig',
        [
            'app_area' => 'admin',
            'request' => edgeCaseRequest(['id' => 1]),
            'admin' => edgeCaseAdmin(),
            'profile' => [
                'id' => 1,
                'name' => 'Test Admin',
                'email' => 'admin@example.com',
                'signature' => '-- Admin',
            ],
            'canned_delay_message' => '',
            'request_message' => null,
            'ticket' => [
                'id' => 1,
                'status' => 'onhold',
                'subject' => 'Pending task',
                'priority' => 6,
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
                'support_helpdesk_id' => 1,
                'client_id' => 1,
                'rel_type' => 'task',
                'rel_id' => 7,
                'rel_task' => 'followup',
                'rel_new_value' => null,
                'rel_status' => 'pending',
                'rel' => [
                    'id' => 7,
                    'type' => 'task',
                    'task' => 'followup',
                    'new_value' => null,
                    'status' => 'pending',
                ],
                'notes' => [],
                'messages' => [],
                'replies' => 0,
                'first' => null,
                'helpdesk' => ['id' => 1, 'name' => 'Helpdesk', 'can_reopen' => true, 'signature' => ''],
                'client' => ['id' => 1, 'first_name' => 'Test', 'last_name' => 'Client'],
            ],
        ],
    );

    expect($html)->toBeString();
});

/*
 * A permissive stub that returns a registered method result when called as a
 * function, otherwise falls back to PermissiveStub semantics. Used so we can
 * stub `admin.activity_log_get_list({...})` style calls with real data while
 * still letting other `admin.*` lookups succeed.
 */
final class PermissiveCallableStub
{
    /** @var array<string, mixed> */
    private array $methods;

    public function __construct(array $methods = [])
    {
        $this->methods = $methods;
    }

    public function __call(string $name, array $args): mixed
    {
        if (array_key_exists($name, $this->methods)) {
            return $this->methods[$name];
        }

        return new Tests\Support\PermissiveStub();
    }

    public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->methods)) {
            return $this->methods[$name];
        }

        return new Tests\Support\PermissiveStub();
    }

    public function __toString(): string
    {
        return '';
    }
}
