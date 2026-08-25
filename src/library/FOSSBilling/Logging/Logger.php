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

use FOSSBilling\Interfaces\InjectionAwareInterface;
use Pimple\Container;
use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;

class Logger extends AbstractLogger implements InjectionAwareInterface
{
    /**
     * Numeric priorities are retained because they are persisted in the
     * activity_system table and consumed by the file writer.
     */
    final public const int EMERG = 0;
    final public const int ALERT = 1;
    final public const int CRIT = 2;
    final public const int ERR = 3;
    final public const int WARN = 4;
    final public const int NOTICE = 5;
    final public const int INFO = 6;
    final public const int DEBUG = 7;

    private const array PRIORITY_NAMES = [
        self::EMERG => 'EMERGENCY',
        self::ALERT => 'ALERT',
        self::CRIT => 'CRITICAL',
        self::ERR => 'ERROR',
        self::WARN => 'WARNING',
        self::NOTICE => 'NOTICE',
        self::INFO => 'INFO',
        self::DEBUG => 'DEBUG',
    ];

    private const array LEVEL_PRIORITIES = [
        LogLevel::EMERGENCY => self::EMERG,
        LogLevel::ALERT => self::ALERT,
        LogLevel::CRITICAL => self::CRIT,
        LogLevel::ERROR => self::ERR,
        LogLevel::WARNING => self::WARN,
        LogLevel::NOTICE => self::NOTICE,
        LogLevel::INFO => self::INFO,
        LogLevel::DEBUG => self::DEBUG,
    ];

    private const array MASKED_KEYS = ['password', 'pass', 'token', 'key', 'apisecret', 'secret', 'api_token'];

    private ?Container $di = null;

    /** @var array<int, object> */
    private array $writers = [];

    /** @var array<string, mixed> */
    private array $context = [];

    private string $channel = 'application';

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
    {
        return $this->di;
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $levelName = is_string($level)
            ? $level
            : (is_scalar($level) ? (string) $level : get_debug_type($level));
        $priority = self::LEVEL_PRIORITIES[$levelName] ?? null;
        if ($priority === null) {
            throw new InvalidArgumentException(sprintf('Unknown log level "%s"', $levelName));
        }

        $this->writePsrEvent($priority, $message, $context);
    }

    /**
     * Add a writer to this logger.
     *
     * A writer must expose write(array $event, string $channel): void.
     */
    public function addWriter(object $writer): static
    {
        $this->writers[] = $writer;

        return $this;
    }

    /**
     * Return a logger with additional context scoped to that instance.
     *
     * The container logger is shared, so operation-specific metadata must not
     * be added to it directly or it can leak into later operations.
     *
     * @param array<string, mixed> $context
     */
    public function withContext(array $context): static
    {
        $logger = clone $this;
        $logger->context = [...$this->context, ...$context];

        return $logger;
    }

    public function withChannel(string $channel): static
    {
        $logger = clone $this;
        $logger->channel = $channel;

        return $logger;
    }

    private function writePsrEvent(int $priority, string|\Stringable $message, array $context): void
    {
        $context = $this->maskContext($context);
        $message = $this->interpolate((string) $message, $context);

        $this->writeEvent($message, $priority, $context);
    }

    /**
     * @param array<string|int, mixed> $context
     *
     * @return array<string|int, mixed>
     */
    private function maskContext(array $context, int $depthLimit = 15): array
    {
        if ($depthLimit <= 0) {
            return ['error' => 'Recursion limit reached while masking event log parameters'];
        }

        foreach ($context as $key => $value) {
            if (in_array(strtolower((string) $key), self::MASKED_KEYS, true)) {
                $context[$key] = '********';
            } elseif (is_array($value)) {
                $context[$key] = $this->maskContext($value, $depthLimit - 1);
            }
        }

        return $context;
    }

    private function interpolate(string $message, array $context): string
    {
        $replacements = [];
        foreach ($context as $key => $value) {
            if ($value === null || is_scalar($value) || $value instanceof \Stringable) {
                $replacements['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($message, $replacements);
    }

    /**
     * @param array<string|int, mixed> $context
     */
    private function writeEvent(string $message, int $priority, array $context): void
    {
        if ($this->writers === []) {
            return;
        }

        $scopedContext = $this->maskContext($this->context);
        $event = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message,
            'priority' => $priority,
            'priorityName' => self::PRIORITY_NAMES[$priority],
            ...$scopedContext,
        ];

        $writerContext = $this->maskContext([...$this->context, ...$context]);
        if ($writerContext !== []) {
            $event['info'] = $writerContext;
        }

        // Do not log debug level messages if debug is OFF.
        // @phpstan-ignore identical.alwaysTrue (DEBUG is a runtime constant that may be true during debugging)
        if ($priority > self::INFO && DEBUG === false) {
            return;
        }

        foreach ($this->writers as $writer) {
            try {
                $writer->write($event, $this->channel);
            } catch (\Throwable $e) {
                // A writer failure cannot be routed through this logger without recursion.
                error_log(sprintf('[FOSSBilling\\Logger] writer failure: %s at %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));
            }
        }
    }
}
