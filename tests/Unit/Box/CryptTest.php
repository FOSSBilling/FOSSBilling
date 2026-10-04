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

    $crypt = new Box_Crypt();
    $encoded = $crypt->encrypt($text, $key);
    $decoded = $crypt->decrypt($encoded, $key);
    expect($decoded)->toEqual($text);
});

test('rejects ciphertext without a complete IV and payload without warnings', function (int $length, string $prefix): void {
    set_error_handler(static function (int $severity, string $message): never {
        throw new ErrorException($message, 0, $severity);
    });

    try {
        $crypt = new Box_Crypt();
        expect($crypt->decrypt($prefix . base64_encode(str_repeat('x', $length)), 'password'))->toBeFalse();
    } finally {
        restore_error_handler();
    }
})->with([0, 6, 15, 16])->with(['', Box_Crypt::CURRENT_FORMAT_PREFIX]);

test('decrypts legacy ciphertext', function (): void {
    $password = 'legacy password';
    $iv = random_bytes(openssl_cipher_iv_length(Box_Crypt::METHOD));
    $ciphertext = openssl_encrypt('legacy secret', Box_Crypt::METHOD, pack('H*', hash('md5', $password)), OPENSSL_RAW_DATA, $iv);

    expect((new Box_Crypt())->decrypt(base64_encode($iv . $ciphertext), $password))->toBe('legacy secret');
});
