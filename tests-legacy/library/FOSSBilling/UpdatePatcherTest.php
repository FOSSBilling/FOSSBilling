<?php

declare(strict_types=1);

use FOSSBilling\UpdatePatcher;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class UpdatePatcherTest extends PHPUnit\Framework\TestCase
{
    public function testSetDiDoesNotRequireDbalWhenUpdatingWithLegacyContainer(): void
    {
        $di = new Pimple\Container();

        $patcher = new UpdatePatcher();
        $patcher->setDi($di);

        self::assertFalse($di->offsetExists('dbal'));
    }

    public function testDatabaseAccessUsesPdoWhenLegacyContainerWasInjectedBeforehand(): void
    {
        $di = new Pimple\Container();
        $di['pdo'] = new PDO('sqlite::memory:');
        $patcher = new UpdatePatcher();

        $diProperty = new ReflectionProperty(UpdatePatcher::class, 'di');
        $diProperty->setValue($patcher, $di);

        $method = new ReflectionMethod(UpdatePatcher::class, 'getPdo');
        $pdo = $method->invoke($patcher);

        self::assertSame($di['pdo'], $pdo);
        self::assertFalse($di->offsetExists('dbal'));
    }

    public function testPatchMethodsDeferUnlessForced(): void
    {
        $patcher = new UpdatePatcher();

        $patcher->applyConfigPatches();
        $patcher->applyCorePatches();

        self::addToAssertionCount(1);
    }

    public function testCorePatchesCanBeForced(): void
    {
        $patcher = new UpdatePatcher();

        $this->expectException(FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Database connection is not available.');

        $patcher->applyCorePatches(force: true);
    }
}
