<?php

/**
 * @group Core
 */
class Box_ValidateTest extends PHPUnit_Framework_TestCase
{
    public static function domains()
    {
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

    public function testCheckRequiredParamsForArray()
    {
        $data     = array(
            'id'  => rand(1, 10),
            'key' => 'KEY must be set'
        );
        $required = array(
            'id'  => 'ID must be set',
            'key' => 'KEY must be set'
        );
        $v        = new Box_Validate();
        $this->assertNull($v->checkRequiredParamsForArray($required, $data));
    }

    /**
     * @expectedException \Box_Exception
     * @expectedExceptionCode 0
     * @expectedExceptionMessage ID must be set
     */
    public function testCheckRequiredParamsForArrayNotExist()
    {
        $data     = array();
        $required = array(
            'id' => 'ID must be set'
        );
        $v        = new Box_Validate();
        $v->checkRequiredParamsForArray($required, $data);
    }


    /**
     * @expectedException \Box_Exception
     * @expectedExceptionCode 0
     * @expectedExceptionMessage KEY must be set
     */
    public function testCheckRequiredParamsForArrayOneKeyNotExists()
    {
        $data     = array(
            'id' => rand(1, 10)
        );
        $required = array(
            'id'  => 'ID must be set',
            'key' => 'KEY must be set'
        );
        $v        = new Box_Validate();
        $v->checkRequiredParamsForArray($required, $data);
    }


    /**
     * @expectedException \Box_Exception
     * @expectedExceptionCode 0
     * @expectedExceptionMessage KEY placeholder_key must be set
     */
    public function testCheckRequiredParamsForArrayMessagePlaceholder()
    {
        $data     = array(
            'id' => rand(1, 10)
        );
        $required = array(
            'id'  => 'ID must be set',
            'key' => 'KEY :key must be set'
        );

        $variables = array(':key' => 'placeholder_key');
        $v         = new Box_Validate();
        $v->checkRequiredParamsForArray($required, $data, $variables);
    }

    /**
     * @expectedException \Box_Exception
     * @expectedExceptionCode 0
     * @expectedExceptionMessage KEY placeholder_key must be set for array config
     */
    public function testCheckRequiredParamsForArrayMessagePlaceholders()
    {
        $data     = array(
            'id' => rand(1, 10)
        );
        $required = array(
            'id'  => 'ID must be set',
            'key' => 'KEY :key must be set for array :array'
        );

        $variables = array(
            ':key'   => 'placeholder_key',
            ':array' => 'config'
        );
        $v         = new Box_Validate();
        $v->checkRequiredParamsForArray($required, $data, $variables);
    }


    /**
     * @expectedException \Box_Exception
     * @expectedExceptionCode 12345
     * @expectedExceptionMessage KEY placeholder_key must be set
     */
    public function testCheckRequiredParamsForArrayErrorCode()
    {
        $data     = array(
            'id' => rand(1, 10)
        );
        $required = array(
            'id'  => 'ID must be set',
            'key' => 'KEY :key must be set'
        );

        $variables = array(':key' => 'placeholder_key');
        $v         = new Box_Validate();
        $v->checkRequiredParamsForArray($required, $data, $variables, 12345);
    }

    /**
     * @expectedException \Box_Exception
     * @expectedExceptionCode 54321
     * @expectedExceptionMessage KEY must be set
     */
    public function testCheckRequiredParamsForArrayErrorCodeVariablesNotSet()
    {
        $data     = array(
            'id' => rand(1, 10)
        );
        $required = array(
            'id'  => 'ID must be set',
            'key' => 'KEY must be set'
        );

        $v = new Box_Validate();
        $v->checkRequiredParamsForArray($required, $data, array(), 54321);
    }

    public function testcheckRequiredParamsForArray_KeyValueIsZero()
    {
        $data     = array(
            'amount' => 0
        );
        $required = array(
            'amount'  => 'amount must be set',
        );

        $v = new Box_Validate();
        $v->checkRequiredParamsForArray($required, $data);
    }

    public function testcheckRequiredParamsForArray_EmptyString()
    {
        $data     = array(
            'message' => ''
        );
        $required = array(
            'message'  => 'message must be set',
        );

        $v = new Box_Validate();
        $this->setExpectedException('\Box_Exception', $required['message']);
        $v->checkRequiredParamsForArray($required, $data);
    }


    public function testcheckRequiredParamsForArray_EmptyStringFilledWithSpaces()
    {
        $data     = array(
            'message' => '    '
        );
        $required = array(
            'message'  => 'message must be set',
        );

        $v = new Box_Validate();
        $this->setExpectedException('\Box_Exception', $required['message']);
        $v->checkRequiredParamsForArray($required, $data);
    }

}