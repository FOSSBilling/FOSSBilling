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
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
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
     * @return array
     *
     * @throws \FOSSBilling\Exception
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
            throw new \FOSSBilling\Exception('Currency not found');
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
     * @optional string $title - custom currency title
     *
     * @return string - currency code
     *
     * @throws \FOSSBilling\Exception
     */
    public function create($data = [])
    {
        $required = [
            'code' => 'Currency code is missing',
            'format' => 'Currency format is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->getService();

        if ($service->getByCode($data['code'] ?? null)) {
            throw new \FOSSBilling\Exception('Currency already registered');
        }

        if (!array_key_exists($data['code'] ?? null, $service->getAvailableCurrencies())) {
            throw new \FOSSBilling\Exception('Currency code is invalid');
        }

        $title = $data['title'] ?? null;
        $conversionRate = $data['conversion_rate'] ?? 1;

        return $service->createCurrency($data['code'] ?? null, $data['format'] ?? null, $title, $conversionRate);
    }

    /**
     * Updates system currency settings.
     *
     * @optional string $title - new currency title
     * @optional string $format - new currency format
     * @optional float $conversion_rate - new currency conversion rate
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function update($data)
    {
        $required = [
            'code' => 'Currency code is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $format = $data['format'] ?? null;
        $title = $data['title'] ?? null;
        $priceFormat = $data['price_format'] ?? null;
        $conversionRate = $data['conversion_rate'] ?? null;

        return $this->getService()->updateCurrency($data['code'], $format, $title, $priceFormat, $conversionRate);
    }

    /**
     * See if CRON jobs are enabled for currency rates.
     */
    public function is_cron_enabled($data): bool
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
     * Remove currency. Default currency cannot be removed.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
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
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function set_default($data)
    {
        $required = [
            'code' => 'Currency code is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->getService();
        $model = $service->getByCode($data['code']);
        if (!$model instanceof \Model_Currency) {
            throw new \FOSSBilling\Exception('Currency not found');
        }

        return $service->setAsDefault($model);
    }
}
