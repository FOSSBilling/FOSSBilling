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

class Patch60 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        $patcher->executeSql("DELETE FROM extension_meta WHERE extension = 'mod_hook' AND rel_type = 'mod' AND rel_id = 'Paidsupport' AND meta_key = 'listener'");
        $patcher->executeSql("DELETE FROM extension_meta WHERE extension = 'mod_paidsupport' AND meta_key = 'config'");

        if ($patcher->di !== null && $patcher->di->offsetExists('cache')) {
            $patcher->di['cache']->delete('config_mod_paidsupport');
        }
    }
}
