<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

test('crypt', function (): void {
    $key = 'le password';
    $text = 'foo bar';

    $crypt = new FOSSBilling\Crypt();
    $encoded = $crypt->encrypt($text, $key);
    $decoded = $crypt->decrypt($encoded, $key);
    expect($decoded)->toEqual($text);
});

test('authenticated ciphertext rejects tampering and wrong keys while retaining exact bytes', function (): void {
    $crypt = new FOSSBilling\Crypt();
    $value = " \0payload\n ";
    $encrypted = $crypt->encrypt($value, 'test-key');
    expect($encrypted)->toStartWith('v3:')->and($crypt->decrypt($encrypted, 'test-key'))->toBe($value)
        ->and($crypt->decrypt($encrypted, 'wrong-key'))->toBeFalse()
        ->and($crypt->encrypt($value, 'test-key'))->not->toBe($encrypted);
    $raw = base64_decode(substr($encrypted, 3), true);
    foreach ([0, 12, 28] as $position) {
        $tampered = $raw;
        $tampered[$position] = chr(ord($tampered[$position]) ^ 1);
        expect($crypt->decrypt('v3:' . base64_encode($tampered), 'test-key'))->toBeFalse();
    }
});

test('existing v2 and original ciphertext can still be decrypted for safe data upgrades', function (): void {
    $crypt = new FOSSBilling\Crypt();
    $pass = 'test-key';
    $iv = str_repeat('x', 16);
    foreach ([hash_pbkdf2('sha256', $pass, 'fossbilling_salt', 100000, 32, true), pack('H*', md5($pass))] as $index => $key) {
        $cipher = base64_encode($iv . openssl_encrypt('{"secret":"preserved"}', FOSSBilling\Crypt::LEGACY_METHOD, $key, OPENSSL_RAW_DATA, $iv));
        expect($crypt->decrypt($cipher, $pass))->toBe('{"secret":"preserved"}');
        if ($index === 0) {
            expect($crypt->decrypt('v2:' . $cipher, $pass))->toBe('{"secret":"preserved"}');
        }
    }
});
