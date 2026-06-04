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

    $findingsFile = dirname(__DIR__, 3) . '/Strict/findings.json';
    $isBaseline = file_exists(dirname(__DIR__, 3) . '/Strict/.baseline');

    if (!is_dir(dirname($findingsFile))) {
        mkdir(dirname($findingsFile), 0o755, true);
    }
    file_put_contents($findingsFile, json_encode($findings, JSON_PRETTY_PRINT));

    if ($isBaseline) {
        // A .baseline file exists, so we expect zero real-bug findings. Any
        // such finding fails the test. Test-infra findings are informational
        // only and never fail the test.
        if (!empty($realBugs)) {
            test()->fail("New strict-variables findings detected:\n" . formatFindings($realBugs));
        }
        expect(true)->toBeTrue();
    } else {
        // No baseline yet - pass with an informational message. Real-bug
        // counts and infra-bug counts are written to the findings file for
        // the developer to review. Once the real-bug count is 0, create the
        // .baseline file to lock the test in CI-gate mode.
        expect(true)->toBeTrue();
    }
})->skip(false, 'Strict-variables render harness always runs');

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
        if (!empty($realBugs)) {
            test()->fail("New strict-variables findings in email templates:\n" . formatFindings($realBugs));
        }
        expect(true)->toBeTrue();
    } else {
        expect(true)->toBeTrue();
    }
})->skip(false, 'Strict-variables email render harness always runs');

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
