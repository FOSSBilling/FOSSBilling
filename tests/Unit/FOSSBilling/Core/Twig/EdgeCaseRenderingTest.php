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
use Twig\Environment;
use Twig\Loader\ArrayLoader;

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

/**
 * Build a default request payload for edge-case Twig rendering tests.
 *
 * @param array<string, mixed> $overrides optional request keys to override
 *
 * @return array<string, mixed>
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
        'period' => null,
    ], $overrides);
}

/**
 * Build an admin API stub with sensible defaults for edge-case tests.
 *
 * @param array<string, mixed> $methods optional method map overrides for the stub
 */
function edgeCaseAdmin(array $methods = []): PermissiveCallableStub
{
    return new PermissiveCallableStub(array_replace([
        'support_helpdesk_get_pairs' => [1 => 'Helpdesk'],
        'support_canned_pairs' => [],
        'product_get_pairs' => [],
    ], $methods));
}

/**
 * Build a guest API stub with defaults used by edge-case rendering scenarios.
 *
 * @param array<string, mixed> $methods optional method map overrides for the stub
 */
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

/**
 * Return a default cart currency payload for edge-case rendering tests.
 *
 * @return array{code: string, conversion_rate: float}
 */
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

test('hosting order form collects a required domain transfer code', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_MODS . '/Servicehosting/templates/client/mod_servicehosting_order_form.html.twig',
        [
            'request' => edgeCaseRequest([
                'transfer_sld' => 'example',
                'transfer_code' => 'EPP-CODE',
            ]),
            'guest' => edgeCaseGuest([
                'serviceDomain_tlds' => [
                    [
                        'tld' => '.com',
                        'require_transfer_code' => true,
                    ],
                ],
            ]),
            'product' => [
                'pricing' => [],
                'config' => [
                    'allow_domain_register' => false,
                    'allow_domain_transfer' => true,
                    'allow_domain_own' => false,
                    'allow_subdomain' => false,
                ],
            ],
            'product_details' => 'Hosting plan',
        ],
    );

    expect($html)
        ->toContain('data-require-transfer-code="1"')
        ->toContain('name="domain[transfer_code]"')
        ->toContain('value="EPP-CODE"')
        ->toContain('id="transfer-code-hint"');
});

test('email template example renders without calling email globals in admin context', function (): void {
    $templateSource = file_get_contents(PATH_MODS . '/Email/templates/admin/mod_email_template.html.twig');
    expect($templateSource)->not()->toBeFalse();

    preg_match('/<textarea class="form-control" id="email-sample-template" rows="20">(.*?)<\/textarea>/s', $templateSource, $matches);
    expect($matches[1] ?? null)->toBeString();

    $twig = new Environment(new ArrayLoader(['example' => $matches[1]]), [
        'strict_variables' => true,
        'cache' => false,
    ]);

    $html = $twig->render('example', [
        'guest' => new class {
            public function __get(string $name): mixed
            {
                if ($name === 'system_email') {
                    throw new RuntimeException('Admin email template example must not access guest.system_email while rendering the editor');
                }

                return null;
            }
        },
        'FOSSBillingVersion' => '0.0.0',
    ]);

    expect($html)
        ->toContain('*Italic text*')
        ->toContain('{{ guest.system_email.signature }}')
        ->toContain('{% if 1 == 2 %}')
        ->not->toContain('{% apply markdown_to_html %}')
        ->not->toContain('{% endapply %}');
});

