<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class FOSSBilling_ValidateTest extends PHPUnit\Framework\TestCase
{
    public static function domains(): array
    {
        return [
            ['google', true],
            ['1goo-gle', true],

            // punny code
            ['xn--bcher-kva', true],

            ['qqq45%%%', false],
            ['()1google', false],
            ['//asdasd()()', false],
            ['--asdasd()()', false],
        ];
    }

    #[PHPUnit\Framework\Attributes\DataProvider('domains')]
    public function testValidator(string $domain, bool $valid): void
    {
        $v = new FOSSBilling\Validate();
        $this->assertEquals($valid, $v->isSldValid($domain));
    }

    public function testCheckRequiredParamsForArray(): void
    {
        $data = [
            'id' => random_int(1, 10),
            'key' => 'KEY must be set',
        ];
        $required = [
            'id' => 'ID must be set',
            'key' => 'KEY must be set',
        ];
        $v = new FOSSBilling\Validate();
        $this->assertNull($v->checkRequiredParamsForArray($required, $data));
    }

    public function testCheckRequiredParamsForArrayNotExist(): void
    {
        $data = [];
        $required = [
            'id' => 'ID must be set',
        ];
        $v = new FOSSBilling\Validate();
        $this->expectException(FOSSBilling\Exception::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('ID must be set');
        $v->checkRequiredParamsForArray($required, $data);
    }

    public function testCheckRequiredParamsForArrayOneKeyNotExists(): void
    {
        $data = [
            'id' => random_int(1, 10),
        ];
        $required = [
            'id' => 'ID must be set',
            'key' => 'KEY must be set',
        ];
        $v = new FOSSBilling\Validate();
        $this->expectException(FOSSBilling\Exception::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('KEY must be set');
        $v->checkRequiredParamsForArray($required, $data);
    }

    public function testCheckRequiredParamsForArrayMessagePlaceholder(): void
    {
        $data = [
            'id' => random_int(1, 10),
        ];
        $required = [
            'id' => 'ID must be set',
            'key' => 'KEY :key must be set',
        ];

        $variables = [':key' => 'placeholder_key'];
        $v = new FOSSBilling\Validate();
        $this->expectException(FOSSBilling\Exception::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('KEY placeholder_key must be set');
        $v->checkRequiredParamsForArray($required, $data, $variables);
    }

    public function testCheckRequiredParamsForArrayMessagePlaceholders(): void
    {
        $data = [
            'id' => random_int(1, 10),
        ];
        $required = [
            'id' => 'ID must be set',
            'key' => 'KEY :key must be set for array :array',
        ];

        $variables = [
            ':key' => 'placeholder_key',
            ':array' => 'config',
        ];
        $v = new FOSSBilling\Validate();
        $this->expectException(FOSSBilling\Exception::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('KEY placeholder_key must be set for array config');
        $v->checkRequiredParamsForArray($required, $data, $variables);
    }

    public function testCheckRequiredParamsForArrayErrorCode(): void
    {
        $data = [
            'id' => random_int(1, 10),
        ];
        $required = [
            'id' => 'ID must be set',
            'key' => 'KEY :key must be set',
        ];

        $variables = [':key' => 'placeholder_key'];
        $v = new FOSSBilling\Validate();
        $this->expectException(FOSSBilling\Exception::class);
        $this->expectExceptionCode(12345);
        $this->expectExceptionMessage('KEY placeholder_key must be set');
        $v->checkRequiredParamsForArray($required, $data, $variables, 12345);
    }

    public function testCheckRequiredParamsForArrayErrorCodeVariablesNotSet(): void
    {
        $data = [
            'id' => random_int(1, 10),
        ];
        $required = [
            'id' => 'ID must be set',
            'key' => 'KEY must be set',
        ];

        $v = new FOSSBilling\Validate();
        $this->expectException(FOSSBilling\Exception::class);
        $this->expectExceptionCode(54321);
        $this->expectExceptionMessage('KEY must be set');
        $v->checkRequiredParamsForArray($required, $data, [], 54321);
    }

    public function testcheckRequiredParamsForArrayKeyValueIsZero(): void
    {
        $data = [
            'amount' => 0,
        ];
        $required = [
            'amount' => 'amount must be set',
        ];

        $v = new FOSSBilling\Validate();
        $v->checkRequiredParamsForArray($required, $data);

        // add needed assert, set to true if no exception called in $v->checkRequiredParamsForArray
        $this->assertTrue(true);
    }

    public function testcheckRequiredParamsForArrayEmptyString(): void
    {
        $data = [
            'message' => '',
        ];
        $required = [
            'message' => 'message must be set',
        ];

        $v = new FOSSBilling\Validate();

        $this->expectException(FOSSBilling\Exception::class);
        $this->expectExceptionMessage($required['message']);

        $v->checkRequiredParamsForArray($required, $data);
    }

    public function testcheckRequiredParamsForArrayEmptyStringFilledWithSpaces(): void
    {
        $data = [
            'message' => '    ',
        ];
        $required = [
            'message' => 'message must be set',
        ];

        $v = new FOSSBilling\Validate();

        $this->expectException(FOSSBilling\Exception::class);
        $this->expectExceptionMessage($required['message']);

        $v->checkRequiredParamsForArray($required, $data);
    }
}
