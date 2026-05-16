<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class FOSSBilling_Doctrine_EntityManagerFactoryTest extends BBTestCase
{
    public function testCacheNamespaceSeedChangesWhenEntityDefinitionChanges(): void
    {
        $this->markTestIncomplete('Refactor this test to assert cache namespace changes via EntityManagerFactory public API instead of invoking private methods via reflection.');
    }
}
