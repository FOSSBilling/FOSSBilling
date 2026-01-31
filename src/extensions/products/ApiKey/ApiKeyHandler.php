<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 */

namespace FOSSBilling\ProductType\ApiKey;

use FOSSBilling\Exception;
use FOSSBilling\Interfaces\ProductTypeHandlerInterface;
use Pimple\Container;
use RedBeanPHP\OODBBean;

class ApiKeyHandler implements ProductTypeHandlerInterface
{
    private ?Container $di = null;

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
    {
        return $this->di;
    }

    public function attachOrderConfig(\Model_Product $product, array $data): array
    {
        $config = json_decode($product->config ?? '', true) ?? [];

        return array_merge($config, $data);
    }

    public function create(\Model_ClientOrder $order)
    {
        $this->assertDi();

        $model = $this->di['db']->dispense('ext_product_apikey');
        $model->client_id = $order->client_id;
        $model->config = $order->config;
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return $model;
    }

    public function activate(\Model_ClientOrder $order)
    {
        $model = $this->getServiceModel($order);
        $config = json_decode($order->config ?? '', true) ?? [];

        $model->api_key = $this->generateKey($config);
        $this->touch($model);

        return true;
    }

    public function renew(\Model_ClientOrder $order)
    {
        $model = $this->getServiceModel($order);
        $this->touch($model);

        return true;
    }

    public function suspend(\Model_ClientOrder $order)
    {
        $model = $this->getServiceModel($order);
        $this->touch($model);

        return true;
    }

    public function unsuspend(\Model_ClientOrder $order)
    {
        $model = $this->getServiceModel($order);
        $this->touch($model);

        return true;
    }

    public function cancel(\Model_ClientOrder $order)
    {
        return $this->suspend($order);
    }

    public function uncancel(\Model_ClientOrder $order)
    {
        return $this->unsuspend($order);
    }

    public function delete(\Model_ClientOrder $order)
    {
        $model = $this->getServiceModel($order, false);
        if ($model instanceof OODBBean) {
            $this->di['db']->trash($model);
        }
    }

    public function toApiArray(OODBBean $model, bool $deep = true, $identity = null): array
    {
        return [
            'id' => $model->id,
            'created_at' => $model->created_at,
            'updated_at' => $model->updated_at,
            'api_key' => $model->api_key,
            'config' => json_decode($model->config ?? '', true),
        ];
    }

    public function isValid(array $data): bool
    {
        $this->assertDi();

        if (empty($data['key'])) {
            throw new Exception('You must provide an API key to check it\'s validity.');
        }

        $model = $this->di['db']->findOne('ext_product_apikey', 'api_key = :api_key', [':api_key' => $data['key']]);
        if (is_null($model)) {
            throw new Exception('API key does not exist');
        }

        return $this->isActive($model);
    }

    public function getInfo(array $data): array
    {
        $this->assertDi();

        if (empty($data['key'])) {
            throw new Exception('You must provide an API key to check it\'s validity.');
        }

        $model = $this->di['db']->findOne('ext_product_apikey', 'api_key = :api_key', [':api_key' => $data['key']]);
        if (is_null($model)) {
            throw new Exception('API key does not exist');
        }

        $rawConfig = json_decode($model->config ?? '', true);
        $strippedConfig = [];
        if (!is_array($rawConfig)) {
            $rawConfig = [];
        }

        foreach ($rawConfig as $key => $value) {
            if (str_starts_with((string) $key, 'custom_')) {
                $name = substr((string) $key, 7);
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

    public function resetApiKey(array $data): bool
    {
        $this->assertDi();

        if (empty($data['key']) && empty($data['order_id'])) {
            throw new Exception('You must provide either the API key or API key order ID in order to reset it.');
        } elseif (!empty($data['order_id'])) {
            $order = $this->di['db']->getExistingModelById('ClientOrder', $data['order_id'], 'Order not found');
            $model = $this->getServiceModel($order);
        } else {
            $model = $this->di['db']->findOne('ext_product_apikey', 'api_key = :api_key', [':api_key' => $data['key']]);
        }

        if (is_null($model)) {
            throw new Exception('API key does not exist');
        }

        try {
            $client = $this->di['loggedin_client'];
        } catch (\Exception) {
            $client = null;
        }

        if (!is_null($client) && $client->id !== $model->client_id) {
            throw new Exception('API key does not exist');
        }

        $config = json_decode($model->config ?? '', true);

        $model->api_key = $this->generateKey($config);
        $this->touch($model);

        return true;
    }

    public function updateApiKey(array $data): bool
    {
        $this->assertDi();

        if (empty($data['order_id'])) {
            throw new Exception('You must provide the API key order ID in order to update it.');
        }

        $order = $this->di['db']->getExistingModelById('ClientOrder', $data['order_id'], 'Order not found');
        $model = $this->getServiceModel($order);

        if (is_null($model)) {
            throw new Exception('API key does not exist');
        }

        if (isset($data['api_key']) && $model->api_key !== $data['api_key']) {
            throw new Exception('To change the API key, please use the reset function rather than updating it.');
        }

        $config = !empty($data['config']) ? json_encode($data['config']) : $model->config;
        $model->config = $config;
        $this->touch($model);

        return true;
    }

    private function touch(OODBBean $model): void
    {
        $this->assertDi();
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);
    }

    private function getServiceModel(\Model_ClientOrder $order, bool $required = true): ?OODBBean
    {
        $this->assertDi();

        if (empty($order->service_id)) {
            if ($required) {
                throw new Exception('Order does not exist.');
            }

            return null;
        }

        $model = $this->di['db']->load('ext_product_apikey', $order->service_id);
        if ((!$model || !$model->id) && $required) {
            throw new Exception('Order does not exist.');
        }

        return $model && $model->id ? $model : null;
    }

    private function generateKey(array $config = []): string
    {
        $this->assertDi();

        $length = $config['length'] ?? 32;
        $split = $config['split'] ?? true;
        $splitLength = $config['split_interval'] ?? 8;
        $case = $config['case'] ?? 'upper';

        $i = 0;
        do {
            if ($i++ >= 10) {
                throw new Exception('Maximum number of iterations reached while generating API key');
            }

            $randomBytes = random_bytes((int) ceil($length / 2));
            $apiKey = substr(bin2hex($randomBytes), 0, $length);

            if ($split) {
                $apiKey = chunk_split($apiKey, $splitLength, '-');
                $apiKey = rtrim($apiKey, '-');
            }

            switch ($case) {
                case 'lower':
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
                    throw new Exception("Unknown uppercase option ':case:'. API generator only accepts 'lower', 'upper', or 'mixed'.", [':case:' => $case]);
            }
        } while ($this->di['db']->findOne('ext_product_apikey', 'api_key = :api_key', [':api_key' => $apiKey]) !== null);

        return $apiKey;
    }

    private function assertDi(): void
    {
        if (!$this->di) {
            throw new Exception('DI container is not set for API key handler.');
        }
    }

    private function isActive(OODBBean $model): bool
    {
        $order = $this->di['db']->findOne(
            'ClientOrder',
            'service_id = :id AND (product_type = "apikey" OR (product_type IS NULL AND service_type = "apikey"))',
            [':id' => $model->id]
        );
        if (is_null($order)) {
            throw new Exception('API key does not exist');
        }

        return $order->status === 'active';
    }
}
