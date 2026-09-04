<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\Logging;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use Symfony\Component\Filesystem\Path;

class Factory
{
    protected array $logger = [];
    public string $dateFormat = 'd-M-Y H:i:s e';
    public string $outputFormat = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";

    public array $channels = [
        'activity',
        'application',
        'cron',
        'update',
        'database',
        'license',
        'mail',
        'event',
        'routing',
        'billing',
        'security',
        'email',
    ];

    public function __construct()
    {
        $channels = $this->channels;

        foreach ($channels as $channel) {
            $path = Path::join(PATH_LOG, $channel, "{$channel}.log");

            $this->logger[$channel] = new Logger($channel);
            $rotatingHandler = new RotatingFileHandler($path, 90, Level::Debug);
            $this->logger[$channel]->pushHandler($rotatingHandler);

            $formatter = new LineFormatter($this->outputFormat, $this->dateFormat, true, true, true);
            $rotatingHandler->setFormatter($formatter);
        }
    }

    /**
     * @return Logger The logger for the specified channel. If the channel does not exist, the default logger (the 'application' channel) is returned.
     */
    public function getChannel(string $channel = 'application'): Logger
    {
        return $this->logger[$channel] ?? $this->logger['application'];
    }

    /**
     * Convert numeric FOSSBilling priority to Monolog Level.
     */
    public function parsePriority(int $priority): Level
    {
        // Map numeric priority to Monolog Level
        return match ($priority) {
            \FOSSBilling\Core\Logging\Logger::EMERG => Level::Emergency,
            \FOSSBilling\Core\Logging\Logger::ALERT => Level::Alert,
            \FOSSBilling\Core\Logging\Logger::CRIT => Level::Critical,
            \FOSSBilling\Core\Logging\Logger::ERR => Level::Error,
            \FOSSBilling\Core\Logging\Logger::WARN => Level::Warning,
            \FOSSBilling\Core\Logging\Logger::NOTICE => Level::Notice,
            \FOSSBilling\Core\Logging\Logger::INFO => Level::Info,
            \FOSSBilling\Core\Logging\Logger::DEBUG => Level::Debug,
            default => Level::Debug,
        };
    }

    public function write(array $event, string $channel = 'application'): void
    {
        $priority = $this->parsePriority($event['priority']);
        $message = $event['message'];
        $context = isset($event['info']) && is_array($event['info']) ? $event['info'] : [];

        try {
            $this->getChannel($channel)->log($priority, $message, $context);
        } catch (\Throwable $e) {
            // This is the final fallback when a Monolog handler itself fails;
            // routing it through the application logger would recurse.
            error_log(sprintf('[FOSSBilling\\Core\\Monolog] writer failure: %s at %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));
        }
    }
}
