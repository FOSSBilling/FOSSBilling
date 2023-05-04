<?php
/**
 * FOSSBilling.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * Copyright FOSSBilling 2022
 * This software may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE.
 */

class Model_Product extends \RedBeanPHP\SimpleModel implements \FOSSBilling\InjectionAwareInterface
{
    const STATUS_ENABLED    = 'enabled';
    const STATUS_DISABLED   = 'disabled';

    const CUSTOM            = 'custom';
    const LICENSE           = 'license';
    const ADDON             = 'addon';
    const DOMAIN            = 'domain';
    const DOWNLOADABLE      = 'downloadable';
    const HOSTING           = 'hosting';
    const MEMBERSHIP        = 'membership';
    const VPS               = 'vps';

    const SETUP_AFTER_ORDER     = 'after_order';
    const SETUP_AFTER_PAYMENT   = 'after_payment';
    const SETUP_MANUAL          = 'manual';

    protected ?\Pimple\Container $di;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getTable()
    {
        $tableName = 'Model_Product'. ucfirst($this->type). 'Table';
        if(!class_exists($tableName)) {
            $tableName = 'Model_ProductTable';
        }
        $productTable = new $tableName;
        $productTable->setDi($this->di);
        return $productTable;
    }

    public function getService()
    {
        return $this->di['mod_service']('service'.$this->type);
    }
}
