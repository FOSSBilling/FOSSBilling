<?php
/**
 * @group Core
 */
class Box_CryptTest extends PHPUnit\Framework\TestCase
{
    public function testCrypt()
    {
        $key = 'le password';
        $text = 'foo bar';

        $crypt = new Box_Crypt();
        $encoded = $crypt->encrypt($text, $key);
        $decoded = $crypt->decrypt($encoded, $key);
        $this->assertEquals($text, $decoded);
    }
}