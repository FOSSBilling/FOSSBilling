<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
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
     * @param  array  $event  event data
     * @return void
     */
    public function write($event)
    {
        try {
            if(method_exists($this->service, 'logEvent')) {
                $this->service->logEvent($event);
            }
        } catch(Exception $e) {
            error_log($e);
        }
    }

}
