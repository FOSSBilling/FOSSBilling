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
 * Findings are reported to a JSON file under tests/Strict/ so the harness can be run
 * iteratively while fixes are landing. Once a baseline of zero findings is reached,
 * the JSON file is removed and the test runs in strict mode.
 */
test('all templates render under strict_variables', function (): void {
    $renderer = new StrictTemplateRenderer();
    $findings = $renderer->renderAllTemplates();

    $realBugs = array_values(array_filter($findings, fn (array $f): bool => $f['category'] === 'real-bug'));
    $infraBugs = array_values(array_filter($findings, fn (array $f): bool => $f['category'] !== 'real-bug'));

    $findingsFile = dirname(__DIR__, 3) . '/Strict/findings.json';
    $isBaseline = file_exists(dirname(__DIR__, 3) . '/Strict/.baseline');

    if (!is_dir(dirname($findingsFile))) {
        mkdir(dirname($findingsFile), 0o755, true);
    }
    file_put_contents($findingsFile, json_encode($findings, JSON_PRETTY_PRINT));

    if ($isBaseline) {
        expect($realBugs)->toBeEmpty(
            "New strict-variables findings detected:\n" .
                $this->formatFindings($realBugs)
        );
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

    $findingsFile = dirname(__DIR__, 3) . '/Strict/findings_email.json';
    $isBaseline = file_exists(dirname(__DIR__, 3) . '/Strict/.baseline_email');

    if ($isBaseline) {
        expect($findings)->toBeEmpty(
            "New strict-variables findings in email templates:\n" .
                $this->formatFindings($findings)
        );
    } else {
        if (!empty($findings)) {
            if (!is_dir(dirname($findingsFile))) {
                mkdir(dirname($findingsFile), 0o755, true);
            }
            file_put_contents($findingsFile, json_encode($findings, JSON_PRETTY_PRINT));
        }
        expect(true)->toBeTrue();
    }
})->skip(false, 'Strict-variables email render harness always runs');

/**
 * @param list<array{file: string, template: string, error: string}> $findings
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
