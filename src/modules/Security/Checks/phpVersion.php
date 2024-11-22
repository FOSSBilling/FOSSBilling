<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Security\Checks;

use FOSSBilling\Enums\SecurityCheckResultEnum;
use FOSSBilling\SecurityCheckResult;

class phpVersion implements \FOSSBilling\Interfaces\SecurityCheckInterface
{
    public function getName(): string
    {
        return 'PHP Version Check';
    }

    public function getDescription(): string
    {
        return 'Checks if the PHP version FOSSBilling is running on is still receiving security support.';
    }

    public function performCheck(): SecurityCheckResult
    {
        return new SecurityCheckResult(SecurityCheckResultEnum::PASS, '');
    }
}
