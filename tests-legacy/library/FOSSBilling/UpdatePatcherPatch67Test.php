<?php

declare(strict_types=1);

use FOSSBilling\UpdatePatcher;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
/**
 * Regression tests for the destructive hash migration in patch67.
 *
 * patch67 adds the invoice.hash_expires_at column, the
 * invoice_hash_lifetime_days setting, and a destructive UPDATE that
 * NULLs any legacy invoice.hash outside the modern 30-60 lowercase hex
 * format. This addresses the numeric-prefix hash enumeration root cause
 * (see RedBeanBindingTest for the underlying fix).
 *
 * A full integration test would require a live MySQL database (the
 * UPDATE uses MySQL-specific REGEXP syntax), so we assert on the source
 * directly: the method must be registered, must add the right column
 * and setting, and must include the destructive UPDATE. Removing any of
 * these is a deliberate code review decision, not a silent refactor.
 */
final class UpdatePatcherPatch67Test extends PHPUnit\Framework\TestCase
{
    private string $patcherPath;

    protected function setUp(): void
    {
        $this->patcherPath = realpath(__DIR__ . '/../../../src/library/FOSSBilling/UpdatePatcher.php');
        $this->assertNotFalse($this->patcherPath, 'UpdatePatcher.php must exist for this test to be meaningful');
    }

    public function testPatch67IsRegisteredInPatchesArray(): void
    {
        $contents = (string) file_get_contents($this->patcherPath);
        $this->assertStringContainsString(
            "67 => 'patch67'",
            $contents,
            'patch67 must be registered in the patches array so it runs once on upgrade from a pre-patch67 install.'
        );
    }

    public function testPatch67AddsHashExpiresAtColumn(): void
    {
        $contents = (string) file_get_contents($this->patcherPath);
        $this->assertStringContainsString(
            "ADD COLUMN `hash_expires_at`",
            $contents,
            'patch67 must add the hash_expires_at column so the new lifetime logic has somewhere to store the expiry.'
        );
    }

    public function testPatch67SeedsInvoiceHashLifetimeDaysSetting(): void
    {
        $contents = (string) file_get_contents($this->patcherPath);
        $this->assertStringContainsString(
            "'invoice_hash_lifetime_days'",
            $contents,
            'patch67 must seed the invoice_hash_lifetime_days system setting with a default of 90 days.'
        );
    }

    public function testPatch67DestructiveMigrationInvalidatesLegacyHashes(): void
    {
        $contents = (string) file_get_contents($this->patcherPath);
        $this->assertStringContainsString(
            'UPDATE invoice SET hash = NULL',
            $contents,
            'patch67 must include a destructive UPDATE that NULLs legacy invoice.hash values outside the modern format.'
        );
        $this->assertStringContainsString(
            "LENGTH(hash) < 30 OR LENGTH(hash) > 60 OR hash NOT REGEXP '^[a-f0-9]+\$'",
            $contents,
            'patch67 destructive UPDATE must use the same 30-60 lowercase hex guard that the API regex enforces, so the two stay in lockstep.'
        );
    }
}
