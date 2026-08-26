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
use FOSSBilling\I18n\I18n;
use FOSSBilling\Tools;

class Guest extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Get a list of available currencies.
     */
    public function get_pairs(): array
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
            throw new \FOSSBilling\Exception\BaseException('Currency not found.');
        }

        return $model->toApiArray();
    }

    /**
     * Format price by currency code.
     *
     * @optional bool $convert          - convert to default currency rate. Default - true
     * @optional bool $without_currency - show only number, no symbols. Default - false
     * @optional float $price           - price to be formatted. Default 0
     * @optional string $code           - currency code, e.g. USD. Default - default currency
     *
     * @return string - formatted string
     */
    public function format(array $data): string
    {
        $c = $this->get($data);

        $price = $data['price'] ?? 0;
        $convert = \FOSSBilling\Utils\Normalizer::normalizeBoolean($data['convert'] ?? true, true);
        $withoutCurrency = \FOSSBilling\Utils\Normalizer::normalizeBoolean($data['without_currency'] ?? false);

        $p = floatval($price);
        if ($convert) {
            $p = $price * $c['conversion_rate'];
        }

        $di = $this->getDi();
        $locale = I18n::getActiveLocale($di['request'], true, $di['cookie_queue']);

        if ($withoutCurrency) {
            return $this->getService()->formatNumber($p, $c['code'], locale: $locale);
        }

        return $this->getService()->formatCurrency($p, $c['code'], locale: $locale);
    }
}
