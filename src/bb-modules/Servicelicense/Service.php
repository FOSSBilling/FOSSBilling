<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Servicelicense;

use Box\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    /**
     * @var \Box_Di
     */
    protected $di = null;

    /**
     * @param \Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return \Box_Di
     */
    public function getDi()
    {
        return $this->di;
    }

    /**
     * Method called before adding product to cart.
     *
     * @return array
     */
    public function attachOrderConfig(\Model_Product $product, array $data)
    {
        $c = json_decode($product->config, 1);
        if (!is_array($c)) {
            return [];
        }

        return array_merge($c, $data);
    }

    /**
     * Method is called before adding product to cart.
     *
     * @return bool
     */
    public function validateOrderData(array &$data)
    {
        return true;
    }

    /**
     * @return array
     */
    public function getLicensePlugins()
    {
        $dir = dirname(__FILE__).'/Plugin/';
        $files = [];
        $directory = opendir($dir);
        while ($item = readdir($directory)) {
            // We filter the elements that we don't want to appear ".", ".." and ".svn"
            if (('.' != $item) && ('..' != $item) && ('.svn' != $item)) {
                $info = pathinfo($item);
                $info['path'] = $dir.$item;
                $files[] = $info;
            }
        }
        closedir($directory);

        return $files;
    }

    /**
     * @return \Model_ServiceLicense
     */
    public function action_create(\Model_ClientOrder $order)
    {
        $orderService = $this->di['mod_service']('order');
        $c = $orderService->getConfig($order);
        $this->validateOrderData($c);

        $model = $this->di['db']->dispense('ServiceLicense');
        $model->client_id = $order->client_id;
        $model->validate_ip = (bool) $this->di['array_get']($c, 'validate_ip', false);
        $model->validate_host = (bool) $this->di['array_get']($c, 'validate_host', false);
        $model->validate_path = (bool) $this->di['array_get']($c, 'validate_path', false);
        $model->validate_version = (bool) $this->di['array_get']($c, 'validate_version', false);
        $model->plugin = $this->di['array_get']($c, 'plugin', 'Simple');

        $model->ips = null;
        $model->versions = null;
        $model->hosts = null;
        $model->paths = null;

        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return $model;
    }

    /**
     * @return bool
     */
    public function action_activate(\Model_ClientOrder $order)
    {
        $orderService = $this->di['mod_service']('order');
        $c = $orderService->getConfig($order);
        $iterations = $this->di['array_get']($c, 'iterations', 10);
        $model = $orderService->getOrderService($order);
        if (!$model instanceof \Model_ServiceLicense) {
            throw new \Box_Exception('Could not activate order. Service was not created');
        }

        $plugin = $this->_getPlugin($model);

        if (!is_object($plugin)) {
            throw new \Box_Exception('License plugin :plugin was not found', [':plugin' => $model->plugin]);
        }

        if (!method_exists($plugin, 'generate')) {
            throw new \Box_Exception('License plugin do not have generate method');
        }

        if (method_exists($plugin, 'setDi')) {
            $plugin->setDi($this->di);
        }

        $i = 0;
        do {
            $licenseKey = $plugin->generate($model, $order, $c);
            if ($i++ >= $iterations) {
                throw new \Box_Exception('Maximum number of iterations reached while generating license key');
            }
        } while (null !== $this->di['db']->findOne('ServiceLicense', 'license_key = :license_key', [':license_key' => $licenseKey]));

        $model->license_key = $licenseKey;
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return true;
    }

    /**
     * @todo
     *
     * @return bool
     */
    public function action_renew(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     * @todo
     *
     * @return bool
     */
    public function action_suspend(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     * @todo
     *
     * @return bool
     */
    public function action_unsuspend(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     * @todo
     *
     * @return bool
     */
    public function action_cancel(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     * @todo
     *
     * @return bool
     */
    public function action_uncancel(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     * @return void
     */
    public function action_delete(\Model_ClientOrder $order)
    {
        $prderService = $this->di['mod_service']('order');
        $service = $prderService->getOrderService($order);
        if ($service instanceof \Model_ServiceLicense) {
            $this->di['db']->trash($service);
        }
    }

    public function reset(\Model_ServiceLicense $model)
    {
        $data = [
            'id' => $model->id,
            'ips' => $model->ips,
            'hosts' => $model->hosts,
            'paths' => $model->paths,
            'versions' => $model->versions,
            'client_id' => $model->client_id,
        ];
        $this->di['events_manager']->fire(['event' => 'onBeforeServicelicenseReset', 'params' => $data]);

        $model->ips = json_encode([]);
        $model->hosts = json_encode([]);
        $model->paths = json_encode([]);
        $model->versions = json_encode([]);
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);
        $this->di['logger']->info('Reset license %s information', $model->id);

        $data = [
            'id' => $model->id,
            'client_id' => $model->client_id,
            'updated_at' => $model->updated_at,
        ];
        $this->di['events_manager']->fire(['event' => 'onAfterServicelicenseReset', 'params' => $data]);

        return true;
    }

    public function isLicenseActive(\Model_ServiceLicense $model)
    {
        $prderService = $this->di['mod_service']('order');
        $o = $prderService->getServiceOrder($model);
        if ($o instanceof \Model_ClientOrder) {
            return \Model_ClientOrder::STATUS_ACTIVE == $o->status;
        }

        return false;
    }

    public function isValidIp(\Model_ServiceLicense $model, $value)
    {
        $defined = $model->getAllowedIps();
        if (empty($defined)) {
            $this->_addValue($model, 'ips', $value);

            return true;
        }

        if (!$model->validate_ip) {
            $this->_addValue($model, 'ips', $value);

            return true;
        }

        return in_array($value, $defined);
    }

    public function isValidVersion(\Model_ServiceLicense $model, $value)
    {
        $defined = $model->getAllowedVersions();
        if (empty($defined)) {
            $this->_addValue($model, 'versions', $value);

            return true;
        }

        if (!$model->validate_version) {
            $this->_addValue($model, 'versions', $value);

            return true;
        }

        return in_array($value, $defined);
    }

    public function isValidPath(\Model_ServiceLicense $model, $value)
    {
        $defined = $model->getAllowedPaths();
        if (empty($defined)) {
            $this->_addValue($model, 'paths', $value);

            return true;
        }

        if (!$model->validate_path) {
            $this->_addValue($model, 'paths', $value);

            return true;
        }

        return in_array($value, $defined);
    }

    public function isValidHost(\Model_ServiceLicense $model, $value)
    {
        $defined = $model->getAllowedHosts();
        if (empty($defined)) {
            $this->_addValue($model, 'hosts', $value);

            return true;
        }

        if (!$model->validate_host) {
            $this->_addValue($model, 'hosts', $value);

            return true;
        }

        return in_array($value, $defined);
    }

    public function getAdditionalParams(\Model_ServiceLicense $model, $data = [])
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

    public function getOwnerName(\Model_ServiceLicense $model)
    {
        $client = $this->di['db']->load('Client', $model->client_id);

        return $client->getFullName();
    }

    public function getExpirationDate(\Model_ServiceLicense $model)
    {
        $prderService = $this->di['mod_service']('order');
        $o = $prderService->getServiceOrder($model);
        if ($o instanceof \Model_ClientOrder) {
            return $o->expires_at;
        }

        return date('Y-m-d H:i:s');
    }

    public function toApiArray(\Model_ServiceLicense $model, $deep = false, $identity = null)
    {
        $result = [
            'license_key' => $model->license_key,
            'validate_ip' => (bool) $model->validate_ip,
            'validate_host' => (bool) $model->validate_host,
            'validate_version' => (bool) $model->validate_version,
            'validate_path' => (bool) $model->validate_path,
            'ips' => $model->getAllowedIps(),
            'hosts' => $model->getAllowedHosts(),
            'paths' => $model->getAllowedPaths(),
            'versions' => $model->getAllowedVersions(),
            'pinged_at' => $model->pinged_at,
        ];
        if ($identity instanceof \Model_Admin) {
            $result['plugin'] = $model->plugin;
        }

        return $result;
    }

    /**
     * @param string $key
     */
    private function _addValue(\Model_ServiceLicense $model, $key, $value)
    {
        $m = 'getAllowed'.ucfirst($key);
        $allowed = $model->{$m}();
        $allowed[] = $value;

        $model->{$key} = json_encode(array_unique($allowed));
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);
    }

    private function _getPlugin(\Model_ServiceLicense $model)
    {
        $plugins = $this->getLicensePlugins();
        foreach ($plugins as $plugin) {
            if ($model->plugin == $plugin['filename']) {
                require_once $plugin['path'];
                $class_name = 'Box\\Mod\\Servicelicense\\Plugin\\'.$model->plugin;

                return new $class_name();
            }
        }
        error_log(sprintf('License #%s plugin %s is not valid', $model->id, $model->plugin));

        return null;
    }

    public function update(\Model_ServiceLicense $s, array $data)
    {
        $s->plugin = $this->di['array_get']($data, 'plugin', $s->plugin);
        $s->validate_ip = (bool) $this->di['array_get']($data, 'validate_ip', $s->validate_ip);
        $s->validate_host = (bool) $this->di['array_get']($data, 'validate_host', $s->validate_host);
        $s->validate_path = (bool) $this->di['array_get']($data, 'validate_path', $s->validate_path);
        $s->validate_version = (bool) $this->di['array_get']($data, 'validate_version', $s->validate_version);
        if (isset($data['license_key']) && !empty($data['license_key'])) {
            $s->license_key = $data['license_key'];
        }

        foreach (['ips', 'hosts', 'paths', 'versions'] as $key) {
            if (isset($data[$key])) {
                $array = explode(PHP_EOL, $data[$key]);
                $array = array_map('trim', $array);
                $array = array_diff($array, ['']);
                $s->{$key} = json_encode($array);
            }
        }

        $s->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($s);

        return true;
    }

    /**
     * @return array
     */
    public function checkLicenseDetails(array $data)
    {
        $log = $this->di['logger'];
        $log->addWriter(new \Box_LogStream(BB_PATH_LOG.'/license.log'));
        if ($this->di['config']['debug']) {
            $log->debug(print_r($data, 1));
        }

        /**
         * Return error code in result field if related to license error
         * If error comes from FOSSBilling core use $result['error'] field.
         *
         * @since v2.7.1
         */
        if (isset($data['format']) && 2 == $data['format']) {
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

        /**
         * Old style api format return.
         *
         * @deprecated
         */
        if (isset($data['legacy']) && $data['legacy']) {
            $server = $this->di['license_server'];
            $array = $server->handle_deprecated($data);
            if (APPLICATION_ENV == 'testing') {
                return $array;
            }

            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
            header('Content-type: application/json; charset=utf-8');
            echo json_encode($array);
            exit();
        }

        $server = $this->di['license_server'];

        return $server->process($data);
    }
}
