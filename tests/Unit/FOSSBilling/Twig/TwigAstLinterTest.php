<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 */

declare(strict_types=1);

use Tests\Support\TwigAstLinter;

/*
 * Verify that no template introduces a new unguarded attribute access.
 *
 * The linter walks the AST of every `*.html.twig` and flags
 * `GetAttrExpression` nodes whose root is not a known global and which are
 * not wrapped in `is defined`, `?? defaultValue`, or `| default(...)`.
 *
 * This catches the same class of bug the strict-variables render test
 * catches, but it does so at parse time across every code path — including
 * partials that the render harness never exercises because their parent
 * template's runtime branch isn't taken. The PermissiveStub the renderer
 * uses to absorb undefined access would otherwise hide a missing
 * `|default(...)` guard.
 *
 * Findings are written to `tests/Strict/ast-linter-findings.json` for
 * review. Once that file's content is acceptable, create the empty
 * `tests/Strict/.ast-linter-baseline` marker file to lock the test in
 * CI-gate mode. In that mode, any new finding fails the test.
 */
test('no template introduces unguarded attribute accesses', function (): void {
    $linter = new TwigAstLinter();
    $findings = $linter->lint();

    $strictDir = dirname(__DIR__, 3) . '/Strict';
    $findingsFile = $strictDir . '/ast-linter-findings.json';
    $markerFile = $strictDir . '/.ast-linter-baseline';
    $baselineFile = $strictDir . '/ast-linter-baseline.json';

    if (!is_dir($strictDir)) {
        mkdir($strictDir, 0o755, true);
    }

    // Always write the current findings so a developer can diff them.
    file_put_contents($findingsFile, json_encode($findings, JSON_PRETTY_PRINT));

    if (!file_exists($markerFile)) {
        // No baseline yet - pass and let the developer review the file.
        expect(true)->toBeTrue();

        return;
    }

    // Compute a stable signature for each finding. We don't include the
    // raw snippet because it can change with formatting; the line number
    // and template path are the durable identifiers.
    $signature = static fn (array $f): string => $f['file'] . ':' . $f['line'];

    $current = array_unique(array_map($signature, $findings));
    sort($current);

    $allowed = [];
    if (file_exists($baselineFile)) {
        $raw = json_decode((string) file_get_contents($baselineFile), true);
        if (is_array($raw)) {
            $allowed = $raw;
        }
    }
    sort($allowed);

    $new = array_values(array_diff($current, $allowed));
    $gone = array_values(array_diff($allowed, $current));

    if (!empty($new)) {
        $lines = array_map(
            static function (string $sig): string {
                $parts = explode(':', $sig, 2);

                return '  - ' . str_replace(dirname(__DIR__, 3) . '/../', '', $parts[0]) . ':' . $parts[1];
            },
            array_slice($new, 0, 50),
        );
        $msg = sprintf(
            "Found %d new unguarded attribute access(es):\n%s",
            count($new),
            implode("\n", $lines),
        );
        if (count($new) > 50) {
            $msg .= sprintf("\n  ... and %d more", count($new) - 50);
        }
        test()->fail($msg);
    }

    if (!empty($gone)) {
        // Findings removed: log a hint. Not a failure, but a signal that
        // the baseline should be regenerated.
        fwrite(STDERR, sprintf(
            'Hint: %d previously-baselined finding(s) are no longer reported. ' .
            "Consider regenerating %s.\n",
            count($gone),
            basename($baselineFile),
        ));
    }

    expect(true)->toBeTrue();
});
