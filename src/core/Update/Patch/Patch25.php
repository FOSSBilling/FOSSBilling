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

class Patch25 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        $patcher->executeSql('UPDATE email_template SET content = REPLACE(content, :old_filter, :new_filter)', [
            'old_filter' => '{% filter markdown %}',
            'new_filter' => '{% apply markdown_to_html %}',
        ]);

        $patcher->executeSql('UPDATE email_template SET content = REPLACE(content, :old_endfilter, :new_endfilter)', [
            'old_endfilter' => '{% endfilter %}',
            'new_endfilter' => '{% endapply %}',
        ]);
    }
}
