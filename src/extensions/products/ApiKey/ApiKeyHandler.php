<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\ProductType\ApiKey;

use FOSSBilling\Exception;
use FOSSBilling\Interfaces\ProductTypeHandlerInterface;
use FOSSBilling\ProductType\ApiKey\Entity\ApiKey;
use FOSSBilling\ProductType\ApiKey\Repository\ApiKeyRepository;
use Pimple\Container;

class ApiKeyHandler implements ProductTypeHandlerInterface
{
    private ?Container $di = null;
    private ?ApiKeyRepository $repository = null;

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
    {
        return $this->di;
    }

    protected function getRepository(): ApiKeyRepository
    {
        if ($this->repository === null) {
            $this->repository = $this->di['em']->getRepository(ApiKey::class);
        }

        return $this->repository;
    }

    protected function loadEntity(int $id): ApiKey
    {
        $entity = $this->getRepository()->find($id);
        if (!$entity instanceof ApiKey) {
            throw new Exception('API key not found');
        }

        return $entity;
    }

    public function attachOrderConfig(\Model_Product $product, array $data): array
    {
        $config = json_decode($product->config ?? '', true) ?? [];

        return array_merge($config, $data);
    }

    public function create(\Model_ClientOrder $order): ApiKey
    {
        $this->assertDi();

        $apiKey = new ApiKey($order->client_id);
        $apiKey->setConfig($order->config);

        $em = $this->di['em'];
        $em->persist($apiKey);
        $em->flush();

        return $apiKey;
    }

    public function activate(\Model_ClientOrder $order): bool
    {
        $apiKey = $this->getServiceModel($order);
        $config = json_decode($order->config ?? '', true) ?? [];

        $apiKey->setApiKey($this->generateKey($config));

        $em = $this->di['em'];
        $em->persist($apiKey);
        $em->flush();

        return true;
    }

    public function renew(\Model_ClientOrder $order): bool
    {
        $apiKey = $this->getServiceModel($order);
        $this->touch($apiKey);

        return true;
    }

    public function suspend(\Model_ClientOrder $order): bool
    {
        $apiKey = $this->getServiceModel($order);
        $this->touch($apiKey);

        return true;
    }

    public function unsuspend(\Model_ClientOrder $order): bool
    {
        $apiKey = $this->getServiceModel($order);
        $this->touch($apiKey);

        return true;
    }

    public function cancel(\Model_ClientOrder $order): bool
    {
        return $this->suspend($order);
    }

    public function uncancel(\Model_ClientOrder $order): bool
    {
        return $this->unsuspend($order);
    }

    public function delete(\Model_ClientOrder $order): void
    {
        $apiKey = $this->getServiceModel($order, false);

        if ($apiKey instanceof ApiKey) {
            $em = $this->di['em'];
            $em->remove($apiKey);
            $em->flush();
        }
    }

    public function toApiArray(ApiKey $model, bool $deep = true, $identity = null): array
    {
        return [
            'id' => $model->getId(),
            'created_at' => $model->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $model->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'api_key' => $model->getApiKey(),
            'config' => json_decode($model->getConfig(), true) ?: [],
        ];
    }

    public function isValid(array $data): bool
    {
        $this->assertDi();

        if (empty($data['key'])) {
            throw new Exception('You must provide an API key to check it\'s validity.');
        }

        $model = $this->getRepository()->findOneByApiKey($data['key']);
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

        $model = $this->getRepository()->findOneByApiKey($data['key']);
        if (is_null($model)) {
            throw new Exception('API key does not exist');
        }

        $rawConfig = json_decode($model->getConfig() ?? '', true);
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

        $model = null;

        if (empty($data['key']) && empty($data['order_id'])) {
            throw new Exception('You must provide either the API key or API key order ID in order to reset it.');
        } elseif (!empty($data['order_id'])) {
            $order = $this->di['db']->getExistingModelById('ClientOrder', $data['order_id'], 'Order not found');
            $model = $this->getServiceModel($order);
        } else {
            $model = $this->getRepository()->findOneByApiKey($data['key']);
        }

        if (is_null($model)) {
            throw new Exception('API key does not exist');
        }

        $client = $this->di['loggedin_client'] ?? null;

        if (!is_null($client) && $client->id !== $model->getClientId()) {
            throw new Exception('API key does not exist');
        }

        $config = json_decode($model->getConfig() ?? '', true);

        $model->setApiKey($this->generateKey($config));
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

        if (isset($data['api_key']) && $model->getApiKey() !== $data['api_key']) {
            throw new Exception('To change the API key, please use the reset function rather than updating it.');
        }

        $config = !empty($data['config']) ? json_encode($data['config']) : $model->getConfig();
        $model->setConfig($config);
        $this->touch($model);

        return true;
    }

    private function touch(ApiKey $model): void
    {
        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();
    }

    private function getServiceModel(\Model_ClientOrder $order, bool $required = true): ?ApiKey
    {
        $this->assertDi();

        if (empty($order->service_id)) {
            if ($required) {
                throw new Exception('Order does not exist.');
            }

            return null;
        }

        try {
            $model = $this->loadEntity((int) $order->service_id);
        } catch (Exception $e) {
            if ($required) {
                throw new Exception('Order does not exist.');
            }

            return null;
        }

        return $model;
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
        } while ($this->getRepository()->findOneByApiKey($apiKey) !== null);

        return $apiKey;
    }

    private function assertDi(): void
    {
        if (!$this->di) {
            throw new Exception('DI container is not set for API key handler.');
        }
    }

    private function isActive(ApiKey $model): bool
    {
        $order = $this->di['db']->findOne(
            'ClientOrder',
            'service_id = :id AND (product_type = "apikey" OR (product_type IS NULL AND service_type = "apikey"))',
            [':id' => $model->getId()]
        );
        if (is_null($order)) {
            throw new Exception('API key does not exist');
        }

        return $order->status === 'active';
    }
}
