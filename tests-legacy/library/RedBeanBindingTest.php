<?php

declare(strict_types=1);

/**
 * Regression test for the RedBean string-only binding fix in src/di.php.
 * Asserts on the source rather than booting the full DI container so
 * that removing the call must be a deliberate code review decision.
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
            'setUseStringOnlyBinding(true)',
            $contents,
            'src/di.php must enable RedBean string-only binding to prevent '
            . 'auto-promotion of string literals to PDO::PARAM_INT, which '
            . 'caused the invoice hash enumeration vulnerability.'
        );
    }
}
