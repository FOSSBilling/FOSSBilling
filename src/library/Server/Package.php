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

    /**
     * @param array $param
     * @return $this
     */
    public function setCustomValues(array $param): static
    {
        $this->custom = $param;

        return $this;
    }

    /**
     * @param $param
     * @param $value
     * @return $this
     */
    public function setCustomValue($param, $value): static
    {
        $this->custom[$param] = $value;

        return $this;
    }

    /**
     * @param string $param
     * @return mixed|null
     */
    public function getCustomValue(string $param)
    {
        return $this->custom[$param] ?? null;
    }

    /**
     * @return ?string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setName($param): static
    {
        $this->name = $param;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getQuota(): mixed
    {
        return $this->quota;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setQuota($param): static
    {
        $this->quota = $param;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getBandwidth(): mixed
    {
        return $this->bandwidth;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setBandwidth($param): static
    {
        $this->bandwidth = $param;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getMaxDomains(): mixed
    {
        return $this->maxdomains;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setMaxDomains($param): static
    {
        $this->maxdomains = $param;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getMaxSubdomains(): mixed
    {
        return $this->maxsubdomains;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setMaxSubdomains($param): static
    {
        $this->maxsubdomains = $param;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getMaxParkedDomains(): mixed
    {
        return $this->maxparkeddomains;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setMaxParkedDomains($param): static
    {
        $this->maxparkeddomains = $param;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getMaxFtp(): mixed
    {
        return $this->maxftp;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setMaxFtp($param): static
    {
        $this->maxftp = $param;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getMaxSql(): mixed
    {
        return $this->maxsql;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setMaxSql($param): static
    {
        $this->maxsql = $param;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getMaxPop(): mixed
    {
        return $this->maxpop;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setMaxPop($param): static
    {
        $this->maxpop = $param;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getMaxQuota(): mixed
    {
        return $this->quota;
    }
}
