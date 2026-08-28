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

use Box\Mod\Extension\Entity\Extension;
use FOSSBilling\Update\Patcher;
use Symfony\Component\Filesystem\Path;

class Patch55 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Migrate Spamchecker module to Anti-Spam module
        // @see https://github.com/FOSSBilling/FOSSBilling/pull/2700

        try {
            $extService = $patcher->di['mod_service']('extension');

            $oldConfig = $extService->getConfig('mod_spamchecker');
            $spamcheckerSettings = array_diff_key($oldConfig, ['ext' => true]);
            if (!empty($spamcheckerSettings)) {
                $existingAntispamConfig = $extService->getConfig('mod_antispam');
                $existingAntispamSettings = array_diff_key($existingAntispamConfig, ['ext' => true]);
                $newConfig = array_merge($spamcheckerSettings, $existingAntispamSettings);
                $newConfig['ext'] = 'mod_antispam';
                $newConfig['honeypot_enabled'] ??= true;
                $newConfig['honeypot_field'] ??= 'bio';
                $extService->setConfig($newConfig);
            }

            $patcher->executeSql("DELETE FROM extension_meta WHERE extension = 'mod_hook' AND rel_type = 'mod' AND rel_id = 'spamchecker' AND meta_key = 'listener'");

            $hookService = $patcher->di['mod_service']('hook');
            $hookService->batchConnect('antispam');

            $patcher->executeSql("DELETE FROM extension_meta WHERE extension = 'mod_spamchecker' AND meta_key = 'config'");

            $spamcheckerExt = $extService->getExtensionRepository()->findOneByTypeAndName('mod', 'spamchecker');
            if ($spamcheckerExt instanceof Extension) {
                $extService->deactivate($spamcheckerExt);
                $extService->uninstall('mod', 'spamchecker');
            }

            $patcher->di['cache']->delete('config_mod_spamchecker');
            $patcher->di['cache']->delete('config_mod_antispam');
        } catch (\Exception $e) {
            $patcher->logUpdate('error', 'Spamchecker to Anti-Spam migration error: ' . $e->getMessage());
        }

        $fileActions = [
            Path::join(PATH_MODS, 'Spamchecker') => 'unlink',
        ];
        $patcher->executeFileActions($fileActions);
    }
}
