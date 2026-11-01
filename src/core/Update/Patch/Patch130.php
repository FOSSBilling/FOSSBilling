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

class Patch130 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Default branding assets join public/branding; migrate on every driver -
        // plain check-then-copy SQL and file moves, idempotent.
        $patcher->migrateDefaultBrandingAssetsToPublicDirectory();
    }
}
