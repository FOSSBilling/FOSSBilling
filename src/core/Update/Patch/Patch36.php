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

use Box\Mod\Extension\Entity\Extension;
use FOSSBilling\Core\Update\Patcher;
use Symfony\Component\Filesystem\Path;

class Patch36 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Patch to complete merging the Kb and Support modules.
        // @see https://github.com/FOSSBilling/FOSSBilling/pull/1180
        $q = 'RENAME TABLE kb_article TO support_kb_article, kb_article_category TO support_kb_article_category;';
        $patcher->executeSql($q);

        // An error here can pretty safely be ignored.
        try {
            // If the Kb extension is currently active, set enabled in Support settings.
            $ext_service = $patcher->di['mod_service']('extension');
            if ($ext_service->isExtensionActive('mod', 'kb')) {
                $support_ext_config = $ext_service->getConfig('mod_support');
                $support_ext_config['kb_enable'] = true;
                $ext_service->setConfig($support_ext_config);
            }

            // If the Kb extension exists, uninstall it.
            $kb_ext = $ext_service->getExtensionRepository()->findOneByTypeAndName('mod', 'kb');
            if ($kb_ext instanceof Extension) {
                $ext_service->deactivate($kb_ext);
                $ext_service->uninstall('mod', 'kb');
            }
        } catch (\Exception) {
        }

        $fileActions = [
            Path::join(PATH_MODS, 'Kb') => 'unlink',
        ];
        $patcher->executeFileActions($fileActions);
    }
}
