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

class Patch117 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // library/FOSSBilling moved to core/ in 58cd89d61. Patch116 already removed the
        // in-directory reorg orphans; everything left here is obsolete. The PSR-0 adapter
        // layer (Payment/Registrar/Server) and TranslationFunctions.php stay.
        $patcher->executeFileActions([
            Path::join(PATH_LIBRARY, 'FOSSBilling') => 'unlink',
        ]);
    }
}
