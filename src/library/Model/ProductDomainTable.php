<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Model_ProductDomainTable extends Model_ProductTable
{
    public function getUnit(Model_Product $model)
    {
        return 'year';
    }

    protected function getStartingFromPrice(Model_Product $model)
    {
        $p = [];
        $prices = $this->getPricingArray($model);
        foreach ($prices as $tld) {
            $p[] = $tld['price_registration'];
        }

        return empty($p) ? 0 : min($p);
    }

    public function isRecurrentPricing(Model_Product $model)
    {
        return false;
    }

    /**
     * Determine discount for items in cart (Hosting and related domain discount).
     *
     * @param array         $items   Array of cart products
     * @param Model_Product $product current product in iteration
     * @param array         $config  configurations specified in product config
     *
     * @return number discount
     */
    public function getRelatedDiscount(array $items, Model_Product $product, array $config)
    {
        /**For each cart product,
         * Compare it with other items in the cart
         * If a domain has related hosting package, determine discount configured.
         */
        foreach ($items as $addon) {
            if (
                $this->isActionNameSet($addon, 'register')
                && $this->_isFreeDomainSet($addon)
                && $this->registerDomainMatch($addon, $config)
            ) {
                if ($this->_hasFreePeriod($addon)) {
                    $factor = $this->discountFactor($addon, $config['period']);

                    return $factor * $this->getProductPrice($product, $config);
                } else {
                    return 0;
                }
            }

            if (
                $this->isActionNameSet($addon, 'transfer')
                && $this->isFreeTransferSet($addon)
                && $this->transferDomainMatch($addon, $config)
            ) {
                return $this->getProductPrice($product, $config);
            }
        }

        return 0;
    }

    private function _hasFreePeriod($addon)
    {
        $free_domain_periods = $addon['config']['free_domain_periods'];
        $addon_period = $addon['config']['period'];
        if (in_array($addon_period, $free_domain_periods) || sizeof($free_domain_periods) > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Determine the number of years configured for free domain.
     */
    private function discountFactor($addon, $period)
    {
        $ref_item_period = $this->di['period']($period);
        $ref_item_qty = $ref_item_period->getQty();

        $addon_period = $addon['config']['period'];
        $addon_sys_period = $this->di['period']($addon_period);
        $addon_qty = $addon_sys_period->getQty();

        $free_domain_periods = $addon['config']['free_domain_periods'];
        if ((is_countable($free_domain_periods) ? count($free_domain_periods) : 0) > 0) {
            // if hosting and domain periods are equal, return domain quantity (year)
            if ($addon_period == $period) {
                if (in_array($addon_period, $free_domain_periods)) {
                    return $ref_item_qty;
                }
            }

            if (str_contains($addon_period, 'Y')) {
                if (min($ref_item_qty, $addon_qty) == 1) {
                    return 1;
                }

                $free_domain_qtys = [];
                foreach ($free_domain_periods as $fp) {
                    $prd = $this->di['period']($fp);
                    $qnty = $prd->getQty();
                    if ($ref_item_qty - $qnty > 0) {
                        $free_domain_qtys[] = $qnty;
                    }
                }

                if (count($free_domain_qtys) > 1) {
                    return min($ref_item_qty, min($free_domain_qtys));
                } else {
                    return min($ref_item_qty, $free_domain_qtys[0]);
                }
            }
        } else {
            return 0;
        }
    }

    /**
     * @param string $actionName
     */
    private function isActionNameSet($item, $actionName)
    {
        return isset($item['config']['domain']['action']) && $item['config']['domain']['action'] == $actionName;
    }

    private function _isFreeDomainSet($item)
    {
        $free_domain = $item['config']['free_domain'] ?? false;
        $tld = $item['config']['tld'] ?? null;
        $free_tlds = $item['config']['free_tlds'] ?? [];

        if ($tld != null && !$free_domain && is_array($free_tlds) && in_array($tld, $free_tlds)) {
            return true;
        } else {
            return false;
        }
    }

    private function registerDomainMatch($item, $config)
    {
        if (!isset($item['config']['domain']['register_sld'])) {
            return false;
        }

        return $item['config']['domain']['register_sld'] == $config['register_sld'] && $item['config']['domain']['register_tld'] == $config['register_tld'];
    }

    private function transferDomainMatch($item, $config)
    {
        if (!isset($item['config']['domain']['transfer_sld'])) {
            return false;
        }

        return $item['config']['domain']['transfer_sld'] == $config['transfer_sld'] && $item['config']['domain']['transfer_tld'] == $config['transfer_tld'];
    }

    private function isFreeTransferSet($item)
    {
        return isset($item['config']['free_transfer']) && $item['config']['free_transfer'];
    }

    /**
     * @return array<mixed, array<'active'|'allow_register'|'allow_transfer'|'min_years'|'price_registration'|'price_renew'|'price_transfer'|'registrar'|'tld', mixed>>
     */
    public function getPricingArray(Model_Product $product): array
    {
        $pricing = [];

        $sql = '
            SELECT t.*, r.name
            FROM tld t
            LEFT JOIN tld_registrar r ON (r.id = t.tld_registrar_id)
            WHERE t.active = 1
            ORDER BY t.id ASC
        ';
        $stmt = $this->di['pdo']->prepare($sql);
        $stmt->execute();

        foreach ($stmt->fetchAll() as $tld) {
            $pricing[$tld['tld']] = [
                'tld' => $tld['tld'],
                'price_registration' => $tld['price_registration'],
                'price_renew' => $tld['price_renew'],
                'price_transfer' => $tld['price_transfer'],
                'active' => $tld['active'],
                'allow_register' => $tld['allow_register'],
                'allow_transfer' => $tld['allow_transfer'],
                'min_years' => $tld['min_years'],
                'registrar' => [
                    'id' => $tld['tld_registrar_id'],
                    'title' => $tld['name'],
                ],
            ];
        }

        return $pricing;
    }

    public function getProductPrice(Model_Product $product, array $config = null)
    {
        $rtable = $this->di['mod_service']('servicedomain', 'Tld');
        $tld = '';

        if (!isset($config['action'])) {
            throw new FOSSBilling\Exception('Could not determine domain price. Domain action is missing', null, 498);
        }

        if ($config['action'] == 'owndomain') {
            return 0;
        }

        if ($config['action'] == 'register') {
            $tld = $config['register_tld'];
        }

        if ($config['action'] == 'transfer') {
            $tld = $config['transfer_tld'];
        }

        $tld = $rtable->findOneByTld($tld);
        if (!$tld instanceof Model_Tld) {
            throw new FOSSBilling\Exception('Unknown TLD. Could not determine registration price');
        }

        if ($config['action'] == 'register') {
            return $tld->price_registration;
        }

        if ($config['action'] == 'transfer') {
            return $tld->price_transfer;
        }

        return 0;
    }

    public function getProductSetupPrice(Model_Product $product, array $config = null)
    {
        return 0;
    }

    public function rm(Model_Product $product): never
    {
        throw new FOSSBilling\Exception('Domain product cannot be removed.');
    }
}
