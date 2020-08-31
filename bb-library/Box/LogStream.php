<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */


class Box_LogStream
{
    private $_stream = NULL;

    /**
     * @param string $streamOrUrl
     */
    public function __construct($streamOrUrl, $mode = null)
    {
        // Setting the default
        if (null === $mode) {
            $mode = 'a';
        }

        if (is_resource($streamOrUrl)) {
            if (get_resource_type($streamOrUrl) != 'stream') {
                throw new \Box_Exception('Resource is not a stream');
            }

            if ($mode != 'a') {
                throw new \Box_Exception('Mode cannot be changed on existing streams');
            }

            $this->_stream = $streamOrUrl;
        } else {
            if (is_array($streamOrUrl) && isset($streamOrUrl['stream'])) {
                $streamOrUrl = $streamOrUrl['stream'];
            }

            if(!file_exists($streamOrUrl)) {
                @touch($streamOrUrl);
            }

            if (! $this->_stream = @fopen($streamOrUrl, $mode, false)) {
                throw new \Box_Exception(":stream cannot be opened with mode :mode", array(':stream'=>$streamOrUrl, ':mode'=>$mode));
            }
        }
    }

    public function write($event)
    {
        $output = '%timestamp% %priorityName% (%priority%): %message%'.PHP_EOL;
        foreach ($event as $name => $value) {
            if ((is_object($value) && !method_exists($value,'__toString'))
                || is_array($value)
            ) {
                $value = gettype($value);
            }
            $output = str_replace("%$name%", $value, $output);
        }

        if (false === @fwrite($this->_stream, $output)) {
            throw new \Box_Exception("Unable to write to stream");
        }
    }
}
