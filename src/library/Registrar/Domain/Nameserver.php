<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Registrar_Domain_Nameserver implements Stringable
{
    private $host;
    private $ip;

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

    public function __toString(): string
    {
        $c = '';
        $c .= sprintf('Host: %s', $this->getHost()) . PHP_EOL;

        return $c . (sprintf('Ip: %s', $this->getIp()) . PHP_EOL);
    }
}
