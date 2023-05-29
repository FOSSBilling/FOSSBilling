<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * This file is a delegate for module. Class does not extend any other class.
 *
 * All methods provided in this example are optional, but function names are
 * still reserved.
 */

namespace Box\Mod\Serviceapikey;

use \FOSSBilling\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    /**
     * @return array
     */
    public function attachOrderConfig(\Model_Product $product, array $data)
    {
        !empty($product->config) ?  $config = json_decode($product->config, true) : $config = [];
        return array_merge($config, $data);
    }

    public function create(\RedBeanPHP\OODBBean $order)
    {
        $model = $this->di['db']->dispense('service_apikey');
        $model->client_id = $order->client_id;
        $model->valid = false;
        $model->config = $order->config;

        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return $model;
    }

    public function activate(\RedBeanPHP\OODBBean $order, $model): bool
    {
        $config = json_decode($order->config, 1);
        if (!is_object($model)) {
            throw new \Box_Exception('Order does not exist.');
        }

        $length = $config['length'] ?? 32;

        $model->api_key = $this->generateKey($length);
        $model->valid = true;
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return true;
    }

    public function suspend(\RedBeanPHP\OODBBean $order, $model): bool
    {
        $model->valid = false;
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return true;
    }

    public function unsuspend(\RedBeanPHP\OODBBean $order, $model): bool
    {
        $model->valid = true;
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return true;
    }

    public function cancel(\RedBeanPHP\OODBBean $order, $model): bool
    {
        return $this->suspend($order, $model);
    }

    public function uncancel(\RedBeanPHP\OODBBean $order, $model): bool
    {
        return $this->unsuspend($order, $model);
    }

    public function delete(\RedBeanPHP\OODBBean $order, $model): void
    {
        if (is_object($model)) {
            $this->di['db']->trash($model);
        }
    }

    /**
     * Checks if an API key is valid or not.
     * 
     * @param array $data 
     *              - 'key' What API key to check.
     */
    public function isValid(array $data): bool
    {
        if (empty($data['key'])) {
            throw new \Box_Exception('You must provide an API key to check it\'s validity.');
        }

        $model = $this->di['db']->findOne('service_apikey', 'api_key = :api_key', [':api_key' => $data['key']]);
        if (is_null($model)) {
            throw new \Box_Exception("API key does not exist.");
        }

        return $model->valid;
    }

    /**
     * Checks if an API key is valid or not.
     * 
     * @param array $data 
     *              - 'key' What API key to check.
     */
    public function getInfo(array $data): array
    {
        if (empty($data['key'])) {
            throw new \Box_Exception('You must provide an API key to check it\'s validity.');
        }

        $model = $this->di['db']->findOne('service_apikey', 'api_key = :api_key', [':api_key' => $data['key']]);
        if (is_null($model)) {
            throw new \Box_Exception("API key does not exist.");
        }

        // Load the stored JSON config from the DB
        $rawConfig = json_decode($model->config, true);
        $strippedConfig = [];
        if (!is_array($rawConfig)) {
            $rawConfig = [];
        }

        // Then loop through it an only select the custom parameters, removing the 'custom_' prefix from the start so they are a bit cleaner to work with.
        foreach ($rawConfig as $key => $value) {
            if (str_starts_with($key, 'custom_')) {
                $name = substr($key, 7);
                $strippedConfig[$name] = $value;
            }
        }

        $data = [
            'valid'  => $model->valid,
            'id'     => $model->id,
            'config' => $strippedConfig,
        ];

        return $data;
    }

    public function toApiArray(\RedBeanPHP\OODBBean $model): array
    {
        return [
            'id'         => $model->id,
            'valid'      => $model->valid,
            'created_at' => $model->created_at,
            'updated_at' => $model->updated_at,
            'api_key'    => $model->api_key,
            'config'     => json_decode($model->config, true),
        ];
    }

    public function resetApiKey(array $data): bool
    {
        if (empty($data['key']) && empty($data['id'])) {
            throw new \Box_Exception("You must provide either the API key or API key ID in order to reset it.");
        } elseif (!empty($data['id'])) {
            $model = $this->di['db']->findOne('service_apikey', 'id = :id', [':id' => $data['id']]);
        } elseif (!empty($data['key'])) {
            $model = $this->di['db']->findOne('service_apikey', 'api_key = :api_key', [':api_key' => $data['key']]);
        }

        if (is_null($model)) {
            throw new \Box_Exception("API key does not exist.");
        }

        // Ensure the currently logged in client matches the client ID for the order. If it doesn't, error out stating that it doesn't exist.
        if ($this->di['is_client_logged']) {
            $client = $this->di['loggedin_client'];
            if ($client->id !== $model->client_id) {
                throw new \Box_Exception("API key does not exist.");
            }
        }

        // Ensure we generate a new key with the same length.
        $length = strlen(str_replace('-', '', $model->api_key));

        $model->api_key = $this->generateKey($length);
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);
        return true;
    }

    public function updateApiKey(array $data): bool
    {
        if (empty($data['id'])) {
            throw new \Box_Exception("You must provide the API key ID in order to update it.");
        }
        $model = $this->di['db']->findOne('service_apikey', 'id = :id', [':id' => $data['id']]);

        if (is_null($model)) {
            throw new \Box_Exception("API key does not exist.");
        }

        // Ensure the currently logged in client matches the client ID for the order. If it doesn't, error out stating that it doesn't exist.
        if ($this->di['is_client_logged']) {
            $client = $this->di['loggedin_client'];
            if ($client->id !== $model->client_id) {
                throw new \Box_Exception("API key does not exist.");
            }
        }

        if (isset($data['api_key']) && $model->api_key !== $data['api_key']) {
            throw new \Box_Exception('To change the API key, please use the reset function rather than updating it.');
        }

        $config = !empty($data['config']) ? json_encode($data['config']) : $model->config;

        //ID and client ID should remain constant so we don't try to update those here.
        $model->config = $config;
        $model->valid = $data['valid'] ?? $model->valid;
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);
        return true;
    }

    /**
     * Creates the database structure to store the API keys in. 
     */
    public function install(): bool
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS `service_apikey` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT UNIQUE,
            `client_id` bigint(20) NOT NULL,
            `api_key` varchar(255),
            `config` text NOT NULL,
            `valid` bool NOT NULL,
            `created_at` datetime,
            `updated_at` datetime,
            PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";
        $this->di['db']->exec($sql);

        return true;
    }

    /**
     * Removes the API keys from the database.
     */
    public function uninstall(): bool
    {
        $this->di['db']->exec("DROP TABLE IF EXISTS `service_apikey`");
        return true;
    }

    private function generateKey(int $length = 32): string
    {
        $i = 0;
        do {
            // Try 10 times to generate a unique API key. Fail if we are unable to.
            if ($i++ >= 10) {
                throw new \Box_Exception('Maximum number of iterations reached while generating API key');
            }

            $randomBytesLength = ceil($length  / 2);
            $randomBytes = random_bytes($randomBytesLength);
            $apiKey = chunk_split(substr(bin2hex($randomBytes), 0, $length), 8, '-');
            $apiKey = rtrim($apiKey, '-');
        } while (null !== $this->di['db']->findOne('service_apikey', 'api_key = :api_key', [':api_key' => $apiKey]));

        return $apiKey;
    }
}
