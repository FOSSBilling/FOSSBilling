<?php

declare(strict_types=1);

#[PHPUnit\Framework\Attributes\Group('Core')]
final class Box_EventManagerTest extends PHPUnit\Framework\TestCase
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

        $di = new Pimple\Container();
        $di['logger'] = new Box_Log();
        $di['db'] = $db_mock;

        $manager = new Box_EventManager();
        $manager->setDi($di);

        $manager->fire(['event' => 'onBeforeClientSignup']);
    }
}
