<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Serviceapikey;

use FOSSBilling\InjectionAwareInterface;
use RedBeanPHP\OODBBean;

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

    public function attachOrderConfig(\Model_Product $product, array $data): array
    {
        !empty($product->config) ? $config = json_decode($product->config, true) : $config = [];

        return array_merge($config, $data);
    }

    public function create(OODBBean $order)
    {
        $model = $this->di['db']->dispense('service_apikey');
        $model->client_id = $order->client_id;
        $model->config = $order->config;

        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return $model;
    }

    public function activate(OODBBean $order, OODBBean $model): bool
    {
        $config = json_decode($order->config, 1);
        if (!is_object($model)) {
            throw new \FOSSBilling\Exception('Order does not exist.');
        }

        $model->api_key = $this->generateKey($config);
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return true;
    }

    public function suspend(OODBBean $order, OODBBean $model): bool
    {
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return true;
    }

    public function unsuspend(OODBBean $order, OODBBean $model): bool
    {
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return true;
    }

    public function cancel(OODBBean $order, OODBBean $model): bool
    {
        return $this->suspend($order, $model);
    }

    public function uncancel(OODBBean $order, OODBBean $model): bool
    {
        return $this->unsuspend($order, $model);
    }

    public function delete(?OODBBean $order, ?OODBBean $model): void
    {
        if (is_object($model)) {
            $this->di['db']->trash($model);
        }
    }

    public function toApiArray(OODBBean $model): array
    {
        return [
            'id' => $model->id,
            'created_at' => $model->created_at,
            'updated_at' => $model->updated_at,
            'api_key' => $model->api_key,
            'config' => json_decode($model->config, true),
        ];
    }

    /**
     * Checks if an API key is valid or not.
     *
     *                    - 'key' What API key to check
     */
    public function isValid(array $data): bool
    {
        if (empty($data['key'])) {
            throw new \FOSSBilling\Exception('You must provide an API key to check it\'s validity.');
        }

        $model = $this->di['db']->findOne('service_apikey', 'api_key = :api_key', [':api_key' => $data['key']]);
        if (is_null($model)) {
            throw new \FOSSBilling\Exception('API key does not exist');
        }

        return $this->isActive($model);
    }

    /**
     * Checks if an API key is valid or not and returns that + any configured custom parameters.
     *
     *                    - 'key' What API key to check
     */
    public function getInfo(array $data): array
    {
        if (empty($data['key'])) {
            throw new \FOSSBilling\Exception('You must provide an API key to check it\'s validity.');
        }

        $model = $this->di['db']->findOne('service_apikey', 'api_key = :api_key', [':api_key' => $data['key']]);
        if (is_null($model)) {
            throw new \FOSSBilling\Exception('API key does not exist');
        }

        // Load the stored JSON config from the DB
        $rawConfig = json_decode($model->config, true);
        $strippedConfig = [];
        if (!is_array($rawConfig)) {
            $rawConfig = [];
        }

        // Then loop through it and only select the custom parameters, removing the 'custom_' prefix & converting numerical strings to a float.
        foreach ($rawConfig as $key => $value) {
            if (str_starts_with($key, 'custom_')) {
                $name = substr($key, 7);
                if (is_numeric($value)) {
                    $strippedConfig[$name] = floatval($value);
                } else {
                    $strippedConfig[$name] = $value;
                }
            }
        }

        return [
            'valid' => $this->isActive($model),
            'config' => $strippedConfig,
        ];
    }

    /**
     * Used to reset an API key using the API key generator.
     *
     * @param array $data An array containing what API key to reset. At least one of the possible identification methods must be provided.
     *                    - int 'order_id' (optional) The ID of the API key to rest.
     *                    - string 'key' (optional) The API key to reset.
     */
    public function resetApiKey(array $data): bool
    {
        if (empty($data['key']) && empty($data['order_id'])) {
            throw new \FOSSBilling\Exception('You must provide either the API key or API key order ID in order to reset it.');
        } elseif (!empty($data['order_id'])) {
            $order = $this->di['db']->getExistingModelById('ClientOrder', $data['order_id'], 'Order not found');
            $orderService = $this->di['mod_service']('order');
            $model = $orderService->getOrderService($order);
        } else {
            $model = $this->di['db']->findOne('service_apikey', 'api_key = :api_key', [':api_key' => $data['key']]);
        }

        if (is_null($model)) {
            throw new \FOSSBilling\Exception('API key does not exist');
        }

        try {
            $this->di['is_client_logged'];
            $client = $this->di['loggedin_client'];
        } catch (\Exception) {
            $client = null;
        }

        if (!is_null($client) && $client->id !== $model->client_id) {
            throw new \FOSSBilling\Exception('API key does not exist');
        }

        $config = json_decode($model->config, true);

        $model->api_key = $this->generateKey($config);
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return true;
    }

    /**
     * Used to update an API key, but prevents changing the API key so we can ensure they use the reset function.
     *
     * @param array $data An array containing what API key to update and what info to update.
     *                    - int 'order_id' The order ID of the API key to update.
     *                    - array 'config' (optional) The new config to attach to the API key.
     */
    public function updateApiKey(array $data): bool
    {
        if (empty($data['order_id'])) {
            throw new \FOSSBilling\Exception('You must provide the API key order ID in order to update it.');
        }

        $order = $this->di['db']->getExistingModelById('ClientOrder', $data['order_id'], 'Order not found');
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);

        if (is_null($model)) {
            throw new \FOSSBilling\Exception('API key does not exist');
        }

        if (isset($data['api_key']) && $model->api_key !== $data['api_key']) {
            throw new \FOSSBilling\Exception('To change the API key, please use the reset function rather than updating it.');
        }

        $config = !empty($data['config']) ? json_encode($data['config']) : $model->config;

        // ID and client ID should remain constant so we don't try to update those here.
        $model->config = $config;
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return true;
    }

    /**
     * Creates the database structure to store the API keys in.
     */
    public function install(): bool
    {
        $sql = '
        CREATE TABLE IF NOT EXISTS `service_apikey` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT UNIQUE,
            `client_id` bigint(20) NOT NULL,
            `api_key` varchar(255),
            `config` text NOT NULL,
            `created_at` datetime,
            `updated_at` datetime,
            PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;';
        $this->di['db']->exec($sql);

        return true;
    }

    /**
     * Removes the API keys from the database.
     */
    public function uninstall(): bool
    {
        $this->di['db']->exec('DROP TABLE IF EXISTS `service_apikey`');

        return true;
    }

    /**
     * Generates a new API using PHP's built-in cryptographically secure random_bytes to ensure the API keys are truly random and not predictable.
     *
     * @param array $config (optional) An array of configuration options. All configuration keys are optional.
     *                      - int 'length' How long of an API key to generate.
     *                      - bool 'split' True to enable splitting of the API key using dashes. Does not count towards the total key length.
     *                      - int 'split_interval' How often the API key should be split with dashes. Example: 8 for every 8 characters.
     *                      - string 'case' What capitalization should be used for the generated API key. 'lower', 'upper', or 'mixed'.
     */
    private function generateKey(array $config = []): string
    {
        $length = $config['length'] ?? 32;
        $split = $config['split'] ?? true;
        $splitLength = $config['split_interval'] ?? 8;
        $case = $config['case'] ?? 'upper';

        $i = 0;
        do {
            // Try 10 times to generate a unique API key. Fail if we are unable to.
            if ($i++ >= 10) {
                throw new \FOSSBilling\Exception('Maximum number of iterations reached while generating API key');
            }

            // Generate random bytes half the length of the configured length, as the length will doubled when converted to a hex string.
            $randomBytes = random_bytes(ceil($length / 2));
            $apiKey = substr(bin2hex($randomBytes), 0, $length);

            if ($split) {
                $apiKey = chunk_split($apiKey, $splitLength, '-');
                $apiKey = rtrim($apiKey, '-');
            }

            switch ($case) {
                case 'lower':
                    // Do nothing, the API key generated will be lowercase by default.
                    break;
                case 'upper':
                    $apiKey = strtoupper($apiKey);

                    break;
                case 'mixed':
                    $characters = str_split($apiKey);
                    $result = '';

                    foreach ($characters as $character) {
                        if (random_int(0, 1) <= 0.5) {
                            $character = strtoupper($character);
                        }

                        $result .= $character;
                    }
                    $apiKey = $result;

                    break;
                default:
                    throw new \FOSSBilling\Exception("Unknown uppercase option ':case:'. API generator only accepts 'lower', 'upper', or 'mixed'.", [':case:' => $case]);
            }
        } while ($this->di['db']->findOne('service_apikey', 'api_key = :api_key', [':api_key' => $apiKey]) !== null);

        return $apiKey;
    }

    private function isActive(OODBBean $model): bool
    {
        $order = $this->di['db']->findOne('ClientOrder', 'service_id = :id AND service_type = "apikey"', [':id' => $model->id]);
        if (is_null($order)) {
            throw new \FOSSBilling\Exception('API key does not exist');
        }

        return $order->status === 'active';
    }
}
