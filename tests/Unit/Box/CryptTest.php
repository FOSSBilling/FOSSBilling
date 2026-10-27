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

function cryptDerivedKeys(): array
{
    return (new ReflectionClass(FOSSBilling\Crypt::class))->getProperty('derivedKeys')->getValue();
}

test('a ciphertext is only readable with the passphrase that produced it', function (): void {
    $crypt = new FOSSBilling\Crypt();

    $a = $crypt->encrypt('secret for A', 'passphrase-A');
    $b = $crypt->encrypt('secret for B', 'passphrase-B');

    expect($crypt->decrypt($a, 'passphrase-A'))->toBe('secret for A')
        ->and($crypt->decrypt($b, 'passphrase-B'))->toBe('secret for B')
        ->and($crypt->decrypt($a, 'passphrase-B'))->toBeFalse()
        ->and($crypt->decrypt($b, 'passphrase-A'))->toBeFalse();
});

test('the derived key is computed once per passphrase and kept only in memory', function (): void {
    $crypt = new FOSSBilling\Crypt();
    $before = count(cryptDerivedKeys());

    $cipher = $crypt->encrypt('repeat me', 'memo-passphrase-1');
    $crypt->decrypt($cipher, 'memo-passphrase-1');
    $crypt->decrypt($cipher, 'memo-passphrase-1');
    expect(count(cryptDerivedKeys()))->toBe($before + 1);

    $crypt->encrypt('another', 'memo-passphrase-2');
    expect(count(cryptDerivedKeys()))->toBe($before + 2);

    // The memo is keyed by a hash, so the passphrase itself is never held as an array key.
    expect(array_keys(cryptDerivedKeys()))->not->toContain('memo-passphrase-1');
});
