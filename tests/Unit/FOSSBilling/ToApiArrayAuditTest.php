<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 */

declare(strict_types=1);

use Tests\Support\ToApiArrayAuditor;

/*
 * Audits every `function toApiArray` under `src/modules` and reports fields
 * that are assigned to `$data['xxx']` only inside conditional blocks. Such
 * fields are a structural risk: any caller that doesn't go through the
 * admin path that sets them will see `Key "xxx" does not exist` under
 * strict_variables.
 *
 * The test is informational by default. It always writes its findings to
 * `tests/Strict/to-api-array-audit.json` and prints a summary, but only
 * fails the suite when a baseline marker file is present:
 *   tests/Strict/.to-api-array-audit-baseline
 *
 * To promote the test to a CI gate, create the empty marker file
 * (`touch tests/Strict/.to-api-array-audit-baseline`). Until then, the
 * report is the value, not the test outcome.
 */
test('toApiArray methods expose all their fields at the top level', function (): void {
    $srcDir = dirname(__DIR__, 3) . '/src';
    $auditor = new ToApiArrayAuditor($srcDir);
    $findings = $auditor->audit();

    $jsonPath = __DIR__ . '/../../Strict/to-api-array-audit.json';
    $report = [];
    foreach ($findings as $file => $info) {
        $report[$file] = [
            'top' => $info['top'],
            'conditional' => $info['conditional'],
        ];
    }
    file_put_contents($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $baseline = __DIR__ . '/../../Strict/.to-api-array-audit-baseline';
    $gated = is_file($baseline);

    if (!empty($findings)) {
        $lines = [];
        $lines[] = '';
        $lines[] = 'toApiArray audit: ' . count($findings) . ' method(s) set fields only inside conditional blocks.';
        foreach ($findings as $file => $info) {
            $lines[] = '';
            $lines[] = "=== $file ===";
            $lines[] = '  Top-level: ' . (empty($info['top']) ? '(none)' : implode(', ', $info['top']));
            $lines[] = '  Conditional-only:';
            foreach ($info['conditional'] as $field => $locs) {
                $lines[] = "    - $field  (" . implode(', ', $locs) . ')';
            }
        }
        $lines[] = '';
        fwrite(STDERR, implode("\n", $lines));
    }

    if ($gated) {
        expect($findings)->toBeEmpty();
    } else {
        expect($findings)->toBeArray();
    }
});
