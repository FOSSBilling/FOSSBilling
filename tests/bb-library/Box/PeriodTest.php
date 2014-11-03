<?php
/**
 * @group Core
 */
class Box_PeriodTest extends PHPUnit_Framework_TestCase
{
    /**
     * @expectedException Box_Exception
     */
    public function testException()
    {
        $p = new Box_Period('1');
    }

    /**
     * @expectedException Box_Exception
     */
    public function testException2()
    {
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