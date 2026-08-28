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

class Patch115 implements PatchInterface
{
    public function getVersion(): int
    {
        return 115;
    }

    public function apply(Patcher $patcher): void
    {
        // No-op: orphan cleanups for the library reorg (6a716aec3) are now handled by Patch116's
        // superset, which deletes both the 115 set (FOSSBilling/* orphans) and its own 116 set
        // (Box/*). Kept as gap to preserve last_patch ordering for installs already at 115.
    }
}
