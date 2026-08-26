<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Security;

use FOSSBilling\Security\CheckResultStatus;

class CheckResult
{
    /**
     * @param CheckResultStatus $result  the result of the check
     * @param string            $message an optional message to go with the result
     */
    public function __construct(public readonly CheckResultStatus $result, public readonly string $message = '')
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
