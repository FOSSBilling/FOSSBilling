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

class RateLimitResult
{
    public const string REASON_ALLOWED = 'allowed';
    public const string REASON_LIMITED = 'limited';
    public const string REASON_DISABLED = 'disabled';
    public const string REASON_WHITELISTED = 'whitelisted';

    private readonly string $reason;

    public function __construct(
        private readonly string $policy,
        private readonly bool $limited,
        private readonly ?int $limit,
        private readonly ?int $remaining,
        private readonly ?\DateTimeImmutable $retryAfter = null,
        ?string $reason = null,
    ) {
        $this->reason = $reason ?? ($limited ? self::REASON_LIMITED : self::REASON_ALLOWED);
    }

    public function getPolicy(): string
    {
        return $this->policy;
    }

    public function isLimited(): bool
    {
        return $this->limited;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getRemaining(): ?int
    {
        if ($this->remaining === null) {
            return null;
        }

        return max(0, $this->remaining);
    }

    public function getRetryAfter(): ?\DateTimeImmutable
    {
        return $this->retryAfter;
    }

    public function hasRetryAfter(): bool
    {
        return $this->retryAfter instanceof \DateTimeImmutable;
    }

    public function getRetryAfterSeconds(): int
    {
        if (!$this->hasRetryAfter()) {
            return 0;
        }

        return max(0, $this->retryAfter->getTimestamp() - time());
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function isBypassed(): bool
    {
        return in_array($this->reason, [self::REASON_DISABLED, self::REASON_WHITELISTED], true);
    }
}
