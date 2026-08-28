<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Update\Patch;

use FOSSBilling\Update\Patcher;

class Patch79 implements PatchInterface
{
    public function getVersion(): int
    {
        return 79;
    }

    public function apply(Patcher $patcher): void
    {
        // Create better default groups with sensible permissions
        //
        // This patch creates new default groups named Support Lead and Support Staff
        // Support Lead is allowed to create/edit staff members and appoint them to groups below itself (in this case, the Support Staff group)
        // Support Staff is allowed to access and manage support tickets without any additional permissions
        //
        // This is part of the admin groups and permissions rework. See https://github.com/FOSSBilling/FOSSBilling/pull/3821
        $now = date('Y-m-d H:i:s');
        $superAdminGroupId = $patcher->fetchOne("SELECT id FROM admin_group WHERE system_name = 'super_admin' LIMIT 1");
        $supportLeadPermissions = json_encode([
            'support' => [
                'access' => true,
                'view' => true,
                'manage_tickets' => true,
                'manage_helpdesk' => true,
                'manage_canned' => true,
                'manage_kb' => true,
            ],
            'staff' => [
                'access' => true,
                'view' => true,
                'create_and_edit_staff' => true,
                'reset_staff_password' => true,
                'manage_groups' => true,
                'manage_settings' => true,
            ],
        ], JSON_THROW_ON_ERROR);
        $patcher->executeSql(
            "INSERT INTO admin_group (name, system_name, parent_id, permissions, protected, created_at, updated_at) VALUES ('Support Lead', 'support_lead', :parent_id, :permissions, 0, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE name = 'Support Lead', parent_id = :parent_id, permissions = :permissions, protected = 0, updated_at = :updated_at",
            ['parent_id' => $superAdminGroupId, 'permissions' => $supportLeadPermissions, 'created_at' => $now, 'updated_at' => $now],
        );
        $supportLeadGroupId = (int) $patcher->fetchOne("SELECT id FROM admin_group WHERE system_name = 'support_lead' LIMIT 1");

        $supportStaffPermissions = json_encode([
            'support' => [
                'access' => true,
                'view' => true,
                'manage_tickets' => true,
            ],
        ], JSON_THROW_ON_ERROR);
        $patcher->executeSql(
            "INSERT INTO admin_group (name, system_name, parent_id, permissions, protected, created_at, updated_at) VALUES ('Support Staff', 'support_staff', :parent_id, :permissions, 0, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE name = 'Support Staff', parent_id = :parent_id, permissions = :permissions, protected = 0, updated_at = :updated_at",
            ['parent_id' => $supportLeadGroupId, 'permissions' => $supportStaffPermissions, 'created_at' => $now, 'updated_at' => $now],
        );
    }
}
