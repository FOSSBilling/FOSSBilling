<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc. 
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

class Box_LogDb
{
    /**
     * $service - module service class
     *
     * @var object $service
     */
    protected $service = null;

    /**
     * Class constructor
     *
     * @param object $service - module service class object
     */
    public function __construct($service)
    {
        $this->service = $service;
    }

    /**
     * Write a message to the log.
     *
     * @param array $event
     * @param string $channel
     *
     * @return void
     */
    public function write(array $event, string $channel = 'application'): void
    {
        try {
            if (method_exists($this->service, 'logEvent')) {
                $this->service->logEvent($event);
            }
        } catch (Exception $e) {
            error_log($e);
        }
    }

}
