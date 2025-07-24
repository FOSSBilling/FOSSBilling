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

namespace FOSSBilling;

use FOSSBilling\Enums\SecurityCheckResultEnum;

class SecurityCheckResult
{
    /**
     * @param SecurityCheckResultEnum $result  the result of the check
     * @param string                  $message an optional message to go with the result
     */
    public function __construct(public readonly SecurityCheckResultEnum $result, public readonly string $message = '')
    {
    }

    public function jsonSerialize(): array
    {
        return [
            'result' => $this->result->value,
            'message' => $this->message,
        ];
    }
}
