<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * Currency management.
 */

namespace Box\Mod\Currency\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get list of available currencies on system.
     *
     * @return array
     */
    public function get_list($data)
    {
        [$query, $params] = $this->getService()->getSearchQuery();
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        $pager = $this->di['pager']->getSimpleResultSet($query, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $currency = $this->di['db']->getExistingModelById('Currency', $item['id'], 'Currency not found');
            $pager['list'][$key] = $this->getService()->toApiArray($currency);
        }

        return $pager;
    }

    /**
     * Get code title pairs of currencies.
     *
     * @return array
     */
    public function get_pairs()
    {
        $service = $this->getService();

        return $service->getAvailableCurrencies();
    }

    /**
     * Return currency details by cde.
     *
     * @param string $code - currency code USD
     *
     * @return array
     *
     * @throws Exception
     */
    public function get($data)
    {
        $required = [
            'code' => 'Currency code is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->getService();
        $model = $service->getByCode($data['code']);

        if (!$model instanceof \Model_Currency) {
            throw new \Box_Exception('Currency not found');
        }

        return $service->toApiArray($model);
    }

    /**
     * Return default system currency.
     *
     * @return array
     */
    public function get_default($data)
    {
        $service = $this->getService();
        $currency = $service->getDefault();

        return $service->toApiArray($currency);
    }

    /**
     * Add new currency to system.
     *
     * @param string $code   - currency ISO 4217 code
     * @param string $format - must have {{price}} tag
     *
     * @optional string $title - custom currency title
     *
     * @return string - currency code
     *
     * @throws Exception
     */
    public function create($data = [])
    {
        $required = [
            'code' => 'Currency code is missing',
            'format' => 'Currency format is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->getService();

        if ($service->getByCode($this->di['array_get']($data, 'code'))) {
            throw new \Box_Exception('Currency already registered');
        }

        if (!array_key_exists($this->di['array_get']($data, 'code'), $service->getAvailableCurrencies())) {
            throw new \Box_Exception('Currency code is not valid');
        }

        $title = $this->di['array_get']($data, 'title');
        $conversionRate = $this->di['array_get']($data, 'conversion_rate', 1);

        return $service->createCurrency($this->di['array_get']($data, 'code'), $this->di['array_get']($data, 'format'), $title, $conversionRate);
    }

    /**
     * Updates system currency settings.
     *
     * @param string $code - currency ISO 4217 code
     *
     * @optional string $title - new currency title
     * @optional string $format - new currency format
     * @optional float $conversion_rate - new currency conversion rate
     *
     * @return bool
     *
     * @throws Exception
     */
    public function update($data)
    {
        $required = [
            'code' => 'Currency code is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $format = $this->di['array_get']($data, 'format');
        $title = $this->di['array_get']($data, 'title');
        $priceFormat = $this->di['array_get']($data, 'price_format');
        $conversionRate = $this->di['array_get']($data, 'conversion_rate');

        return $this->getService()->updateCurrency($data['code'], $format, $title, $priceFormat, $conversionRate);
    }

    /**
     * Gets the API key for currencylayer.
     *
     * @since 4.22.0
     *
     * @return string
     */
    public function get_key($data)
    {
        return $this->getService()->getKey();
    }

    /**
     * Updates the API key for currencylayer.
     *
     * @since 4.22.0
     *
     * @return bool
     */
    public function update_rate_settings($data)
    {
        $this->getService()->updateKey($this->di['array_get']($data, 'currencylayer_key'));

        if ($this->di['array_get']($data, 'crons_enabled') == '1') {
            $set = '1';
        } else {
            $set = '0';
        }

        $this->getService()->setCron($set);

        return true;
    }

    /**
     * See if CRON jobs are enabled for currency rates.
     *
     * @todo why does this even return a string instead of a boolean?
     *
     * @since 4.22.0
     *
     * @return string (0/1)
     */
    public function is_cron_enabled($data)
    {
        return $this->getService()->isCronEnabled();
    }

    /**
     * Automatically update all currency rates.
     *
     * @return bool
     */
    public function update_rates($data)
    {
        return $this->service->updateCurrencyRates($data);
    }

    /**
     * Remove currency. Default currency can not be removed.
     *
     * @param string $code - currency ISO 4217 code
     *
     * @return bool
     *
     * @throws Exception
     */
    public function delete($data)
    {
        $required = [
            'code' => 'Currency code is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->deleteCurrencyByCode($data['code']);
    }

    /**
     * Set default currency. If you have active orders or invoices
     * not recalculation on profits and refunds are made.
     *
     * @param string $code - currency ISO 4217 code
     *
     * @return bool
     *
     * @throws Exception
     */
    public function set_default($data)
    {
        $required = [
            'code' => 'Currency code is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->getService();
        $model = $service->getByCode($data['code']);
        if (!$model instanceof \Model_currency) {
            throw new \Box_Exception('Currency not found');
        }

        return $service->setAsDefault($model);
    }
}
