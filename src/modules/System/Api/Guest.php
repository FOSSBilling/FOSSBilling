<?php
/**
 * Copyright 2022-2024 FOSSBilling
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

use FOSSBilling\i18n;

class Guest extends \Api_Abstract
{
    /**
     * Get FOSSBilling version.
     *
     * @return string
     */
    public function version()
    {
        $hideVersionGuest = $this->getService()->getParamValue('hide_version_public');

        // Only provide the FOSSBilling version if configured to do so or if the request is being made by an administrator.
        if ($this->di['auth']->isAdminLoggedIn() || !$hideVersionGuest) {
            return $this->getService()->getVersion();
        } else {
            // return an empty string
            return '';
        }
    }

    /**
     * Returns company information.
     *
     * @return array
     */
    public function company()
    {
        $companyInfo = $this->getService()->getCompany();
        $auth = $this->di['auth'];
        $hideExtraCompanyInfoFromGuest = $this->getService()->getParamValue('hide_company_public');

        if (!$auth->isAdminLoggedIn() && !$auth->isClientLoggedIn() && $hideExtraCompanyInfoFromGuest) {
            unset($companyInfo['vat_number']);
            unset($companyInfo['email']);
            unset($companyInfo['tel']);
            unset($companyInfo['account_number']);
            unset($companyInfo['number']);

            unset($companyInfo['address_1']);
            unset($companyInfo['address_2']);
            unset($companyInfo['address_3']);
            unset($companyInfo['bank_name']);
            unset($companyInfo['bic']);
        }

        return $companyInfo;
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
        return i18n::getActiveLocale();
    }

    public function get_pending_messages()
    {
        $messages = $this->getService()->getPendingMessages();
        $this->getService()->clearPendingMessages();

        return $messages;
    }
}
