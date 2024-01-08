<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Server_Package
{
    private ?string $name = 'FOSSBilling';
    private $quota;
    private $bandwidth;

    private $maxdomains;
    private $maxsubdomains;
    private $maxparkeddomains;
    private $maxftp;
    private $maxsql;
    private $maxpop;

    private array $custom = [];

    public function __call($name, $arguments)
    {
        if (version_compare(PHP_VERSION, '5.4.0') < 0) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        } else {
            // Get only the stack frames we need (PHP 5.4 only).
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        }
        error_log(sprintf('Calling %s inaccessible method %s from %s::%d', static::class, $name, $backtrace[1]['file'], $backtrace[1]['line']));

        return '';
    }

    public function setCustomValues(array $param)
    {
        $this->custom = $param;

        return $this;
    }

    public function setCustomValue($param, $value)
    {
        $this->custom[$param] = $value;

        return $this;
    }

    /**
     * @param string $param
     */
    public function getCustomValue($param)
    {
        return $this->custom[$param] ?? null;
    }

    public function setName($param)
    {
        $this->name = $param;

        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setQuota($param)
    {
        $this->quota = $param;

        return $this;
    }

    public function getQuota()
    {
        return $this->quota;
    }

    public function setBandwidth($param)
    {
        $this->bandwidth = $param;

        return $this;
    }

    public function getBandwidth()
    {
        return $this->bandwidth;
    }

    public function setMaxDomains($param)
    {
        $this->maxdomains = $param;

        return $this;
    }

    public function getMaxDomains()
    {
        return $this->maxdomains;
    }

    public function setMaxSubdomains($param)
    {
        $this->maxsubdomains = $param;

        return $this;
    }

    public function getMaxSubdomains()
    {
        return $this->maxsubdomains;
    }

    public function setMaxParkedDomains($param)
    {
        $this->maxparkeddomains = $param;

        return $this;
    }

    public function getMaxParkedDomains()
    {
        return $this->maxparkeddomains;
    }

    public function setMaxFtp($param)
    {
        $this->maxftp = $param;

        return $this;
    }

    public function getMaxFtp()
    {
        return $this->maxftp;
    }

    public function setMaxSql($param)
    {
        $this->maxsql = $param;

        return $this;
    }

    public function getMaxSql()
    {
        return $this->maxsql;
    }

    public function setMaxPop($param)
    {
        $this->maxpop = $param;

        return $this;
    }

    public function getMaxPop()
    {
        return $this->maxpop;
    }

    public function getMaxQuota()
    {
        return $this->quota;
    }
}
