<?php


class Box_PasswordTest extends PHPUnit\Framework\TestCase {

    public function testsetAlgo()
    {
        $boxPassword = new Box_Password();
        $algo = PASSWORD_BCRYPT;
        $boxPassword->setAlgo($algo);
        $result = $boxPassword->getAlgo();
        $this->assertEquals($algo, $result);
    }

    public function testSetOptions()
    {
        $boxPassword = new Box_Password();
        $options = array(
            'cost' => 12,
        );
        $boxPassword->setOptions($options);
        $result = $boxPassword->getOptions();
        $this->assertEquals($options, $result);
    }

    public function testHashing()
    {
        $boxPassword = new Box_Password();
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

    public function testNeedsRehashing()
    {
        $boxPassword = new Box_Password();
        $password = '123456';
        $hash = $boxPassword->hashIt($password);
        $this->assertIsString($hash);
        $this->assertNotEmpty($hash);

        $newOptions = array('cost' => 15);
        $boxPassword->setOptions($newOptions);

        $needRehashing = $boxPassword->needsRehash($hash);
        $this->assertIsBool($needRehashing);
        $this->assertTrue($needRehashing);
    }
}
 