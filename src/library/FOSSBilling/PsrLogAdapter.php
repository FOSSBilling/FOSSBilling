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

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Presents the legacy Box_Log as a PSR-3 logger.
 *
 * Extensions are handed a PSR-3 logger so that nothing in the extension API
 * names a FOSSBilling internal. Everything written through here still reaches
 * the writers configured on Box_Log, so extension logging continues to appear
 * in the activity log.
 */
final class PsrLogAdapter extends AbstractLogger
{
    private const array PRIORITIES = [
        LogLevel::EMERGENCY => \Box_Log::EMERG,
        LogLevel::ALERT => \Box_Log::ALERT,
        LogLevel::CRITICAL => \Box_Log::CRIT,
        LogLevel::ERROR => \Box_Log::ERR,
        LogLevel::WARNING => \Box_Log::WARN,
        LogLevel::NOTICE => \Box_Log::NOTICE,
        LogLevel::INFO => \Box_Log::INFO,
        LogLevel::DEBUG => \Box_Log::DEBUG,
    ];

    public function __construct(private readonly \Box_Log $boxLog)
    {
    }

    /**
     * @param array<mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $priority = self::PRIORITIES[(string) $level] ?? \Box_Log::INFO;

        $this->boxLog->log((string) $message, $priority, $context);
    }
}
