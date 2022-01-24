<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Servicelicense;

class Server implements \Box\InjectionAwareInterface
{
    private $_log = NULL;

    private $_result = array(
        'licensed_to' => NULL,
        'created_at'  => NULL,
        'expires_at'  => NULL,
        'valid'       => FALSE,
    );

    protected $di;

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

    public function __construct(\Box_Log $log)
    {
        $this->_log = $log;
    }

    /**
     * @deprecated
     * @param type $data
     * @return type
     */
    public function handle_deprecated($data)
    {
        try {
            $this->process($data);
        } catch (\LogicException $e) {
            $this->_log->info($e->getMessage() . ' ' . $e->getCode());
            $this->_result['error']      = $e->getMessage();
            $this->_result['error_code'] = $e->getCode();
        } catch (\Exception $e) {
            error_log($e);
            $this->_log->info($e->getMessage() . ' ' . $e->getCode());
            $this->_result['error']      = 'Licensing server is temporary unavailable';
            $this->_result['error_code'] = $e->getCode();
        }

        return $this->_result;
    }

    /**
     * @param string $key
     */
    private function getServer($key = null, $default = null)
    {
        if (null === $key) {
            return $_SERVER;
        }

        return (isset($_SERVER[$key])) ? $_SERVER[$key] : $default;
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
        $ip      = trim($ips_arr[0]);

        return $ip;
    }

    public function process($data)
    {
        if (!is_array($data)) {
            $data = (json_decode($data, true)) ? json_decode($data, true) : array();
        }

        if (empty ($data)) {
            throw new \LogicException('Invalid request. Parameters missing?', 1000);
        }

        if (!isset($data['license']) || empty($data['license'])) {
            throw new \LogicException('License key is not present in call', 1001);
        }

        $service = $this->di['mod_service']('servicelicense');
        $model   = $this->di['db']->findOne('ServiceLicense', 'license_key = :license_key', array(':license_key' => $data['license']));

        if (!$model instanceof \Model_ServiceLicense) {
            throw new \LogicException('Your license key is not valid.', 1005);
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

        $ip      = $this->getIp();
        $host    = $data['host'];
        $version = $data['version'];
        $path    = $data['path'];

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
            throw new \LogicException(sprintf('Version "%s" is not valid for this license', $version), 1009);
        }

        if (!$service->isValidPath($model, $path)) {
            throw new \LogicException(sprintf('Software install path "%s" is not valid for this license', $path), 1010);
        }

        $this->_result['licensed_to'] = $service->getOwnerName($model);
        $this->_result['created_at']  = $model->created_at;
        $this->_result['expires_at']  = $service->getExpirationDate($model);
        $this->_result['valid']       = TRUE;

        $array = $service->getAdditionalParams($model, $data);
        if (!empty($array)) {
            foreach ($array as $k => $v) {
                $this->_result[$k] = $v;
            }
        }

        return $this->_result;
    }
}