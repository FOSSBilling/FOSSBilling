<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Model_Product extends RedBeanPHP\SimpleModel implements FOSSBilling\InjectionAwareInterface
{
    final public const STATUS_ENABLED = 'enabled';
    final public const STATUS_DISABLED = 'disabled';

    final public const CUSTOM = 'custom';
    final public const LICENSE = 'license';
    final public const ADDON = 'addon';
    final public const DOMAIN = 'domain';
    final public const DOWNLOADABLE = 'downloadable';
    final public const HOSTING = 'hosting';
    final public const MEMBERSHIP = 'membership';
    final public const VPS = 'vps';

    final public const SETUP_AFTER_ORDER = 'after_order';
    final public const SETUP_AFTER_PAYMENT = 'after_payment';
    final public const SETUP_MANUAL = 'manual';

    protected ?Pimple\Container $di = null;

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Pimple\Container
    {
        return $this->di;
    }

    public function getTable()
    {
        $tableName = 'Model_Product' . ucfirst($this->type) . 'Table';
        if (!class_exists($tableName)) {
            $tableName = 'Model_ProductTable';
        }
        $productTable = new $tableName();
        $productTable->setDi($this->di);

        return $productTable;
    }

    public function getService()
    {
        return $this->di['mod_service']('service' . $this->type);
    }
}
