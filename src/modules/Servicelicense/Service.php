<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicelicense;

use Box\Mod\Client\Entity\Client;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Servicelicense\Entity\ServiceLicense;
use Box\Mod\Servicelicense\Repository\ServiceLicenseRepository;
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

    /**
     * Top-level cart-config keys a client is authorized to set when ordering
     * a license product. Admin-controlled fields (plugin, validate_*, length,
     * prefix, iterations) are stripped from client input before the merge.
     *
     * @return list<string>
     */
    public function clientSettableConfigKeys(): array
    {
        return ['period', 'quantity'];
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

    public function action_create(\Model_ClientOrder $order): ServiceLicense
    {
        $orderService = $this->di['mod_service']('order');
        $c = $orderService->getConfig($order);
        $this->validateOrderData($c);

        $model = new ServiceLicense();
        $model->setClientId((int) $order->client_id);
        $model->setValidateIp((bool) ($c['validate_ip'] ?? false));
        $model->setValidateHost((bool) ($c['validate_host'] ?? false));
        $model->setValidatePath((bool) ($c['validate_path'] ?? false));
        $model->setValidateVersion((bool) ($c['validate_version'] ?? false));
        $model->setPlugin($c['plugin'] ?? 'Simple');

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        return $model;
    }

    public function action_activate(\Model_ClientOrder $order): bool
    {
        $orderService = $this->di['mod_service']('order');
        $c = $orderService->getConfig($order);
        $iterations = $c['iterations'] ?? 10;
        $model = $this->_getOrderService($order);

        $plugin = $this->_getPlugin($model);

        if (!is_object($plugin)) {
            throw new \FOSSBilling\Exception('License plugin :plugin was not found.', [':plugin' => $model->getPlugin()]);
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
        } while ($this->getRepository()->findByLicenseKey($licenseKey) !== null);

        $model->setLicenseKey($licenseKey);
        $this->di['em']->flush();

        return true;
    }

    /**
     * @todo
     */
    public function action_renew(\Model_ClientOrder $order): bool
    {
        return true;
    }

    /**
     * @todo
     */
    public function action_suspend(\Model_ClientOrder $order): bool
    {
        return true;
    }

    /**
     * @todo
     */
    public function action_unsuspend(\Model_ClientOrder $order): bool
    {
        return true;
    }

    /**
     * @todo
     */
    public function action_cancel(\Model_ClientOrder $order): bool
    {
        return true;
    }

    /**
     * @todo
     */
    public function action_uncancel(\Model_ClientOrder $order): bool
    {
        return true;
    }

    public function action_delete(\Model_ClientOrder $order): void
    {
        $model = $this->_getOrderService($order, false);
        if ($model instanceof ServiceLicense) {
            $this->di['em']->remove($model);
            $this->di['em']->flush();
        }
    }

    public function reset(ServiceLicense $model): bool
    {
        $data = [
            'id' => $model->getId(),
            'ips' => $model->getIps(),
            'hosts' => $model->getHosts(),
            'paths' => $model->getPaths(),
            'versions' => $model->getVersions(),
            'client_id' => $model->getClientId(),
        ];
        $this->di['events_manager']->fire(['event' => 'onBeforeServicelicenseReset', 'params' => $data]);

        $model->setIps(json_encode([]));
        $model->setHosts(json_encode([]));
        $model->setPaths(json_encode([]));
        $model->setVersions(json_encode([]));
        $this->di['em']->flush();
        $this->di['logger']->info('Reset license %s information', $model->getId());

        $data = [
            'id' => $model->getId(),
            'client_id' => $model->getClientId(),
            'updated_at' => $model->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
        $this->di['events_manager']->fire(['event' => 'onAfterServicelicenseReset', 'params' => $data]);

        return true;
    }

    public function isLicenseActive(ServiceLicense $model): bool
    {
        $orderService = $this->di['mod_service']('order');
        $o = $orderService->getServiceOrder($model);
        if (!$o instanceof Order) {
            return false;
        }

        if ($o->getStatus() !== Order::STATUS_ACTIVE) {
            return false;
        }

        $expiresAt = $o->getExpiresAt();
        if ($expiresAt !== null && $expiresAt->getTimestamp() <= time()) {
            return false;
        }

        return true;
    }

    public function isValidIp(ServiceLicense $model, $value)
    {
        $defined = $model->getAllowedIps();
        if (empty($defined)) {
            $this->_addValue($model, 'ips', $value);

            return true;
        }

        if (!$model->isValidateIp()) {
            $this->_addValue($model, 'ips', $value);

            return true;
        }

        return in_array($value, $defined);
    }

    public function isValidVersion(ServiceLicense $model, $value)
    {
        $defined = $model->getAllowedVersions();
        if (empty($defined)) {
            $this->_addValue($model, 'versions', $value);

            return true;
        }

        if (!$model->isValidateVersion()) {
            $this->_addValue($model, 'versions', $value);

            return true;
        }

        return in_array($value, $defined);
    }

    public function isValidPath(ServiceLicense $model, $value)
    {
        $defined = $model->getAllowedPaths();
        if (empty($defined)) {
            $this->_addValue($model, 'paths', $value);

            return true;
        }

        if (!$model->isValidatePath()) {
            $this->_addValue($model, 'paths', $value);

            return true;
        }

        return in_array($value, $defined);
    }

    public function isValidHost(ServiceLicense $model, $value)
    {
        $defined = $model->getAllowedHosts();
        if (empty($defined)) {
            $this->_addValue($model, 'hosts', $value);

            return true;
        }

        if (!$model->isValidateHost()) {
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

    public function getOwnerName(ServiceLicense $model): string
    {
        $clientId = $model->getClientId();
        if ($clientId === null) {
            return '';
        }

        $client = $this->di['em']->getRepository(Client::class)->find($clientId);

        return $client instanceof Client ? $client->getFullName() : '';
    }

    public function getExpirationDate(ServiceLicense $model)
    {
        $orderService = $this->di['mod_service']('order');
        $o = $orderService->getServiceOrder($model);
        if ($o instanceof Order) {
            return $o->getExpiresAt()?->format('Y-m-d H:i:s');
        }

        return date('Y-m-d H:i:s');
    }

    public function toApiArray(ServiceLicense $model, $deep = false, $identity = null): array
    {
        $result = [
            'license_key' => $model->getLicenseKey(),
            'validate_ip' => $model->isValidateIp(),
            'validate_host' => $model->isValidateHost(),
            'validate_version' => $model->isValidateVersion(),
            'validate_path' => $model->isValidatePath(),
            'ips' => $model->getAllowedIps(),
            'hosts' => $model->getAllowedHosts(),
            'paths' => $model->getAllowedPaths(),
            'versions' => $model->getAllowedVersions(),
            'pinged_at' => $model->getPingedAt()?->format('Y-m-d H:i:s'),
        ];
        if ($identity instanceof \Model_Admin) {
            $result['plugin'] = $model->getPlugin();
        }

        return $result;
    }

    /**
     * @param string $key
     */
    private function _addValue(ServiceLicense $model, $key, $value): void
    {
        $allowed = match ($key) {
            'ips' => $model->getAllowedIps(),
            'hosts' => $model->getAllowedHosts(),
            'paths' => $model->getAllowedPaths(),
            'versions' => $model->getAllowedVersions(),
            default => throw new \InvalidArgumentException("Unknown list key: {$key}"),
        };
        $allowed[] = $value;
        $json = json_encode(array_unique($allowed));

        match ($key) {
            'ips' => $model->setIps($json),
            'hosts' => $model->setHosts($json),
            'paths' => $model->setPaths($json),
            default => $model->setVersions($json),
        };
        $this->di['em']->flush();
    }

    private function _getPlugin(ServiceLicense $model): ?object
    {
        $plugins = $this->getLicensePlugins();
        foreach ($plugins as $plugin) {
            if ($model->getPlugin() == $plugin['filename']) {
                require_once $plugin['path'];
                $class_name = 'Box\\Mod\\Servicelicense\\Plugin\\' . $model->getPlugin();

                return new $class_name();
            }
        }
        if (isset($this->di['logger'])) {
            $this->di['logger']->info('License #%s plugin %s is invalid.', $model->getId(), $model->getPlugin());
        }

        return null;
    }

    public function update(ServiceLicense $s, array $data): bool
    {
        $s->setPlugin($data['plugin'] ?? $s->getPlugin());
        $s->setValidateIp((bool) ($data['validate_ip'] ?? $s->isValidateIp()));
        $s->setValidateHost((bool) ($data['validate_host'] ?? $s->isValidateHost()));
        $s->setValidatePath((bool) ($data['validate_path'] ?? $s->isValidatePath()));
        $s->setValidateVersion((bool) ($data['validate_version'] ?? $s->isValidateVersion()));
        if (isset($data['license_key']) && !empty($data['license_key'])) {
            $s->setLicenseKey($data['license_key']);
        }

        foreach (['ips', 'hosts', 'paths', 'versions'] as $key) {
            if (isset($data[$key])) {
                $array = explode(PHP_EOL, $data[$key]);
                $array = array_map(trim(...), $array);
                $array = array_diff($array, ['']);
                $json = json_encode(array_values($array));
                match ($key) {
                    'ips' => $s->setIps($json),
                    'hosts' => $s->setHosts($json),
                    'paths' => $s->setPaths($json),
                    default => $s->setVersions($json),
                };
            }
        }

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
        // @phpstan-ignore if.alwaysFalse (DEBUG is a runtime constant that may be true during debugging)
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

    private function _getOrderService(\Model_ClientOrder $order, bool $required = true): ?ServiceLicense
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof ServiceLicense) {
            if ($required) {
                throw new \FOSSBilling\Exception('Could not find associated service license');
            }

            return null;
        }

        return $model;
    }

    private function getRepository(): ServiceLicenseRepository
    {
        return $this->di['em']->getRepository(ServiceLicense::class);
    }
}
