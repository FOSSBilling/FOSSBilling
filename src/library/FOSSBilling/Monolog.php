<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use Symfony\Component\Filesystem\Path;

class Monolog
{
    protected $logger;
    public string $dateFormat = 'd-M-Y H:i:s e';
    public string $outputFormat = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";

    public array $channels = [
        'activity',
        'application',
        'cron',
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
            $this->logger[$channel]->getHandlers()[0]->setFormatter($formatter);
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
            \Box_Log::EMERG => Level::Emergency,
            \Box_Log::ALERT => Level::Alert,
            \Box_Log::CRIT => Level::Critical,
            \Box_Log::ERR => Level::Error,
            \Box_Log::WARN => Level::Warning,
            \Box_Log::NOTICE => Level::Notice,
            \Box_Log::INFO => Level::Info,
            \Box_Log::DEBUG => Level::Debug,
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
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }
    }
}
