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
use FOSSBilling\Core\Pagination\Options;
use FOSSBilling\Core\Validation\Api\RequiredParams;
use Symfony\Component\Intl\Currencies;

class Admin extends \FOSSBilling\Core\Api\AbstractApi
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

        return $this->getDi()['pager']->paginateDoctrineQuery($qb, Options::fromArray($data));
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
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['code' => 'Currency code is missing'])]
    public function get($data): array
    {
        $this->checkPermissions('currency', 'view');

        /** @var \Box\Mod\Currency\Repository\CurrencyRepository $repo */
        $repo = $this->getService()->getCurrencyRepository();

        $model = $repo->findOneByCode($data['code']);

        if (!$model instanceof Currency) {
            throw new \FOSSBilling\Core\Exception\BaseException('Currency not found.');
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
            throw new \FOSSBilling\Core\Exception\BaseException('Default currency not found');
        }

        return $default->toApiArray();
    }

    /**
     * Add new currency to system.
     *
     * @return string - currency code
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['code' => 'Currency code is missing'])]
    public function create($data = []): string
    {
        $this->checkPermissions('currency', 'create');

        $service = $this->getService();

        /** @var \Box\Mod\Currency\Repository\CurrencyRepository $repo */
        $repo = $service->getCurrencyRepository();

        if ($repo->findOneByCode($data['code'] ?? null)) {
            throw new \FOSSBilling\Core\Exception\BaseException('Currency already registered.');
        }

        if (!Currencies::exists($data['code'] ?? null)) {
            throw new \FOSSBilling\Core\Exception\BaseException('Currency code is invalid.');
        }

        $conversionRate = $data['conversion_rate'] ?? null;
        $isRateManual = \FOSSBilling\Core\Utils\Normalizer::normalizeBoolean($data['is_rate_manual'] ?? false);

        return $service->createCurrency($data['code'] ?? null, $conversionRate, $isRateManual);
    }

    /**
     * Updates system currency settings.
     *
     * @optional float $conversion_rate - new currency conversion rate
     * @optional bool $is_rate_manual - preserve the conversion rate during automatic and manual bulk synchronization
     * @optional string $format_pattern - plain-text display pattern containing one {amount} placeholder
     * @optional int $fraction_digits - fraction digit override from 0 to 6, blank to use the ISO default
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['code' => 'Currency code is missing'])]
    public function update($data): bool
    {
        $this->checkPermissions('currency', 'edit');

        $conversionRate = $data['conversion_rate'] ?? null;
        $isRateManual = array_key_exists('is_rate_manual', $data)
            ? \FOSSBilling\Core\Utils\Normalizer::normalizeBoolean($data['is_rate_manual'])
            : null;
        $formatting = array_intersect_key($data, [
            'format_pattern' => true,
            'fraction_digits' => true,
        ]);

        return $this->getService()->updateCurrency(
            $data['code'],
            $conversionRate,
            $formatting,
            $isRateManual,
        );
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
     * @throws \FOSSBilling\Core\Exception\BaseException
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
     * @throws \FOSSBilling\Core\Exception\BaseException
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
            throw new \FOSSBilling\Core\Exception\BaseException('Currency not found.');
        }

        return $service->setAsDefault($model);
    }
}
