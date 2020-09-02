<?php
/**
* BoxBilling
*
* @copyright BoxBilling, Inc (http://www.boxbilling.com)
* @license   Apache-2.0
*
* Copyright BoxBilling, Inc
* This source file is subject to the Apache-2.0 License that is bundled
* with this source code in the file LICENSE
*/

namespace Box\Mod\Invoice;
use Box\InjectionAwareInterface;

class ServicePayGateway implements InjectionAwareInterface
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


    public function getSearchQuery(array $data)
    {
        $sql = 'SELECT *
            FROM pay_gateway
            WHERE 1 ';

        $search = $this->di['array_get']($data, 'search', NULL);
        $params = array();
        if($search) {
            $sql .= 'AND m.name LIKE :search';
            $params['search'] = "%$search%";
        }

        $sql .= ' ORDER by gateway ASC';

        return array($sql, $params);
    }

    public function getPairs()
    {
        $sql = 'SELECT id, gateway, name
            FROM pay_gateway';

        $rows = $this->di['db']->getAll($sql);
        $result = array();
        foreach ($rows as $row){
            $result[ $row['id'] ] = $row['name'];
        }
        return $result;
    }

    public function getAvailable()
    {
        $sql = 'SELECT id, gateway, name
            FROM pay_gateway';

        $rows = $this->di['db']->getAll($sql);
        $exists = array();
        foreach ($rows as $row){
            $exists[ $row['gateway'] ] = $row['name'];
        }

        $pattern = BB_PATH_LIBRARY.'/Payment/Adapter/*.php';
        $adapters = array();
        foreach(glob($pattern) as $path) {
            $adapter = pathinfo($path, PATHINFO_FILENAME);
            if(!array_key_exists($adapter, $exists)) {
                $adapters[] = $adapter;
            }
        }

        return $adapters;
    }

    public function install($code)
    {
        $available = $this->getAvailable();
        if(!in_array($code, $available)) {
            throw new \Box_Exception('Payment gateway is not available for installation.');
        }

        $new = $this->di['db']->dispense('PayGateway');
        $new->name = $code;
        $new->gateway = $code;
        $new->enabled = 0;
        $new->accepted_currencies = NULL;
        $new->test_mode = 0;
        $new->config = NULL;
        $this->di['db']->store($new);

        $this->di['logger']->info('Installed new payment gateway %s', $code);
        return true;
    }

    public function toApiArray(\Model_PayGateway $model, $deep = false, $identity = null)
    {
        list($single, $recurrent) = $this->_getAllowTuple($model);

        $result = array(
            'id'                        =>  $model->id,
            'code'                      =>  $model->gateway,
            'title'                     =>  $model->name,
            'allow_single'              =>  $model->allow_single,
            'allow_recurrent'           =>  $model->allow_recurrent,
            'accepted_currencies'       =>  $this->getAcceptedCurrencies($model),
        );

        if($identity instanceof \Model_Admin) {
            $result['supports_one_time_payments']  = $single;
            $result['supports_subscriptions']      = $recurrent;
            $result['config']               = json_decode($model->config, 1);
            $result['form']                 = $this->getFormElements($model);
            $result['description']          = $this->getDescription($model);
            $result['enabled']              = $model->enabled;
            $result['test_mode']            = $model->test_mode;
            $result['callback']             = $this->getCallbackUrl($model);
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
        $model->name = $this->di['array_get']($data, 'title', $model->name);
        if(isset($data['config']) && is_array($data['config'])) {
            $model->config = json_encode($data['config']);
        }

        if(isset($data['accepted_currencies']) && is_array($data['accepted_currencies'])) {
            $model->accepted_currencies = json_encode($data['accepted_currencies']);
        }

        $model->enabled         = $this->di['array_get']($data, 'enabled', $model->enabled);
        $model->allow_single    = (bool)$this->di['array_get']($data, 'allow_single', $model->allow_single);
        $model->allow_recurrent = (bool)$this->di['array_get']($data, 'allow_recurrent', $model->allow_recurrent);
        $model->test_mode       = $this->di['array_get']($data, 'test_mode', $model->test_mode);
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

    public function getActive(array $data)
    {
        $format = $this->di['array_get']($data, 'format', null);

        $gateways = $this->di['db']->find('PayGateway', 'enabled = 1 ORDER BY id desc');
        $result = array();
        foreach($gateways as $gtw) {
            if($format == 'pairs') {
                $result[$gtw->id] = $gtw->name;
            } else {
                $result[] = $this->toApiArray($gtw);
            }
        }
        return $result;
    }

    public function canPerformRecurrentPayment(\Model_PayGateway $model)
    {
        return (bool)$model->allow_recurrent;
    }

    public function getPaymentAdapter(\Model_PayGateway $pg, \Model_Invoice $model = null, $optional = array())
    {
        $config = $this->di['tools']->decodeJ($pg->config);
        $defaults = array();
        $defaults['auto_redirect']  = false;
        $defaults['test_mode']      = $pg->test_mode;
        $defaults['return_url']     = $this->getReturnUrl($pg, $model);
        $defaults['cancel_url']     = $this->getCancelUrl($pg, $model);
        $defaults['notify_url']     = $this->getCallbackUrl($pg, $model);
        $defaults['redirect_url']   = $this->getCallbackRedirect($pg, $model);
        $defaults['continue_shopping_url'] = $this->di['tools']->url('/order');
        $defaults['single_page'] = true;
        if($model instanceof \Model_Invoice) {
            $defaults['thankyou_url']     = $this->di['tools']->url('/invoice/thank-you/'.$model->hash);
            $defaults['invoice_url']     = $this->di['tools']->url('/invoice/'.$model->hash);
        }

        if(isset($optional['auto_redirect'])) {
            $defaults['auto_redirect'] = $optional['auto_redirect'];
        }

        $config = array_merge($config, $defaults);

        $class = $this->getAdapterClassName($pg);

        if(!class_exists($class)) {
            throw new \Box_Exception("Payment gateway :adapter was not found", array(':adapter'=>$class));
        }

        $adapter = new $class($config);

        if(method_exists($adapter, 'setDi')) {
            $adapter->setDi($this->di);
        }

        return $adapter;
    }

    private function _getAllowTuple(\Model_PayGateway $model)
    {
        $adapter_config = $this->getAdapterConfig($model);
        $single = $this->di['array_get']($adapter_config, 'supports_one_time_payments', FALSE);
        $recurrent = $this->di['array_get']($adapter_config, 'supports_subscriptions', FALSE);

        return array(
            $single,
            $recurrent,
        );
    }

    public function getAdapterConfig(\Model_PayGateway $pg)
    {
        $class = $this->getAdapterClassName($pg);
        if(!file_exists(BB_PATH_LIBRARY.'/Payment/Adapter/'.$pg->gateway.'.php')) {
            throw new \Box_Exception("Payment gateway :adapter was not found", array(':adapter'=>$pg->gateway));
        }

        if(!class_exists($class)) {
            throw new \Box_Exception("Payment gateway class $class was not found");
        }

        if(!method_exists($class, 'getConfig')) {
            error_log("Payment $class gateway does not have getConfig method");
            return array();
        }

        return call_user_func(array($class, 'getConfig'));
    }

    public function getAdapterClassName(\Model_PayGateway $pg)
    {
        return sprintf('Payment_Adapter_%s', $pg->gateway);
    }

    public function getAcceptedCurrencies(\Model_PayGateway $model)
    {
        if(null === $model->accepted_currencies || empty($model->accepted_currencies)) {
            $currencyService = $this->di['mod_service']('Currency');
            return array_keys($currencyService->getPairs());
        }

        return json_decode($model->accepted_currencies, 1);
    }

    public function getFormElements(\Model_PayGateway $model)
    {
        $config = $this->getAdapterConfig($model);
        if(isset($config['form']) && is_array($config['form'])) {
            return $config['form'];
        }
        return array();
    }

    public function getDescription(\Model_PayGateway $model)
    {
        $config = $this->getAdapterConfig($model);
        return (isset($config['description'])) ? $config['description'] : NULL;
    }

    /**
     * @param \Model_Invoice $model
     */
    public function getCallbackUrl(\Model_PayGateway $pg, $model = null)
    {
        $p = array(
            'bb_gateway_id'     =>  $pg->id,
        );
        if($model instanceof \Model_Invoice) {
            $p['bb_invoice_id'] = $model->id;
        }
        return $this->di['config']['url'] . 'bb-ipn.php?'.http_build_query($p);
    }

    /**
     * @param \Model_Invoice $model
     */
    private function getReturnUrl(\Model_PayGateway $pg, $model = null)
    {
        if($model instanceof \Model_Invoice) {
            return $this->di['url']->link('/invoice/'.$model->hash, array('status'=> 'ok'));
        }
        return $this->di['url']->link('/invoice', array('status'=> 'ok'));
    }


    /**
     * @param \Model_Invoice $model
     */
    private function getCancelUrl(\Model_PayGateway $pg, $model = null)
    {
        if($model instanceof \Model_Invoice) {
            return $this->di['url']->link('/invoice/'.$model->hash, array('status'=> 'cancel'));
        }
        return $this->di['url']->link('/invoice', array('status'=> 'cancel'));
    }

    /**
     * @param \Model_Invoice $model
     */
    private function getCallbackRedirect(\Model_PayGateway $pg, $model = null)
    {
        $p = array(
            'bb_gateway_id'     =>  $pg->id,
        );

        if($model instanceof \Model_Invoice) {
            $p['bb_invoice_id']     = $model->id;
            $p['bb_invoice_hash']   = $model->hash;
            $p['bb_redirect']       = 1;
        }
        return $this->di['config']['url'] . 'bb-ipn.php?'.http_build_query($p);
    }
}