<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * System methods 
 */
namespace Box\Mod\System\Api;

class Guest extends \Api_Abstract
{
    /**
     * Get BoxBilling version
     * 
     * @return string
     */
    public function version()
    {
        return $this->getService()->getVersion();
    }
    
    /**
     * Returns company information
     * 
     * @return array
     */
    public function company()
    {
        return $this->getService()->getCompany();
    }
    
    /**
     * Returns world wide phone codes
     * 
     * @optional $country - if passed country code the result will be phone code only
     * 
     * @return array
     */
    public function phone_codes($data)
    {
       return $this->getService()->getPhoneCodes($data);
    }
    
    /**
     * Returns USA states list
     * 
     * @return array
     */
    public function states()
    {
        return $this->getService()->getStates();
    }

    /**
     * Returns list of european union countries
     * 
     * @return array
     */
    public function countries_eunion()
    {
        return $this->getService()->getEuCountries();
    }

    /**
     * Returns list of world countries
     * 
     * @return array
     */
    public function countries()
    {
       return $this->getService()->getCountries();
    }

    /**
     * Returns system parameter by key
     * 
     * @param string $key - Parameter name
     * 
     * @return string
     */
    public function param($data)
    {
        $required = array(
            'key'    => 'Parameter key is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->getPublicParamValue($data['key']);
    }

    /**
     * Return list of available payment periods
     * 
     * @return array
     */
    public function periods()
    {
    	return \Box_Period::getPredefined();
    }

    /**
     * Gets period title by identifier
     * 
     * @param string $code - Period code name, ie: 1M => Monthly
     * 
     * @return string 
     */
    public function period_title($data)
    {
        $code = $this->di['array_get']($data, 'code', NULL);
        if($code == NULL) {
            return '-';
        }
        return $this->getService()->getPeriod($code);
    }

    /**
     * Returns info for paginator according to list
     *
     * @return array
     */
    public function paginator($data)
    {
        $midrange = 7;
        $current_page = $data['page'];
        $limit = $data['per_page'];
        $itemsCount = $data['total'];

        $p = new \Box_Paginator($itemsCount, $current_page, $limit, $midrange);
        return $p->toArray();
    }

    /**
     * If called from template file this function returns current url
     * @return string
     */
    public function current_url()
    {
        return $this->di['request']->getURI();
    }
    
    /**
     * Check if passed file name template exists for client area
     * 
     * @param string $file - template file name, example: mod_index_dashboard.phtml
     * @return bool
     */
    public function template_exists($data)
    {
        if(!isset($data['file'])) {
            return false;
        }

        return $this->getService()->templateExists($data['file']);
    }
    
    /**
     * Get current client locale
     * 
     * @return string
     */
    public function locale()
    {
        $cookie = $this->di['cookie'];
        $locale = $this->di['config']['locale'];;
        if ($cookie->has('BBLANG')){
            $bblang = $cookie->get('BBLANG');
            if (!empty($bblang)){
                $locale = $bblang;
            }
        }
        return $locale;
    }

    public function get_pending_messages()
    {
        $messages = $this->getService()->getPendingMessages();
        $this->getService()->clearPendingMessages();
        return $messages;
    }
}