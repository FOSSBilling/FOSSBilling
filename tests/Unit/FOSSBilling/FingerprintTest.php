<?php

declare(strict_types=1);

use FOSSBilling\Fingerprint;
use Symfony\Component\HttpFoundation\Request;

function createFingerprint(array $properties): Fingerprint
{
    $stub = new class extends Fingerprint {
        public function __construct()
        {
            // Skip parent constructor so the readonly $fingerprintProperties
            // property stays uninitialized and can be set once via reflection.
        }
    };

    $reflection = new ReflectionClass(Fingerprint::class);
    $prop = $reflection->getProperty('fingerprintProperties');
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

test('XOR property present only in stored fingerprint counts as differing', function (): void {
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

test('constructor sources request headers and remote address into fingerprint properties', function (): void {
    $request = Request::create('https://example.com/', 'GET');
    $request->server->set('REMOTE_ADDR', '203.0.113.42');
    $request->headers->set('User-Agent', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    $request->headers->set('Accept-Language', 'en-US,en;q=0.9');
    $request->headers->set('Referer', 'https://referrer.example.com/');
    $request->headers->set('X-Forwarded-For', '198.51.100.1');

    $fingerprint = new Fingerprint($request);
    $generated = $fingerprint->fingerprint();

    // Browser, OS, and IP weights are derived from the request; each non-empty source is hashed into the fingerprint.
    expect($generated)->toHaveKey('browser')
        ->and($generated)->toHaveKey('os')
        ->and($generated)->toHaveKey('agentString')
        ->and($generated)->toHaveKey('ip')
        ->and($generated['browser'])->toBe(md5('Chrome'))
        ->and($generated['os'])->toBe(md5('Linux'))
        ->and($generated['ip'])->toBe(md5('203.0.113.42'))
        ->and($generated['language'])->toBe(md5('en-US,en;q=0.9'))
        ->and($generated['referrer'])->toBe(md5('https://referrer.example.com/'))
        ->and($generated['forwardedFor'])->toBe(md5('198.51.100.1'));
});
