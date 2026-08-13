<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

/**
 * PDO statement used for the application's debug SQL trace.
 *
 * PDO instantiates this class directly, so it intentionally writes to PHP's
 * error log instead of depending on the application logger or the container.
 */
class DbLoggedPDOStatement extends \PDOStatement
{
    public function execute(?array $input_parameters = null): bool
    {
        error_log($this->queryString);

        return parent::execute($input_parameters);
    }
}
