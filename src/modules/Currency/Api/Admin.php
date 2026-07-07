<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Currency\Api;

use Box\Mod\Currency\Entity\Currency;
use FOSSBilling\PaginationOptions;
use FOSSBilling\Validation\Api\RequiredParams;
use Symfony\Component\Intl\Currencies;

class Admin extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Get a list of available currencies on the system.
     *
     * @param array $data Filtering and pagination parameters
     *
     * @return array Paginated list of currencies
     */
    public function get_list(array $data): array
    {
        $this->checkPermissions('currency', 'view');

        /** @var \Box\Mod\Currency\Repository\CurrencyRepository $repo */
        $repo = $this->getService()->getCurrencyRepository();

        $qb = $repo->getSearchQueryBuilder($data);

        return $this->getDi()['pager']->paginateDoctrineQuery($qb, PaginationOptions::fromArray($data));
    }

    /**
     * Get list of available currencies on system as key-value pairs.
     *
     * @return array<string, string> Array of currency code => formatted currency display name pairs (e.g., 'USD' => 'USD (US Dollar)')
     */
    public function get_pairs(): array
    {
        $this->checkPermissions('currency', 'view');

        $currencies = Currencies::getNames();
        foreach ($currencies as $currencyCode => $currencyName) {
            /** @var string $currencyCode */
            if (!Currencies::isValidInAnyCountry($currencyCode)) {
                unset($currencies[$currencyCode]);
            } else {
                $currencies[$currencyCode] = sprintf('%s (%s)', $currencyCode, $currencyName);
            }
        }

        return $currencies;
    }

    /**
     * Return currency details by cde.
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['code' => 'Currency code is missing'])]
    public function get($data): array
    {
        $this->checkPermissions('currency', 'view');

        /** @var \Box\Mod\Currency\Repository\CurrencyRepository $repo */
        $repo = $this->getService()->getCurrencyRepository();

        $model = $repo->findOneByCode($data['code']);

        if (!$model instanceof Currency) {
            throw new \FOSSBilling\Exception('Currency not found.');
        }

        return $model->toApiArray();
    }

    /**
     * Return default system currency.
     */
    public function get_default(): array
    {
        $this->checkPermissions('currency', 'view');

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
     * @return string - currency code
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['code' => 'Currency code is missing'])]
    public function create($data = []): string
    {
        $this->checkPermissions('currency', 'create');

        $service = $this->getService();

        /** @var \Box\Mod\Currency\Repository\CurrencyRepository $repo */
        $repo = $service->getCurrencyRepository();

        if ($repo->findOneByCode($data['code'] ?? null)) {
            throw new \FOSSBilling\Exception('Currency already registered.');
        }

        if (!Currencies::exists($data['code'] ?? null)) {
            throw new \FOSSBilling\Exception('Currency code is invalid.');
        }

        $conversionRate = $data['conversion_rate'] ?? null;

        return $service->createCurrency($data['code'] ?? null, $conversionRate);
    }

    /**
     * Updates system currency settings.
     *
     * @optional float $conversion_rate - new currency conversion rate
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['code' => 'Currency code is missing'])]
    public function update($data): bool
    {
        $this->checkPermissions('currency', 'edit');

        $conversionRate = $data['conversion_rate'] ?? null;

        return $this->getService()->updateCurrency($data['code'], $conversionRate);
    }

    /**
     * See if CRON jobs are enabled for currency rates.
     */
    public function is_cron_enabled(): bool
    {
        $this->checkPermissions('currency', 'view');

        return $this->getService()->isCronEnabled();
    }

    /**
     * Automatically update all currency rates.
     */
    public function update_rates(): bool
    {
        $this->checkPermissions('currency', 'update_rates');

        return $this->service->updateCurrencyRates();
    }

    /**
     * Remove a currency. Default currency cannot be removed.
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['code' => 'Currency code is missing'])]
    public function delete($data): bool
    {
        $this->checkPermissions('currency', 'delete');

        return $this->getService()->removeCurrency($data['code']);
    }

    /**
     * Set default currency. If you have active orders or invoices
     * not recalculation on profits and refunds are made.
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['code' => 'Currency code is missing'])]
    public function set_default($data): bool
    {
        $this->checkPermissions('currency', 'set_default');

        $service = $this->getService();

        /** @var \Box\Mod\Currency\Repository\CurrencyRepository $repo */
        $repo = $service->getCurrencyRepository();

        $model = $repo->findOneByCode($data['code']);
        if (!$model instanceof Currency) {
            throw new \FOSSBilling\Exception('Currency not found.');
        }

        return $service->setAsDefault($model);
    }
}
