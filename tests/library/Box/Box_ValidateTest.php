<?php

/**
 * @group Core
 */
class Box_ValidateTest extends PHPUnit\Framework\TestCase
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

   
    public function testCheckRequiredParamsForArrayNotExist()
    {
        $data     = array();
        $required = array(
            'id' => 'ID must be set'
        );
        $v        = new Box_Validate();
        $this->expectException(Box_Exception::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('ID must be set');
        $v->checkRequiredParamsForArray($required, $data);
    }


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
        $this->expectException(Box_Exception::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('KEY must be set');
        $v->checkRequiredParamsForArray($required, $data);
    }

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
        $this->expectException(Box_Exception::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('KEY placeholder_key must be set');
        $v->checkRequiredParamsForArray($required, $data, $variables);
    }

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
        $this->expectException(Box_Exception::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('KEY placeholder_key must be set for array config');
        $v->checkRequiredParamsForArray($required, $data, $variables);
    }


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
        $this->expectException(Box_Exception::class);
        $this->expectExceptionCode(12345);
        $this->expectExceptionMessage('KEY placeholder_key must be set');
        $v->checkRequiredParamsForArray($required, $data, $variables, 12345);
    }

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
        $this->expectException(Box_Exception::class);
        $this->expectExceptionCode(54321);
        $this->expectExceptionMessage('KEY must be set');
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

        //add needed assert, set to true if no exception called in $v->checkRequiredParamsForArray
        $this->assertTrue(true);
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

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage($required['message']);

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

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage($required['message']);

        $v->checkRequiredParamsForArray($required, $data);
    }

}