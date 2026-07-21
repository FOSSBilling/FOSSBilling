<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Serviceapikey;

use Box\Mod\Order\Entity\Order;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Serviceapikey\Entity\ServiceApiKey;
use Box\Mod\Serviceapikey\Repository\ServiceApiKeyRepository;
use FOSSBilling\InjectionAwareInterface;

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
            'manage' => [
                'type' => 'bool',
                'display_name' => __trans('Manage API keys'),
                'description' => __trans('Allows the staff member to update and reset API keys.'),
            ],
        ];
    }

    public function getServiceApiKeyRepository(): ServiceApiKeyRepository
    {
        return $this->di['em']->getRepository(ServiceApiKey::class);
    }

    public function attachOrderConfig(Product $product, array $data): array
    {
        $config = json_decode($product->getConfig() ?? '', true) ?? [];

        return array_merge($config, $data);
    }

    /**
     * @return ServiceApiKey
     */
    public function create(Order $order)
    {
        $model = new ServiceApiKey();
        $model->setClientId($order->getClientId());
        $model->setConfig($order->getConfig());

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        return $model;
    }

    public function activate(Order $order, ServiceApiKey $model): bool
    {
        $config = json_decode($order->getConfig() ?? '', true);
        $this->_setModelProperty($model, 'api_key', $this->generateKey($config));
        $this->_setModelProperty($model, 'updated_at', date('Y-m-d H:i:s'));

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        return true;
    }

    public function suspend(Order $order, ServiceApiKey $model): bool
    {
        $this->_setModelProperty($model, 'updated_at', date('Y-m-d H:i:s'));
        $this->di['em']->persist($model);
        $this->di['em']->flush();

        return true;
    }

    public function unsuspend(Order $order, ServiceApiKey $model): bool
    {
        $this->_setModelProperty($model, 'updated_at', date('Y-m-d H:i:s'));
        $this->di['em']->persist($model);
        $this->di['em']->flush();

        return true;
    }

    public function cancel(Order $order, ServiceApiKey $model): bool
    {
        return $this->suspend($order, $model);
    }

    public function uncancel(Order $order, ServiceApiKey $model): bool
    {
        return $this->unsuspend($order, $model);
    }

    public function delete(?Order $order, ServiceApiKey|null $model): void
    {
        if (is_object($model)) {
            $this->di['em']->remove($model);
            $this->di['em']->flush();
        }
    }

    public function toApiArray(ServiceApiKey $model): array
    {
        return [
            'id' => $model->getId(),
            'created_at' => $model->getCreatedAt(),
            'updated_at' => $model->getUpdatedAt(),
            'api_key' => $model->getApiKey(),
            'config' => json_decode($model->getConfig() ?? '', true),
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

        $model = $this->getServiceApiKeyRepository()->findOneByApiKey($data['key']);
        if (is_null($model)) {
            throw new \FOSSBilling\Exception('API key does not exist');
        }

        return $this->isActive($model);
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
            $order = $this->di['em']->getRepository(Order::class)->find($data['order_id']) ?? throw new \FOSSBilling\Exception('Order not found');
            $orderService = $this->di['mod_service']('order');
            $model = $orderService->getOrderService($order);
        } else {
            $model = $this->getServiceApiKeyRepository()->findOneByApiKey($data['key']);
        }

        if (is_null($model)) {
            throw new \FOSSBilling\Exception('API key does not exist');
        }

        $client = null;
        if ($this->di['auth']->isClientLoggedIn()) {
            $client = $this->di['loggedin_client'];
        }

        $modelClientId = $model->getClientId();
        if (!is_null($client) && $client->getId() !== $modelClientId) {
            throw new \FOSSBilling\Exception('API key does not exist');
        }

        if (!$this->isActive($model)) {
            throw new \FOSSBilling\InformationException('Order is not active');
        }

        $config = json_decode($model->getConfig() ?? '', true);

        $this->_setModelProperty($model, 'api_key', $this->generateKey($config));
        $this->_setModelProperty($model, 'updated_at', date('Y-m-d H:i:s'));

        $this->di['em']->persist($model);
        $this->di['em']->flush();

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

        $order = $this->di['em']->getRepository(Order::class)->find($data['order_id']) ?? throw new \FOSSBilling\Exception('Order not found');
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);

        if (is_null($model)) {
            throw new \FOSSBilling\Exception('API key does not exist');
        }

        $currentApiKey = $model->getApiKey();
        if (isset($data['api_key']) && $currentApiKey !== $data['api_key']) {
            throw new \FOSSBilling\Exception('To change the API key, please use the reset function rather than updating it.');
        }

        $currentConfig = $model->getConfig();
        $config = !empty($data['config']) ? json_encode($data['config']) : $currentConfig;

        $this->_setModelProperty($model, 'config', $config);
        $this->_setModelProperty($model, 'updated_at', date('Y-m-d H:i:s'));

        $this->di['em']->persist($model);
        $this->di['em']->flush();

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
        $this->di['em']->getConnection()->executeStatement($sql);

        return true;
    }

    /**
     * Removes the API keys from the database.
     */
    public function uninstall(): bool
    {
        $this->di['em']->getConnection()->executeStatement('DROP TABLE IF EXISTS `service_apikey`');

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
            if ($i++ >= 10) {
                throw new \FOSSBilling\Exception('Maximum number of iterations reached while generating API key');
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
                    throw new \FOSSBilling\Exception("Unknown uppercase option ':case:'. API generator only accepts 'lower', 'upper', or 'mixed'.", [':case:' => $case]);
            }
        } while ($this->getServiceApiKeyRepository()->findOneByApiKey($apiKey) !== null);

        return $apiKey;
    }

    private function isActive(ServiceApiKey $model): bool
    {
        $modelId = $model->getId();
        $order = $this->di['em']->getRepository(Order::class)->findOneBy(['serviceId' => $modelId, 'serviceType' => 'apikey']);
        if (is_null($order)) {
            throw new \FOSSBilling\Exception('API key does not exist');
        }

        return $order->getStatus() === 'active';
    }

    private function _setModelProperty(ServiceApiKey $model, string $property, mixed $value): void
    {
        match ($property) {
            'id' => $model->setId($value),
            'api_key' => $model->setApiKey($value),
            'client_id' => $model->setClientId($value),
            'config' => $model->setConfig($value),
            'created_at' => $model->setCreatedAt(is_string($value) ? new \DateTime($value) : $value),
            'updated_at' => $model->setUpdatedAt(is_string($value) ? new \DateTime($value) : $value),
            default => null,
        };
    }
}
