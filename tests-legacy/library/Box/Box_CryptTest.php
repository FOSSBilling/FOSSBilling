<?php

declare(strict_types=1);

#[Group('Core')]
final class Box_CryptTest extends PHPUnit\Framework\TestCase
{
    public function testCrypt(): void
    {
        $key = 'le password';
        $text = 'foo bar';

        $crypt = new Box_Crypt();
        $encoded = $crypt->encrypt($text, $key);
        $decoded = $crypt->decrypt($encoded, $key);
        $this->assertEquals($text, $decoded);
    }

    public function testDecryptLegacyCiphertext(): void
    {
        $key = 'le password';
        $text = 'foo bar';

        $crypt = new Box_Crypt();

        $this->assertSame($text, $crypt->decrypt($this->encryptWithLegacyKey($text, $key), $key));
    }

    public function testDecryptCurrentUnversionedCiphertext(): void
    {
        $key = 'le password';
        $text = 'foo bar';

        $crypt = new Box_Crypt();

        $this->assertSame($text, $crypt->decrypt($this->encryptWithCurrentKey($text, $key), $key));
    }

    private function encryptWithLegacyKey(string $text, string $pass): string
    {
        return $this->encryptWithKey($text, pack('H*', hash('md5', $pass)));
    }

    private function encryptWithCurrentKey(string $text, string $pass): string
    {
        return $this->encryptWithKey($text, hash_pbkdf2('sha256', $pass, 'fossbilling_salt', 100000, 32, true));
    }

    private function encryptWithKey(string $text, string $key): string
    {
        $iv = str_repeat("\x01", openssl_cipher_iv_length(Box_Crypt::METHOD));
        $ciphertext = openssl_encrypt($text, Box_Crypt::METHOD, $key, OPENSSL_RAW_DATA, $iv);

        return base64_encode($iv . $ciphertext);
    }
}
