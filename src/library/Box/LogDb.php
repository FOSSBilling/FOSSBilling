<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Box_LogDb
{
    private array $ignoredChannels = ['billing', 'routing', 'security', 'email'];

    /**
     * Class constructor.
     *
     * @param object|string $service - module service class object or class name
     */
    public function __construct(protected object|string $service)
    {
    }

    /**
     * Write a message to the log.
     */
    public function write(array $event, string $channel = 'application'): void
    {
        // TODO: Temporary! Redo logging stuff in more depth for a major release.
        if (in_array($channel, $this->ignoredChannels)) {
            return;
        }

        try {
            if (method_exists($this->service, 'logEvent')) {
                $this->service->logEvent($event);
            }
        } catch (Throwable $e) {
            // The database writer cannot use the application logger while it is
            // writing that logger, so retain PHP's last-resort error log here.
            error_log(sprintf('[Box_LogDb] writer failure: %s at %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));
        }
    }
}
