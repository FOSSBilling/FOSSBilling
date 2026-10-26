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

class Patch128 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Repair the legacy (utf8) character set for cleaned-up custom page text so
        // stored page content is consistent with the utf8mb4 connection. The generic
        // widening skips customized columns/indexes with a logged warning.
        $patcher->widenLegacyTextColumns('custom_pages', ['title', 'description', 'keywords', 'content']);
    }
}
