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

class Patch78 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Rework admin groups and permissions
        //
        // This patch migrates from individual admin-scoped permissions to group permissions.
        // It allows admins to be a part of multiple groups,
        // sets up a hierarchy between admin groups and creates
        // a new protected group named Super Administrator.
        //
        // See https://github.com/FOSSBilling/FOSSBilling/pull/3821.
        if (!$patcher->tableHasColumn('admin_group', 'system_name')) {
            $patcher->executeSql('ALTER TABLE `admin_group` ADD COLUMN `system_name` VARCHAR(100) DEFAULT NULL AFTER `name`;');
        }

        if (!$patcher->tableHasColumn('admin_group', 'parent_id')) {
            $patcher->executeSql('ALTER TABLE `admin_group` ADD COLUMN `parent_id` bigint(20) DEFAULT NULL AFTER `system_name`;');
        }

        if (!$patcher->tableHasColumn('admin_group', 'permissions')) {
            $patcher->executeSql('ALTER TABLE `admin_group` ADD COLUMN `permissions` JSON AFTER `parent_id`;');
        }

        if (!$patcher->tableHasColumn('admin_group', 'protected')) {
            $patcher->executeSql("ALTER TABLE `admin_group` ADD COLUMN `protected` TINYINT(1) DEFAULT '0' AFTER `permissions`;");
        }

        if (!$patcher->tableHasIndex('admin_group', 'system_name')) {
            $patcher->executeSql('ALTER TABLE `admin_group` ADD UNIQUE INDEX `system_name` (`system_name`);');
        }

        if (!$patcher->tableHasIndex('admin_group', 'admin_group_parent_id_idx')) {
            $patcher->executeSql('ALTER TABLE `admin_group` ADD KEY `admin_group_parent_id_idx` (`parent_id`);');
        }

        if (!$patcher->tableExists('admin_group_member')) {
            $patcher->executeSql('CREATE TABLE `admin_group_member` (
                `id` bigint(20) NOT NULL AUTO_INCREMENT,
                `admin_id` bigint(20) NOT NULL,
                `admin_group_id` bigint(20) NOT NULL,
                `created_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `admin_group_member_unique` (`admin_id`, `admin_group_id`),
                KEY `admin_group_member_admin_id_idx` (`admin_id`),
                KEY `admin_group_member_group_id_idx` (`admin_group_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
        }

        // Convert the existing admin group into the protected Super Administrator group.
        $now = date('Y-m-d H:i:s');
        $superAdminGroupId = $patcher->fetchOne("SELECT id FROM admin_group WHERE system_name = 'super_admin' LIMIT 1");
        if (!$superAdminGroupId) {
            $firstGroupId = $patcher->fetchOne('SELECT id FROM admin_group WHERE id = 1 LIMIT 1');
            if ($firstGroupId) {
                $patcher->executeSql(
                    "UPDATE admin_group SET name = 'Super Administrator', system_name = 'super_admin', permissions = NULL, protected = 1, updated_at = :updated_at WHERE id = 1",
                    ['updated_at' => $now],
                );
                $superAdminGroupId = 1;
            } else {
                $patcher->executeSql(
                    "INSERT INTO admin_group (name, system_name, permissions, protected, created_at, updated_at) VALUES ('Super Administrator', 'super_admin', NULL, 1, :created_at, :updated_at)",
                    ['created_at' => $now, 'updated_at' => $now],
                );
                $superAdminGroupId = (int) $patcher->getPdo()->lastInsertId();
            }
        } else {
            $patcher->executeSql(
                'UPDATE admin_group SET protected = 1, permissions = NULL, updated_at = :updated_at WHERE id = :id',
                ['updated_at' => $now, 'id' => $superAdminGroupId],
            );
        }

        $patcher->executeSql(
            "INSERT INTO admin_group (name, system_name, parent_id, permissions, protected, created_at, updated_at) VALUES ('Migrated staff', 'migrated_staff', :parent_id, NULL, 0, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE name = 'Migrated staff', parent_id = :parent_id, permissions = NULL, protected = 0, updated_at = :updated_at",
            ['parent_id' => $superAdminGroupId, 'created_at' => $now, 'updated_at' => $now],
        );
        $migratedStaffGroupId = (int) $patcher->fetchOne("SELECT id FROM admin_group WHERE system_name = 'migrated_staff' LIMIT 1");

        // Move legacy one-group-per-admin assignments into the new membership table before dropping the column.
        $patcher->executeSql('
            INSERT IGNORE INTO admin_group_member (admin_id, admin_group_id, created_at)
            SELECT id, admin_group_id, :created_at
            FROM admin
            WHERE admin_group_id IS NOT NULL
              AND (admin_group_id != :super_admin_group_id OR role = :role)
        ', [
            'created_at' => $now,
            'super_admin_group_id' => $superAdminGroupId,
            'role' => 'admin',
        ]);

        $patcher->executeSql('
            INSERT IGNORE INTO admin_group_member (admin_id, admin_group_id, created_at)
            SELECT id, :admin_group_id, :created_at
            FROM admin
            WHERE admin_group_id = :super_admin_group_id
              AND (role IS NULL OR role != :role)
        ', [
            'admin_group_id' => $migratedStaffGroupId,
            'created_at' => $now,
            'super_admin_group_id' => $superAdminGroupId,
            'role' => 'admin',
        ]);

        $patcher->executeSql('
            INSERT IGNORE INTO admin_group_member (admin_id, admin_group_id, created_at)
            SELECT id, :admin_group_id, :created_at
            FROM admin
            WHERE role = :role
        ', [
            'admin_group_id' => $superAdminGroupId,
            'created_at' => $now,
            'role' => 'admin',
        ]);

        // Drop legacy staff-level group ID columns after their data has been migrated.
        if ($patcher->tableHasColumn('admin', 'admin_group_id')) {
            if ($patcher->tableHasIndex('admin', 'admin_group_id_idx')) {
                $patcher->executeSql('ALTER TABLE `admin` DROP INDEX `admin_group_id_idx`;');
            }

            $patcher->executeSql('ALTER TABLE `admin` DROP COLUMN `admin_group_id`;');
        }

        if (!$patcher->tableHasColumn('admin', 'system_name')) {
            $patcher->executeSql('ALTER TABLE `admin` ADD COLUMN `system_name` varchar(100) DEFAULT NULL AFTER `id`;');
        }

        if ($patcher->tableHasColumn('admin', 'role')) {
            $patcher->executeSql("
                UPDATE `admin`
                SET `system_name` = 'cron'
                WHERE `role` = 'cron'
                  AND (`system_name` IS NULL OR `system_name` = '')
                  AND NOT EXISTS (
                      SELECT 1 FROM (SELECT `id` FROM `admin` WHERE `system_name` = 'cron' LIMIT 1) AS existing_cron
                  )
                ORDER BY `id` ASC
                LIMIT 1;
            ");
            $patcher->executeSql('ALTER TABLE `admin` DROP COLUMN `role`;');
        }

        if (!$patcher->tableHasIndex('admin', 'system_name')) {
            $patcher->executeSql('ALTER TABLE `admin` ADD UNIQUE KEY `system_name` (`system_name`);');
        }

        if ($patcher->tableHasColumn('admin', 'permissions')) {
            $patcher->executeSql('ALTER TABLE `admin` DROP COLUMN `permissions`;');
        }

        if ($patcher->tableHasColumn('admin', 'protected')) {
            $patcher->executeSql('ALTER TABLE `admin` DROP COLUMN `protected`;');
        }
    }
}
