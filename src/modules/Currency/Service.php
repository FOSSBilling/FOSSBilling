<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Currency;

use FOSSBilling\InformationException;
use FOSSBilling\InjectionAwareInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\Cache\ItemInterface;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getModulePermissions(): array
    {
        return [
            'can_always_access' => true,
            'manage_settings' => [],
        ];
    }

    public function getSearchQuery()
    {
        $sql = 'SELECT * FROM currency WHERE 1';
        $filter = [];

        return [$sql, $filter];
    }

    /**
     * Convert foreign price back to default currency.
     */
    public function toBaseCurrency($foreign_code, $amount)
    {
        $default = $this->getDefault();

        if ($default->code == $foreign_code) {
            return $amount;
        }

        $rate = $this->getBaseCurrencyRate($foreign_code);

        return $amount * $rate;
    }

    public function getBaseCurrencyRate($foreign_code)
    {
        $f_rate = $this->getRateByCode($foreign_code);
        if ($f_rate == 0) {
            throw new InformationException('Currency conversion rate cannot be zero');
        }

        return 1 / $f_rate;
    }

    public function getCurrencyByClientId($client_id)
    {
        $sql = 'SELECT currency FROM client WHERE id = :client_id';
        $values = [':client_id' => $client_id];

        $db = $this->di['db'];
        $currency = $db->getCell($sql, $values);

        if ($currency === null) {
            return $this->getDefault();
        }

        $currency = $this->getByCode($currency);
        if ($currency instanceof \Model_Currency) {
            return $currency;
        }

        return $this->getDefault();
    }

    /**
     * @return \Model_Currency
     */
    public function getByCode($code)
    {
        return $this->di['db']->findOne('Currency', 'code = :code', [':code' => $code]);
    }

    public function getRateByCode($code)
    {
        $sql = 'SELECT conversion_rate FROM currency WHERE code = :code';
        $values = [':code' => $code];

        $db = $this->di['db'];
        $rate = $db->getCell($sql, $values);

        return is_numeric($rate) ? $rate : 1;
    }

    public function getDefault()
    {
        $db = $this->di['db'];
        $default = $db->findOne('Currency', 'is_default = 1');

        if (is_array($default) && count($default) == 0) {
            $default = $db->load('Currency', '1');
        }

        return $default;
    }

    public function setAsDefault(\Model_Currency $currency)
    {
        $db = $this->di['db'];

        if ($currency->is_default) {
            return true;
        }

        if ($currency->code === null || empty($currency->code)) {
            throw new \FOSSBilling\Exception('Currency code not provided');
        }

        $sql1 = 'UPDATE currency SET is_default = 0 WHERE 1';
        $sql2 = 'UPDATE currency SET is_default = 1 WHERE code = :code';
        $values2 = [':code' => $currency->code];

        $db->exec($sql1);
        $db->exec($sql2, $values2);

        $this->di['logger']->info('Set currency %s as default', $currency->code);

        return true;
    }

    public function getPairs()
    {
        $sql = 'SELECT code, title FROM currency';
        $db = $this->di['db'];

        return $db->getAssoc($sql);
    }

    /**
     * Returns a list of available currencies.
     *
     * @return array List of currencies in the "[short code] - [name]" format
     */
    public function getAvailableCurrencies(): array
    {
        $options = [
            'AED' => 'United Arab Emirates dirham',
            'AFN' => 'Afghan afghani',
            'ALL' => 'Albanian lek',
            'AMD' => 'Armenian dram',
            'ANG' => 'Netherlands Antillean guilder',
            'AOA' => 'Angolan kwanza',
            'ARS' => 'Argentine peso',
            'AUD' => 'Australian dollar',
            'AWG' => 'Aruban florin',
            'AZN' => 'Azerbaijani manat',
            'BAM' => 'Bosnia and Herzegovina convertible mark',
            'BBD' => 'Barbados dollar',
            'BDT' => 'Bangladeshi taka',
            'BGN' => 'Bulgarian lev',
            'BHD' => 'Bahraini dinar',
            'BIF' => 'Burundian franc',
            'BMD' => 'Bermudian dollar',
            'BND' => 'Brunei dollar',
            'BOB' => 'Boliviano',
            'BRL' => 'Brazilian real',
            'BSD' => 'Bahamian dollar',
            'BTN' => 'Bhutanese ngultrum',
            'BWP' => 'Botswana pula',
            'BYR' => 'Belarusian ruble',
            'BZD' => 'Belize dollar',
            'CAD' => 'Canadian dollar',
            'CDF' => 'Congolese franc',
            'CHF' => 'Swiss franc',
            'CLP' => 'Chilean peso',
            'CNY' => 'Chinese yuan',
            'COP' => 'Colombian peso',
            'COU' => 'Unidad de Valor Real',
            'CRC' => 'Costa Rican colon',
            'CUC' => 'Cuban convertible peso',
            'CUP' => 'Cuban peso',
            'CVE' => 'Cape Verde escudo',
            'CZK' => 'Czech koruna',
            'DJF' => 'Djiboutian franc',
            'DKK' => 'Danish krone',
            'DOP' => 'Dominican peso',
            'DZD' => 'Algerian dinar',
            'EGP' => 'Egyptian pound',
            'ERN' => 'Eritrean nakfa',
            'ETB' => 'Ethiopian birr',
            'EUR' => 'Euro',
            'FJD' => 'Fiji dollar',
            'FKP' => 'Falkland Islands pound',
            'GBP' => 'Pound sterling',
            'GEL' => 'Georgian lari',
            'GHS' => 'Ghanaian cedi',
            'GIP' => 'Gibraltar pound',
            'GMD' => 'Gambian dalasi',
            'GNF' => 'Guinean franc',
            'GTQ' => 'Guatemalan quetzal',
            'GYD' => 'Guyanese dollar',
            'HKD' => 'Hong Kong dollar',
            'HNL' => 'Honduran lempira',
            'HRK' => 'Croatian kuna',
            'HTG' => 'Haitian gourde',
            'HUF' => 'Hungarian forint',
            'IDR' => 'Indonesian rupiah',
            'ILS' => 'Israeli new sheqel',
            'INR' => 'Indian rupee',
            'IQD' => 'Iraqi dinar',
            'IRR' => 'Iranian rial',
            'ISK' => 'Icelandic króna',
            'JMD' => 'Jamaican dollar',
            'JOD' => 'Jordanian dinar',
            'JPY' => 'Japanese yen',
            'KES' => 'Kenyan shilling',
            'KGS' => 'Kyrgyzstani som',
            'KHR' => 'Cambodian riel',
            'KMF' => 'Comoro franc',
            'KPW' => 'North Korean won',
            'KRW' => 'South Korean won',
            'KWD' => 'Kuwaiti dinar',
            'KYD' => 'Cayman Islands dollar',
            'KZT' => 'Kazakhstani tenge',
            'LAK' => 'Lao kip',
            'LBP' => 'Lebanese pound',
            'LKR' => 'Sri Lanka rupee',
            'LRD' => 'Liberian dollar',
            'LSL' => 'Lesotho loti',
            'LYD' => 'Libyan dinar',
            'MAD' => 'Moroccan dirham',
            'MDL' => 'Moldovan leu',
            'MGA' => 'Malagasy ariary',
            'MKD' => 'Macedonian denar',
            'MMK' => 'Myanma kyat',
            'MNT' => 'Mongolian tugrik',
            'MOP' => 'Macanese pataca',
            'MRO' => 'Mauritanian ouguiya',
            'MUR' => 'Mauritian rupee',
            'MVR' => 'Maldivian rufiyaa',
            'MWK' => 'Malawian kwacha',
            'MXN' => 'Mexican peso',
            'MYR' => 'Malaysian ringgit',
            'MZN' => 'Mozambican metical',
            'NAD' => 'Namibian dollar',
            'NGN' => 'Nigerian naira',
            'NIO' => 'Cordoba oro',
            'NOK' => 'Norwegian krone',
            'NPR' => 'Nepalese rupee',
            'NZD' => 'New Zealand dollar',
            'OMR' => 'Omani rial',
            'PAB' => 'Panamanian balboa',
            'PEN' => 'Peruvian nuevo sol',
            'PGK' => 'Papua New Guinean kina',
            'PHP' => 'Philippine peso',
            'PKR' => 'Pakistani rupee',
            'PLN' => 'Polish złoty',
            'PYG' => 'Paraguayan guaraní',
            'QAR' => 'Qatari rial',
            'RON' => 'Romanian new leu',
            'RSD' => 'Serbian dinar',
            'RUB' => 'Russian rouble',
            'RWF' => 'Rwandan franc',
            'SAR' => 'Saudi riyal',
            'SBD' => 'Solomon Islands dollar',
            'SCR' => 'Seychelles rupee',
            'SDG' => 'Sudanese pound',
            'SEK' => 'Swedish krona/kronor',
            'SGD' => 'Singapore dollar',
            'SHP' => 'Saint Helena pound',
            'SLL' => 'Sierra Leonean leone',
            'SOS' => 'Somali shilling',
            'SRD' => 'Surinamese dollar',
            'STD' => 'São Tomé and Príncipe dobra',
            'SYP' => 'Syrian pound',
            'SZL' => 'Lilangeni',
            'THB' => 'Thai baht',
            'TJS' => 'Tajikistani somoni',
            'TMT' => 'Turkmenistani manat',
            'TND' => 'Tunisian dinar',
            'TOP' => 'Tongan paʻanga',
            'TRY' => 'Turkish lira',
            'TTD' => 'Trinidad and Tobago dollar',
            'TWD' => 'New Taiwan dollar',
            'TZS' => 'Tanzanian shilling',
            'UAH' => 'Ukrainian hryvnia',
            'UGX' => 'Ugandan shilling',
            'USD' => 'United States dollar',
            'UYU' => 'Uruguayan peso',
            'UZS' => 'Uzbekistan som',
            'VEF' => 'Venezuelan bolívar fuerte',
            'VND' => 'Vietnamese đồng',
            'VUV' => 'Vanuatu vatu',
            'WST' => 'Samoan tala',
            'XOF' => 'West African CFA franc',
            'YER' => 'Yemeni rial',
            'ZAR' => 'South African rand',
            'ZMK' => 'Zambian kwacha',
            'ZWL' => 'Zimbabwe dollar',
        ];

        foreach ($options as $key => &$name) {
            $name = $key . ' - ' . $name;
        }

        return $options;
    }

    public function rm(\Model_Currency $model)
    {
        if ($model->is_default) {
            throw new InformationException('Cannot remove default currency');
        }

        if ($model->code === null || empty($model->code)) {
            throw new \FOSSBilling\Exception('Currency not found');
        }

        $sql = 'DELETE FROM currency WHERE code = :code';
        $values = [':code' => $model->code];

        $db = $this->di['db'];
        $db->exec($sql, $values);
    }

    /**
     * See if we should update exchange rates whenever the CRON jobs are run.
     */
    public function isCronEnabled(): bool
    {
        $config = $this->di['mod_config']('currency');

        return ($config['sync_rate'] ?? 'auto') !== 'never';
    }

    public function toApiArray(\Model_Currency $model)
    {
        return [
            'code' => $model->code,
            'title' => $model->title,
            'conversion_rate' => (float) $model->conversion_rate,
            'format' => $model->format,
            'price_format' => $model->price_format,
            'default' => $model->is_default,
        ];
    }

    public function createCurrency($code, $format, $title = null, $conversionRate = 1)
    {
        $systemService = $this->di['mod_service']('system');
        $systemService->checkLimits('Model_Currency', 2);

        $this->validateCurrencyFormat($format);

        $model = $this->di['db']->dispense('Currency');
        $model->code = $code;
        $model->title = $title;
        $model->format = $format;
        $model->conversion_rate = $conversionRate;
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Added new currency %s', $model->code);

        return $model->code;
    }

    public function validateCurrencyFormat($format)
    {
        if (!str_contains($format, '{{price}}')) {
            throw new \Exception('Currency format must include {{price}} tag', 3569);
        }
    }

    public function updateCurrency($code, $format = null, $title = null, $priceFormat = null, $conversionRate = null)
    {
        $db = $this->di['db'];

        $model = $this->getByCode($code);
        if (!$model instanceof \Model_Currency) {
            throw new \FOSSBilling\Exception('Currency not found');
        }

        if (isset($title)) {
            $model->title = $title;
        }

        if (isset($format)) {
            $this->validateCurrencyFormat($format);
            $model->format = $format;
        }

        if (isset($priceFormat)) {
            $model->price_format = $priceFormat;
        }

        if (isset($conversionRate)) {
            if (!is_numeric($conversionRate) || $conversionRate <= 0) {
                throw new InformationException('Currency rate is invalid', null, 151);
            }
            $model->conversion_rate = $conversionRate;
        }

        $model->updated_at = date('Y-m-d H:i:s');
        $db->store($model);

        $this->di['logger']->info('Updated currency %s', $model->code);

        return true;
    }

    public function updateCurrencyRates()
    {
        $dc = $this->getDefault();

        $db = $this->di['db'];

        $all = $db->find('Currency'); // should return Array of beans

        foreach ($all as $currency) {
            if ($currency->is_default) {
                $rate = 1;
            } else {
                $rate = $this->_getRate($dc->code, $currency->code);
            }

            if (!is_numeric($rate)) {
                continue;
            }

            $currency->conversion_rate = $rate;
            $db->store($currency);
        }

        $this->di['logger']->info('Updated currency rates');

        return true;
    }

    /**
     * Gives a conversion rate between two currencies.
     * Handles selecting the right function to query the data sources & passing the correct parameters.
     */
    protected function _getRate(string $from, string $to): float|false
    {
        $config = $this->di['mod_config']('currency');
        $validFor = match ($config['sync_rate'] ?? 'auto') {
            '1h' => 3600,
            '10m' => 600,
            '5m' => 300,
            '1m' => 60,
            'never' => 0,
            default => 86_400, // Intentionally matches '1d', 'auto', and anything else
        };

        $provider = $config['provider'] ?? '';

        if ($provider === 'currency_data_api') {
            if (empty($config['currencydata_key'])) {
                throw new InformationException('You must configure your API key to use Currency Data API as an exchange rate data source.');
            }
            $rates = $this->getCurrencyDataRates($from, $validFor, $config['currencydata_key']);
        } elseif ($provider === 'currencylayer') {
            if (empty($config['currencylayer_key'])) {
                throw new InformationException('You must configure your API key to use currencylayer as an exchange rate data source.');
            }
            $rates = $this->getCurrencyLayerRates($from, $validFor, $config['currencylayer_key']);
        } else {
            $key = $config['exchangerate_api_key'] ?? ''; // No key is OK here, we will just use the open API
            if ($config['sync_rate'] ?? 'auto' === 'auto') {
                $rates = $this->getExchangeRateAPIRates($from, 0, $key);
            } else {
                $rates = $this->getExchangeRateAPIRates($from, $validFor, $key);
            }
        }

        if (isset($rates[$to]) && is_numeric($rates[$to])) {
            return floatval($rates[$to]);
        } else {
            return false;
        }
    }

    /**
     * Gets the rates from https://www.exchangerate-api.com.
     * Handles both their open API endpoint as well as the authenticated ones.
     * Implements smart caching using their API provided next update time and will also alert us if the open endpoint goes EOL.
     */
    protected function getExchangeRateAPIRates(string $from, int $validFor, string $key): array
    {
        $result = $this->di['cache']->get("exchangerate.api.$from.$key.$validFor", function (ItemInterface $item) use ($from, $validFor, $key): array {
            $from_currency = urlencode($from);

            if (!empty($key)) {
                $key = urlencode($key);
                $requestUrl = "https://v6.exchangerate-api.com/v6/$key/latest/$from_currency";
            } else {
                $requestUrl = "https://open.er-api.com/v6/latest/$from_currency";
            }

            $client = HttpClient::create(['bindto' => BIND_TO]);
            $response = $client->request('GET', $requestUrl);
            $array = $response->toArray();

            if ($array['result'] !== 'success') {
                $item->expiresAfter(15 * 60 * 60); // Try again in 15 min
                error_log('ExchangeRate-API Gave an error: ' . $array['error-type']);

                throw new \FOSSBilling\Exception('There was an error when fetching currency rates from ExchangeRate-API. See the error log for details.');
            }

            if ($validFor === 0) {
                // ExchangeRate-API is great and will tell us exactly when the data will next have an update, so we will use that for the cache expiration when "auto" is the sync mode.
                $item->expiresAt(new \DateTime($array['time_next_update_utc']));
            } else {
                $item->expiresAfter($validFor);
            }

            return $array;
        });

        // Their open access API endpoint has a specific param to inform of if it ever goes EOL, so let's monitor that and trigger an error to alert us if it's deprecated
        if (array_key_exists('time_eol_unix', $result) && $result['time_eol_unix'] !== 0) {
            trigger_error('ExchangeRate-API has deprecated their open endpoint. Investigate!', E_USER_DEPRECATED); // Should be sent via error reporting, making monitoring this easy
        }

        // Different array key between the open and authenticated endpoint, but otherwise it's the same structure.
        if (!empty($key)) {
            return $result['conversion_rates'] ?? [];
        } else {
            return $result['rates'] ?? [];
        }
    }

    /**
     * Gets the rates from https://apilayer.com/marketplace/currency_data-api.
     * Fetches a complete list off currencies and then caches that result for the specified period.
     * Normalizes the return array.
     */
    protected function getCurrencyDataRates(string $from, int $validFor, string $key)
    {
        $result = $this->di['cache']->get("currency.data.api.$from.$key.$validFor", function (ItemInterface $item) use ($from, $validFor, $key): array {
            $item->expiresAfter($validFor);

            $from_currency = urlencode($from);

            $client = HttpClient::create(['bindto' => BIND_TO]);
            $response = $client->request('GET', 'https://api.apilayer.com/currency_data/live', [
                'query' => [
                    'source' => $from_currency,
                ],
                'headers' => [
                    'Content-Type' => 'text/plain',
                    'apikey' => $key,
                ],
            ]);
            $array = $response->toArray();

            if ($array['success'] !== true) {
                error_log($array['error']['info']);

                throw new \FOSSBilling\Exception('There was an error when fetching currency rates from Currency Data API. See the error log for details.');
            }

            return $array;
        });

        return $this->processApiLayerFormat($result, $from);
    }

    /**
     * Gets the rates from https://currencylayer.com/.
     * Fetches a complete list off currencies and then caches that result for the specified period.
     * Normalizes the return array.
     */
    protected function getCurrencyLayerRates(string $from, int $validFor, string $key)
    {
        $result = $this->di['cache']->get("currencylayer.$from.$key.$validFor", function (ItemInterface $item) use ($from, $validFor, $key): array {
            $item->expiresAfter($validFor);

            $from_currency = urlencode($from);

            $client = HttpClient::create(['bindto' => BIND_TO]);
            $response = $client->request('GET', 'https://api.apilayer.com/currency_data/live', [
                'query' => [
                    'access_key' => $key,
                    'source' => $from_currency,
                ],
            ]);
            $array = $response->toArray();

            if ($array['success'] !== true) {
                error_log($array['error']['info']);

                throw new \FOSSBilling\Exception('There was an error when fetching currency rates from currencylayer. See the error log for details.');
            }

            return $array;
        });

        return $this->processApiLayerFormat($result, $from);
    }

    /**
     * Normalizes the response from Currency Data API / currencylayer.
     */
    private function processApiLayerFormat(array $result, string $from): array
    {
        $rates = [];
        $prefixLen = strlen($from);
        foreach ($result['quotes'] as $key => $rate) {
            if (!is_numeric($rate)) {
                continue;
            }
            // All values are prefixed with our 'from' currency (EX: 'USDAUD'), so strip that off before storing it.
            $strippedName = substr($key, $prefixLen);
            $rates[$strippedName] = $rate;
        }

        return $rates;
    }

    public function deleteCurrencyByCode($code)
    {
        $model = $this->getByCode($code);

        if (!$model instanceof \Model_Currency) {
            throw new \FOSSBilling\Exception('Currency not found');
        }
        $code = $model->code;

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminDeleteCurrency', 'params' => ['code' => $code]]);

        $this->rm($model);

        $this->di['events_manager']->fire(['event' => 'onAfterAdminDeleteCurrency', 'params' => ['code' => $code]]);

        $this->di['logger']->info('Removed currency %s', $code);

        return true;
    }

    /**
     * If enabled, automatically call _getRate to fetch exchange rates whenever CRON jobs are run.
     */
    public static function onBeforeAdminCronRun(\Box_Event $event)
    {
        $di = $event->getDi();
        $currencyService = $di['mod_service']('currency');

        try {
            if ($currencyService->isCronEnabled()) {
                $currencyService->updateCurrencyRates();
            }
        } catch (\Exception $e) {
            error_log($e);
        }

        return true;
    }
}
