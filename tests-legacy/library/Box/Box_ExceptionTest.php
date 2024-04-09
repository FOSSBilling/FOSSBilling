<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Box_ExceptionTest extends PHPUnit\Framework\TestCase
{
    public function testException(): void
    {
        $e = new Box_Exception('php :msg', [':msg' => 'unit'], 789);
        $this->assertEquals('php unit', $e->getMessage());
        $this->assertEquals(789, $e->getCode());
    }
}
