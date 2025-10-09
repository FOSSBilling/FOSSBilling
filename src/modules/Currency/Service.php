<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Currency;

use Box\Mod\Currency\Entity\Currency;
use Box\Mod\Currency\Repository\CurrencyRepository;
use FOSSBilling\InformationException;
use FOSSBilling\InjectionAwareInterface;
use PrinsFrank\Standards\Currency\CurrencyAlpha3;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\Cache\ItemInterface;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    protected CurrencyRepository $currencyRepository;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        $this->currencyRepository = $this->di['em']->getRepository(Currency::class);
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getCurrencyRepository(): CurrencyRepository
    {
        return $this->currencyRepository;
    }

    public function getModulePermissions(): array
    {
        return [
            'can_always_access' => true,
            'manage_settings' => [],
        ];
    }

    /**
     * Convert foreign price back to default currency.
     */
    public function toBaseCurrency($foreign_code, $amount)
    {
        $default = $this->currencyRepository->findDefault();

        if ($default->getCode() == $foreign_code) {
            return $amount;
        }

        $rate = $this->getBaseCurrencyRate($foreign_code);

        return $amount * $rate;
    }

    public function getBaseCurrencyRate($foreign_code)
    {
        $f_rate = $this->currencyRepository->getRateByCode($foreign_code);
        
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
            return $this->currencyRepository->findDefault();
        }

        $currency = $this->currencyRepository->findOneByCode($currency);
        if ($currency instanceof Currency) {
            return $currency;
        }

        return $this->currencyRepository->findDefault();
    }

    public function setAsDefault(Currency $currency)
    {
        if ($currency->isDefault()) {
            return true;
        }

        if ($currency->getCode() === null || empty($currency->getCode())) {
            throw new \FOSSBilling\Exception('Currency code not provided');
        }

        $em = $this->di['em'];

        // Clear all default flags
        $this->currencyRepository->clearDefaultFlags();

        // Set this currency as default
        $currency->setIsDefault(true);
        $em->persist($currency);
        $em->flush();

        $this->di['logger']->info('Set currency %s as default', $currency->getCode());

        return true;
    }

    /**
     * Returns a list of available currencies.
     *
     * @return array List of currencies in the "[short code] - [name]" format
     */
    public function getAvailableCurrencies(): array
    {
        $options = [];
        foreach (CurrencyAlpha3::cases() as $currency) {
            $name = $currency->toCurrencyName()->value;

            // Ensure legacy / outdated currencies aren't listed
            if (str_contains(strtolower($name), '_old')) {
                continue;
            }

            $options[$currency->value] = $currency->value . ' - ' . $name;
        }

        unset($options['XXX'], $options['XTS']);

        ksort($options);

        return $options;
    }

    public function getCurrencyDefaults(string $code): array
    {
        try {
            $currency = CurrencyAlpha3::from($code);
        } catch (\ValueError) {
            throw new InformationException('Currency code is invalid');
        }

        return [
            'code' => $currency->value,
            'name' => $currency->toCurrencyName()->value,
            'symbol' => $currency->getSymbol()->value,
            'minorUnits' => $currency->getMinorUnits(),
        ];
    }

    public function rm(Currency $model)
    {
        if ($model->isDefault()) {
            throw new InformationException('Cannot remove default currency');
        }

        if ($model->getCode() === null || empty($model->getCode())) {
            throw new \FOSSBilling\Exception('Currency not found');
        }

        $em = $this->di['em'];
        $em->remove($model);
        $em->flush();
    }

    /**
     * See if we should update exchange rates whenever the CRON jobs are run.
     */
    public function isCronEnabled(): bool
    {
        $config = $this->di['mod_config']('currency');

        return ($config['sync_rate'] ?? 'auto') !== 'never';
    }

    public function createCurrency(string $code, string $format, ?string $title = null, string|float|null $conversionRate = 1): string
    {
        $systemService = $this->di['mod_service']('system');
        $systemService->checkLimits('Model_Currency', 2);

        $this->validateCurrencyFormat($format);
        $defaults = $this->getCurrencyDefaults($code);

        // Automatically set the correct title
        if (empty($title)) {
            $title = $defaults['name'];
        }

        // Automatically set the correct conversion rate if it's not specified
        if (empty($conversionRate)) {
            $conversionRate = $this->_getRate(null, $code);
            if ($conversionRate === false) {
                $conversionRate = 1;
            }
        }

        $model = new Currency($code, $format);
        $model->setTitle($title);
        $model->setConversionRate($conversionRate);

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();

        $this->di['logger']->info('Added new currency %s', $model->getCode());

        return $model->getCode();
    }

    public function validateCurrencyFormat($format)
    {
        if (!str_contains($format, '{{price}}')) {
            throw new \Exception('Currency format must include {{price}} tag', 3569);
        }
    }

    public function updateCurrency($code, $format = null, $title = null, $priceFormat = null, $conversionRate = null)
    {
        $model = $this->currencyRepository->findOneByCode($code);
        if (!$model instanceof Currency) {
            throw new \FOSSBilling\Exception('Currency not found');
        }

        if (isset($title)) {
            $model->setTitle($title);
        }

        if (isset($format)) {
            $this->validateCurrencyFormat($format);
            $model->setFormat($format);
        }

        if (isset($priceFormat)) {
            $model->setPriceFormat($priceFormat);
        }

        if (isset($conversionRate)) {
            if (!is_numeric($conversionRate) || $conversionRate <= 0) {
                throw new InformationException('Currency rate is invalid', null, 151);
            }
            $model->setConversionRate($conversionRate);
        }

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();

        $this->di['logger']->info('Updated currency %s', $model->getCode());

        return true;
    }

    public function updateCurrencyRates()
    {
        $dc = $this->currencyRepository->findDefault();
        $em = $this->di['em'];

        $all = $this->currencyRepository->findAll();

        foreach ($all as $currency) {
            if ($currency->isDefault()) {
                $rate = 1;
            } else {
                $rate = $this->_getRate($dc->getCode(), $currency->getCode());
            }

            if (!is_numeric($rate)) {
                continue;
            }

            $currency->setConversionRate($rate);
            $em->persist($currency);
        }

        $em->flush();

        $this->di['logger']->info('Updated currency rates');

        return true;
    }

    /**
     * Gives a conversion rate between two currencies.
     * Handles selecting the right function to query the data sources & passing the correct parameters.
     */
    protected function _getRate(?string $from, string $to): float|false
    {
        // Automatically select the default currency if the from currency is not specified
        if ($from === null || $from === '') {
            $from = $this->currencyRepository->findDefault()->getCode();
        }

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
        $model = $this->currencyRepository->findOneByCode($code);

        if (!$model instanceof Currency) {
            throw new \FOSSBilling\Exception('Currency not found');
        }
        $code = $model->getCode();

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
            error_log($e->getMessage());
        }

        return true;
    }
}
