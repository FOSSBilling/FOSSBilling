<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicelicense;

use Box\Mod\Client\Entity\Client;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Servicelicense\Entity\ServiceLicense;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Servicelicense\Repository\ServiceLicenseRepository;
use Box\Mod\Staff\Entity\Admin;
use FOSSBilling\InjectionAwareInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

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
                'display_name' => __trans('Manage licenses'),
                'description' => __trans('Allows the staff member to update and reset license validation rules.'),
            ],
        ];
    }

    public function getServiceLicenseRepository(): ServiceLicenseRepository
    {
        return $this->di['em']->getRepository(ServiceLicense::class);
    }

    /**
     * Method called before adding product to cart.
     */
    public function attachOrderConfig(Product $product, array $data): array
    {
        $config = json_decode($product->getConfig() ?? '', true) ?? [];

        return array_merge($config, $data);
    }

    /**
     * Method is called before adding product to cart.
     */
    public function validateOrderData(array &$data): bool
    {
        return true;
    }

    public function getLicensePlugins(): array
    {
        $dir = Path::join(__DIR__, 'Plugin');
        $files = [];

        $finder = new Finder();
        $finder->files()->ignoreVCS(true)->in($dir);
        foreach ($finder as $file) {
            $info['filename'] = $file->getFilenameWithoutExtension();
            $info['path'] = $file->getPathname();
            $files[] = $info;
        }

        return $files;
    }

    /**
     * @return ServiceLicense
     */
    public function action_create(Order $order)
    {
        $orderService = $this->di['mod_service']('order');
        $c = $orderService->getConfig($order);
        $this->validateOrderData($c);

        $model = new ServiceLicense();
        $model->setClientId($order->getClientId());
        $model->setValidateIp((bool) ($c['validate_ip'] ?? false));
        $model->setValidateHost((bool) ($c['validate_host'] ?? false));
        $model->setValidatePath((bool) ($c['validate_path'] ?? false));
        $model->setValidateVersion((bool) ($c['validate_version'] ?? false));
        $model->setPlugin($c['plugin'] ?? 'Simple');

        $model->setIps(null);
        $model->setVersions(null);
        $model->setHosts(null);
        $model->setPaths(null);

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        return $model;
    }

    public function action_activate(Order $order): bool
    {
        $orderService = $this->di['mod_service']('order');
        $c = $orderService->getConfig($order);
        $iterations = $c['iterations'] ?? 10;
        $model = $orderService->getOrderService($order);
        if (!$model instanceof ServiceLicense) {
            throw new \FOSSBilling\Exception('Could not activate order. Service was not created');
        }

        $plugin = $this->_getPlugin($model);

        if (!is_object($plugin)) {
            throw new \FOSSBilling\Exception('License plugin :plugin was not found.', [':plugin' => $this->_getModelProperty($model, 'plugin')]);
        }

        if (!method_exists($plugin, 'generate')) {
            throw new \FOSSBilling\Exception('License plugin does not have generate method');
        }

        if (method_exists($plugin, 'setDi')) {
            $plugin->setDi($this->di);
        }

        $i = 0;
        do {
            $licenseKey = $plugin->generate($model, $order, $c);
            if ($i++ >= $iterations) {
                throw new \FOSSBilling\Exception('Maximum number of iterations reached while generating license key');
            }
        } while ($this->getServiceLicenseRepository()->findOneByLicenseKey($licenseKey) !== null);

        $this->_setModelProperty($model, 'license_key', $licenseKey);
        $this->_setModelProperty($model, 'updated_at', date('Y-m-d H:i:s'));
        $this->di['em']->persist($model);
        $this->di['em']->flush();

        return true;
    }

    /**
     * @todo
     */
    public function action_renew(Order $order): bool
    {
        return true;
    }

    /**
     * @todo
     */
    public function action_suspend(Order $order): bool
    {
        return true;
    }

    /**
     * @todo
     */
    public function action_unsuspend(Order $order): bool
    {
        return true;
    }

    /**
     * @todo
     */
    public function action_cancel(Order $order): bool
    {
        return true;
    }

    /**
     * @todo
     */
    public function action_uncancel(Order $order): bool
    {
        return true;
    }

    public function action_delete(Order $order): void
    {
        $orderService = $this->di['mod_service']('order');
        $service = $orderService->getOrderService($order);
        if ($service instanceof ServiceLicense) {
            $this->di['em']->remove($service);
            $this->di['em']->flush();
        }
    }

    public function reset(ServiceLicense $model): bool
    {
        $data = [
            'id' => $this->_getModelProperty($model, 'id'),
            'ips' => $this->_getModelProperty($model, 'ips'),
            'hosts' => $this->_getModelProperty($model, 'hosts'),
            'paths' => $this->_getModelProperty($model, 'paths'),
            'versions' => $this->_getModelProperty($model, 'versions'),
            'client_id' => $this->_getModelProperty($model, 'client_id'),
        ];
        $this->di['events_manager']->fire(['event' => 'onBeforeServicelicenseReset', 'params' => $data]);

        $this->_setModelProperty($model, 'ips', json_encode([]));
        $this->_setModelProperty($model, 'hosts', json_encode([]));
        $this->_setModelProperty($model, 'paths', json_encode([]));
        $this->_setModelProperty($model, 'versions', json_encode([]));
        $this->_setModelProperty($model, 'updated_at', date('Y-m-d H:i:s'));
        $this->di['em']->persist($model);
        $this->di['em']->flush();
        $this->di['logger']->info('Reset license %s information', $this->_getModelProperty($model, 'id'));

        $data = [
            'id' => $this->_getModelProperty($model, 'id'),
            'client_id' => $this->_getModelProperty($model, 'client_id'),
            'updated_at' => $model instanceof ServiceLicense ? $model->getUpdatedAt() : $model->getUpdatedAt(),
        ];
        $this->di['events_manager']->fire(['event' => 'onAfterServicelicenseReset', 'params' => $data]);

        return true;
    }

    public function isLicenseActive(ServiceLicense $model)
    {
        $orderService = $this->di['mod_service']('order');
        $o = $orderService->getServiceOrder($model);
        if ($o instanceof Order) {
            if ($o->getStatus() != Order::STATUS_ACTIVE) {
                return false;
            }

            if ($o->getExpiresAt() !== null && $o->getExpiresAt() <= new \DateTime()) {
                return false;
            }

            return true;
        }

        return false;
    }

    public function isValidIp(ServiceLicense $model, $value)
    {
        $defined = $model instanceof ServiceLicense ? $model->getAllowedIps() : $model->getAllowedIps();
        if (empty($defined)) {
            $this->_addValue($model, 'ips', $value);

            return true;
        }

        $validateIp = $model instanceof ServiceLicense ? $model->getValidateIp() : $model->getValidateIp();
        if (!$validateIp) {
            $this->_addValue($model, 'ips', $value);

            return true;
        }

        return in_array($value, $defined);
    }

    public function isValidVersion(ServiceLicense $model, $value)
    {
        $defined = $model instanceof ServiceLicense ? $model->getAllowedVersions() : $model->getAllowedVersions();
        if (empty($defined)) {
            $this->_addValue($model, 'versions', $value);

            return true;
        }

        $validateVersion = $model instanceof ServiceLicense ? $model->getValidateVersion() : $model->getValidateVersion();
        if (!$validateVersion) {
            $this->_addValue($model, 'versions', $value);

            return true;
        }

        return in_array($value, $defined);
    }

    public function isValidPath(ServiceLicense $model, $value)
    {
        $defined = $model instanceof ServiceLicense ? $model->getAllowedPaths() : $model->getAllowedPaths();
        if (empty($defined)) {
            $this->_addValue($model, 'paths', $value);

            return true;
        }

        $validatePath = $model instanceof ServiceLicense ? $model->getValidatePath() : $model->getValidatePath();
        if (!$validatePath) {
            $this->_addValue($model, 'paths', $value);

            return true;
        }

        return in_array($value, $defined);
    }

    public function isValidHost(ServiceLicense $model, $value)
    {
        $defined = $model instanceof ServiceLicense ? $model->getAllowedHosts() : $model->getAllowedHosts();
        if (empty($defined)) {
            $this->_addValue($model, 'hosts', $value);

            return true;
        }

        $validateHost = $model instanceof ServiceLicense ? $model->getValidateHost() : $model->getValidateHost();
        if (!$validateHost) {
            $this->_addValue($model, 'hosts', $value);

            return true;
        }

        return in_array($value, $defined);
    }

    public function getAdditionalParams(ServiceLicense $model, $data = []): array
    {
        $plugin = $this->_getPlugin($model);
        if (is_object($plugin) && method_exists($plugin, 'validate')) {
            $res = $plugin->validate($model, $data);
            if (is_array($res)) {
                return $res;
            }
        }

        return [];
    }

    public function getOwnerName(ServiceLicense $model)
    {
        $clientId = $model instanceof ServiceLicense ? $model->getClientId() : $model->getClientId();
        $client = $this->di['em']->getRepository(Client::class)->find($clientId);

        return $client->getFullName();
    }

    public function getExpirationDate(ServiceLicense $model)
    {
        $orderService = $this->di['mod_service']('order');
        $o = $orderService->getServiceOrder($model);
        if ($o instanceof Order) {
            return $o->getExpiresAt();
        }

        return date('Y-m-d H:i:s');
    }

    public function toApiArray(ServiceLicense $model, $deep = false, $identity = null): array
    {
        $result = [
            'license_key' => $model instanceof ServiceLicense ? $model->getLicenseKey() : $model->getLicenseKey(),
            'validate_ip' => (bool) ($model instanceof ServiceLicense ? $model->getValidateIp() : $model->getValidateIp()),
            'validate_host' => (bool) ($model instanceof ServiceLicense ? $model->getValidateHost() : $model->getValidateHost()),
            'validate_version' => (bool) ($model instanceof ServiceLicense ? $model->getValidateVersion() : $model->getValidateVersion()),
            'validate_path' => (bool) ($model instanceof ServiceLicense ? $model->getValidatePath() : $model->getValidatePath()),
            'ips' => $model instanceof ServiceLicense ? $model->getAllowedIps() : $model->getAllowedIps(),
            'hosts' => $model instanceof ServiceLicense ? $model->getAllowedHosts() : $model->getAllowedHosts(),
            'paths' => $model instanceof ServiceLicense ? $model->getAllowedPaths() : $model->getAllowedPaths(),
            'versions' => $model instanceof ServiceLicense ? $model->getAllowedVersions() : $model->getAllowedVersions(),
            'pinged_at' => $model instanceof ServiceLicense ? $model->getPingedAt() : $model->getPingedAt(),
        ];
        if ($identity instanceof Admin) {
            $result['plugin'] = $model instanceof ServiceLicense ? $model->getPlugin() : $model->getPlugin();
        }

        return $result;
    }

    /**
     * @param string $key
     */
    private function _addValue(ServiceLicense $model, $key, $value): void
    {
        $m = 'getAllowed' . ucfirst($key);
        $allowed = $model->{$m}();
        $allowed[] = $value;

        $encoded = json_encode(array_unique($allowed));
        if ($model instanceof ServiceLicense) {
            match ($key) {
                'ips' => $model->setIps($encoded),
                'hosts' => $model->setHosts($encoded),
                'paths' => $model->setPaths($encoded),
                'versions' => $model->setVersions($encoded),
                default => throw new \InvalidArgumentException("Unknown key: {$key}"),
            };
            $model->setUpdatedAt(new \DateTime());
        } else {
            $model->{$key} = $encoded;
            $model->updated_at = date('Y-m-d H:i:s');
        }
        $this->di['em']->persist($model);
        $this->di['em']->flush();
    }

    private function _getPlugin(ServiceLicense $model): ?object
    {
        $pluginName = $model instanceof ServiceLicense ? $model->getPlugin() : $model->getPlugin();
        $plugins = $this->getLicensePlugins();
        foreach ($plugins as $plugin) {
            if ($pluginName == $plugin['filename']) {
                require_once $plugin['path'];
                $class_name = 'Box\\Mod\\Servicelicense\\Plugin\\' . $pluginName;

                return new $class_name();
            }
        }
        if (isset($this->di['logger'])) {
            $modelId = $model instanceof ServiceLicense ? $model->getId() : $model->getId();
            $this->di['logger']->info('License #%s plugin %s is invalid.', $modelId, $pluginName);
        }

        return null;
    }

    public function update(ServiceLicense $s, array $data): bool
    {
        if ($s instanceof ServiceLicense) {
            $s->setPlugin($data['plugin'] ?? $s->getPlugin());
            $s->setValidateIp((bool) ($data['validate_ip'] ?? $s->getValidateIp()));
            $s->setValidateHost((bool) ($data['validate_host'] ?? $s->getValidateHost()));
            $s->setValidatePath((bool) ($data['validate_path'] ?? $s->getValidatePath()));
            $s->setValidateVersion((bool) ($data['validate_version'] ?? $s->getValidateVersion()));
            if (isset($data['license_key']) && !empty($data['license_key'])) {
                $s->setLicenseKey($data['license_key']);
            }

            foreach (['ips', 'hosts', 'paths', 'versions'] as $key) {
                if (isset($data[$key])) {
                    $array = explode(PHP_EOL, $data[$key]);
                    $array = array_map(trim(...), $array);
                    $array = array_diff($array, ['']);
                    $encoded = json_encode($array);
                    match ($key) {
                        'ips' => $s->setIps($encoded),
                        'hosts' => $s->setHosts($encoded),
                        'paths' => $s->setPaths($encoded),
                        'versions' => $s->setVersions($encoded),
                    };
                }
            }

            $s->setUpdatedAt(new \DateTime());
        } else {
            $s->plugin = $data['plugin'] ?? $s->getPlugin();
            $s->validate_ip = (bool) ($data['validate_ip'] ?? $s->getValidateIp());
            $s->validate_host = (bool) ($data['validate_host'] ?? $s->getValidateHost());
            $s->validate_path = (bool) ($data['validate_path'] ?? $s->getValidatePath());
            $s->validate_version = (bool) ($data['validate_version'] ?? $s->getValidateVersion());
            if (isset($data['license_key']) && !empty($data['license_key'])) {
                $s->license_key = $data['license_key'];
            }

            foreach (['ips', 'hosts', 'paths', 'versions'] as $key) {
                if (isset($data[$key])) {
                    $array = explode(PHP_EOL, $data[$key]);
                    $array = array_map(trim(...), $array);
                    $array = array_diff($array, ['']);
                    $s->{$key} = json_encode($array);
                }
            }

            $s->updated_at = date('Y-m-d H:i:s');
        }

        $this->di['em']->persist($s);
        $this->di['em']->flush();

        return true;
    }

    /**
     * @return array
     */
    public function checkLicenseDetails(array $data)
    {
        $result = [];
        $log = $this->di['logger']->setChannel('license');
        if (DEBUG) {
            $log->debug(print_r($data, true));
        }

        /*
         * Return error code in result field if related to license error
         * If error comes from FOSSBilling core use $result['error'] field.
         *
         * @since v2.7.1
         */
        if (isset($data['format']) && $data['format'] == 2) {
            $server = $this->di['license_server'];

            try {
                $result = $server->process($data);
                $result['error'] = null;
                $result['error_code'] = null;
            } catch (\LogicException $e) {
                $result['licensed_to'] = null;
                $result['created_at'] = null;
                $result['expires_at'] = null;
                $result['valid'] = false;
                $result['error'] = $e->getMessage();
                $result['error_code'] = $e->getCode();
            }

            return $result;
        }

        $server = $this->di['license_server'];

        return $server->process($data);
    }

    private function _getModelProperty(ServiceLicense $model, string $property): mixed
    {
        if ($model instanceof ServiceLicense) {
            return match ($property) {
                'id' => $model->getId(),
                'client_id' => $model->getClientId(),
                'license_key' => $model->getLicenseKey(),
                'validate_ip' => $model->getValidateIp(),
                'validate_host' => $model->getValidateHost(),
                'validate_path' => $model->getValidatePath(),
                'validate_version' => $model->getValidateVersion(),
                'ips' => $model->getIps(),
                'hosts' => $model->getHosts(),
                'paths' => $model->getPaths(),
                'versions' => $model->getVersions(),
                'config' => $model->getConfig(),
                'plugin' => $model->getPlugin(),
                'checked_at' => $model->getCheckedAt(),
                'pinged_at' => $model->getPingedAt(),
                'created_at' => $model->getCreatedAt(),
                'updated_at' => $model->getUpdatedAt(),
                default => null,
            };
        }

        return $model->{$property} ?? null;
    }

    private function _setModelProperty(ServiceLicense $model, string $property, mixed $value): void
    {
        if ($model instanceof ServiceLicense) {
            match ($property) {
                'id' => $model->setId($value),
                'client_id' => $model->setClientId($value),
                'license_key' => $model->setLicenseKey($value),
                'validate_ip' => $model->setValidateIp($value),
                'validate_host' => $model->setValidateHost($value),
                'validate_path' => $model->setValidatePath($value),
                'validate_version' => $model->setValidateVersion($value),
                'ips' => $model->setIps($value),
                'hosts' => $model->setHosts($value),
                'paths' => $model->setPaths($value),
                'versions' => $model->setVersions($value),
                'config' => $model->setConfig($value),
                'plugin' => $model->setPlugin($value),
                'checked_at' => $model->setCheckedAt(is_string($value) ? new \DateTime($value) : $value),
                'pinged_at' => $model->setPingedAt(is_string($value) ? new \DateTime($value) : $value),
                'created_at' => $model->setCreatedAt(is_string($value) ? new \DateTime($value) : $value),
                'updated_at' => $model->setUpdatedAt(is_string($value) ? new \DateTime($value) : $value),
                default => null,
            };

            return;
        }

        $model->{$property} = $value;
    }
}
