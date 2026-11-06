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

    $crypt = new FOSSBilling\Core\Security\Crypt();
    $encoded = $crypt->encrypt($text, $key);
    $decoded = $crypt->decrypt($encoded, $key);
    expect($decoded)->toEqual($text);
});

test('authenticated ciphertext rejects tampering and wrong keys while retaining exact bytes', function (): void {
    $crypt = new FOSSBilling\Core\Security\Crypt();
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
    $crypt = new FOSSBilling\Core\Security\Crypt();
    $pass = 'test-key';
    $iv = str_repeat('x', 16);
    foreach ([hash_pbkdf2('sha256', $pass, 'fossbilling_salt', 100000, 32, true), pack('H*', md5($pass))] as $index => $key) {
        $cipher = base64_encode($iv . openssl_encrypt('{"secret":"preserved"}', FOSSBilling\Core\Security\Crypt::LEGACY_METHOD, $key, OPENSSL_RAW_DATA, $iv));
        expect($crypt->decrypt($cipher, $pass))->toBe('{"secret":"preserved"}');
        if ($index === 0) {
            expect($crypt->decrypt('v2:' . $cipher, $pass))->toBe('{"secret":"preserved"}');
        }
    }
});

test('a ciphertext is only readable with the passphrase that produced it', function (): void {
    $crypt = new FOSSBilling\Core\Security\Crypt();

    $a = $crypt->encrypt('secret for A', 'passphrase-A');
    $b = $crypt->encrypt('secret for B', 'passphrase-B');

    expect($crypt->decrypt($a, 'passphrase-A'))->toBe('secret for A')
        ->and($crypt->decrypt($b, 'passphrase-B'))->toBe('secret for B')
        // A wrong key usually fails padding and returns false, but it can occasionally
        // decrypt to unrelated garbage, so assert it never returns the other secret.
        ->and($crypt->decrypt($a, 'passphrase-B'))->not->toBe('secret for A')
        ->and($crypt->decrypt($b, 'passphrase-A'))->not->toBe('secret for B');
});

test('the derived key is computed once per passphrase and kept only in memory', function (): void {
    FOSSBilling\Core\Security\Crypt::clearDerivedKeys();
    $derivedKeyCount = fn (): int => count((new ReflectionClass(FOSSBilling\Core\Security\Crypt::class))->getStaticPropertyValue('derivedKeys'));

    $crypt = new FOSSBilling\Core\Security\Crypt();

    $cipher = $crypt->encrypt('repeat me', 'memo-passphrase-1');
    $crypt->decrypt($cipher, 'memo-passphrase-1');
    $crypt->decrypt($cipher, 'memo-passphrase-1');
    expect($derivedKeyCount())->toBe(1);

    $crypt->encrypt('another', 'memo-passphrase-2');
    expect($derivedKeyCount())->toBe(2);

    // The memo is keyed by a hash, so the passphrase itself is never held as an array key.
    $keys = (new ReflectionClass(FOSSBilling\Core\Security\Crypt::class))->getStaticPropertyValue('derivedKeys');
    expect(array_keys($keys))->not->toContain('memo-passphrase-1');
});

test('a cache hit reuses the stored derived key without deriving it again', function (): void {
    FOSSBilling\Core\Security\Crypt::clearDerivedKeys();
    $crypt = new FOSSBilling\Core\Security\Crypt();
    $crypt->encrypt('warm up', 'hit-passphrase');

    // Replace the cached entry with a sentinel. A cache hit returns the stored value,
    // so the sentinel must survive the next call; re-deriving would overwrite it.
    $cache = new ReflectionClass(FOSSBilling\Core\Security\Crypt::class);
    $id = hash('sha256', 'hit-passphrase');
    $sentinel = str_repeat('s', 32);

    $derived = $cache->getStaticPropertyValue('derivedKeys');
    $derived[$id] = $sentinel;
    $cache->setStaticPropertyValue('derivedKeys', $derived);

    $crypt->encrypt('again', 'hit-passphrase');

    expect($cache->getStaticPropertyValue('derivedKeys')[$id])->toBe($sentinel);
});
