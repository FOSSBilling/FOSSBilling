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

class Patch49 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        $q = "UPDATE setting SET value = 'public/branding/logo.svg' WHERE param = 'company_logo' AND value = 'themes/huraga/assets/img/logo.svg';";
        $patcher->executeSql($q);

        $q = "UPDATE setting SET value = 'public/branding/logo-dark.svg' WHERE param = 'company_logo_dark' AND value = 'themes/huraga/assets/img/logo_white.svg';";
        $patcher->executeSql($q);

        $q = "UPDATE setting SET value = 'public/branding/favicon.ico' WHERE param = 'company_favicon' AND value = 'themes/huraga/assets/favicon.ico';";
        $patcher->executeSql($q);
    }
}
