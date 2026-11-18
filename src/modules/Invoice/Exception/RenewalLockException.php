<?php

declare(strict_types=1);

namespace Box\Mod\Invoice\Exception;

/** Temporary contention must not turn a verified subscription receipt into final wallet credit. */
final class RenewalLockException extends RetryableCallbackException
{
}
