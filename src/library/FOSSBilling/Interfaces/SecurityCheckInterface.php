<?php

declare(strict_types=1);

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Interfaces;

use FOSSBilling\SecurityCheckResult;

interface SecurityCheckInterface
{
    /**
     * Returns the name of the check.
     */
    public function getName(): string;

    /**
     * Returns a description of what the check performs.
     */
    public function getDescription(): string;

    /**
     * Performs the check and returns the appropriate result.
     */
    public function performCheck(): SecurityCheckResult;
}
