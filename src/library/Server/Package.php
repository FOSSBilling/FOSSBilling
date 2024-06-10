<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Server_Package
{
    private ?string $name = 'FOSSBilling';
    private ?string $quota = null;
    private ?string $bandwidth = null;
    private ?string $maxDomains = null;
    private ?string $maxSubdomains = null;
    private ?string $maxParkedDomains = null;
    private ?string $maxFtp = null;
    private ?string $maxSql = null;
    private ?string $MaxPop = null;
    private array $custom = [];

    /**
     * Handle calls to inaccessible methods.
     *
     * @param string $name      the name of the method being called
     * @param array  $arguments an enumerated array containing the parameters passed to the $name'ed method
     *
     * @return string always returns an empty string
     */
    public function __call(string $name, array $arguments)
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
     * Set custom values for the Server_Package instance.
     *
     * @param array $param the custom values to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setCustomValues(array $param): static
    {
        $this->custom = $param;

        return $this;
    }

    /**
     * Set a custom value for the Server_Package instance.
     *
     * @param string      $param the name of the custom value to be set
     * @param string|null $value the value to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setCustomValue(string $param, ?string $value): static
    {
        $this->custom[$param] = $value;

        return $this;
    }

    /**
     * Get a custom value from the Server_Package instance.
     *
     * @param string $param the name of the custom value to be retrieved
     *
     * @return string|null returns the custom value if it exists, otherwise returns null
     */
    public function getCustomValue(string $param): ?string
    {
        return $this->custom[$param] ?? null;
    }

    /**
     * Get the name of the Server_Package instance.
     *
     * @return string|null returns the name as a string if it exists, otherwise returns null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Set the name of the Server_Package instance.
     *
     * @param string|null $name the name to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the quota of the Server_Package instance.
     *
     * @return string returns the quota
     */
    public function getQuota(): ?string
    {
        return $this->quota;
    }

    /**
     * Set the quota of the Server_Package instance.
     *
     * @param string|null $quota the quota to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setQuota(?string $quota): static
    {
        $this->quota = $quota;

        return $this;
    }

    /**
     * Get the bandwidth of the Server_Package instance.
     *
     * @return string returns the bandwidth
     */
    public function getBandwidth(): ?string
    {
        return $this->bandwidth;
    }

    /**
     * Set the bandwidth of the Server_Package instance.
     *
     * @param string|null $bandwidth the bandwidth to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setBandwidth(?string $bandwidth): static
    {
        $this->bandwidth = $bandwidth;

        return $this;
    }

    /**
     * Get the maximum number of domains for the Server_Package instance.
     *
     * @return string returns the maximum number of domains
     */
    public function getMaxDomains(): ?string
    {
        return $this->maxDomains;
    }

    /**
     * Set the maximum number of domains for the Server_Package instance.
     *
     * @param string|null $maxDomains the maximum number of domains to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setMaxDomains(?string $maxDomains): static
    {
        $this->maxDomains = $maxDomains;

        return $this;
    }

    /**
     * Get the maximum number of subdomains for the Server_Package instance.
     *
     * @return string returns the maximum number of subdomains
     */
    public function getMaxSubdomains(): ?string
    {
        return $this->maxSubdomains;
    }

    /**
     * Set the maximum number of subdomains for the Server_Package instance.
     *
     * @param string|null $maxSubdomains the maximum number of subdomains to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setMaxSubdomains(?string $maxSubdomains): static
    {
        $this->maxSubdomains = $maxSubdomains;

        return $this;
    }

    /**
     * Get the maximum number of parked domains for the Server_Package instance.
     *
     * @return string returns the maximum number of parked domains
     */
    public function getMaxParkedDomains(): ?string
    {
        return $this->maxParkedDomains;
    }

    /**
     * Set the maximum number of parked domains for the Server_Package instance.
     *
     * @param string|null $maxParkedDomains the maximum number of parked domains to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setMaxParkedDomains(?string $maxParkedDomains): static
    {
        $this->maxParkedDomains = $maxParkedDomains;

        return $this;
    }

    /**
     * Get the maximum number of FTP accounts for the Server_Package instance.
     *
     * @return string returns the maximum number of FTP accounts
     */
    public function getMaxFtp(): ?string
    {
        return $this->maxFtp;
    }

    /**
     * Set the maximum number of FTP accounts for the Server_Package instance.
     *
     * @param string|null $maxFtp the maximum number of FTP accounts to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setMaxFtp(?string $maxFtp): static
    {
        $this->maxFtp = $maxFtp;

        return $this;
    }

    /**
     * Get the maximum number of SQL databases for the Server_Package instance.
     *
     * @return string returns the maximum number of SQL databases
     */
    public function getMaxSql(): ?string
    {
        return $this->maxSql;
    }

    /**
     * Set the maximum number of SQL databases for the Server_Package instance.
     *
     * @param string|null $maxSql the maximum number of SQL databases to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setMaxSql(?string $maxSql): static
    {
        $this->maxSql = $maxSql;

        return $this;
    }

    /**
     * Get the maximum number of POP email accounts for the Server_Package instance.
     *
     * @return string|null returns the maximum number of POP email accounts
     */
    public function getMaxPop(): ?string
    {
        return $this->MaxPop;
    }

    /**
     * Set the maximum number of POP email accounts for the Server_Package instance.
     *
     * @param string|null $maxPop the maximum number of POP email accounts to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setMaxPop(?string $maxPop): static
    {
        $this->MaxPop = $maxPop;

        return $this;
    }

    /**
     * Get the maximum quota for the Server_Package instance.
     *
     * @return string|null returns the maximum quota
     */
    public function getMaxQuota(): ?string
    {
        return $this->quota;
    }

    /**
     * Set the maximum quota for the Server_Package instance.
     *
     * @param string|null $maxQuota the maximum quota to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setMaxQuota(?string $maxQuota): static
    {
        $this->quota = $maxQuota;

        return $this;
    }
}
