<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * System methods.
 */

namespace Box\Mod\System\Api;

class Guest extends \Api_Abstract
{
    /**
     * Get FOSSBilling version.
     *
     * @return string
     */
    public function version()
    {
        // check if the user is logged in as admin and if so, return the version
        if ($this->di['auth']->isAdminLoggedIn()) {
            return $this->getService()->getVersion();
        }

        // check if the "show_version_public" parameter is set to true
        $showVersionPublic = $this->getService()->getParamValue('show_version_public');
        if ($showVersionPublic == 1) {
            return $this->getService()->getVersion();
        } else {
            // return empty array
            return [];
        }
        return $this->getService()->getVersion();
    }

    /**
     * Returns company information.
     *
     * @return array
     */
    public function company()
    {
        // check if the "show_company_public" parameter is set to true
        $showCompanyPublic = $this->getService()->getParamValue('show_company_public');
        if ($showCompanyPublic == 1) {
            return $this->getService()->getCompany();
        } else {
            // return empty array
            return [];
        }
    }

    /**
     * Returns world wide phone codes.
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
     * Returns USA states list.
     *
     * @return array
     */
    public function states()
    {
        return $this->getService()->getStates();
    }

    /**
     * Returns list of european union countries.
     *
     * @return array
     */
    public function countries_eunion()
    {
        return $this->getService()->getEuCountries();
    }

    /**
     * Returns list of world countries.
     *
     * @return array
     */
    public function countries()
    {
        return $this->getService()->getCountries();
    }

    /**
     * Returns system parameter by key.
     *
     * @return string
     */
    public function param($data)
    {
        $required = [
            'key' => 'Parameter key is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->getPublicParamValue($data['key']);
    }

    /**
     * Return list of available payment periods.
     *
     * @return array
     */
    public function periods()
    {
        return \Box_Period::getPredefined();
    }

    /**
     * Gets period title by identifier.
     *
     * @return string
     */
    public function period_title($data)
    {
        $code = $data['code'] ?? null;
        if ($code == null) {
            return '-';
        }

        return $this->getService()->getPeriod($code);
    }

    /**
     * Returns info for paginator according to list.
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
     * If called from template file this function returns current url.
     *
     * @return string
     */
    public function current_url()
    {
        return $_SERVER['REQUEST_URI'] ?? null;
    }

    /**
     * Check if passed file name template exists for client area.
     *
     * @return bool
     */
    public function template_exists($data)
    {
        if (!isset($data['file'])) {
            return false;
        }

        return $this->getService()->templateExists($data['file']);
    }

    /**
     * Get current client locale.
     *
     * @return string
     */
    public function locale()
    {
        $locale = $this->di['config']['i18n']['locale'];
        if (isset($_COOKIE['BBLANG'])) {
            $bblang = $_COOKIE['BBLANG'];
            if (!empty($bblang)) {
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
