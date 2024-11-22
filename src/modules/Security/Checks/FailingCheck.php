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

class FailingCheck implements \FOSSBilling\Interfaces\SecurityCheckInterface
{
    public function getName(): string
    {
        return 'Failing Check';
    }

    public function getDescription(): string
    {
        return 'This check will fail.';
    }

    public function performCheck(): SecurityCheckResult
    {
        return new SecurityCheckResult(SecurityCheckResultEnum::FAIL, 'I am testing things');
    }
}
