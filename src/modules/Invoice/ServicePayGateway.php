<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Invoice;

use FOSSBilling\InjectionAwareInterface;

class ServicePayGateway implements InjectionAwareInterface
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

    public function getSearchQuery(array $data)
    {
        $sql = 'SELECT *
            FROM pay_gateway
            WHERE 1 ';

        $search = $data['search'] ?? null;
        $params = [];
        if ($search) {
            $sql .= 'AND name LIKE :search';
            $params[':search'] = "%$search%";
        }

        $sql .= ' ORDER by gateway ASC';

        return [$sql, $params];
    }

    /**
     * @return mixed[]
     */
    public function getPairs(): array
    {
        $sql = 'SELECT id, gateway, name
            FROM pay_gateway';

        $rows = $this->di['db']->getAll($sql);
        $result = [];
        foreach ($rows as $row) {
            $result[$row['id']] = $row['name'];
        }

        return $result;
    }

    /**
     * @return mixed[]
     */
    public function getAvailable(): array
    {
        $sql = 'SELECT id, gateway, name
            FROM pay_gateway';

        $rows = $this->di['db']->getAll($sql);
        $exists = [];
        foreach ($rows as $row) {
            $exists[$row['gateway']] = $row['name'];
        }

        $pattern = PATH_LIBRARY . '/Payment/Adapter/*.php';
        $adapters = [];
        foreach (glob($pattern) as $path) {
            $adapter = pathinfo($path, PATHINFO_FILENAME);
            if (!array_key_exists($adapter, $exists)) {
                $adapters[] = $adapter;
            }
        }
        $pattern = PATH_LIBRARY . '/Payment/Adapter/*/*.php';
        foreach (glob($pattern) as $path) {
            $directory = explode('/', pathinfo($path, PATHINFO_DIRNAME));
            $adapter = end($directory);
            if (!array_key_exists($adapter, $exists)) {
                if ($path == PATH_LIBRARY . '/Payment/Adapter/' . $adapter . '/' . $adapter . '.php') {
                    $adapters[] = $adapter;
                }
            }
        }

        return $adapters;
    }

    public function install($code)
    {
        $available = $this->getAvailable();
        if (!in_array($code, $available)) {
            throw new \FOSSBilling\Exception('Payment gateway is not available for installation.');
        }

        $new = $this->di['db']->dispense('PayGateway');
        $new->name = $code;
        $new->gateway = $code;
        $new->enabled = 0;
        $new->accepted_currencies = null;
        $new->test_mode = 0;
        $new->config = null;
        $this->di['db']->store($new);

        $this->di['logger']->info('Installed new payment gateway %s', $code);

        return true;
    }

    public function toApiArray(\Model_PayGateway $model, $deep = false, $identity = null): array
    {
        [$single, $recurrent] = $this->_getAllowTuple($model);

        $result = [
            'id' => $model->id,
            'code' => $model->gateway,
            'title' => $model->name,
            'allow_single' => $model->allow_single,
            'allow_recurrent' => $model->allow_recurrent,
            'accepted_currencies' => $this->getAcceptedCurrencies($model),
        ];

        if ($identity instanceof \Model_Admin) {
            $result['supports_one_time_payments'] = $single;
            $result['supports_subscriptions'] = $recurrent;
            if (!empty($model->config) && json_validate($model->config)) {
                $result['config'] = json_decode($model->config, true);
            } else {
                $result['config'] = [];
            }
            $result['form'] = $this->getFormElements($model);
            $result['description'] = $this->getDescription($model);
            $result['enabled'] = $model->enabled;
            $result['test_mode'] = $model->test_mode;
            $result['callback'] = $this->getCallbackUrl($model);
        }

        return $result;
    }

    public function copy(\Model_PayGateway $model)
    {
        $new = $this->di['db']->dispense('PayGateway');
        $new->name = $model->name . ' (Copy)';
        $new->gateway = $model->gateway;
        $new->enabled = 0;
        $new->accepted_currencies = $model->accepted_currencies;
        $new->test_mode = $model->test_mode;
        $new->config = $model->config;
        $newId = $this->di['db']->store($new);
        $this->di['logger']->info('Copied payment gateway #%s - %s', $newId, $model->gateway);

        return $newId;
    }

    public function update(\Model_PayGateway $model, array $data)
    {
        $model->name = $data['title'] ?? $model->name;
        if (isset($data['config']) && is_array($data['config'])) {
            $model->config = json_encode($data['config']);
        }

        if (isset($data['accepted_currencies']) && is_array($data['accepted_currencies'])) {
            $model->accepted_currencies = json_encode($data['accepted_currencies']);
        }

        $model->enabled = $data['enabled'] ?? $model->enabled;
        $model->allow_single = (bool) ($data['allow_single'] ?? $model->allow_single);
        $model->allow_recurrent = (bool) ($data['allow_recurrent'] ?? $model->allow_recurrent);
        $model->test_mode = $data['test_mode'] ?? $model->test_mode;
        $this->di['db']->store($model);
        $this->di['logger']->info('Updated payment gateway %s', $model->gateway);

        return true;
    }

    public function delete(\Model_PayGateway $model)
    {
        $id = $model->id;
        $this->di['db']->trash($model);
        $this->di['logger']->info('Removed payment gateway %s', $id);

        return true;
    }

    /**
     * @return mixed[]
     */
    public function getActive(array $data): array
    {
        $format = $data['format'] ?? null;

        $gateways = $this->di['db']->find('PayGateway', 'enabled = 1 ORDER BY id desc');
        $result = [];
        foreach ($gateways as $gtw) {
            if ($format == 'pairs') {
                $result[$gtw->id] = $gtw->name;
            } else {
                $gateway = $this->toApiArray($gtw);
                $adapter = $this->getPaymentAdapter($gtw);
                if (array_key_exists('logo', $adapter->getConfig())) {
                    $gateway['logo'] = $adapter->getConfig()['logo'];
                    if (file_exists(PATH_LIBRARY . '/Payment/Adapter/' . $adapter->getConfig()['logo']['logo'])) {
                        $gateway['logo']['logo'] = $this->di['tools']->url('/library/Payment/Adapter/' . $adapter->getConfig()['logo']['logo']);
                    } else {
                        if (file_exists(PATH_DATA . '/assets/gateways/' . $adapter->getConfig()['logo']['logo'])) {
                            $gateway['logo']['logo'] = $this->di['tools']->url('/data/assets/gateways/' . $adapter->getConfig()['logo']['logo']);
                        } else {
                            $gateway['logo']['logo'] = $this->di['tools']->url('/data/assets/gateways/default.png');
                        }
                    }
                } else {
                    $gateway['logo']['logo'] = $this->di['tools']->url('/data/assets/gateways/default.png');
                }
                $result[] = $gateway;
            }
        }

        return $result;
    }

    public function canPerformRecurrentPayment(\Model_PayGateway $model)
    {
        return (bool) $model->allow_recurrent;
    }

    public function getPaymentAdapter(\Model_PayGateway $pg, \Model_Invoice $model = null, $optional = [])
    {
        if (is_string($pg->config) && json_validate($pg->config)) {
            $config = json_decode($pg->config, true);
        } else {
            $config = [];
        }
        $defaults = [];
        $defaults['auto_redirect'] = false;
        $defaults['test_mode'] = $pg->test_mode;
        $defaults['return_url'] = $this->getReturnUrl($pg, $model);
        $defaults['cancel_url'] = $this->getCancelUrl($pg, $model);
        $defaults['notify_url'] = $this->getCallbackUrl($pg, $model);
        $defaults['redirect_url'] = $this->getCallbackRedirect($pg, $model);
        $defaults['continue_shopping_url'] = $this->di['tools']->url('/order');
        $defaults['single_page'] = true;
        if ($model instanceof \Model_Invoice) {
            $defaults['thankyou_url'] = $this->di['url']->link('/invoice/thank-you/' . $model->hash, ['restore_session' => session_id()]);
            $defaults['invoice_url'] = $this->di['tools']->url('/invoice/' . $model->hash);
        }

        if (isset($optional['auto_redirect'])) {
            $defaults['auto_redirect'] = $optional['auto_redirect'];
        }
        $defaults['logo'] = null;

        $config = array_merge($config, $defaults);

        $class = $this->getAdapterClassName($pg);

        if (!class_exists($class)) {
            throw new \FOSSBilling\Exception('Payment gateway :adapter was not found', [':adapter' => $class]);
        }

        $adapter = new $class($config);

        if (method_exists($adapter, 'setDi')) {
            $adapter->setDi($this->di);
        }

        return $adapter;
    }

    private function _getAllowTuple(\Model_PayGateway $model)
    {
        $adapter_config = $this->getAdapterConfig($model);
        $single = $adapter_config['supports_one_time_payments'] ?? false;
        $recurrent = $adapter_config['supports_subscriptions'] ?? false;

        return [
            $single,
            $recurrent,
        ];
    }

    public function getAdapterConfig(\Model_PayGateway $pg)
    {
        $class = $this->getAdapterClassName($pg);
        if (!file_exists(PATH_LIBRARY . '/Payment/Adapter/' . $pg->gateway . '.php')) {
            if (!file_exists(PATH_LIBRARY . '/Payment/Adapter/' . $pg->gateway . '/' . $pg->gateway . '.php')) {
                throw new \FOSSBilling\Exception('Payment gateway :adapter was not found', [':adapter' => $pg->gateway]);
            }
        }

        if (!class_exists($class)) {
            throw new \FOSSBilling\Exception("Payment gateway class $class was not found");
        }

        if (!method_exists($class, 'getConfig')) {
            error_log("Payment $class gateway does not have getConfig method");

            return [];
        }

        return call_user_func([$class, 'getConfig']);
    }

    public function getAdapterClassName(\Model_PayGateway $pg)
    {
        $class = sprintf('Payment_Adapter_%s', $pg->gateway);
        if (!class_exists($class)) {
            include PATH_LIBRARY . '/Payment/Adapter/' . $pg->gateway . '/' . $pg->gateway . '.php';

            return sprintf('Payment_Adapter_%s', $pg->gateway);
        } else {
            return $class;
        }
    }

    public function getAcceptedCurrencies(\Model_PayGateway $model)
    {
        if ($model->accepted_currencies === null || empty($model->accepted_currencies)) {
            $currencyService = $this->di['mod_service']('Currency');

            return array_keys($currencyService->getPairs());
        }

        return json_decode($model->accepted_currencies, 1);
    }

    public function getFormElements(\Model_PayGateway $model)
    {
        $config = $this->getAdapterConfig($model);
        if (isset($config['form']) && is_array($config['form'])) {
            return $config['form'];
        }

        return [];
    }

    public function getDescription(\Model_PayGateway $model)
    {
        $config = $this->getAdapterConfig($model);

        return $config['description'] ?? null;
    }

    /**
     * @param \Model_Invoice $model
     */
    public function getCallbackUrl(\Model_PayGateway $pg, $model = null)
    {
        $p = [
            'gateway_id' => $pg->id,
        ];
        if ($model instanceof \Model_Invoice) {
            $p['invoice_id'] = $model->id;
        }

        return SYSTEM_URL . 'ipn.php?' . http_build_query($p);
    }

    /**
     * @param \Model_Invoice $model
     */
    private function getReturnUrl(\Model_PayGateway $pg, $model = null)
    {
        if ($model instanceof \Model_Invoice) {
            return $this->di['url']->link('/invoice/' . $model->hash, ['status' => 'ok', 'restore_session' => session_id()]);
        }

        return $this->di['url']->link('/invoice', ['status' => 'ok', 'restore_session' => session_id()]);
    }

    /**
     * @param \Model_Invoice $model
     */
    private function getCancelUrl(\Model_PayGateway $pg, $model = null)
    {
        if ($model instanceof \Model_Invoice) {
            return $this->di['url']->link('/invoice/' . $model->hash, ['status' => 'cancel', 'restore_session' => session_id()]);
        }

        return $this->di['url']->link('/invoice', ['status' => 'cancel', 'restore_session' => session_id()]);
    }

    /**
     * @param \Model_Invoice $model
     */
    private function getCallbackRedirect(\Model_PayGateway $pg, $model = null)
    {
        $p = [
            'gateway_id' => $pg->id,
        ];

        if ($model instanceof \Model_Invoice) {
            $p['invoice_id'] = $model->id;
            $p['invoice_hash'] = $model->hash;
            $p['redirect'] = 1;
        }

        return SYSTEM_URL . 'ipn.php?' . http_build_query($p);
    }
}
