<?php
abstract class Registrar_AdapterAbstract
{
    use Symfony\Component\HttpClient\HttpClient;
    
    protected $_log = null;

    /**
     * Are we in test mode ?
     *
     * @var boolean
     */
    protected $_testMode = false;

    /**
     * Return array with configuration
     * 
     * Must be overriden in adapter class
     * 
     * @return array
     */
    public static function getConfig()
    {
        throw new Registrar_Exception('Domain registrar class did not implement configuration options method', 749);
    }

    /**
     * Returns an array of top-level domains (TLDs) that the registrar is capable of registering.
     *
     * If the function returns an empty array, the registrar can register any TLD.
     *
     * @return array Array of TLDs that the registrar is capable of registering.
     */
    abstract public function getTlds();

    /**
     * Checks if a domain is available for registration.
     *
     * @param Registrar_Domain $domain Domain object containing the details of the domain to check.
     * 
     * @return bool True if the domain is available, otherwise the adapter should throw an exception
     * 
     * @throws Registrar_Exception If there was an error while checking the domain availability.
     */
    abstract public function isDomainAvailable(Registrar_Domain $domain);

    /**
     * Checks if a domain can be transferred to the registrar.
     *
     * @param Registrar_Domain $domain Domain object containing the details of the domain to check.
     * 
     * @return bool True if the domain can be transferred, otherwise the adapter should throw an exception
     * 
     * @throws Registrar_Exception If there was an error while checking the domain transferability.
     */
    abstract public function isDomaincanBeTransferred(Registrar_Domain $domain);

    /**
     * Modifies the name servers for a domain.
     *
     * @param Registrar_Domain $domain Domain object containing the details of the domain to update, including the new name servers.
     * 
     * @return bool True if the name servers were modified successfully, otherwise the adapter should throw an exception
     * 
     * @throws Registrar_Exception If there was an error while modifying the name servers.
     */
    abstract public function modifyNs(Registrar_Domain $domain);

    /**
     * Modifies the contact information for a domain.
     *
     * @param Registrar_Domain $domain Domain object containing the details of the domain to update, including the new contact information.
     * 
     * @return bool True if the contact information was modified successfully, otherwise the adapter should throw an exception
     * 
     * @throws Registrar_Exception If there was an error while modifying the contact information.
     */
    abstract public function modifyContact(Registrar_Domain $domain);

    /**
     * Transfers a domain to the registrar.
     *
     * @param Registrar_Domain $domain Domain object containing the details of the domain to transfer, including the domain transfer code.
     * 
     * @return bool True if the domain was transferred successfully, otherwise the adapter should throw an exceptions
     * 
     * @throws Registrar_Exception If there was an error while transferring the domain.
     */
    abstract public function transferDomain(Registrar_Domain $domain);

    /**
     * Returns the details of a registered domain.
     *
     * @param Registrar_Domain $domain Domain object containing the details of the domain to query.
     * 
     * @return Registrar_Domain Domain object containing the updated details of the registered domain.
     * 
     * @throws Registrar_Exception If the domain is not registered or there was an error while retrieving the domain details.
     */
    abstract public function getDomainDetails(Registrar_Domain $domain);

    /**
     * Returns the domain transfer code (also known as the EPP code or auth code) for a domain.
     *
     * @param Registrar_Domain $domain Domain object containing the details of the domain.
     * 
     * @return string The domain transfer code.
     * 
     * @throws Registrar_Exception If there was an error while retrieving the domain transfer code.
     */
    abstract public function getEpp(Registrar_Domain $domain);

    /**
     * Registers a domain with the registrar.
     *
     * @param Registrar_Domain $domain Domain object containing the details of the domain to register.
     * 
     * @return bool True if the domain was registered successfully, otherwise the adapter should throw an exception
     * 
     * @throws Registrar_Exception If there was an error while registering the domain.
     */
    abstract public function registerDomain(Registrar_Domain $domain);

    /**
     * Renews a domain registration with the registrar.
     *
     * @param Registrar_Domain $domain Domain object containing the details of the domain to renew.
     * 
     * @return bool True if the domain was renewed successfully, otherwise the adapter should throw an exception
     * 
     * @throws Registrar_Exception If there was an error while renewing the domain.
     */
    abstract public function renewDomain(Registrar_Domain $domain);

    /**
     * Deletes a domain from the registrar.
     *
     * @param Registrar_Domain $domain Domain object containing the details of the domain to delete.
     * 
     * @return bool True if the domain was deleted successfully, otherwise the adapter should throw an exception
     * 
     * @throws Registrar_Exception If there was an error while deleting the domain.
     */
    abstract public function deleteDomain(Registrar_Domain $domain);

    /**
     * Enables privacy protection for a domain.
     *
     * @param Registrar_Domain $domain Domain object containing the details of the domain for which to enable privacy protection.
     * 
     * @return bool True if privacy protection was enabled successfully, otherwise the adapter should throw an exception
     * 
     * @throws Registrar_Exception If there was an error while enabling privacy protection.
     */
    abstract public function enablePrivacyProtection(Registrar_Domain $domain);

    /**
     * Disables privacy protection for a domain.
     *
     * @param Registrar_Domain $domain Domain object containing the details of the domain for which to disable privacy protection.
     * 
     * @return bool True if privacy protection was disabled successfully, otherwise the adapter should throw an exception
     * 
     * @throws Registrar_Exception If there was an error while disabling privacy protection.
     */
    abstract public function disablePrivacyProtection(Registrar_Domain $domain);

    /**
     * Locks a domain to prevent transfer to another registrar.
     *
     * @param Registrar_Domain $domain Domain object containing the details of the domain to lock.
     * 
     * @return bool True if the domain was locked successfully, otherwise the adapter should throw an exception
     * 
     * @throws Registrar_Exception If there was an error while locking the domain.
     */
    abstract public function lock(Registrar_Domain $domain);

    /**
     * Unlocks a domain to allow transfer to another registrar.
     *
     * @param Registrar_Domain $domain Domain object containing the details of the domain to unlock.
     * 
     * @return bool True if the domain was unlocked successfully, otherwise the adapter should throw an exception
     * 
     * @throws Registrar_Exception If there was an error while unlocking the domain.
     */
    abstract public function unlock(Registrar_Domain $domain);

    /**
     * Sets the logger object to use for logging messages.
     *
     * @param Box_Log $log The logger object to use.
     * 
     * @return Registrar_AdapterAbstract The current adapter object, for method chaining.
     */
    public function setLog(Box_Log $log)
    {
        $this->_log = $log;
        return $this;
    }

    /**
     * Gets the logger object currently in use for logging messages.
     *
     * @return Box_Log The logger object.
     */
    public function getLog()
    {
        $log = $this->_log;
        if (!$log instanceof Box_Log) {
            $log = new Box_Log();
            $log->addWriter(new Box_LogDb('Model_ActivitySystem'));
        }
        return $log;
    }

    /**
     * Enables test mode for the adapter.
     *
     * @return Registrar_AdapterAbstract The current adapter object, for method chaining.
     */
    public function enableTestMode()
    {
        $this->_testMode = true;
        return $this;
    }
}
