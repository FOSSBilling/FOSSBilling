<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Currency\Api;

use Box\Mod\Currency\Entity\Currency;

class Admin extends \Api_Abstract
{
    /**
     * Get a list of available currencies on the system.
     *
     * @param array $data Filtering and pagination parameters
     * @return array Paginated list of currencies
     */
    public function get_list(array $data): array
    {
        /** @var \Box\Mod\Currency\Repository\CurrencyRepository $repo */
        $repo = $this->getService()->getCurrencyRepository();

        $qb = $repo->getSearchQueryBuilder($data);

        return $this->di['pager']->paginateDoctrineQuery($qb);
    }

    /**
     * Get list of available currencies on system as key-value pairs.
     *
     * @return array<string, string> Array of currency code => formatted currency display name pairs (e.g., 'USD' => 'USD - United States dollar')
     */
    public function get_pairs(): array
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
    public function get(array $data): array
    {
        $required = [
            'code' => 'Currency code is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        /** @var \Box\Mod\Currency\Repository\CurrencyRepository $repo */
        $repo = $this->getService()->getCurrencyRepository();

        $model = $repo->findOneByCode($data['code']);

        if (!$model instanceof Currency) {
            throw new \FOSSBilling\Exception('Currency not found');
        }

        return $model->toApiArray();
    }

    /**
     * Return default system currency.
     *
     * @return array
     */
    public function get_default(array $data): array
    {
        /** @var \Box\Mod\Currency\Repository\CurrencyRepository $repo */
        $repo = $this->getService()->getCurrencyRepository();

        $default = $repo->findDefault();

        if (!$default instanceof Currency) {
            throw new \FOSSBilling\Exception('Default currency not found');
        }
        return $default->toApiArray();
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
    public function create(array $data): string
    {
        $required = [
            'code' => 'Currency code is missing',
            'format' => 'Currency format is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->getService();

        /** @var \Box\Mod\Currency\Repository\CurrencyRepository $repo */
        $repo = $service->getCurrencyRepository();

        if ($repo->findOneByCode($data['code'] ?? null)) {
            throw new \FOSSBilling\Exception('Currency already registered');
        }

        if (!array_key_exists($data['code'] ?? null, $service->getAvailableCurrencies())) {
            throw new \FOSSBilling\Exception('Currency code is invalid');
        }

        $title = $data['title'] ?? null;
        $conversionRate = $data['conversion_rate'] ?? null;

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
    public function update(array $data): bool
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
    public function is_cron_enabled(array $data): bool
    {
        return $this->getService()->isCronEnabled();
    }

    /**
     * Automatically update all currency rates.
     *
     * @return bool
     */
    public function update_rates(array $data): bool
    {
        return $this->service->updateCurrencyRates();
    }

    /**
     * Remove a currency. Default currency cannot be removed.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function delete(array $data): bool
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
    public function set_default(array $data): bool
    {
        $required = [
            'code' => 'Currency code is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->getService();

        /** @var \Box\Mod\Currency\Repository\CurrencyRepository $repo */
        $repo = $service->getCurrencyRepository();

        $model = $repo->findOneByCode($data['code']);
        if (!$model instanceof Currency) {
            throw new \FOSSBilling\Exception('Currency not found');
        }

        return $service->setAsDefault($model);
    }
}
