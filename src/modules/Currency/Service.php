<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
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
            'view' => [
                'type' => 'bool',
                'display_name' => __trans('View currencies'),
                'description' => __trans('Allows the staff member to view available currencies and their details.'),
            ],
            'create' => [
                'type' => 'bool',
                'display_name' => __trans('Create Currencies'),
                'description' => __trans('Allows the staff member to add new currencies to the system.'),
            ],
            'edit' => [
                'type' => 'bool',
                'display_name' => __trans('Edit Currencies'),
                'description' => __trans('Allows the staff member to update currency conversion rates.'),
            ],
            'delete' => [
                'type' => 'bool',
                'display_name' => __trans('Delete Currencies'),
                'description' => __trans('Allows the staff member to remove currencies from the system.'),
            ],
            'set_default' => [
                'type' => 'bool',
                'display_name' => __trans('Set Default Currency'),
                'description' => __trans('Allows the staff member to change the system default currency.'),
            ],
            'update_rates' => [
                'type' => 'bool',
                'display_name' => __trans('Update Currency Rates'),
                'description' => __trans('Allows the staff member to refresh all currency conversion rates.'),
            ],
            'manage_settings' => [],
        ];
    }

    /**
     * Convert an amount from a foreign currency to the system's default
     * currency using the current conversion rate.
     *
     * @param string    $fromCurrencyCode Currency code of the amount to convert (e.g., 'USD')
     * @param float|int $amount           Amount in the foreign currency
     *
     * @return float Amount converted to the default currency
     *
     * @throws \FOSSBilling\Exception If default currency cannot be found
     */
    public function toBaseCurrency(string $fromCurrencyCode, float|int $amount): float
    {
        $defaultCurrency = $this->currencyRepository->findDefault();

        if ($defaultCurrency === null) {
            throw new \FOSSBilling\Exception('Default currency not found.');
        }

        if ($defaultCurrency->getCode() === $fromCurrencyCode) {
            return (float) $amount;
        }

        $rate = $this->getBaseCurrencyRate($fromCurrencyCode);

        return $amount * $rate;
    }

    /**
     * Get the conversion rate to convert from a specified currency to the default currency.
     *
     * @param string $fromCurrencyCode Currency code to get the base rate for (e.g., 'USD')
     *
     * @return float Conversion rate to convert from the specified currency to the default currency
     *
     * @throws \FOSSBilling\Exception If currency not found or rate is zero
     */
    public function getBaseCurrencyRate(string $fromCurrencyCode): float
    {
        $rate = $this->currencyRepository->getRateByCode($fromCurrencyCode);

        if ($rate === null) {
            throw new \FOSSBilling\Exception('Currency not found.');
        }

        if ($rate === 0.0) {
            throw new \FOSSBilling\Exception('Currency conversion rate cannot be zero.');
        }

        return 1 / $rate;
    }

    /**
     * Get the currency associated with a client by their ID.
     * If the client does not have a specific currency set, returns the default currency.
     *
     * @param int $clientId Client ID
     *
     * @return Currency Currency entity for the client's currency or the default currency if client
     *                  has no specific currency set
     *
     * @throws \FOSSBilling\Exception If default currency cannot be found
     */
    public function getCurrencyByClientId(int $clientId): Currency
    {
        $currencyCode = $this->currencyRepository->getClientCurrencyCode($clientId);

        if ($currencyCode === null) {
            $defaultCurrency = $this->currencyRepository->findDefault();
            if ($defaultCurrency === null) {
                throw new \FOSSBilling\Exception('Default currency not found.');
            }

            return $defaultCurrency;
        }

        $currency = $this->currencyRepository->findOneByCode($currencyCode);
        if ($currency instanceof Currency) {
            return $currency;
        }

        $defaultCurrency = $this->currencyRepository->findDefault();
        if ($defaultCurrency === null) {
            throw new \FOSSBilling\Exception('Default currency not found.');
        }

        return $defaultCurrency;
    }

    /**
     * Set a specific currency as the default currency for the system.
     * This will update the default flag in the database.
     *
     * @param Currency $currency Currency entity to set as default
     *
     * @throws \FOSSBilling\Exception If currency code is invalid or if the currency cannot be found after clearing the identity map
     */
    public function setAsDefault(Currency $currency): bool
    {
        if ($currency->isDefault()) {
            return true;
        }

        if (!$currency->getCode()) {
            throw new \FOSSBilling\Exception('Currency code not provided.');
        }

        // Store currency code before clearing identity map (entity will be detached)
        $currencyCode = $currency->getCode();

        $em = $this->di['em'];

        $this->currencyRepository->clearDefaultFlags();
        $em->clear(Currency::class);
        $em->clear(Currency::class);

        $currency = $this->currencyRepository->findOneByCode($currencyCode);
        if (!$currency instanceof Currency) {
            throw new \FOSSBilling\Exception("Currency with code {$currencyCode} not found after clearing identity map.");
        }

        $currency->setIsDefault(true);
        $em->persist($currency);
        $em->flush();

        $this->di['logger']->info('Set currency %s as default.', $currency->getCode());

        return true;
    }

    /**
     * Check if automatic currency rate updates via Cron are enabled based on the module configuration.
     */
    public function isCronEnabled(): bool
    {
        $config = $this->di['mod_config']('currency');

        return ($config['sync_rate'] ?? 'auto') !== 'never';
    }

    /**
     * Add a new currency to the system with the specified code and conversion rate.
     * If the conversion rate is not provided, it will attempt to fetch it automatically.
     *
     * @param string            $currencyCode   The ISO currency code (e.g., 'USD')
     * @param string|float|null $conversionRate The conversion rate to the default currency (optional)
     *
     * @return string The code of the newly created currency
     *
     * @throws \FOSSBilling\Exception If currency code is invalid or if fetching the conversion rate fails
     */
    public function createCurrency(string $currencyCode, string|float|null $conversionRate = 1.0): string
    {
        if ($conversionRate === null || $conversionRate === '') {
            try {
                $conversionRate = $this->getRate(null, $currencyCode);
            } catch (\Exception $e) {
                // If rate fetch fails, log a warning and use a default rate of 1.0
                $this->di['logger']->warning(
                    'Failed to fetch conversion rate for %s: %s. Using default rate of 1.0.',
                    $currencyCode,
                    $e->getMessage()
                );
                $conversionRate = 1.0;
            }
        } else {
            if (!is_numeric($conversionRate) || $conversionRate <= 0) {
                throw new \FOSSBilling\Exception('Currency conversion rate must be a positive number.');
            }

            $conversionRate = (float) $conversionRate;
        }

        $currency = new Currency($currencyCode);
        $currency->setConversionRate($conversionRate);

        $em = $this->di['em'];
        $em->persist($currency);
        $em->flush();

        $this->di['logger']->info('Added new currency %s.', $currency->getCode());

        return $currency->getCode();
    }

    /**
     * Remove a currency from the system. The default currency cannot be removed.
     *
     * @param string $currencyCode Currency code to remove
     *
     * @throws InformationException   If trying to remove the default currency
     * @throws \FOSSBilling\Exception If currency code is invalid
     */
    public function removeCurrency(string $currencyCode): bool
    {
        $currency = $this->currencyRepository->findOneByCode($currencyCode);

        if (!$currency instanceof Currency) {
            throw new \FOSSBilling\Exception('Currency not found.');
        }

        if ($currency->isDefault()) {
            throw new InformationException('Cannot remove the default currency.');
        }

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminDeleteCurrency', 'params' => ['code' => $currencyCode]]);

        $em = $this->di['em'];
        $em->remove($currency);
        $em->flush();

        $this->di['events_manager']->fire(['event' => 'onAfterAdminDeleteCurrency', 'params' => ['code' => $currencyCode]]);

        $this->di['logger']->info('Removed currency %s.', $currency->getCode());

        return true;
    }

    /**
     * Update an existing currency with new values.
     *
     * @param string            $currencyCode   Currency code to update
     * @param string|float|null $conversionRate Conversion rate (optional)
     *
     * @throws \FOSSBilling\Exception If currency not found
     * @throws InformationException   If conversion rate is invalid
     */
    public function updateCurrency(string $currencyCode, string|float|null $conversionRate = null): bool
    {
        $model = $this->currencyRepository->findOneByCode($currencyCode);
        if (!$model instanceof Currency) {
            throw new \FOSSBilling\Exception('Currency not found.');
        }

        if ($conversionRate !== null) {
            if (!is_numeric($conversionRate) || $conversionRate <= 0) {
                throw new InformationException('Currency conversion rate is invalid.', null, 151);
            }
            $model->setConversionRate($conversionRate);
        }

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();

        $this->di['logger']->info('Updated currency %s.', $model->getCode());

        return true;
    }

    /**
     * Update conversion rates for all currencies based on the default currency.
     * This will fetch the latest rates from the configured provider and update all
     * non-default currencies accordingly.
     *
     * @throws \FOSSBilling\Exception If default currency cannot be found
     */
    public function updateCurrencyRates(): bool
    {
        $defaultCurrency = $this->currencyRepository->findDefault();

        if ($defaultCurrency === null) {
            throw new \FOSSBilling\Exception('Default currency not found. Cannot update rates.');
        }

        $em = $this->di['em'];
        $all = $this->currencyRepository->findAll();
        $updatedCount = 0;

        foreach ($all as $currency) {
            if ($currency->isDefault()) {
                $rate = 1.0;
            } else {
                $rate = $this->getRate($defaultCurrency->getCode(), $currency->getCode());
            }

            $currency->setConversionRate($rate);
            ++$updatedCount;
        }

        $em->flush();

        $this->di['logger']->info('Updated %d currency rates.', $updatedCount);

        return true;
    }

    /**
     * Get the conversion rate from a source currency to a target currency.
     * If the source currency is not specified, it will use the default currency as the source.
     *
     * Handles selecting the right function to query the data sources & passing the correct parameters.
     *
     * @param string|null $fromCurrencyCode Source currency code
     * @param string      $toCurrencyCode   Target currency code to get the conversion rate for
     *
     * @return float Conversion rate from the source currency to the target currency
     *
     * @throws \FOSSBilling\Exception If default currency cannot be found
     * @throws InformationException   If API configuration is invalid, or unable to fetch conversion rate
     */
    protected function getRate(?string $fromCurrencyCode, string $toCurrencyCode): float
    {
        // Automatically select the default currency if the from currency is not specified
        if ($fromCurrencyCode === null || $fromCurrencyCode === '') {
            $defaultCurrency = $this->currencyRepository->findDefault();
            if ($defaultCurrency === null) {
                throw new \FOSSBilling\Exception('Default currency not found.');
            }
            $fromCurrencyCode = $defaultCurrency->getCode();
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
            $rates = $this->getCurrencyDataRates($fromCurrencyCode, $validFor, $config['currencydata_key']);
        } elseif ($provider === 'currencylayer') {
            if (empty($config['currencylayer_key'])) {
                throw new InformationException('You must configure your API key to use currencylayer as an exchange rate data source.');
            }
            $rates = $this->getCurrencyLayerRates($fromCurrencyCode, $validFor, $config['currencylayer_key']);
        } else {
            $key = $config['exchangerate_api_key'] ?? ''; // No key is OK here, we will just use the open API
            if (($config['sync_rate'] ?? 'auto') === 'auto') {
                $rates = $this->getExchangeRateAPIRates($fromCurrencyCode, 0, $key);
            } else {
                $rates = $this->getExchangeRateAPIRates($fromCurrencyCode, $validFor, $key);
            }
        }

        if (isset($rates[$toCurrencyCode]) && is_numeric($rates[$toCurrencyCode])) {
            return floatval($rates[$toCurrencyCode]);
        }

        throw new \FOSSBilling\Exception("Unable to fetch conversion rate for currency: {$toCurrencyCode}.");
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

            $httpClient = $this->di['http_client'];
            $response = $httpClient->request('GET', $requestUrl);
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

            $httpClient = $this->di['http_client'];
            $response = $httpClient->request('GET', 'https://api.apilayer.com/currency_data/live', [
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

            $httpClient = $this->di['http_client'];
            $response = $httpClient->request('GET', 'https://api.apilayer.com/currency_data/live', [
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
