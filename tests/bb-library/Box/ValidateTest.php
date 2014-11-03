<?php
/**
 * @group Core
 */
class Box_ValidateTest extends PHPUnit_Framework_TestCase
{
    public static function domains() {
        return array(
            array('google', true),
            array('1goo-gle', true),

            //punny code
            array('xn--bcher-kva', true),

            array('qqq45%%%', false),
            array('()1google', false),
            array('//asdasd()()', false),
            array('--asdasd()()', false),
        );
    }

    /**
     * @dataProvider domains
     */
    public function testValidator($domain, $valid)
    {
        $v = new Box_Validate();
        $this->assertEquals($valid, $v->isSldValid($domain));
    }
}