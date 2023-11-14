<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Currency;

use FOSSBilling\InjectionAwareInterface;
use Symfony\Component\HttpClient\HttpClient;

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
            'manage_settings' => []
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
            throw new \FOSSBilling\InformationException('Currency conversion rate can not be zero');
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

        $pairs = $db->getAssoc($sql);

        return $pairs;
    }

    /**
     * Returns a list of available currencies.
     *
     * @return array List of currencies in the "[short code] - [name]" format
     */
    public function getAvailableCurrencies()
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
            throw new \FOSSBilling\InformationException('Can not remove default currency');
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
     * Returns the API credentials for currencylayer.
     *
     * @todo maybe make this extensible so people can choose their data provider?
     *
     * @since 4.22.0
     *
     * @return string
     */
    public function getKey()
    {
        $sql = 'SELECT `param`, `value` FROM setting';
        $db = $this->di['db'];

        $pairs = $db->getAssoc($sql);

        return $pairs['currencylayer'] ?? '';
    }

    /**
     * Updates the API credentials for currencylayer.
     *
     * @todo maybe make this extensible so people can choose their data provider?
     *
     * @since 4.22.0
     */
    public function updateKey($key)
    {
        $sql = "INSERT INTO `setting` (`param`, `value`, `public`, `created_at`, `updated_at`) VALUES ('currencylayer', :key, '0', CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP()) ON DUPLICATE KEY UPDATE `value`=:key, `updated_at`=CURRENT_TIMESTAMP()";
        $values = [':key' => $key];

        $db = $this->di['db'];
        $db->exec($sql, $values);
    }

    /**
     * See if we should update exchange rates whenever the CRON jobs are run.
     *
     * @since 4.22.0
     *
     * @return bool
     */
    public function isCronEnabled()
    {
        $sql = 'SELECT `param`, `value` FROM setting';
        $db = $this->di['db'];

        $pairs = $db->getAssoc($sql);

        if (isset($pairs['currency_cron_enabled']) && $pairs['currency_cron_enabled'] == '1') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Enable or disable updating exchange rates whenever the CRON jobs are run.
     */
    public function setCron($data): void
    {
        $sql = "INSERT INTO `setting` (`param`, `value`, `public`, `created_at`, `updated_at`) VALUES ('currency_cron_enabled', :key, '0', CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP()) ON DUPLICATE KEY UPDATE `value`=:key, `updated_at`=CURRENT_TIMESTAMP()";

        if ($data == '1') {
            $key = '1';
        } else {
            $key = '0';
        }

        $values = [':key' => $key];

        $db = $this->di['db'];
        $db->exec($sql, $values);
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
                throw new \FOSSBilling\InformationException('Currency rate is invalid', null, 151);
            }
            $model->conversion_rate = $conversionRate;
        }

        $model->updated_at = date('Y-m-d H:i:s');
        $db->store($model);

        $this->di['logger']->info('Updated currency %s', $model->code);

        return true;
    }

    public function updateCurrencyRates($data)
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
     * Fetch exchange rates from external sources.
     * Uses data from the European Central Bank and currencylayer when the base currencies are Euro and US Dollar respectively.
     *
     * @todo use HTTPClient instead of simplexml_load_file()
     *
     * @param string $from Short code for the base currency
     * @param string $to   Short code for the target currency
     *
     * @return float Exchange rate
     */
    protected function _getRate($from, $to)
    {
        $from_Currency = urlencode($from);
        $to_Currency = urlencode($to);

        if ($from_Currency == 'EUR' && empty($this->getKey())) {
            $XML = simplexml_load_file('https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml');
            foreach ($XML->Cube->Cube->Cube as $rate) {
                if ($rate['currency'] == $to_Currency) {
                    return (float) $rate['rate'];
                }
            }

            throw new \FOSSBilling\Exception('Failed to get currency rates for :currency from the European Central Bank API', [':currency' => $to_Currency]);
        } else {
            $client = HttpClient::create();
            $response = $client->request('GET', 'https://api.apilayer.com/currency_data/live', [
                'query' => [
                    'currencies' => $to_Currency,
                    'source' => $from_Currency,
                ],
                'headers' => [
                    'Content-Type' => 'text/plain',
                    'apikey' => $this->getKey(),
                ],
            ]);
            $array = $response->toArray();

            if ($array['success'] !== true) {
                throw new \FOSSBilling\Exception('<b>Currencylayer threw an error:</b><br />:errorInfo', [':errorInfo' => $array['error']['info']]);
            } else {
                return (float) $array['quotes'][$from_Currency . $to_Currency];
            }
        }
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
     *
     * @since 4.22.0
     */
    public static function onBeforeAdminCronRun(\Box_Event $event)
    {
        $di = $event->getDi();
        $currencyService = $di['mod_service']('currency');

        try {
            if ($currencyService->isCronEnabled()) {
                $currencyService->updateCurrencyRates('');
            }
        } catch (\Exception $e) {
            error_log($e);
        }

        return true;
    }
}
