<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Serviceapikey;

use Box\Mod\Order\Entity\Order;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Serviceapikey\Entity\ServiceApiKey;
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

    /**
     * Top-level cart-config keys a client is authorized to set when ordering
     * an API-key product. Admin-controlled fields (length, split, case, etc.)
     * are stripped from client input before the merge.
     *
     * @return list<string>
     */
    public function clientSettableConfigKeys(): array
    {
        return ['period', 'quantity'];
    }

    public function attachOrderConfig(Product $product, array $data): array
    {
        $config = json_decode($product->getConfig() ?? '', true) ?? [];

        return array_merge($config, $data);
    }

    /**
     * @param \Model_ClientOrder $order with client_id and config properties
     */
    public function action_create(\Model_ClientOrder $order): ServiceApiKey
    {
        $model = new ServiceApiKey();
        $model->setClientId((int) $order->client_id);
        $model->setConfig($order->config);

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        return $model;
    }

    public function action_activate(\Model_ClientOrder $order): bool
    {
        $model = $this->_getService($order);
        $config = json_decode($order->config ?? '', true);
        $model->setApiKey($this->generateKey($config));

        $this->di['em']->flush();

        return true;
    }

    public function action_suspend(\Model_ClientOrder $order): bool
    {
        $this->_getService($order);

        return true;
    }

    public function action_unsuspend(\Model_ClientOrder $order): bool
    {
        $this->_getService($order);

        return true;
    }

    public function action_cancel(\Model_ClientOrder $order): bool
    {
        return $this->action_suspend($order);
    }

    public function action_uncancel(\Model_ClientOrder $order): bool
    {
        return $this->action_unsuspend($order);
    }

    public function action_delete(\Model_ClientOrder $order): void
    {
        $model = $this->_getService($order, false);
        if ($model instanceof ServiceApiKey) {
            $this->di['em']->remove($model);
            $this->di['em']->flush();
        }
    }

    public function toApiArray(ServiceApiKey $model): array
    {
        return [
            'id' => $model->getId(),
            'created_at' => $model->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $model->getUpdatedAt()?->format('Y-m-d H:i:s'),
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

        $model = $this->getRepository()->findByApiKey($data['key']);
        if ($model === null) {
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
            $order = $this->getOrderRepository()->find($data['order_id']);
            if ($order === null) {
                throw new \FOSSBilling\Exception('Order not found');
            }

            $orderService = $this->di['mod_service']('order');
            $model = $orderService->getOrderService($order);
        } else {
            $model = $this->getRepository()->findByApiKey($data['key']);
        }

        if (!$model instanceof ServiceApiKey) {
            throw new \FOSSBilling\Exception('API key does not exist');
        }

        $client = null;
        if ($this->di['auth']->isClientLoggedIn()) {
            $client = $this->di['loggedin_client'];
        }

        if (!is_null($client) && $client->id !== $model->getClientId()) {
            throw new \FOSSBilling\Exception('API key does not exist');
        }

        if (!$this->isActive($model)) {
            throw new \FOSSBilling\InformationException('Order is not active');
        }

        $config = json_decode($model->getConfig() ?? '', true);

        $model->setApiKey($this->generateKey($config));
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

        $order = $this->getOrderRepository()->find($data['order_id']);
        if ($order === null) {
            throw new \FOSSBilling\Exception('Order not found');
        }

        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);

        if (!$model instanceof ServiceApiKey) {
            throw new \FOSSBilling\Exception('API key does not exist');
        }

        if (isset($data['api_key']) && $model->getApiKey() !== $data['api_key']) {
            throw new \FOSSBilling\Exception('To change the API key, please use the reset function rather than updating it.');
        }

        $config = !empty($data['config']) ? json_encode($data['config']) : $model->getConfig();
        $model->setConfig($config);
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
            $randomBytes = random_bytes((int) ceil($length / 2));
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
        } while ($this->getRepository()->findByApiKey($apiKey) !== null);

        return $apiKey;
    }

    private function isActive(ServiceApiKey $model): bool
    {
        $order = $this->getOrderRepository()->findOneBy([
            'serviceId' => $model->getId(),
            'serviceType' => \Box\Mod\Product\Service::APIKEY,
        ]);
        if (!$order instanceof Order) {
            throw new \FOSSBilling\Exception('API key does not exist');
        }

        if ($order->getStatus() !== Order::STATUS_ACTIVE) {
            return false;
        }

        $expiresAt = $order->getExpiresAt();
        if ($expiresAt !== null && $expiresAt->getTimestamp() <= time()) {
            return false;
        }

        return true;
    }

    private function _getService(\Model_ClientOrder $order, bool $required = true): ?ServiceApiKey
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof ServiceApiKey) {
            if ($required) {
                throw new \FOSSBilling\Exception('Could not find active service');
            }

            return null;
        }

        return $model;
    }

    private function getRepository()
    {
        return $this->di['em']->getRepository(ServiceApiKey::class);
    }

    private function getOrderRepository()
    {
        return $this->di['em']->getRepository(Order::class);
    }
}
