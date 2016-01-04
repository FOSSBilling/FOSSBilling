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


class Model_ProductDomainTable extends Model_ProductTable
{
    public function getUnit(Model_Product $model)
    {
        return 'year';
    }

    protected function getStartingFromPrice(Model_Product $model)
    {
        $p = array();
        $prices = $this->getPricingArray($model);
        foreach($prices as $tld) {
            $p[] = $tld['price_registration'];
        }
        return empty($p) ? 0 : min($p);
    }

    public function isRecurrentPricing(Model_Product $model)
    {
        return false;
    }

    public function getRelatedDiscount(array $items, Model_Product $product, array $config)
    {
        foreach($items as $item) {

            if($this->isActionNameSet($item, 'register') &&
                $this->isFreeDomainSet($item) &&
                $this->registerDomainMatch($item, $config) )
            {
                        return $this->getProductPrice($product, $config);
            }
            
            if($this->isActionNameSet($item, 'transfer') &&
                $this->isFreeTransferSet($item) &&
                $this->transferDomainMatch($item, $config) ) {
                    return $this->getProductPrice($product, $config);
            }
        }
        
        return 0;
    }

    /**
     * @param string $actionName
     */
    private function isActionNameSet($item, $actionName)
    {
        return isset($item['config']['domain']['action']) && $item['config']['domain']['action'] == $actionName;
    }

    private function isFreeDomainSet($item)
    {
         if(isset($item['config']['free_domain']) && $item['config']['free_domain'] && isset($item['config']['free_domain_periods']) && in_array($item['config']['period'],$item['config']['free_domain_periods']) && isset($item['config']['free_tlds']) && in_array($item['config']['tld'],$item['config']['free_tlds']))
	   { return true;}
   else{ return false;}
    }

    private function registerDomainMatch($item, $config)
    {
        if (!isset($item['config']['domain']['register_sld'])){
            return false;
        }
        return $item['config']['domain']['register_sld'] == $config['register_sld'] && $item['config']['domain']['register_tld'] == $config['register_tld'];
    }

    private function transferDomainMatch($item, $config)
    {
        if (!isset($item['config']['domain']['transfer_sld'])){
            return false;
        }
        return $item['config']['domain']['transfer_sld'] == $config['transfer_sld'] && $item['config']['domain']['transfer_tld'] == $config['transfer_tld'];
    }

    private function isFreeTransferSet($item)
    {
        return isset($item['config']['free_transfer']) && $item['config']['free_transfer'];
    }

    
    public function getPricingArray(Model_Product $product)
    {
        $pricing = array();

        $sql="
            SELECT t.*, r.name
            FROM tld t
            LEFT JOIN tld_registrar r ON (r.id = t.tld_registrar_id)
            WHERE t.active = 1
            ORDER BY t.id ASC
        ";
        $stmt = $this->di['pdo']->prepare($sql);
        $stmt->execute();

        foreach($stmt->fetchAll() as $tld) {
            $pricing[$tld['tld']] = array(
                'tld'                   => $tld['tld'],
                'price_registration'    => $tld['price_registration'],
                'price_renew'           => $tld['price_renew'],
                'price_transfer'        => $tld['price_transfer'],
                'active'                => $tld['active'],
                'allow_register'        => $tld['allow_register'],
                'allow_transfer'        => $tld['allow_transfer'],
                'min_years'             => $tld['min_years'],
                'registrar'             => array(
                    'id'                =>  $tld['tld_registrar_id'],
                    'title'             =>  $tld['name'],
                )
            );
        }

        return $pricing;
    }

    public function getProductPrice(Model_Product $product, array $config = array())
    {
        $rtable = $this->di['mod_service']('servicedomain', 'Tld');
        $tld = '';

        if(!isset($config['action'])) {
            throw new \Box_Exception('Could not determine domain price. Domain action is missing', null, 498);
        }
        
        if($config['action'] == 'owndomain') {
            return 0;
        }
        
        if($config['action'] == 'register') {
            $tld = $config['register_tld'];
        }

        if($config['action'] == 'transfer') {
            $tld = $config['transfer_tld'];
        }
        
        $tld = $rtable->findOneByTld($tld);
        if(!$tld instanceof Model_Tld) {
            throw new \Box_Exception('Unknown TLD. Could not determine registration price');
        }

        if($config['action'] == 'register') {
            return $tld->price_registration;
        }

        if($config['action'] == 'transfer') {
            return $tld->price_transfer;
        }
        
        return 0;
    }

    public function getProductSetupPrice(Model_Product $product, array $config = array())
    {
        return 0;
    }

    public function rm(Model_Product $product)
    {
        throw new \Box_Exception('Domain product can not be removed.');
    }
}