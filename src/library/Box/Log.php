<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * @method void emerg(string $message)
 * @method void alert(string $message)
 * @method void crit(string $message)
 * @method void err(string $message)
 * @method void warn(string $message)
 * @method void notice(string $message)
 * @method void info(string $message)
 * @method void debug(string $message)
 */
class Box_Log implements FOSSBilling\InjectionAwareInterface
{
    final public const EMERG = 0; // Emergency: system is unusable
    final public const ALERT = 1; // Alert: action must be taken immediately
    final public const CRIT = 2; // Critical: critical conditions
    final public const ERR = 3; // Error: error conditions
    final public const WARN = 4; // Warning: warning conditions
    final public const NOTICE = 5; // Notice: normal but significant condition
    final public const INFO = 6; // Informational: informational messages
    final public const DEBUG = 7; // Debug: debug messages

    protected array $_priorities = [
        self::EMERG => 'EMERG',
        self::ALERT => 'ALERT',
        self::CRIT => 'CRIT',
        self::ERR => 'ERR',
        self::WARN => 'WARN',
        self::NOTICE => 'NOTICE',
        self::INFO => 'INFO',
        self::DEBUG => 'DEBUG',
    ];

    protected ?Pimple\Container $di = null;
    protected $_min_priority;

    protected array $_writers = [];
    protected array $_extras = [];
    protected string $_channel = 'application';

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Pimple\Container
    {
        return $this->di;
    }

    /**
     * @throws FOSSBilling\Exception
     */
    public function __call($method, $params): void
    {
        $priority = strtoupper($method);
        if (($priority = array_search($priority, $this->_priorities)) !== false) {
            switch (is_countable($params) ? count($params) : 0) {
                case 0:
                    throw new FOSSBilling\Exception('Missing log message');
                case 1:
                    $message = array_shift($params);
                    $extras = null;

                    break;
                default:
                    $message = array_shift($params);
                    $message = vsprintf($message, array_values($params));
                    if (!$message) {
                        throw new LogicException('Number of placeholders does not match number of variables');
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
    public function log($message, $priority, $extras = null): void
    {
        // sanity checks
        if (empty($this->_writers)) {
            return;
        }

        if (!isset($this->_priorities[$priority])) {
            throw new FOSSBilling\Exception('Bad log priority');
        }

        if ($this->_min_priority && $priority > $this->_min_priority) {
            return;
        }

        $event = $this->_packEvent($message, $priority);

        // Check to see if any extra information was passed
        if (!empty($extras)) {
            $info = [];
            if (is_array($extras)) {
                foreach ($extras as $key => $value) {
                    if (is_string($key)) {
                        $event[$key] = $value;
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
        if ($event['priority'] > self::INFO && DEBUG === false) {
            return;
        }

        // send to each writer
        foreach ($this->_writers as $writer) {
            $writer->write($event, $this->_channel);
        }
    }

    protected function _packEvent($message, $priority)
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
