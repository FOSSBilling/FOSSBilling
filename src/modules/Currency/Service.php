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
    protected ?CurrencyRepository $currencyRepository = null;

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
        if ($this->currencyRepository === null) {
            if ($this->di === null) {
                throw new \FOSSBilling\Exception('The dependency injection container has not been set.');
            }

            $this->currencyRepository = $this->di['em']->getRepository(Currency::class);
        }

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
     *
     * @param string    $foreign_code The foreign currency code
     * @param float|int $amount       The amount in foreign currency
     *
     * @return float The amount in default currency
     *
     * @throws \FOSSBilling\Exception If default currency cannot be found
     */
    public function toBaseCurrency(string $foreign_code, float|int $amount): float
    {
        $default = $this->currencyRepository->findDefault();

        if ($default === null) {
            throw new \FOSSBilling\Exception('Default currency not found');
        }

        if ($default->getCode() === $foreign_code) {
            return (float) $amount;
        }

        $rate = $this->getBaseCurrencyRate($foreign_code);

        return $amount * $rate;
    }

    /**
     * Get the base currency rate for a foreign currency.
     * This is the inverse of the conversion rate (1 / rate).
     *
     * @param string $foreign_code Currency code to get the rate for
     *
     * @return float The base currency rate
     *
     * @throws \FOSSBilling\Exception If currency not found or rate is zero
     */
    public function getBaseCurrencyRate(string $foreign_code): float
    {
        $rate = $this->currencyRepository->getRateByCode($foreign_code);

        // Throw exception if currency not found
        if ($rate === null) {
            throw new \FOSSBilling\Exception('Currency not found');
        }

        if ($rate === 0.0) {
            throw new \FOSSBilling\Exception('Currency conversion rate cannot be zero');
        }

        return 1 / $rate;
    }

    /**
     * Get the currency for a specific client.
     * Falls back to default currency if client has no currency set.
     *
     * @param int $client_id The client ID
     *
     * @return Currency The currency entity
     *
     * @throws \FOSSBilling\Exception If default currency cannot be found
     */
    public function getCurrencyByClientId(int $client_id): Currency
    {
        $sql = 'SELECT currency FROM client WHERE id = :client_id';
        $values = [':client_id' => $client_id];

        $db = $this->di['db'];
        $currencyCode = $db->getCell($sql, $values);

        if ($currencyCode === null || $currencyCode === '') {
            $default = $this->currencyRepository->findDefault();
            if ($default === null) {
                throw new \FOSSBilling\Exception('Default currency not found');
            }

            return $default;
        }

        $currency = $this->currencyRepository->findOneByCode($currencyCode);
        if ($currency instanceof Currency) {
            return $currency;
        }

        $default = $this->currencyRepository->findDefault();
        if ($default === null) {
            throw new \FOSSBilling\Exception('Default currency not found');
        }

        return $default;
    }

    /**
     * Set a currency as the default currency for the system.
     *
     * @param Currency $currency The currency to set as default
     *
     * @throws \FOSSBilling\Exception If currency code is not provided
     */
    public function setAsDefault(Currency $currency): bool
    {
        if ($currency->isDefault()) {
            return true;
        }

        if (!$currency->getCode()) {
            throw new \FOSSBilling\Exception('Currency code not provided');
        }

        // Store currency code before clearing identity map (entity will be detached)
        $currencyCode = $currency->getCode();

        $em = $this->di['em'];

        $this->currencyRepository->clearDefaultFlags();
        $em->clear(Currency::class);
        $em->clear(Currency::class);

        $currency = $this->currencyRepository->findOneByCode($currencyCode);
        if (!$currency instanceof Currency) {
            throw new \FOSSBilling\Exception("Currency with code {$currencyCode} not found after clearing identity map");
        }

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

    /**
     * Remove a currency from the system.
     *
     * @param Currency $model The currency to remove
     *
     * @throws InformationException   If trying to remove the default currency
     * @throws \FOSSBilling\Exception If currency code is invalid
     */
    public function rm(Currency $model): void
    {
        if ($model->isDefault()) {
            throw new InformationException('Cannot remove default currency');
        }

        if (empty($model->getCode())) {
            throw new \FOSSBilling\Exception('Currency code is invalid or missing');
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

    public function createCurrency(string $code, string $format, ?string $title = null, string|float|null $conversionRate = 1.0): string
    {
        $systemService = $this->di['mod_service']('system');
        $systemService->checkLimits('Currency', 2);

        $this->validateCurrencyFormat($format);
        $defaults = $this->getCurrencyDefaults($code);

        // Automatically set the correct title
        if (empty($title)) {
            $title = $defaults['name'];
        }

        // Automatically set the correct conversion rate if it's not specified
        if ($conversionRate === null || $conversionRate === 0.0) {
            try {
                $conversionRate = $this->_getRate(null, $code);
            } catch (\Exception $e) {
                // If rate fetch fails, log a warning and use a default rate of 1.0
                $this->di['logger']->warning(
                    'Failed to fetch conversion rate for %s: %s. Using default rate of 1.0',
                    $code,
                    $e->getMessage()
                );
                $conversionRate = 1.0;
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

    /**
     * Validate that a currency format string contains the required {{price}} placeholder.
     *
     * @param string $format The format string to validate
     *
     * @throws \FOSSBilling\Exception If format is invalid
     */
    public function validateCurrencyFormat(string $format): void
    {
        if (!str_contains($format, '{{price}}')) {
            throw new \FOSSBilling\Exception('Currency format must include {{price}} tag', null, 3569);
        }
    }

    /**
     * Update an existing currency with new values.
     *
     * @param string            $code           The currency code to update
     * @param string|null       $format         The display format (optional)
     * @param string|null       $title          The currency title (optional)
     * @param string|null       $priceFormat    The price format (optional)
     * @param string|float|null $conversionRate The conversion rate (optional)
     *
     * @throws \FOSSBilling\Exception If currency not found
     * @throws InformationException   If conversion rate is invalid
     */
    public function updateCurrency(string $code, ?string $format = null, ?string $title = null, ?string $priceFormat = null, string|float|null $conversionRate = null): bool
    {
        $model = $this->currencyRepository->findOneByCode($code);
        if (!$model instanceof Currency) {
            throw new \FOSSBilling\Exception('Currency not found');
        }

        if ($title !== null) {
            $model->setTitle($title);
        }

        if ($format !== null) {
            $this->validateCurrencyFormat($format);
            $model->setFormat($format);
        }

        if ($priceFormat !== null) {
            $model->setPriceFormat($priceFormat);
        }

        if ($conversionRate !== null) {
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

    /**
     * Update all currency conversion rates from the configured exchange rate provider.
     * Uses batch processing for optimal performance.
     *
     * @throws \FOSSBilling\Exception If default currency cannot be found
     */
    public function updateCurrencyRates(): bool
    {
        $dc = $this->currencyRepository->findDefault();

        if ($dc === null) {
            throw new \FOSSBilling\Exception('Default currency not found. Cannot update rates.');
        }

        $em = $this->di['em'];
        $all = $this->currencyRepository->findAll();
        $updatedCount = 0;

        foreach ($all as $currency) {
            if ($currency->isDefault()) {
                $rate = 1.0;
            } else {
                $rate = $this->_getRate($dc->getCode(), $currency->getCode());
            }

            $currency->setConversionRate($rate);
            ++$updatedCount;
        }

        $em->flush();

        $this->di['logger']->info('Updated %d currency rates', $updatedCount);

        return true;
    }

    /**
     * Gives a conversion rate between two currencies.
     * Handles selecting the right function to query the data sources & passing the correct parameters.
     *
     * @param string|null $from The source currency code (null for default)
     * @param string      $to   The target currency code
     *
     * @return float The conversion rate
     *
     * @throws \FOSSBilling\Exception If default currency cannot be found
     * @throws InformationException   if API configuration is invalid, or unable to fetch conversion rate
     */
    protected function _getRate(?string $from, string $to): float
    {
        // Automatically select the default currency if the from currency is not specified
        if ($from === null || $from === '') {
            $default = $this->currencyRepository->findDefault();
            if ($default === null) {
                throw new \FOSSBilling\Exception('Default currency not found');
            }
            $from = $default->getCode();
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
            if (($config['sync_rate'] ?? 'auto') === 'auto') {
                $rates = $this->getExchangeRateAPIRates($from, 0, $key);
            } else {
                $rates = $this->getExchangeRateAPIRates($from, $validFor, $key);
            }
        }

        if (isset($rates[$to]) && is_numeric($rates[$to])) {
            return floatval($rates[$to]);
        }

        throw new \FOSSBilling\Exception("Unable to fetch conversion rate for currency: {$to}");
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
        }

        return $result['rates'] ?? [];
    }

    /**
     * Gets the rates from https://apilayer.com/marketplace/currency_data-api.
     * Fetches a complete list off currencies and then caches that result for the specified period.
     * Normalizes the return array.
     */
    protected function getCurrencyDataRates(string $from, int $validFor, string $key): array
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
    protected function getCurrencyLayerRates(string $from, int $validFor, string $key): array
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
            $strippedName = substr((string) $key, $prefixLen);
            $rates[$strippedName] = $rate;
        }

        return $rates;
    }

    /**
     * Delete a currency by its code.
     * Fires events before and after deletion.
     *
     * @param string $code The currency code to delete
     *
     * @throws \FOSSBilling\Exception If currency not found
     * @throws InformationException   If trying to delete default currency
     */
    public function deleteCurrencyByCode(string $code): bool
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
    public static function onBeforeAdminCronRun(\Box_Event $event): bool
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
