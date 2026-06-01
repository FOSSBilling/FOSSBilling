<?php

declare(strict_types=1);

/**
 * Regression test for the RedBean string-to-int type-coercion fix in
 * src/di.php. The fix disables RedBean's automatic promotion of string
 * literals to PDO::PARAM_INT, which is what allowed ?hash=107 in
 * /api/guest/invoice/get to resolve to an invoice whose actual hash
 * started with the digits '107' (MySQL silently truncates VARCHAR
 * comparisons against integer values to a leading-digits match).
 *
 * Full DI bootstrap is too heavy for a unit test, so we assert on the
 * source instead: removing the call from src/di.php must be a conscious
 * code review decision, not a silent refactor.
 */
final class RedBeanBindingTest extends BBTestCase
{
    public function testDiForcesStringOnlyBinding(): void
    {
        $diPath = realpath(__DIR__ . '/../../src/di.php');
        $this->assertNotFalse($diPath, 'src/di.php must exist for this test to be meaningful');

        $contents = file_get_contents($diPath);
        $this->assertNotFalse($contents, 'src/di.php must be readable');

        $this->assertStringContainsString(
            "setUseStringOnlyBinding(true)",
            $contents,
            'src/di.php must enable RedBean string-only binding to prevent '
            . 'auto-promotion of string literals to PDO::PARAM_INT, which '
            . 'caused the invoice hash enumeration vulnerability (CVE-pending).'
        );
    }
}
