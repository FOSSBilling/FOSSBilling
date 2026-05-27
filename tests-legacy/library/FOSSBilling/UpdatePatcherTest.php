<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use FOSSBilling\UpdatePatcher;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class UpdatePatcherTest extends PHPUnit\Framework\TestCase
{
    public function testSetDiRegistersDbalWhenUpdatingWithLegacyContainer(): void
    {
        $di = new Pimple\Container();

        $patcher = new UpdatePatcher();
        $patcher->setDi($di);

        self::assertTrue($di->offsetExists('dbal'));
    }

    public function testDbalAccessCreatesConnectionWhenLegacyContainerWasInjectedBeforehand(): void
    {
        $di = new Pimple\Container();
        $patcher = new UpdatePatcher();

        $diProperty = new ReflectionProperty(UpdatePatcher::class, 'di');
        $diProperty->setValue($patcher, $di);

        $method = new ReflectionMethod(UpdatePatcher::class, 'getDbalConnection');
        $connection = $method->invoke($patcher);

        self::assertInstanceOf(Connection::class, $connection);
        self::assertFalse($di->offsetExists('dbal'));
    }
}
