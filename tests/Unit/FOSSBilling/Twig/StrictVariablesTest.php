<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Tests\Support\PermissiveStub;
use Tests\Support\StrictTemplateRenderer;

test('cron settings renders when module config has not been saved', function (): void {
    $renderer = new StrictTemplateRenderer();
    $admin = new class {
        public function __isset(string $name): bool
        {
            return $name === 'cron_info';
        }

        public function __get(string $name): mixed
        {
            return match ($name) {
                'cron_info' => [
                    'cron_path' => '/var/www/fossbilling/cron.php',
                    'last_cron_exec' => null,
                ],
                default => null,
            };
        }

        public function extension_config_get(array $data): array
        {
            return ['ext' => $data['ext']];
        }
    };

    $html = $renderer->renderTemplate(PATH_MODS . '/Cron/templates/admin/mod_cron_settings.html.twig', [
        'admin' => $admin,
    ]);

    expect($html)->toContain('Guest Cron Endpoint')
        ->and($html)->not->toContain('checked="checked"')
        ->and($html)->not->toContain('Guest Cron URL');
});

/*
 * Verify that every FOSSBilling template compiles and renders successfully under
 * `strict_variables => true`. This catches undefined variable/attribute/key access,
 * missing macros, missing parent templates, missing blocks, and undefined filters
 * before they reach a production page load.
 *
 * Each rendering error is classified into one of:
 *
 * - `real-bug`: A genuine template bug that would also fail on a real page
 *   load (e.g. referencing a variable that the parent never passes, or a
 *   template that fails to parse). When the `.baseline` marker file exists,
 *   any real-bug finding fails the test.
 * - `test-infra`: A side effect of the render-everything harness itself
 *   (e.g. a permissive stub returned where a typed return is expected, or
 *   a child template referenced by a path the harness doesn't know about).
 *   These are written to the findings JSON for review but never fail the
 *   test.
 *
 * Findings are written to `tests/Strict/findings.json` so a developer can
 * `jq '.[] | select(.category == "real-bug")' tests/Strict/findings.json`
 * to see what still needs fixing. Once the real-bug count is 0, create
 * the `.baseline` marker file to lock the test in CI-gate mode.
 */
test('all templates render under strict_variables', function (): void {
    $renderer = new StrictTemplateRenderer();
    $findings = $renderer->renderAllTemplates();

    $realBugs = array_values(array_filter($findings, fn (array $f): bool => $f['category'] === 'real-bug'));

    $strictDir = dirname(__DIR__, 3) . '/Strict';
    $findingsFile = $strictDir . '/findings.json';
    $isBaseline = file_exists($strictDir . '/.baseline');

    if (!is_dir(dirname($findingsFile))) {
        mkdir(dirname($findingsFile), 0o755, true);
    }
    file_put_contents($findingsFile, json_encode($findings, JSON_PRETTY_PRINT));

    if ($isBaseline) {
        // A .baseline file exists, so we expect zero real-bug findings. Any
        // such finding fails the test. Test-infra findings are informational
        // only and never fail the test.
        expect($realBugs)->toBeEmpty("New strict-variables findings detected:\n" . formatFindings($realBugs));
    } else {
        // No baseline: still assert so failures are never silent.
        expect($realBugs)->toBeEmpty("Strict-variables real-bug findings detected (no baseline present):\n" . formatFindings($realBugs));
    }
});

