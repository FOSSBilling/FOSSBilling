<?php

class FOSSBilling_PasswordManagerTest extends PHPUnit\Framework\TestCase
{
    public function testsetAlgo(): void
    {
        $boxPassword = new FOSSBilling\PasswordManager();
        $algo = PASSWORD_BCRYPT;
        $boxPassword->setAlgo($algo);
        $result = $boxPassword->getAlgo();
        $this->assertEquals($algo, $result);
    }

    public function testSetOptions(): void
    {
        $boxPassword = new FOSSBilling\PasswordManager();
        $options = [
            'cost' => 12,
        ];
        $boxPassword->setOptions($options);
        $result = $boxPassword->getOptions();
        $this->assertEquals($options, $result);
    }

    public function testHashing(): void
    {
        $boxPassword = new FOSSBilling\PasswordManager();
        $password = '123456';
        $hash = $boxPassword->hashIt($password);
        $this->assertIsString($hash);
        $this->assertNotEmpty($hash);

        $veryfied = $boxPassword->verify($password, $hash);
        $this->assertIsBool($veryfied);
        $this->assertTrue($veryfied);

        $needRehashing = $boxPassword->needsRehash($hash);
        $this->assertIsBool($needRehashing);
        $this->assertFalse($needRehashing);
    }

    public function testNeedsRehashing(): void
    {
        $boxPassword = new FOSSBilling\PasswordManager();
        $password = '123456';
        $hash = $boxPassword->hashIt($password);
        $this->assertIsString($hash);
        $this->assertNotEmpty($hash);

        $newOptions = ['cost' => 15];
        $boxPassword->setOptions($newOptions);

        $needRehashing = $boxPassword->needsRehash($hash);
        $this->assertIsBool($needRehashing);
        $this->assertTrue($needRehashing);
    }
}
