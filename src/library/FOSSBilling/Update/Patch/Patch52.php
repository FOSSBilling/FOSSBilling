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

class Patch52 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        $columns = $patcher->getTableColumns('email_template');

        if (!in_array('is_custom', $columns, true)) {
            $patcher->executeSql("ALTER TABLE `email_template` ADD COLUMN `is_custom` TINYINT(1) DEFAULT '0' AFTER `enabled`;");
        }

        if (!in_array('is_overridden', $columns, true)) {
            $patcher->executeSql("ALTER TABLE `email_template` ADD COLUMN `is_overridden` TINYINT(1) DEFAULT '0' COMMENT 'Whether subject/content have been customized from file defaults' AFTER `is_custom`;");
        }

        $templates = $patcher->fetchAll('SELECT id, action_code, subject, content FROM email_template');
        foreach ($templates as $template) {
            $default = $patcher->getDefaultEmailTemplateData((string) ($template['action_code'] ?? ''));
            if ($default === null) {
                $patcher->executeSql('UPDATE email_template SET is_custom = :is_custom WHERE id = :id', [
                    'is_custom' => 1,
                    'id' => $template['id'],
                ]);

                continue;
            }

            $subject = (string) ($template['subject'] ?? '');
            $content = (string) ($template['content'] ?? '');

            $isOverridden = (trim($subject) !== trim((string) $default['subject'])) || (trim($content) !== trim((string) $default['content']));

            if (!$isOverridden) {
                $subject = $default['subject'];
                $content = $default['content'];
            }

            $patcher->executeSql('UPDATE email_template SET is_custom = :is_custom, is_overridden = :is_overridden, subject = :subject, content = :content WHERE id = :id', [
                'is_custom' => 0,
                'is_overridden' => $isOverridden ? 1 : 0,
                'subject' => $subject,
                'content' => $content,
                'id' => $template['id'],
            ]);
        }
    }
}
