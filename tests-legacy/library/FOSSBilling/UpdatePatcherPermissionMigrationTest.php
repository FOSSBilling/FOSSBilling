<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class UpdatePatcherPermissionMigrationTest extends \BBTestCase
{
    public function testPermissionMigrationCombinesLegacyAndNewPermissions(): void
    {
        $permissions = [
            'servicecustom' => ['access' => true, 'custom' => ['create' => true, 'list' => true]],
            'product_custom' => ['read' => true],
        ];

        $codes = ['custom'];

        $result = $this->simulatePermissionMigration($permissions, $codes);

        $this->assertArrayHasKey('product_custom', $result);
        $this->assertArrayHasKey('access', $result['product_custom']);
        $this->assertTrue($result['product_custom']['access']);
        $this->assertArrayHasKey('custom', $result['product_custom']);
        $this->assertTrue($result['product_custom']['custom']['create']);
        $this->assertTrue($result['product_custom']['custom']['list']);
        $this->assertArrayHasKey('read', $result['product_custom']);
        $this->assertTrue($result['product_custom']['read']);
    }

    public function testPermissionMigrationRemovesLegacyKeys(): void
    {
        $permissions = [
            'servicecustom' => ['access' => true],
            'serviceapikey' => ['access' => true],
        ];

        $codes = ['custom', 'apikey'];

        $result = $this->simulatePermissionMigration($permissions, $codes);

        $this->assertArrayNotHasKey('servicecustom', $result);
        $this->assertArrayNotHasKey('serviceapikey', $result);
        $this->assertArrayHasKey('product_custom', $result);
        $this->assertArrayHasKey('product_apikey', $result);
    }

    public function testPermissionMigrationHandlesNoLegacyPermissions(): void
    {
        $permissions = [
            'product_custom' => ['access' => true],
        ];

        $codes = ['custom'];

        $result = $this->simulatePermissionMigration($permissions, $codes);

        $this->assertSame($permissions, $result);
    }

    public function testPermissionMigrationHandlesEmptyPermissions(): void
    {
        $permissions = [];

        $codes = ['custom'];

        $result = $this->simulatePermissionMigration($permissions, $codes);

        $this->assertEmpty($result);
    }

    public function testPermissionMigrationWithMultipleProductTypes(): void
    {
        $permissions = [
            'servicehosting' => ['access' => true, 'manage' => true],
            'servicelicense' => ['access' => true],
            'servicecustom' => ['access' => true],
        ];

        $codes = ['hosting', 'license', 'custom'];

        $result = $this->simulatePermissionMigration($permissions, $codes);

        $this->assertArrayHasKey('product_hosting', $result);
        $this->assertArrayHasKey('product_license', $result);
        $this->assertArrayHasKey('product_custom', $result);

        $this->assertTrue($result['product_hosting']['access']);
        $this->assertTrue($result['product_hosting']['manage']);
        $this->assertTrue($result['product_license']['access']);
        $this->assertTrue($result['product_custom']['access']);

        $this->assertArrayNotHasKey('servicehosting', $result);
        $this->assertArrayNotHasKey('servicelicense', $result);
        $this->assertArrayNotHasKey('servicecustom', $result);
    }

    public function testPermissionMigrationPreservesExistingNewPermissions(): void
    {
        $permissions = [
            'servicecustom' => ['access' => true],
            'product_custom' => [
                'access' => false,
                'create' => true,
            ],
        ];

        $codes = ['custom'];

        $result = $this->simulatePermissionMigration($permissions, $codes);

        $this->assertArrayHasKey('product_custom', $result);
        $this->assertTrue($result['product_custom']['access']);
        $this->assertTrue($result['product_custom']['create']);
    }

    public function testPermissionMigrationAccessBooleanMerging(): void
    {
        $permissions = [
            'servicecustom' => true,
            'product_custom' => ['access' => false],
        ];

        $codes = ['custom'];

        $result = $this->simulatePermissionMigration($permissions, $codes);

        $this->assertTrue($result['product_custom']['access']);
    }

    public function testPermissionMigrationNoDuplicateCodes(): void
    {
        $permissions = [
            'servicecustom' => ['access' => true],
        ];

        $codes = ['custom', 'custom'];

        $result = $this->simulatePermissionMigration($permissions, $codes);

        $this->assertArrayHasKey('product_custom', $result);
    }

    /**
     * Simulates the permission migration logic from UpdatePatcher patch #50.
     */
    private function simulatePermissionMigration(array $permissions, array $codes): array
    {
        foreach ($codes as $code) {
            $legacyKey = 'service' . $code;
            if (!array_key_exists($legacyKey, $permissions)) {
                continue;
            }

            $legacyPerms = $permissions[$legacyKey];
            $newKey = 'product_' . $code;
            $newPerms = $permissions[$newKey] ?? [];

            if (!is_array($newPerms)) {
                $newPerms = [];
            }

            if (is_array($legacyPerms)) {
                foreach ($legacyPerms as $permKey => $value) {
                    if ($permKey === 'access') {
                        $newPerms[$permKey] = (bool) ($newPerms[$permKey] ?? false) || (bool) $value;
                    } elseif (!array_key_exists($permKey, $newPerms)) {
                        $newPerms[$permKey] = $value;
                    }
                }
            } else {
                $newPerms['access'] = (bool) ($newPerms['access'] ?? false) || (bool) $legacyPerms;
            }

            $permissions[$newKey] = $newPerms;
            unset($permissions[$legacyKey]);
        }

        return $permissions;
    }
}