test('orderbutton checkout renders one-time items without a period under strict_variables', function (): void {
    $renderer = new StrictTemplateRenderer();

    $html = $renderer->renderTemplate(PATH_MODS . '/Orderbutton/templates/client/mod_orderbutton_checkout.html.twig', [
        'app_area' => 'client',
        'client' => null,
        'request' => [
            'checkout' => true,
            'show_custom_form_values' => false,
            'promocode' => '',
        ],
        'settings' => [],
        'guest' => [
            'cart_get' => [
                'items' => [
                    [
                        'id' => 1,
                        'title' => 'Test product',
                        'quantity' => 1,
                        'discount_price' => 0,
                        'total' => 10,
                        'setup_price' => 0,
                        'discount_setup' => 0,
                        'form_id' => null,
                    ],
                ],
                'promocode' => '',
                'discount' => 0,
                'subtotal' => 10,
                'total' => 10,
                'subscribable' => true,
                'currency' => [
                    'code' => 'USD',
                ],
            ],
            'cart_get_currency' => [
                'code' => 'USD',
                'conversion_rate' => 1,
            ],
            'invoice_gateways' => [
                [
                    'id' => 1,
                    'title' => 'Unsupported Gateway',
                    'accepted_currencies' => ['EUR'],
                    'allow_single' => true,
                    'allow_recurrent' => false,
                    'logo' => ['logo' => null, 'height' => 0, 'width' => 0],
                ],
                [
                    'id' => 2,
                    'title' => 'Subscription Gateway',
                    'accepted_currencies' => ['USD'],
                    'allow_single' => false,
                    'allow_recurrent' => true,
                    'logo' => ['logo' => null, 'height' => 0, 'width' => 0],
                ],
                [
                    'id' => 3,
                    'title' => 'Secondary Gateway',
                    'accepted_currencies' => ['USD'],
                    'allow_single' => true,
                    'allow_recurrent' => false,
                    'logo' => ['logo' => null, 'height' => 0, 'width' => 0],
                ],
            ],
        ],
    ]);

    expect($html)->toContain('You must first login / create an account before you can checkout.')
        ->and($html)->toContain('Subscription Gateway')
        ->and($html)->toContain('id="order-gateway-2" value="2" autocomplete="off" checked')
        ->and($html)->not->toContain('id="order-gateway-3" value="3" autocomplete="off" checked');
});

test('orderbutton client form renders incomplete custom field configuration under strict_variables', function (): void {
    $renderer = new StrictTemplateRenderer();

    $html = $renderer->renderTemplate(PATH_MODS . '/Orderbutton/templates/client/mod_orderbutton_client.html.twig', [
        'client' => null,
        'request' => new PermissiveStub(['checkout' => true]),
        'settings' => new PermissiveStub(['signup_tos' => 'disabled']),
        'guest' => new PermissiveStub([
            'client_custom_fields' => [
                'custom_1' => ['title' => 'Inactive field'],
                'custom_2' => ['title' => 'Active field', 'active' => true],
            ],
        ]),
    ]);

    expect($html)
        ->not->toContain('id="custom_1"')
        ->toContain('id="custom_2"');
});

/*
 * Email templates use a sandboxed environment and a different global shape.
 * Run the same harness in email mode to catch issues specific to email rendering.
 */
test('all email templates render under strict_variables', function (): void {
    $renderer = new StrictTemplateRenderer();
    $findings = $renderer->renderAllTemplates(emailMode: true);

    $realBugs = array_values(array_filter($findings, fn (array $f): bool => $f['category'] === 'real-bug'));

    $findingsFile = dirname(__DIR__, 3) . '/Strict/findings_email.json';
    $isBaseline = file_exists(dirname(__DIR__, 3) . '/Strict/.baseline_email');

    if (!is_dir(dirname($findingsFile))) {
        mkdir(dirname($findingsFile), 0o755, true);
    }
    file_put_contents($findingsFile, json_encode($findings, JSON_PRETTY_PRINT));

    if ($isBaseline) {
        expect($realBugs)->toBeEmpty("New strict-variables findings in email templates:\n" . formatFindings($realBugs));
    }
});

/**
 * @param list<array{file: string, template: string, error: string, category: string}> $findings
 */
function formatFindings(array $findings): string
{
    $lines = [];
    foreach (array_slice($findings, 0, 50) as $finding) {
        $lines[] = sprintf('  - %s: %s', $finding['template'], $finding['error']);
    }
    if (count($findings) > 50) {
        $lines[] = sprintf('  ... and %d more', count($findings) - 50);
    }

    return implode("\n", $lines);
}
