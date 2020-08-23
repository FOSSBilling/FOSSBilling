<?php
/**
 * @group Core
 */
class Box_SessionTest extends PHPUnit\Framework\TestCase
{
    public function testSession()
    {
        $mock = $this->getMockBuilder("Box_Session")
            ->disableOriginalConstructor()
            ->getMock();

        $mock->expects($this->atLeastOnce())
            ->method("getId")
            ->will($this->returnValue("rrcpqo7tkjh14d2vmf0car64k7"));

        $mock->expects($this->atLeastOnce())
            ->method("get")
            ->will($this->returnValue("testValue"));

        $mock->expects($this->atLeastOnce())
            ->method("delete")
            ->will($this->returnValue(true));

        $this->assertEquals($mock->getId(), 'rrcpqo7tkjh14d2vmf0car64k7', 'Session ID is not equal');

        $this->assertEquals($mock->get('testKey'), 'testValue', 'The value is not equal to the one which was set');

        $this->assertEquals($mock->delete('testKey'), true);

        $this->assertEquals($mock->set('testKey', 'testValue'), null, 'The value is not equal to the one which was set');

        $this->assertEquals($mock->destroy(), null, 'Session destroy did not return true');
    }
}