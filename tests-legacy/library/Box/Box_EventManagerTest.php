<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Box_EventManagerTest extends PHPUnit\Framework\TestCase
{
    #[PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
    public function testEmptyFire(): void
    {
        $manager = new Box_EventManager();
        $manager->fire([]);
    }

    public function testFire(): void
    {
        $db_mock = $this->getMockBuilder('Box_Database')->getMock();
        $db_mock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn([]);

        $di = new Pimple\Container();
        $di['logger'] = new Box_Log();
        $di['db'] = $db_mock;

        $manager = new Box_EventManager();
        $manager->setDi($di);

        $manager->fire(['event' => 'onBeforeClientSignup']);
    }
}
