<?php

declare(strict_types=1);

function createFingerprint(array $properties): FOSSBilling\Fingerprint
{
    $stub = new class extends FOSSBilling\Fingerprint {
        public function __construct()
        {
        }
    };

    $reflection = new ReflectionClass(FOSSBilling\Fingerprint::class);
    $prop = $reflection->getProperty('fingerprintProperties');
    $prop->setAccessible(true);
    $prop->setValue($stub, $properties);

    return $stub;
}

test('matching fingerprints pass', function (): void {
    $properties = [
        'browser' => ['source' => 'Chrome',  'weight' => 100],
        'os' => ['source' => 'Linux',   'weight' => 100],
        'ip' => ['source' => '10.0.0.1', 'weight' => 2],
    ];
    $stored = ['browser' => md5('Chrome'), 'os' => md5('Linux'), 'ip' => md5('10.0.0.1')];

    expect(createFingerprint($properties)->checkFingerprint($stored))->toBeTrue();
});

test('properties absent in both fingerprints do not penalize the score', function (): void {
    $currentProperties = [
        'browser' => ['source' => 'Chrome',  'weight' => 100],
        'os' => ['source' => 'Linux',   'weight' => 100],
        'ip' => ['source' => '10.0.0.1', 'weight' => 2],
        'platform' => ['source' => '',         'weight' => 100],
        'mobile' => ['source' => '',         'weight' => 2],
    ];
    $stored = [
        'browser' => md5('Chrome'),
        'os' => md5('Linux'),
        'ip' => md5('10.0.0.1'),
    ];

    expect(createFingerprint($currentProperties)->checkFingerprint($stored))->toBeTrue();
});

test('mismatched high-weight property rejects the fingerprint', function (): void {
    $properties = [
        'browser' => ['source' => 'Chrome', 'weight' => 100],
    ];
    $stored = ['browser' => md5('Firefox')];

    expect(createFingerprint($properties)->checkFingerprint($stored))->toBeFalse();
});

test('XOR — property present only in stored fingerprint counts as differing', function (): void {
    // Current request has no `platform` source; stored has it.
    $properties = [
        'browser' => ['source' => 'Chrome', 'weight' => 100],
        'platform' => ['source' => '',        'weight' => 1],
    ];
    $stored = [
        'browser' => md5('Chrome'),
        'platform' => md5('"macOS"'),
    ];

    expect(createFingerprint($properties)->checkFingerprint($stored))->toBeFalse();
});
