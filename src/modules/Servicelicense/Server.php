<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicelicense;

class Server implements \FOSSBilling\InjectionAwareInterface
{
    private array $_result = [
        'licensed_to' => null,
        'created_at' => null,
        'expires_at' => null,
        'valid' => false,
    ];

    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function __construct(private readonly \Box_Log $_log)
    {
    }

    /**
     * @param string $key
     */
    private function getServer($key = null, $default = null)
    {
        if ($key === null) {
            return $_SERVER;
        }

        return $_SERVER[$key] ?? $default;
    }

    private function getIp($checkProxy = true)
    {
        $ip = null;
        if ($checkProxy && $this->getServer('HTTP_CLIENT_IP') != null) {
            $ip = $this->getServer('HTTP_CLIENT_IP');
        } else {
            if ($checkProxy && $this->getServer('HTTP_X_FORWARDED_FOR') != null) {
                $ip = $this->getServer('HTTP_X_FORWARDED_FOR');
            } else {
                $ip = $this->getServer('REMOTE_ADDR');
            }
        }

        $ips_arr = explode(',', $ip);

        return trim($ips_arr[0]);
    }

    public function process($data)
    {
        if (!is_array($data)) {
            $data = json_decode($data, true) ?: [];
        }

        if (empty($data)) {
            throw new \LogicException('Invalid request. Parameters missing?', 1000);
        }

        if (!isset($data['license']) || empty($data['license'])) {
            throw new \LogicException('License key is not present in call', 1001);
        }

        $service = $this->di['mod_service']('servicelicense');
        $model = $this->di['db']->findOne('ServiceLicense', 'license_key = :license_key', [':license_key' => $data['license']]);

        if (!$model instanceof \Model_ServiceLicense) {
            throw new \LogicException('Your license key is invalid.', 1005);
        }

        $model->pinged_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        if (!isset($data['host']) || empty($data['host'])) {
            throw new \LogicException('Host key is not present in call', 1002);
        }

        if (!isset($data['version']) || empty($data['version'])) {
            throw new \LogicException('Version key is not present in call', 1003);
        }

        if (!isset($data['path']) || empty($data['path'])) {
            throw new \LogicException('Path key is not present in call', 1004);
        }

        $ip = $this->getIp();
        $host = $data['host'];
        $version = $data['version'];
        $path = $data['path'];

        if (!$service->isLicenseActive($model)) {
            throw new \LogicException('License is not active', 1006);
        }

        if (!$service->isValidIp($model, $ip)) {
            throw new \LogicException(sprintf('Ip "%s" is not allowed for this license', $ip), 1007);
        }

        if (!$service->isValidHost($model, $host)) {
            throw new \LogicException(sprintf('Host "%s" is not allowed for this license', $host), 1008);
        }

        if (!$service->isValidVersion($model, $version)) {
            throw new \LogicException(sprintf('Version "%s" is invalid for this license', $version), 1009);
        }

        if (!$service->isValidPath($model, $path)) {
            throw new \LogicException(sprintf('Software install path "%s" is invalid for this license', $path), 1010);
        }

        $this->_result['licensed_to'] = $service->getOwnerName($model);
        $this->_result['created_at'] = $model->created_at;
        $this->_result['expires_at'] = $service->getExpirationDate($model);
        $this->_result['valid'] = true;

        $array = $service->getAdditionalParams($model, $data);
        if (!empty($array)) {
            foreach ($array as $k => $v) {
                $this->_result[$k] = $v;
            }
        }

        return $this->_result;
    }
}
