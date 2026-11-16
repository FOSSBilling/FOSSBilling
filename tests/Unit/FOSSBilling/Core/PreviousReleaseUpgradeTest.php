<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

test('previous release fixtures match their pinned source digests', function (): void {
    $root = PATH_TESTS . '/Fixtures/upgrades';
    $manifest = json_decode((new Filesystem())->readFile($root . '/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
    foreach ($manifest as $version => $release) {
        foreach ($release['files'] as $file => $digest) {
            expect(hash_file('sha256', $root . '/' . $version . '/' . $file))->toBe($digest);
        }
    }
});

test('normal finalization upgrades a previous release without manual SQL', function (string $version, int $patch, bool $interrupt): void {
    if (!getenv('ATTACHMENT_TEST_MYSQL_PARAMS')) {
        $this->markTestSkipped('Requires a disposable MySQL/MariaDB test server.');
    }
    $root = sys_get_temp_dir() . '/fb-release-upgrade-' . bin2hex(random_bytes(6));
    $filesystem = new Filesystem();

    try {
        $process = new Process([PHP_BINARY, PATH_TESTS . '/Fixtures/previous-release-upgrade.php', $root, $version, $interrupt ? 'interrupt' : 'normal']);
        $process->setTimeout(180);
        $process->mustRun();
        expect($process->getErrorOutput())->toBe('');
        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        expect($result['old_patch'])->toBe($patch)
            ->and($result['new_patch'])->toBe(155)
            ->and($result['status_before_completion'])->toBe('finalized')
            ->and($result['required'])->toBeFalse()
            ->and($result['salt'])->toBe('fixture-encryption-key')
            ->and($result['maintenance'])->toBe(['enabled' => false, 'allowed_urls' => ['/keep-this'], 'allowed_ips' => []])
            ->and($result['client'])->toBe('existing@example.test')
            ->and($result['ticket'])->toBe('Existing ticket')
            ->and($result['setting'])->toBe('keep-me')
            ->and((int) $result['invoice']['nr'])->toBe(42)
            ->and($result['invoice']['status'])->toBe('paid')
            ->and((int) $result['invoice']['issued'])->toBe(1)
            ->and($result['wallet'])->toEqual(25.00)
            ->and($result['journal_entries'])->toBe(1)
            ->and($result['interrupted'])->toBe($interrupt ? 'pending' : null)
            ->and($result['schema_failure'])->toBeNull()
            ->and($result['tables'])->toContain('invoice_gateway_charge_attempt', 'invoice_gateway_refund_attempt', 'support_ticket_close_event', 'support_ticket_secret')
            ->and($result['transaction_columns'])->toContain('processing_token', 'credit_recovery_state');
    } finally {
        $filesystem->remove($root);
    }
})->with(['0.7.2' => ['0.7.2', 43], '0.8.1' => ['0.8.1', 65], '0.8.9' => ['0.8.9', 121]])->with([false, true]);