test('email template variable macro renders nested variables inside an embed', function (): void {
    $macro = file_get_contents(PATH_MODS . '/Email/templates/admin/partial_email_template_macros.html.twig');
    expect($macro)->not->toBeFalse();

    $twig = new Environment(new ArrayLoader([
        'page' => <<<'TWIG'
            {% embed 'section' %}
                {% block content %}
                    {% import 'macros' as email_template %}
                    {{ email_template.render_vars(vars) }}
                {% endblock %}
            {% endembed %}
            TWIG,
        'section' => '{% block content %}{% endblock %}',
        'macros' => $macro,
    ]), [
        'strict_variables' => true,
        'cache' => false,
    ]);

    $html = $twig->render('page', [
        'vars' => [
            'ticket' => [
                'client' => [
                    'email' => 'client@example.com',
                ],
            ],
        ],
    ]);

    expect($html)
        ->toContain('{{ ticket.client.email }}')
        ->toContain('client@example.com');
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
                        '1M' => ['price' => 9.99, 'enabled' => true, 'setup' => '0.00', 'title' => 'Every Month'],
                        '1Y' => ['price' => 99.99, 'enabled' => true, 'setup' => '0.00', 'title' => 'Every Year'],
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
                    'total' => 3,
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

test('client manage page renders related-section footers with tab-level lists', function (): void {
    // Regression test: the orders/invoices/tickets/balance/history/transactions
    // lists were assigned inside their embed content blocks, invisible to the
    // footer blocks under strict_variables ("Variable ... does not exist").
    // They are assigned at tab level now, so the whole page renders.
    $emptyList = ['list' => [], 'pages' => 1, 'per_page' => 25, 'page' => 1, 'total' => 0];

    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_MODS . '/Client/templates/admin/mod_client_manage.html.twig',
        [
            'app_area' => 'admin',
            'request' => edgeCaseRequest([
                'order_page' => 1, 'order_per_page' => 25,
                'invoice_page' => 1, 'invoice_per_page' => 25,
                'ticket_page' => 1, 'ticket_per_page' => 25,
                'balance_page' => 1, 'balance_per_page' => 25,
                'login_page' => 1, 'login_per_page' => 25,
                'email_page' => 1, 'email_per_page' => 25,
                'txn_page' => 1, 'txn_per_page' => 25,
            ]),
            'admin' => edgeCaseAdmin([
                'order_get_list' => $emptyList,
                'invoice_get_list' => $emptyList,
                'support_ticket_get_list' => $emptyList,
                'client_balance_get_list' => $emptyList,
                'client_login_history_get_list' => $emptyList,
                'email_email_get_list' => $emptyList,
                'invoice_transaction_get_list' => $emptyList,
            ]),
            'client' => new Tests\Support\PermissiveStub(['id' => 169]),
        ],
    );

    expect($html)->toContain('Client Orders')
        ->and($html)->toContain('Client Invoices');
});

test('invoice history tab renders translatable labels for every journal type', function (): void {
    $types = ['created', 'issued', 'updated', 'paid', 'refunded', 'debited', 'canceled', 'reissued', 'order_attached', 'reminder'];
    $journal = [];
    foreach ($types as $i => $type) {
        $journal[] = ['id' => $i + 1, 'type' => $type, 'admin_id' => null, 'client_id' => null, 'snapshot' => null, 'created_at' => '2026-01-01 00:00:00'];
    }

    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_MODS . '/Invoice/templates/admin/mod_invoice_invoice.html.twig',
        [
            'app_area' => 'admin',
            'request' => edgeCaseRequest(['id' => 1]),
            'admin' => edgeCaseAdmin(['invoice_journal' => $journal]),
            'invoice' => new Tests\Support\PermissiveStub(['id' => 1]),
        ],
    );

    expect($html)->toContain('Order attached')
        ->and($html)->toContain('Reissued')
        ->and($html)->not->toContain('order_attached');
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
                'author' => ['id' => 1, 'role' => 'client', 'name' => 'Test Client', 'first_name' => 'Test', 'last_name' => 'Client', 'email' => 'client@example.com'],
            ],
        ],
    );

    expect($html)->toBeString();
});

test('support admin ticket renders admin and client messages, including an edited one', function (): void {
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
                'messages' => [
                    [
                        'id' => 1,
                        'content' => 'Client message, not editable',
                        'created_at' => '2026-01-01 00:00:00',
                        'updated_at' => '2026-01-01 00:00:00',
                        'author' => ['role' => 'client', 'name' => 'Test Client', 'email' => 'client@example.com'],
                    ],
                    [
                        'id' => 2,
                        'content' => 'Admin message, never edited',
                        'created_at' => '2026-01-01 00:05:00',
                        'updated_at' => '2026-01-01 00:05:00',
                        'author' => ['role' => 'admin', 'name' => 'Test Admin', 'email' => 'admin@example.com'],
                    ],
                    [
                        'id' => 3,
                        'content' => 'Admin message, edited after the fact',
                        'created_at' => '2026-01-01 00:10:00',
                        'updated_at' => '2026-01-01 00:15:00',
                        'author' => ['role' => 'admin', 'name' => 'Test Admin', 'email' => 'admin@example.com'],
                    ],
                ],
                'replies' => 2,
                'first' => ['author' => ['email' => 'client@example.com']],
                'helpdesk' => ['id' => 1, 'name' => 'Helpdesk', 'can_reopen' => true, 'signature' => ''],
                'client' => ['id' => 1, 'first_name' => 'Test', 'last_name' => 'Client'],
                'author' => ['id' => 1, 'role' => 'client', 'name' => 'Test Client', 'first_name' => 'Test', 'last_name' => 'Client', 'email' => 'client@example.com'],
            ],
        ],
    );

    expect($html)->toBeString();
    expect($html)->toContain('editMessage2');
    expect($html)->toContain('editMessage3');
    expect($html)->not->toContain('editMessage1');
    expect($html)->toContain('messageHistory3');
    expect($html)->not->toContain('messageHistory2');
    expect($html)->not->toContain('messageHistory1');
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
                'author' => ['id' => 1, 'role' => 'client', 'name' => 'Test Client', 'first_name' => 'Test', 'last_name' => 'Client', 'email' => 'client@example.com'],
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
                'author' => ['id' => 1, 'role' => 'client', 'name' => 'Test Client', 'first_name' => 'Test', 'last_name' => 'Client', 'email' => 'client@example.com'],
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
final class PermissiveCallableStub implements Stringable
{
    public function __construct(
        /** @var array<string, mixed> */
        private array $methods = [],
    ) {
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
