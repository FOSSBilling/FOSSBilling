<?php

/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

use Box\InjectionAwareInterface;
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;

class FOSSBilling_Monolog implements InjectionAwareInterface
{
    protected $di = null;
    protected $logger = null;
    public $dateFormat = "d-M-Y H:i:s e";
    public $outputFormat = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";

    public $channels = [
        "fossbilling" => [
            "filename" => "fossbilling.log",
        ],
    ];

    /**
     * @param \Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return \Box_Di
     */
    public function getDi()
    {
        return $this->di;
    }

    /**
     * @param \Box_Di $di
     */
    public function __construct(Box_Di $di)
    {
        $this->di = $di;
        $channels = $this->channels;

        foreach ($channels as $channel => $channelConfig) {
            $this->logger[$channel] = new Logger($channel);
            $stream = new StreamHandler($di['config']['path_data'] . '/log/' . $channelConfig['filename'], Logger::DEBUG);
            $this->logger[$channel]->pushHandler($stream);

            $formatter = new LineFormatter($this->outputFormat, $this->dateFormat, true, true, true);
            $this->logger[$channel]->getHandlers()[0]->setFormatter($formatter);
        }
    }

    /**
     * @param string $channel
     * @return \Monolog\Logger The logger for the specified channel. If the channel does not exist, the default logger (the 'fossbilling' channel) is returned.
     */
    public function getLogger($channel = 'fossbilling')
    {
        return isset($this->logger[$channel]) ? $this->logger[$channel] : $this->logger['fossbilling'];
    }

    /**
     * Convert numeric FOSSBilling priority to Monolog priority
     * 
     * @param int $priority
     * @return int
     */
    public function parsePriority(int $priority) {
        // Map numeric priority to Monolog priority
        $map = array(
            Box_Log::EMERG => Logger::EMERGENCY,
            Box_Log::ALERT => Logger::ALERT,
            Box_Log::CRIT => Logger::CRITICAL,
            Box_Log::ERR => Logger::ERROR,
            Box_Log::WARN => Logger::WARNING,
            Box_Log::NOTICE => Logger::NOTICE,
            Box_Log::INFO => Logger::INFO,
            Box_Log::DEBUG => Logger::DEBUG,
        );

        if (isset($map[$priority])) {
            return $map[$priority];
        } else {
            return Logger::DEBUG;
        }
    }

    /**
     * @param array $event
     * @param string $channel
     */
    public function write(array $event, $channel = 'fossbilling')
    {
        $priority = $this->parsePriority($event['priority']);
        $message = $event['message'];
        $context = isset($event['info']) && is_array($event['info']) ? $event['info'] : array();

        $this->getLogger($channel)->log($priority, $message, $context);
    }
}
