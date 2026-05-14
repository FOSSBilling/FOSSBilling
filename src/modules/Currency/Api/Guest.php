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
use FOSSBilling\Config;
use FOSSBilling\Tools;
use FOSSBilling\Validation\Api\RequiredParams;
use NumberFormatter;
use PrinsFrank\Standards\Currency\CurrencyAlpha3;

class Guest extends \Api_Abstract
{
    /**
     * Get a list of available currencies.
     */
    public function get_pairs(array $data): array
    {
        /** @var \Box\Mod\Currency\Repository\CurrencyRepository $repo */
        $repo = $this->getService()->getCurrencyRepository();

        return $repo->getPairs();
    }

    /**
     * Get a currency by code.
     */
    public function get(array $data): array
    {
        /** @var \Box\Mod\Currency\Repository\CurrencyRepository $repo */
        $repo = $this->getService()->getCurrencyRepository();

        if (isset($data['code']) && !empty($data['code'])) {
            $model = $repo->findOneByCode($data['code']);
        } else {
            $model = $repo->findDefault();
        }

        if (!$model instanceof Currency) {
            throw new \FOSSBilling\Exception('Currency not found');
        }

        return $model->toApiArray();
    }

    /**
     * Gets the ISO defaults for a given currency code.
     */
    #[RequiredParams(['code' => 'Currency code not provided'])]
    public function get_currency_defaults(array $data): array
    {
        return $this->getService()->getCurrencyDefaults($data['code']);
    }

    /**
     * Format price by currency code.
     *
     * @optional bool $convert - convert to default currency rate. Default - true;
     * @optional bool $without_currency - Show only number. No symbols are attached Default - false;
     * @optional float $price - Price to be formatted. Default 0
     * @optional string $code - currency code, ie: USD. Default - default currency
     *
     * @return string - formatted string
     */
    public function format(array $data): string
    {
        $c = $this->get($data);

        $price = $data['price'] ?? 0;
        $convert = Tools::normalizeBoolean($data['convert'] ?? true, true);
        $without_currency = Tools::normalizeBoolean($data['without_currency'] ?? false);

        $p = floatval($price);
        if ($convert) {
            $p = $price * $c['conversion_rate'];
        }

        if ($without_currency) {
            return $this->formatNumber($p, $c['code']);
        }

        return $this->formatCurrency($p, $c['code']);
    }

    private function formatCurrency(float $amount, string $currencyCode): string
    {
        $formatter = new NumberFormatter($this->getLocale(), NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($amount, $currencyCode);
    }

    private function formatNumber(float $amount, string $currencyCode): string
    {
        $formatter = new NumberFormatter($this->getLocale(), NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $this->getMinorUnits($currencyCode));

        return $formatter->format($amount);
    }

    private function getMinorUnits(string $currencyCode): int
    {
        try {
            return CurrencyAlpha3::from($currencyCode)->getMinorUnits() ?? 2;
        } catch (\ValueError) {
            return 2;
        }
    }

    private function getLocale(): string
    {
        return Config::getProperty('i18n.locale', 'en_US');
    }
}
