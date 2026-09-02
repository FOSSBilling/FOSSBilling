<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\Update\Patch;

use FOSSBilling\Core\Update\Patcher;
use Symfony\Component\Filesystem\Path;

class Patch59 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        try {
            $patcher->executeSql("DELETE FROM extension_meta WHERE extension = 'mod_wysiwyg' OR (rel_type = 'mod' AND rel_id = 'wysiwyg')");
            $patcher->executeSql("DELETE FROM extension WHERE type = 'mod' AND name = 'wysiwyg'");
            $patcher->di['cache']->delete('config_mod_wysiwyg');
        } catch (\Exception $e) {
            $patcher->logUpdate('error', 'Wysiwyg cleanup migration error: ' . $e->getMessage());
        }

        $patcher->executeFileActions([
            Path::join(PATH_MODS, 'Wysiwyg') => 'unlink',
        ]);
    }
}
