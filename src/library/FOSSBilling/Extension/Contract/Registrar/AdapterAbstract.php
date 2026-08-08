<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Extension\Contract\Registrar;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

abstract class AdapterAbstract
{
    private ?LoggerInterface $logger = null;

    /**
     * Are we in test mode ?
     *
     * @var bool
     */
    protected $_testMode = false;

    /**
     * Related order.
     */

    /**
     * Return array with configuration.
     *
     * Must be overridden in adapter class
     *
     * @return array
     */
    abstract public static function getConfig();

    /**
     * Checks if a domain is available for registration.
     *
     * @param Domain $domain domain object containing the details of the domain to check
     *
     * @return bool True if the domain is available, otherwise the adapter should throw an exception
     *
     * @throws Exception if there was an error while checking the domain availability
     */
    abstract public function isDomainAvailable(Domain $domain);

    /**
     * Checks if a domain can be transferred to the registrar.
     *
     * @param Domain $domain domain object containing the details of the domain to check
     *
     * @return bool True if the domain can be transferred, otherwise the adapter should throw an exception
     *
     * @throws Exception if there was an error while checking the domain transferability
     */
    abstract public function isDomaincanBeTransferred(Domain $domain);

    /**
     * Modifies the name servers for a domain.
     *
     * @param Domain $domain domain object containing the details of the domain to update, including the new name servers
     *
     * @return bool True if the name servers were modified successfully, otherwise the adapter should throw an exception
     *
     * @throws Exception if there was an error while modifying the name servers
     */
    abstract public function modifyNs(Domain $domain);

    /**
     * Modifies the contact information for a domain.
     *
     * @param Domain $domain domain object containing the details of the domain to update, including the new contact information
     *
     * @return bool True if the contact information was modified successfully, otherwise the adapter should throw an exception
     *
     * @throws Exception if there was an error while modifying the contact information
     */
    abstract public function modifyContact(Domain $domain);

    /**
     * Transfers a domain to the registrar.
     *
     * @param Domain $domain domain object containing the details of the domain to transfer, including the domain transfer code
     *
     * @return bool True if the domain was transferred successfully, otherwise the adapter should throw an exceptions
     *
     * @throws Exception if there was an error while transferring the domain
     */
    abstract public function transferDomain(Domain $domain);

    /**
     * Returns the details of a registered domain.
     *
     * @param Domain $domain domain object containing the details of the domain to query
     *
     * @return Domain domain object containing the updated details of the registered domain
     *
     * @throws Exception if the domain is not registered or there was an error while retrieving the domain details
     */
    abstract public function getDomainDetails(Domain $domain);

    /**
     * Returns the domain transfer code (also known as the EPP code or auth code) for a domain.
     *
     * @param Domain $domain domain object containing the details of the domain
     *
     * @return string the domain transfer code
     *
     * @throws Exception if there was an error while retrieving the domain transfer code
     */
    abstract public function getEpp(Domain $domain);

    /**
     * Registers a domain with the registrar.
     *
     * @param Domain $domain domain object containing the details of the domain to register
     *
     * @return bool True if the domain was registered successfully, otherwise the adapter should throw an exception
     *
     * @throws Exception if there was an error while registering the domain
     */
    abstract public function registerDomain(Domain $domain);

    /**
     * Renews a domain registration with the registrar.
     *
     * @param Domain $domain domain object containing the details of the domain to renew
     *
     * @return bool True if the domain was renewed successfully, otherwise the adapter should throw an exception
     *
     * @throws Exception if there was an error while renewing the domain
     */
    abstract public function renewDomain(Domain $domain);

    /**
     * Deletes a domain from the registrar.
     *
     * @param Domain $domain domain object containing the details of the domain to delete
     *
     * @return bool True if the domain was deleted successfully, otherwise the adapter should throw an exception
     *
     * @throws Exception if there was an error while deleting the domain
     */
    abstract public function deleteDomain(Domain $domain);

    /**
     * Enables privacy protection for a domain.
     *
     * @param Domain $domain domain object containing the details of the domain for which to enable privacy protection
     *
     * @return bool True if privacy protection was enabled successfully, otherwise the adapter should throw an exception
     *
     * @throws Exception if there was an error while enabling privacy protection
     */
    abstract public function enablePrivacyProtection(Domain $domain);

    /**
     * Disables privacy protection for a domain.
     *
     * @param Domain $domain domain object containing the details of the domain for which to disable privacy protection
     *
     * @return bool True if privacy protection was disabled successfully, otherwise the adapter should throw an exception
     *
     * @throws Exception if there was an error while disabling privacy protection
     */
    abstract public function disablePrivacyProtection(Domain $domain);

    /**
     * Locks a domain to prevent transfer to another registrar.
     *
     * @param Domain $domain domain object containing the details of the domain to lock
     *
     * @return bool True if the domain was locked successfully, otherwise the adapter should throw an exception
     *
     * @throws Exception if there was an error while locking the domain
     */
    abstract public function lock(Domain $domain);

    /**
     * Unlocks a domain to allow transfer to another registrar.
     *
     * @param Domain $domain domain object containing the details of the domain to unlock
     *
     * @return bool True if the domain was unlocked successfully, otherwise the adapter should throw an exception
     *
     * @throws Exception if there was an error while unlocking the domain
     */
    abstract public function unlock(Domain $domain);

    /**
     * Sets the logger to use for logging messages.
     */
    public function setLog(LoggerInterface $logger): static
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Gets the logger currently in use for logging messages.
     */
    public function getLog(): LoggerInterface
    {
        return $this->logger ??= new NullLogger();
    }

    /**
     * Creates and returns an interface for the Symfony HTTP client.
     */
    public function getHttpClient(): \Symfony\Contracts\HttpClient\HttpClientInterface
    {
        return \Symfony\Component\HttpClient\HttpClient::create(['bindto' => BIND_TO]);
    }

    /**
     * Enables test mode for the adapter.
     *
     * @return AdapterAbstract the current adapter object, for method chaining
     */
    public function enableTestMode()
    {
        $this->_testMode = true;

        return $this;
    }
}
