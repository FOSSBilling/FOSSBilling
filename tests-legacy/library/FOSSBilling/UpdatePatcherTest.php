<?php

declare(strict_types=1);

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
}
