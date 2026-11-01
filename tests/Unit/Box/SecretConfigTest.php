<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use FOSSBilling\SecretConfig;

test('encrypts only the listed secret fields and leaves the rest readable', function (): void {
    $crypt = new Box_Crypt();

    $result = SecretConfig::encryptSecretFields(
        ['api_key' => 'sk_live_secret', 'label' => 'My Store', 'enabled' => true],
        ['api_key', 'missing_field'],
        $crypt,
        'test-passphrase'
    );

    expect($result['api_key'])->toStartWith(Box_Crypt::CURRENT_FORMAT_PREFIX)
        ->and($result['api_key'])->not->toContain('sk_live_secret')
        ->and($result['label'])->toBe('My Store')
        ->and($result['enabled'])->toBeTrue();
});

test('encryption round-trips through decryption', function (): void {
    $crypt = new Box_Crypt();

    $stored = SecretConfig::encryptSecretFields(
        ['api_key' => 'sk_live_secret', 'webhook_secret' => 'whsec_abc'],
        ['api_key', 'webhook_secret'],
        $crypt,
        'test-passphrase'
    );

    $read = SecretConfig::decryptMarkedValues($stored, $crypt, 'test-passphrase');

    expect($read)->toBe(['api_key' => 'sk_live_secret', 'webhook_secret' => 'whsec_abc']);
});

test('encryption skips empty values and existing ciphertext', function (): void {
    $crypt = new Box_Crypt();
    $alreadyEncrypted = $crypt->encrypt('existing', 'test-passphrase');

    $result = SecretConfig::encryptSecretFields(
        ['api_key' => '', 'other' => null, 'again' => $alreadyEncrypted],
        ['api_key', 'other', 'again'],
        $crypt,
        'test-passphrase'
    );

    expect($result['api_key'])->toBe('')
        ->and($result['other'])->toBeNull()
        ->and($result['again'])->toBe($alreadyEncrypted)
        ->and(substr_count($result['again'], Box_Crypt::CURRENT_FORMAT_PREFIX))->toBe(1);
});

test('encryption does not mistake a plaintext value for ciphertext', function (): void {
    $crypt = new Box_Crypt();

    $stored = SecretConfig::encryptSecretFields(
        ['api_key' => 'v2:looks-marked-but-is-not-ciphertext'],
        ['api_key'],
        $crypt,
        'test-passphrase'
    );

    expect($stored['api_key'])->toStartWith(Box_Crypt::CURRENT_FORMAT_PREFIX)
        ->and($stored['api_key'])->not->toBe('v2:looks-marked-but-is-not-ciphertext')
        ->and($crypt->decrypt($stored['api_key'], 'test-passphrase'))->toBe('v2:looks-marked-but-is-not-ciphertext');
});

test('decryption passes through legacy plaintext and values that cannot be decrypted', function (): void {
    $crypt = new Box_Crypt();

    $result = SecretConfig::decryptMarkedValues(
        [
            'api_key' => 'sk_live_legacy',
            'unrelated' => 'v2:looks-marked-but-is-not-ciphertext',
            'count' => 42,
        ],
        $crypt,
        'test-passphrase'
    );

    expect($result['api_key'])->toBe('sk_live_legacy')
        ->and($result['unrelated'])->toBe('v2:looks-marked-but-is-not-ciphertext')
        ->and($result['count'])->toBe(42);
});
