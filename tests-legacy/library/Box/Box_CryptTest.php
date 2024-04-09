<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Box_CryptTest extends PHPUnit\Framework\TestCase
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
}
