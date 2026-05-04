<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Security;

use FOSSBilling\InformationException;

class RateLimitException extends InformationException
{
    public function __construct(private readonly RateLimitResult $rateLimitResult)
    {
        parent::__construct('Rate limit exceeded. Please try again later.', null, 429);
    }

    public function getRateLimitResult(): RateLimitResult
    {
        return $this->rateLimitResult;
    }

    public function hasRetryAfter(): bool
    {
        return $this->rateLimitResult->hasRetryAfter();
    }

    public function getRetryAfterSeconds(): int
    {
        return $this->rateLimitResult->getRetryAfterSeconds();
    }
}
