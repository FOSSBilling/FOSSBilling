<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use FOSSBilling\Tools\DemoSeed\Command\SeedCommand;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function (): void {
    $this->previousApiKey = getenv('TEST_API_KEY');
    putenv('TEST_API_KEY=test-key');
});

afterEach(function (): void {
    if ($this->previousApiKey === false) {
        putenv('TEST_API_KEY');
    } else {
        putenv('TEST_API_KEY=' . $this->previousApiKey);
    }
});

function demoSeedCommand(): CommandTester
{
    $command = new SeedCommand(sys_get_temp_dir() . '/fossbilling-demo-seed-test', '/nonexistent/demo-download.zip');

    return new CommandTester($command);
}

test('rejects malformed and negative limit options before any API call', function (): void {
    $tester = demoSeedCommand();

    expect(fn (): int => $tester->execute(['--limit-orders' => 'abc', '--url' => 'http://127.0.0.1:9']))
        ->toThrow(InvalidArgumentException::class, 'must be a non-negative integer')
        ->and(fn (): int => $tester->execute(['--limit-clients' => '-3', '--url' => 'http://127.0.0.1:9']))
        ->toThrow(InvalidArgumentException::class);
});

test('accepts valid limits and proceeds to the preflight', function (): void {
    $tester = demoSeedCommand();

    $status = $tester->execute(['--limit-orders' => '5', '--url' => 'http://127.0.0.1:9']);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('Preflight failed');
});

test('warns when the admin API key would cross a remote network over plaintext HTTP', function (): void {
    $tester = demoSeedCommand();

    $status = $tester->execute(['--url' => 'http://demo.example.invalid']);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('plaintext HTTP');
});

test('does not warn for local development domains', function (): void {
    $tester = demoSeedCommand();

    $status = $tester->execute(['--url' => 'http://fossbilling.localhost']);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->not->toContain('plaintext HTTP');
});
