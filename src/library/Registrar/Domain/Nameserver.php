<?php
/**
 * FOSSBilling.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * Copyright FOSSBilling 2022
 * This software may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE.
 */

class Registrar_Domain_Nameserver
{
    private $host = null;
    private $ip = null;

    public function setHost($param)
    {
        $this->host = $param;
        return $this;
    }

    public function getHost()
    {
        return $this->host;
    }

    public function setIp($param)
    {
        $this->ip = $param;
        return $this;
    }

    public function getIp()
    {
        return $this->ip;
    }

    public function __toString() {
        $c = '';
        $c .= sprintf("Host: %s", $this->getHost()).PHP_EOL;
        $c .= sprintf("Ip: %s", $this->getIp()).PHP_EOL;
        return $c;
    }
}