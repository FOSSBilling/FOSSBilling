<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
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
class Box_Log
{
    const EMERG   = 0;  // Emergency: system is unusable
    const ALERT   = 1;  // Alert: action must be taken immediately
    const CRIT    = 2;  // Critical: critical conditions
    const ERR     = 3;  // Error: error conditions
    const WARN    = 4;  // Warning: warning conditions
    const NOTICE  = 5;  // Notice: normal but significant condition
    const INFO    = 6;  // Informational: informational messages
    const DEBUG   = 7;  // Debug: debug messages

    protected $_priorities = array(
         self::EMERG    => 'EMERG',
         self::ALERT    => 'ALERT',
         self::CRIT     => 'CRIT',
         self::ERR      => 'ERR',
         self::WARN     => 'WARN',
         self::NOTICE   => 'NOTICE',
         self::INFO     => 'INFO',
         self::DEBUG    => 'DEBUG',
    );

    protected $_min_priority = NULL;
    
    protected $_writers = array();

    protected $_extras = array();

    protected $di;

    /**
     * @param mixed $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    public function __call($method, $params)
    {
        $priority = strtoupper($method);
        if (($priority = array_search($priority, $this->_priorities)) !== false) {
            switch (count($params)) {
                case 0:
                    throw new \Box_Exception('Missing log message');
                case 1:
                    $message = array_shift($params);
                    $extras  = null;
                    break;
                default:
                    $message = array_shift($params);
                    $message = vsprintf($message, array_values($params));
                    if (!$message) {
                        throw new LogicException('Number of placeholders does not match number of variables');
                    }
                    break;
            }
            $this->log($message, $priority);
        } else {
            throw new \Box_Exception('Bad log priority');
        }
    }

    public function log($message, $priority, $extras = null)
    {
        // sanity checks
        if (empty($this->_writers)) {
            return;
        }

        if (! isset($this->_priorities[$priority])) {
            throw new \Box_Exception('Bad log priority');
        }
        
        if($this->_min_priority && $priority > $this->_min_priority) {
            return;
        }
        
        $event = $this->_packEvent($message, $priority);

        // Check to see if any extra information was passed
        if (!empty($extras)) {
            $info = array();
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

        //do not log debug level messages if debug is OFF

        if($this->di['config']['debug'] === FALSE && $event['priority'] > self::INFO) {
            return ;
        }

        // send to each writer
        foreach ($this->_writers as $writer) {
            $writer->write($event);
        }
    }

    protected function _packEvent($message, $priority)
    {
        return array_merge(array(
            'timestamp'    => date('Y-m-d H:i:s'),
            'message'      => $message,
            'priority'     => $priority,
            'priorityName' => $this->_priorities[$priority]
            ),
            $this->_extras
        );
    }

    /**
     * @param Box_LogDb|Box_LogStream $writer
     */
    public function addWriter($writer)
    {
        $this->_writers[] = $writer;
        return $this;
    }

    public function setEventItem($name, $value)
    {
        $this->_extras = array_merge($this->_extras, array($name => $value));
        return $this;
    }
    
    public function setMinPriority($priority)
    {
        $this->_min_priority = $priority;
        return $this;
    }
}
