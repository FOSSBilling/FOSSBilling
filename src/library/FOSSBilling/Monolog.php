<?php declare(strict_types=1);
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use \FOSSBilling\InjectionAwareInterface;
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;

class Monolog implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di;
    protected $logger = null;
    public string $dateFormat = "d-M-Y H:i:s e";
    public string $outputFormat = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";

    public array $channels = [
        "application",
        "cron",
        "database",
        "license",
        "mail",
        "event"
    ];

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function __construct(\Pimple\Container $di)
    {
        $this->di = $di;
        $channels = $this->channels;

        foreach ($channels as $channel) {
            $path = $di['config']['path_data'] . '/log/' . $channel . '.log';

            $this->logger[$channel] = new Logger($channel);
            $stream = new StreamHandler($path, Logger::DEBUG);
            $this->logger[$channel]->pushHandler($stream);

            $formatter = new LineFormatter($this->outputFormat, $this->dateFormat, true, true, true);
            $this->logger[$channel]->getHandlers()[0]->setFormatter($formatter);
        }
    }

    /**
     * @param string $channel
     * @return \Monolog\Logger The logger for the specified channel. If the channel does not exist, the default logger (the 'application' channel) is returned.
     */
    public function getChannel(string $channel = 'application'): Logger
    {
        return $this->logger[$channel] ?? $this->logger['application'];
    }

    /**
     * Convert numeric FOSSBilling priority to Monolog priority
     *
     * @param int $priority
     * @return int
     */
    public function parsePriority(int $priority): int
    {
        // Map numeric priority to Monolog priority
        $map = [
            \Box_Log::EMERG => Logger::EMERGENCY,
            \Box_Log::ALERT => Logger::ALERT,
            \Box_Log::CRIT => Logger::CRITICAL,
            \Box_Log::ERR => Logger::ERROR,
            \Box_Log::WARN => Logger::WARNING,
            \Box_Log::NOTICE => Logger::NOTICE,
            \Box_Log::INFO => Logger::INFO,
            \Box_Log::DEBUG => Logger::DEBUG,
        ];

        return $map[$priority] ?? Logger::DEBUG;
    }

    /**
     * @param array $event
     * @param string $channel
     */
    public function write(array $event, string $channel = 'application'): void
    {
        $priority = $this->parsePriority($event['priority']);
        $message = $event['message'];
        $context = isset($event['info']) && is_array($event['info']) ? $event['info'] : [];

        $this->getChannel($channel)->log($priority, $message, $context);
    }
}
