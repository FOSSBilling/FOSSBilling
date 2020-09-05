<?php
/**
 * @group Core
 */
class Box_PeriodTest extends PHPUnit\Framework\TestCase
{
    
    public function testException()
    {
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Invalid period code. Period definition must be 2 chars length');
        $p = new Box_Period('1');

    }
    
    public function testException2()
    {
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Period Error. Unit Z is not defined');
        $p = new Box_Period('1Z');
    }

    public function testOneMonth()
    {
        $p = new Box_Period('1M');

        $this->assertEquals('M', $p->getUnit());
        $this->assertEquals(1, $p->getQty());
        $this->assertEquals('1M', $p->getCode());
        $this->assertEquals('Every 1 months', $p->getTitle());
        $this->assertEquals(30, $p->getDays());
        $this->assertEquals(1, $p->getMonths());
        $this->assertEquals(strtotime('+1 month'), $p->getExpirationTime());
    }
}