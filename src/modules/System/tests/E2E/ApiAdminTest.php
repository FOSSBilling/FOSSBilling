<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Tests\Helpers\ApiClient;

if (!getenv('APP_URL') || !getenv('TEST_API_KEY')) {
    return;
}

const SYSTEM_MAX_RETRY_ATTEMPTS = 10;
const SYSTEM_RETRY_DELAY_MICROSECONDS = 200000;
const SYSTEM_DEFAULT_INTERFACE = '0';

function systemRetryUntil(callable $operation, int $maxAttempts = SYSTEM_MAX_RETRY_ATTEMPTS, int $delayMicroseconds = SYSTEM_RETRY_DELAY_MICROSECONDS): bool
{
    for ($attempt = 0; $attempt < $maxAttempts; ++$attempt) {
        if ($operation()) {
            return true;
        }

        if ($attempt < $maxAttempts - 1) {
            usleep($delayMicroseconds);
        }
    }

    return false;
}

function systemIsIpLookupAvailable(): bool
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 2,
        ],
    ]);

    $lookupErrorMessage = null;
    $previousErrorHandler = set_error_handler(
        static function (
            int $severity,
            string $message,
            string $file = '',
            int $line = 0,
        ) use (&$lookupErrorMessage, &$previousErrorHandler): bool {
            if ($severity === E_WARNING) {
                $lookupErrorMessage = $message;

                return true;
            }

            if (is_callable($previousErrorHandler)) {
                return (bool) $previousErrorHandler($severity, $message, $file, $line);
            }

            return false;
        }
    );

    try {
        $response = file_get_contents('https://api.ipify.org', false, $context);
    } finally {
        restore_error_handler();
    }

    if ($response === false && $lookupErrorMessage !== null) {
        $singleLineLookupErrorMessage = str_replace(["\r", "\n"], ' ', $lookupErrorMessage);
        error_log('IP lookup availability check failed: ' . $singleLineLookupErrorMessage);
    }

    return $response !== false;
}

function systemResetInterfaceConfiguration(): void
{
    $resetResult = ApiClient::request(
        'admin/system/set_interface_ip',
        ['custom_interface' => '', 'interface' => SYSTEM_DEFAULT_INTERFACE]
    );
    expect($resetResult->wasSuccessful())->toBeTrue();
}

test('clears cache', function (): void {
    $beforeFirst = ApiClient::request('admin/system/error_reporting_enabled');
    expect($beforeFirst->wasSuccessful())->toBeTrue()
        ->and($beforeFirst->getResult())->toBeBool();

    $beforeSecond = ApiClient::request('admin/system/error_reporting_enabled');
    expect($beforeSecond->wasSuccessful())->toBeTrue()
        ->and($beforeSecond->getResult())->toBeBool()
        ->and($beforeSecond->getResult())->toBe($beforeFirst->getResult());

    $result = ApiClient::request('admin/system/clear_cache');
    expect($result->wasSuccessful())->toBeTrue()
        ->and($result->getResult())->toBeBool();

    $after = ApiClient::request('admin/system/error_reporting_enabled');
    expect($after->wasSuccessful())->toBeTrue()
        ->and($after->getResult())->toBeBool()
        ->and($after->getResult())->toBe($beforeSecond->getResult());
});

test('toggles error reporting', function (): void {
    $beforeResult = ApiClient::request('admin/system/error_reporting_enabled');
    expect($beforeResult->wasSuccessful())->toBeTrue();
    $before = $beforeResult->getResult();
    expect($before)->toBeBool();

    try {
        $result = ApiClient::request('admin/system/toggle_error_reporting');
        expect($result->wasSuccessful())->toBeTrue()
            ->and($result->getResult())->toBeTrue();

        $afterResponse = ApiClient::request('admin/system/error_reporting_enabled');
        expect($afterResponse->wasSuccessful())->toBeTrue();
        $after = $afterResponse->getResult();
        expect($after)->toBeBool()
            ->not->toBe($before);
    } finally {
        $currentResponse = ApiClient::request('admin/system/error_reporting_enabled');
        expect($currentResponse->wasSuccessful())->toBeTrue();
        $current = $currentResponse->getResult();
        expect($current)->toBeBool();

        if ($current !== $before) {
            $restoreResult = ApiClient::request('admin/system/toggle_error_reporting');
            expect($restoreResult->wasSuccessful())->toBeTrue()
                ->and($restoreResult->getResult())->toBeTrue();
        }
    }
});

test('gets and sets network interfaces', function (): void {
    $result = ApiClient::request('admin/system/get_interface_ips');
    expect($result->wasSuccessful())->toBeTrue()
        ->and($result->getResult())->toBeArray();

    foreach ($result->getResult() as $ip) {
        expect((bool) filter_var($ip, FILTER_VALIDATE_IP))->toBeTrue();
    }

    if (!systemIsIpLookupAvailable()) {
        $this->markTestSkipped('Integration test requires ipify.org to be available.');
    }

    foreach ($result->getResult() as $ip) {
        $testResult = ApiClient::request('admin/system/set_interface_ip', ['interface' => $ip]);
        expect($testResult->wasSuccessful())->toBeTrue();

        $isReady = systemRetryUntil(
            static function (): bool {
                $envResult = ApiClient::request('admin/system/env', ['ip' => true]);

                return $envResult->wasSuccessful() && (bool) filter_var($envResult->getResult(), FILTER_VALIDATE_IP);
            }
        );

        expect($isReady)->toBeTrue();
    }

    $cleanupResult = ApiClient::request('admin/system/set_interface_ip', ['interface' => SYSTEM_DEFAULT_INTERFACE]);
    expect($cleanupResult->wasSuccessful())->toBeTrue();
});

test('rejects invalid interface', function (): void {
    $result = ApiClient::request('admin/system/set_interface_ip', ['interface' => '12345']);
    expect($result->wasSuccessful())->toBeFalse();
});

test('rejects invalid custom interface', function (): void {
    $result = ApiClient::request('admin/system/set_interface_ip', ['custom_interface' => '!@#$%']);
    expect($result->wasSuccessful())->toBeFalse();
});

test('rejects malicious interface values', function (string $payload): void {
    $result = ApiClient::request('admin/system/set_interface_ip', ['interface' => $payload]);
    expect($result->wasSuccessful())->toBeFalse();
})->with([
    'quote-escape' => ["x\"; echo 'pwned'; //"],
    'pipe-command' => ['eth0|id'],
    'backticks' => ['eth0`id`'],
    'sub-shell' => ['eth0$(id)'],
    'and-command' => ['eth0 && whoami'],
    'redirect' => ['eth0 > /tmp/pwned'],
    'path-traversal-unix' => ['../etc/passwd'],
    'path-traversal-windows' => ['..\\..\\windows\\system32\\drivers\\etc\\hosts'],
    'null-byte' => ["eth0\0evil"],
    'long-string' => [str_repeat('a', 1024)],
    'newline-injection' => ["eth0\nwhoami"],
    'leading-trailing-space' => [' eth0 '],
]);

test('rejects malicious custom interface', function (): void {
    $result = ApiClient::request('admin/system/set_interface_ip', ['custom_interface' => "x\"; passthru('id'); //"]);
    expect($result->wasSuccessful())->toBeFalse();
});

test('accepts valid custom interface hostname', function (): void {
    try {
        $result = ApiClient::request('admin/system/set_interface_ip', ['custom_interface' => 'eth0']);
        expect($result->wasSuccessful())->toBeTrue();
    } finally {
        systemResetInterfaceConfiguration();
    }
});
