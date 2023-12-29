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
 *Currency management.
 */

namespace Box\Mod\Currency\Api;

class Guest extends \Api_Abstract
{
    /**
     * Get list of available currencies.
     *
     * @return array
     */
    public function get_pairs($data)
    {
        $service = $this->getService();

        return $service->getPairs();
    }

    /**
     * Get currency by code.
     *
     * @return array
     */
    public function get($data)
    {
        $service = $this->getService();
        if (isset($data['code']) && !empty($data['code'])) {
            $model = $service->getByCode($data['code']);
        } else {
            $model = $service->getDefault();
        }

        if (!$model instanceof \Model_Currency) {
            throw new \FOSSBilling\Exception('Currency not found');
        }

        return $service->toApiArray($model);
    }

    /**
     * Format price by currency settings.
     *
     * @optional bool $convert - covert to default currency rate. Default - true;
     * @optional bool $without_currency - Show only number. No symbols are attached Default - false;
     * @optional float $price - Price to be formatted. Default 0
     * @optional string $code - currency code, ie: USD. Default - default currency
     *
     * @return string - formatted string
     */
    public function format($data = [])
    {
        $c = $this->get($data);

        $price = $data['price'] ?? 0;
        $convert = $data['convert'] ?? true;
        $without_currency = (bool) ($data['without_currency'] ?? false);

        $p = floatval($price);
        if ($convert) {
            $p = $price * $c['conversion_rate'];
        }

        $p = $this->select_format($p, $c['price_format']);

        if ($without_currency) {
            return $p;
        }

        if ($p < 0) {
            // Price is negative, so we get a little creative to move the location of the negative symbol (ensuring -$5.00 instead of $-5.00)
            $c['format'] = '-' . $c['format'];
            $p = abs($p);
            // Reformat the price to ensure it's displayed correctly after getting it's absolute value
            $p = $this->select_format($p, $c['price_format']);
        }

        return str_replace('{{price}}', $p, $c['format']);
    }

    private function select_format($p, $format)
    {
        return match (intval($format)) {
            2 => number_format($p, 2, '.', ','),
            3 => number_format($p, 2, ',', '.'),
            4 => number_format($p, 0, '', ','),
            5 => number_format($p, 0, '', ''),
            default => number_format($p, 2, '.', ''),
        };
    }
}
