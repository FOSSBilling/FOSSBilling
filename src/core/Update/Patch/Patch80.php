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

class Patch80 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // #3856 started requiring an explicit "manage_settings" permission to view or edit a
        // module's settings (e.g. Scheduled Tasks), but existing staff groups that already had
        // general access to those modules never had the new permission granted, silently
        // locking non-super-admin staff out of settings pages they could previously use.
        // Grant it wherever a group already had module access, to preserve prior behavior.
        // @see https://github.com/FOSSBilling/FOSSBilling/issues/3873
        //
        // The staff group form only submits checked checkboxes, so a group edited on or after
        // #3856 (2026-06-28, when the manage_settings checkbox first existed) that has no
        // manage_settings key made a deliberate choice to leave it unchecked, not a legacy gap.
        // Restrict the backfill to groups untouched since before that date so we don't clobber
        // an intentional choice, including the default groups patch79 just created above.
        $modules = [
            'activity', 'antispam', 'cookieconsent', 'cron', 'formbuilder',
            'invoice', 'massmailer', 'order', 'orderbutton', 'seo', 'support', 'theme',
        ];

        $groups = $patcher->fetchAll('SELECT id, permissions FROM admin_group WHERE permissions IS NOT NULL AND updated_at < :cutoff', [
            'cutoff' => '2026-06-28 00:00:00',
        ]);

        foreach ($groups as $group) {
            $permissions = json_decode((string) $group['permissions'], true);
            if (!is_array($permissions)) {
                continue;
            }

            $changed = false;
            foreach ($modules as $module) {
                if (($permissions[$module]['access'] ?? false) && !isset($permissions[$module]['manage_settings'])) {
                    $permissions[$module]['manage_settings'] = true;
                    $changed = true;
                }
            }

            if ($changed) {
                $patcher->executeSql('UPDATE admin_group SET permissions = :permissions, updated_at = :updated_at WHERE id = :id', [
                    'permissions' => json_encode($permissions),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'id' => $group['id'],
                ]);
            }
        }
    }
}
