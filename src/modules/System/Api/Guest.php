<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
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
use FOSSBilling\Validation\Api\RequiredParams;
use PrinsFrank\Standards\CountryCallingCode\CountryCallingCode;
use Symfony\Component\Intl\Countries;

class Guest extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Returns company information.
     *
     * @return array
     */
    public function company()
    {
        $companyInfo = $this->getService()->getCompany();
        $auth = $this->getDi()['auth'];
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
     * Return the code of the default country, if set.
     */
    public function default_country(): ?string
    {
        $mod = $this->getDi()['mod']('system');
        $cfg = $mod->getConfig();

        return $cfg['default_country'] ?? null;
    }

    /**
     * Return countries enabled in System settings.
     *
     * @return array<string, string>
     */
    public function countries(): array
    {
        $mod = $this->getDi()['mod']('system');
        $cfg = $mod->getConfig();
        $configuredCountries = trim((string) ($cfg['countries'] ?? ''));

        if ($configuredCountries === '') {
            return Countries::getNames();
        }

        $countries = [];
        foreach (preg_split('/\R/', $configuredCountries) as $line) {
            $parts = explode('=', trim($line), 2);
            if (count($parts) !== 2) {
                continue;
            }

            $code = strtoupper(trim($parts[0]));
            $name = trim($parts[1]);
            if ($code === '' || $name === '' || !Countries::exists($code)) {
                continue;
            }

            $countries[$code] = $name;
        }

        return $countries;
    }

    /**
     * Returns system parameter by key.
     *
     * @return string
     */
    #[RequiredParams(['key' => '"key" parameter was not passed'])]
    public function param($data)
    {
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
     * Return a unique list of available phone country calling codes.
     *
     * @return list<int>
     */
    public function phone_codes(): array
    {
        $codes = array_map(static fn (CountryCallingCode $code): int => $code->value, CountryCallingCode::cases());
        $codes = array_values(array_unique($codes));
        sort($codes, SORT_NUMERIC);

        return $codes;
    }

    /**
     * Gets period title by identifier.
     *
     * @return string
     */
    public function period_title($data)
    {
        $code = $data['code'] ?? null;
        if ($code === null || $code === '' || $code === 0 || $code === '0') {
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
        $page_param = $data['page_param'] ?? 'page';
        $current_page = (int) ($data[$page_param] ?? 1);
        $limit = (int) ($data['per_page'] ?? 20);
        $itemsCount = (int) ($data['total'] ?? 0);

        $p = new \FOSSBilling\Paginator($itemsCount, $current_page, $limit, $midrange);

        return $p->toArray();
    }

    /**
     * If called from template file this function returns current url.
     *
     * @return string
     */
    public function current_url()
    {
        return $this->getDi()['request']->getRequestUri();
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
     */
    public function locale(): string
    {
        return i18n::getActiveLocale($this->getDi()['request'], true, $this->getDi()['cookie_queue']);
    }

    /**
     * IANA timezone identifiers grouped by region, suitable for a `<select>` with `<optgroup>`.
     *
     * @return array<string, list<string>>
     */
    public function timezones(): array
    {
        return i18n::getTimezones();
    }

    public function get_pending_messages()
    {
        $messages = $this->getService()->getPendingMessages();
        $this->getService()->clearPendingMessages();

        return $messages;
    }
}
