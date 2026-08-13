<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Logging;

class DatabaseWriter
{
    /**
     * These channels are kept in file logs only. Email has its own activity
     * table, while the remaining channels contain sensitive or operational
     * diagnostics rather than user-facing activity entries.
     */
    private const array IGNORED_CHANNELS = ['billing', 'routing', 'security', 'email'];

    public function __construct(private object $service)
    {
    }

    /**
     * Write a message to the activity log.
     *
     * @param array<string|int, mixed> $event
     */
    public function write(array $event, string $channel = 'application'): void
    {
        if (in_array($channel, self::IGNORED_CHANNELS, true)) {
            return;
        }

        try {
            if (method_exists($this->service, 'logEvent')) {
                $this->service->logEvent($event);
            }
        } catch (\Throwable $e) {
            // The database writer cannot use the application logger while it is
            // writing that logger, so retain PHP's last-resort error log here.
            error_log(sprintf('[FOSSBilling\\Logging\\DatabaseWriter] writer failure: %s at %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));
        }
    }
}
