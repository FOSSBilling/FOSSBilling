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

class Patch139 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Heal installs that reached patch 132+ without product_category.priority
        // (Sentry FOSSBILLING-RSE: storefront "Unknown column 'p0_.priority'").
        // The numbered loop never reruns Patch132 and the drift healer is
        // hash-gated, so a new number is the only path that re-runs
        // finalization for already-finalized installs. Guarded rerun no-op;
        // non-MySQL drivers get the column from the portable sync.
        $patcher->ensureProductCategoryPriorityColumn();
    }
}
