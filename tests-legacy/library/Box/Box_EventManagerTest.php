<?php

declare(strict_types=1);

#[Group('Core')]
final class Box_EventManagerTest extends BBTestCase
{
    #[PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
    public function testEmptyFire(): void
    {
        $manager = new Box_EventManager();
        $manager->fire([]);
    }

    public function testFire(): void
    {
        $db_mock = $this->createMock('Box_Database');
        $db_mock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn([]);

        $di = $this->getDi();
        $di['logger'] = new Box_Log();
        $di['db'] = $db_mock;

        $manager = new Box_EventManager();
        $manager->setDi($di);

        $manager->fire(['event' => 'onBeforeClientSignup']);
    }
}
