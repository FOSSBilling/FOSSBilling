<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * @method void emergency(string $message)
 * @method void alert(string $message)
 * @method void critical(string $message)
 * @method void error(string $message)
 * @method void warning(string $message)
 * @method void notice(string $message)
 * @method void info(string $message)
 * @method void debug(string $message)
 * @method void emerg(string $message) Legacy alias for emergency()
 * @method void crit(string $message) Legacy alias for critical()
 * @method void err(string $message) Legacy alias for error()
 * @method void warn(string $message) Legacy alias for warning()
 */
class Box_Log implements FOSSBilling\InjectionAwareInterface
{
    final public const int EMERG = 0;
    final public const int ALERT = 1;
    final public const int CRIT = 2;
    final public const int ERR = 3;
    final public const int WARN = 4;
    final public const int NOTICE = 5;
    final public const int INFO = 6;
    final public const int DEBUG = 7;

    protected array $_priorities = [
        self::EMERG => 'EMERGENCY',
        self::ALERT => 'ALERT',
        self::CRIT => 'CRITICAL',
        self::ERR => 'ERROR',
        self::WARN => 'WARNING',
        self::NOTICE => 'NOTICE',
        self::INFO => 'INFO',
        self::DEBUG => 'DEBUG',
    ];

    private const array PRIORITY_ALIASES = [
        'EMERG' => 'EMERGENCY',
        'CRIT' => 'CRITICAL',
        'ERR' => 'ERROR',
        'WARN' => 'WARNING',
    ];

    protected ?Pimple\Container $di = null;
    protected $_min_priority;

    protected array $_writers = [];
    protected array $_extras = [];
    protected string $_channel = 'application';

    private array $_maskedKeys = ['password', 'pass', 'token', 'key', 'apisecret', 'secret', 'api_token'];

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Pimple\Container
    {
        return $this->di;
    }

    private function maskParams(mixed $params, int $depthLimit = 15): mixed
    {
        if (!is_array($params)) {
            return $params;
        }

        if ($depthLimit <= 0) {
            return 'Recursion limit reached while masking event log parameters';
        }

        foreach ($params as $key => $value) {
            if (in_array(strtolower((string) $key), $this->_maskedKeys)) {
                $params[$key] = '********';
            } elseif (is_array($value)) {
                $params[$key] = $this->maskParams($value, $depthLimit - 1);
            }
        }

        return $params;
    }

    /**
     * @throws FOSSBilling\Exception
     */
    public function __call($method, $params): void
    {
        $priority = strtoupper((string) $method);
        $priority = self::PRIORITY_ALIASES[$priority] ?? $priority;
        if (($priority = array_search($priority, $this->_priorities, true)) !== false) {
            switch (FOSSBilling\Tools::safeCount($params)) {
                case 0:
                    throw new FOSSBilling\Exception('Missing log message');
                case 1:
                    $message = array_shift($params);

                    break;
                default:
                    $message = array_shift($params);
                    $params = $this->maskParams($params);

                    $message = vsprintf($message, array_values($params));
                    if (!$message) {
                        throw new FOSSBilling\Exception('Number of placeholders does not match number of variables');
                    }

                    break;
            }
            $this->log($message, $priority, $params);
        } else {
            throw new FOSSBilling\Exception('Bad log priority');
        }
    }

    /**
     * @throws FOSSBilling\Exception
     */
    public function log($message, $priority, array|string|null $extras = null): void
    {
        // sanity checks
        if (!isset($this->_priorities[$priority])) {
            throw new FOSSBilling\Exception('Bad log priority');
        }

        if (empty($this->_writers)) {
            return;
        }

        if ($this->_min_priority && $priority > $this->_min_priority) {
            return;
        }

        $event = $this->_packEvent($message, $priority);
        $extras = $this->maskParams($extras);

        // Check to see if any extra information was passed
        if (!empty($extras)) {
            $info = [];
            if (is_array($extras)) {
                foreach ($extras as $key => $value) {
                    if (is_string($key)) {
                        if (!array_key_exists($key, $event)) {
                            $event[$key] = $value;
                        }
                    } else {
                        $info[] = $value;
                    }
                }
            } else {
                $info = $extras;
            }
            if (!empty($info)) {
                $event['info'] = $info;
            }
        }

        // do not log debug level messages if debug is OFF
        // @phpstan-ignore identical.alwaysTrue (DEBUG is a runtime constant that may be true during debugging)
        if ($event['priority'] > self::INFO && DEBUG === false) {
            return;
        }

        // send to each writer
        foreach ($this->_writers as $writer) {
            try {
                $writer->write($event, $this->_channel);
            } catch (Throwable $e) {
                error_log(sprintf('[Box_Log] writer failure: %s at %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));
            }
        }
    }

    protected function _packEvent($message, $priority): array
    {
        return ['timestamp' => date('Y-m-d H:i:s'), 'message' => $message, 'priority' => $priority, 'priorityName' => $this->_priorities[$priority], ...$this->_extras];
    }

    /**
     * @param Box_LogDb|FOSSBilling\Monolog $writer
     *
     * @return $this The Box_Log instance
     */
    public function addWriter($writer): static
    {
        $this->_writers[] = $writer;

        return $this;
    }

    /**
     * @param $name  string
     * @param $value mixed
     *
     * @return $this The Box_Log instance
     */
    public function setEventItem(string $name, mixed $value): static
    {
        $this->_extras = array_merge($this->_extras, [$name => $value]);

        return $this;
    }

    /**
     * Set the channel name for the logger.
     *
     * @param string $channel Channel name
     *
     * @return $this The Box_Log instance
     */
    public function setChannel(string $channel): static
    {
        $this->_channel = $channel;

        return $this;
    }

    /**
     * @return $this The Box_Log instance
     */
    public function setMinPriority($priority): static
    {
        $this->_min_priority = $priority;

        return $this;
    }
}
