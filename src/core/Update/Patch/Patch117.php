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
        // The FOSSBilling\Core\ classes moved from library/FOSSBilling to core/ (see commit
        // "Move src/library/FOSSBilling to src/core"). Patch116 already deleted the
        // loose files orphaned by the earlier in-directory reorg; everything that
        // remains under library/FOSSBilling is now obsolete, so delete the whole
        // tree. The PSR-0 adapter layer (library/Payment, library/Registrar,
        // library/Server) and TranslationFunctions.php stay.
        $patcher->executeFileActions([
            Path::join(PATH_LIBRARY, 'FOSSBilling') => 'unlink',
        ]);
    }
}
