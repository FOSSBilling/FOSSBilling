<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\ProductType\License;

use FOSSBilling\Exception;
use FOSSBilling\Interfaces\ProductTypeHandlerInterface;
use FOSSBilling\ProductType\License\Entity\License;
use FOSSBilling\ProductType\License\Repository\LicenseRepository;
use Pimple\Container;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

class LicenseHandler implements ProductTypeHandlerInterface
{
    protected ?Container $di = null;
    protected ?LicenseRepository $repository = null;

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
    {
        return $this->di;
    }

    protected function getRepository(): LicenseRepository
    {
        if ($this->repository === null) {
            $this->repository = $this->di['em']->getRepository(License::class);
        }

        return $this->repository;
    }

    protected function loadEntity(int $id): License
    {
        $entity = $this->getRepository()->find($id);
        if (!$entity instanceof License) {
            throw new Exception('License not found');
        }

        return $entity;
    }

    public function attachOrderConfig(\Model_Product $product, array $data): array
    {
        $config = json_decode($product->config ?? '', true) ?? [];

        return array_merge($config, $data);
    }

    public function validateOrderData(array &$data): bool
    {
        return true;
    }

    public function getLicensePlugins(): array
    {
        $dir = Path::join(__DIR__, 'plugins');
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

    public function create(\Model_ClientOrder $order): License
    {
        $orderService = $this->di['mod_service']('order');
        $c = $orderService->getConfig($order);
        $this->validateOrderData($c);

        $license = new License($order->client_id);
        $license->setValidateIp((bool) ($c['validate_ip'] ?? false));
        $license->setValidateHost((bool) ($c['validate_host'] ?? false));
        $license->setValidatePath((bool) ($c['validate_path'] ?? false));
        $license->setValidateVersion((bool) ($c['validate_version'] ?? false));
        $license->setPlugin($c['plugin'] ?? 'Simple');
        $license->setIps(null);
        $license->setVersions(null);
        $license->setHosts(null);
        $license->setPaths(null);

        $em = $this->di['em'];
        $em->persist($license);
        $em->flush();

        return $license;
    }

    public function activate(\Model_ClientOrder $order): bool
    {
        $orderService = $this->di['mod_service']('order');
        $c = $orderService->getConfig($order);
        $iterations = $c['iterations'] ?? 10;

        if ($order->service_id === null) {
            throw new Exception('Could not activate order. Service was not created');
        }

        $license = $this->loadEntity((int) $order->service_id);

        $plugin = $this->getPlugin($license);

        if (!is_object($plugin)) {
            throw new Exception('License plugin :plugin was not found.', [':plugin' => $license->getPlugin()]);
        }

        if (!method_exists($plugin, 'generate')) {
            throw new Exception('License plugin does not have generate method');
        }

        if (method_exists($plugin, 'setDi')) {
            $plugin->setDi($this->di);
        }

        $i = 0;
        do {
            $licenseKey = $plugin->generate($license, $order, $c);
            if ($i++ >= $iterations) {
                throw new Exception('Maximum number of iterations reached while generating license key');
            }
        } while ($this->getRepository()->findOneByLicenseKey($licenseKey) !== null);

        $license->setLicenseKey($licenseKey);

        $em = $this->di['em'];
        $em->persist($license);
        $em->flush();

        return true;
    }

    public function renew(\Model_ClientOrder $order): bool
    {
        return true;
    }

    public function suspend(\Model_ClientOrder $order): bool
    {
        return true;
    }

    public function unsuspend(\Model_ClientOrder $order): bool
    {
        return true;
    }

    public function cancel(\Model_ClientOrder $order): bool
    {
        return true;
    }

    public function uncancel(\Model_ClientOrder $order): bool
    {
        return true;
    }

    public function delete(\Model_ClientOrder $order): void
    {
        $orderService = $this->di['mod_service']('order');
        $service = $orderService->getOrderService($order);

        if ($service instanceof License) {
            $em = $this->di['em'];
            $em->remove($service);
            $em->flush();
        }
    }

    public function reset(License $model): bool
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

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();

        $this->di['logger']->info('Reset license %s information', $model->getId());

        $data = [
            'id' => $model->getId(),
            'client_id' => $model->getClientId(),
            'updated_at' => $model->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
        $this->di['events_manager']->fire(['event' => 'onAfterServicelicenseReset', 'params' => $data]);

        return true;
    }

    public function isLicenseActive(License $model): bool
    {
        $orderService = $this->di['mod_service']('order');
        $o = $orderService->getServiceOrder($model);

        return $o instanceof \Model_ClientOrder && $o->status === \Model_ClientOrder::STATUS_ACTIVE;
    }

    public function isValidIp(License $model, $value): bool
    {
        $defined = $model->getAllowedIps();
        if (empty($defined)) {
            $this->addValue($model, 'ips', $value);

            return true;
        }

        if (!$model->isValidateIp()) {
            $this->addValue($model, 'ips', $value);

            return true;
        }

        return in_array($value, $defined);
    }

    public function isValidVersion(License $model, $value): bool
    {
        $defined = $model->getAllowedVersions();
        if (empty($defined)) {
            $this->addValue($model, 'versions', $value);

            return true;
        }

        if (!$model->isValidateVersion()) {
            $this->addValue($model, 'versions', $value);

            return true;
        }

        return in_array($value, $defined);
    }

    public function isValidPath(License $model, $value): bool
    {
        $defined = $model->getAllowedPaths();
        if (empty($defined)) {
            $this->addValue($model, 'paths', $value);

            return true;
        }

        if (!$model->isValidatePath()) {
            $this->addValue($model, 'paths', $value);

            return true;
        }

        return in_array($value, $defined);
    }

    public function isValidHost(License $model, $value): bool
    {
        $defined = $model->getAllowedHosts();
        if (empty($defined)) {
            $this->addValue($model, 'hosts', $value);

            return true;
        }

        if (!$model->isValidateHost()) {
            $this->addValue($model, 'hosts', $value);

            return true;
        }

        return in_array($value, $defined);
    }

    public function getAdditionalParams(License $model, $data = []): array
    {
        $plugin = $this->getPlugin($model);
        if (is_object($plugin) && method_exists($plugin, 'validate')) {
            $res = $plugin->validate($model, $data);
            if (is_array($res)) {
                return $res;
            }
        }

        return [];
    }

    public function getOwnerName(License $model): string
    {
        $client = $this->di['db']->load('Client', $model->getClientId());

        return $client->getFullName();
    }

    public function getExpirationDate(License $model): string
    {
        $orderService = $this->di['mod_service']('order');
        $o = $orderService->getServiceOrder($model);

        if ($o instanceof \Model_ClientOrder) {
            return $o->expires_at;
        }

        return date('Y-m-d H:i:s');
    }

    public function toApiArray(License $model, $deep = false, $identity = null): array
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

    private function addValue(License $model, $key, $value): void
    {
        $m = 'getAllowed' . ucfirst($key);
        $allowed = $model->{$m}();
        $allowed[] = $value;

        switch ($key) {
            case 'ips':
                $model->setIps(json_encode(array_unique($allowed)));

                break;
            case 'hosts':
                $model->setHosts(json_encode(array_unique($allowed)));

                break;
            case 'paths':
                $model->setPaths(json_encode(array_unique($allowed)));

                break;
            case 'versions':
                $model->setVersions(json_encode(array_unique($allowed)));

                break;
        }

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();
    }

    private function getPlugin(License $model): ?object
    {
        $plugins = $this->getLicensePlugins();
        foreach ($plugins as $plugin) {
            if ($model->getPlugin() == $plugin['filename']) {
                require_once $plugin['path'];
                $className = 'FOSSBilling\\ProductType\\License\\plugins\\' . $model->getPlugin();
                if (class_exists($className)) {
                    return new $className();
                }

                error_log("License #{$model->getId()} plugin {$model->getPlugin()} class is invalid.");

                return null;
            }
        }
        error_log("License #{$model->getId()} plugin {$model->getPlugin()} is invalid.");

        return null;
    }

    public function update(License $s, array $data): bool
    {
        if (isset($data['plugin'])) {
            $s->setPlugin($data['plugin']);
        }
        if (isset($data['validate_ip'])) {
            $s->setValidateIp((bool) $data['validate_ip']);
        }
        if (isset($data['validate_host'])) {
            $s->setValidateHost((bool) $data['validate_host']);
        }
        if (isset($data['validate_path'])) {
            $s->setValidatePath((bool) $data['validate_path']);
        }
        if (isset($data['validate_version'])) {
            $s->setValidateVersion((bool) $data['validate_version']);
        }
        if (isset($data['license_key']) && !empty($data['license_key'])) {
            $s->setLicenseKey($data['license_key']);
        }

        foreach (['ips', 'hosts', 'paths', 'versions'] as $key) {
            if (isset($data[$key])) {
                $array = explode(PHP_EOL, $data[$key]);
                $array = array_map(trim(...), $array);
                $array = array_diff($array, ['']);
                switch ($key) {
                    case 'ips':
                        $s->setIps(json_encode($array));

                        break;
                    case 'hosts':
                        $s->setHosts(json_encode($array));

                        break;
                    case 'paths':
                        $s->setPaths(json_encode($array));

                        break;
                    case 'versions':
                        $s->setVersions(json_encode($array));

                        break;
                }
            }
        }

        $em = $this->di['em'];
        $em->persist($s);
        $em->flush();

        return true;
    }

    public function checkLicenseDetails(array $data): array
    {
        $result = [];
        $log = $this->di['logger']->setChannel('license');
        if (DEBUG) {
            $log->debug(print_r($data, true));
        }

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
}
