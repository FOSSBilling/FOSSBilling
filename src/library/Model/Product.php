<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Model_Product extends RedBeanPHP\SimpleModel implements FOSSBilling\InjectionAwareInterface
{
    final public const string STATUS_ENABLED = 'enabled';
    final public const string STATUS_DISABLED = 'disabled';

    final public const string CUSTOM = 'custom';
    final public const string LICENSE = 'license';
    final public const string ADDON = 'addon';
    final public const string DOMAIN = 'domain';
    final public const string DOWNLOADABLE = 'downloadable';
    final public const string HOSTING = 'hosting';
    final public const string VPS = 'vps';

    final public const string SETUP_AFTER_ORDER = 'after_order';
    final public const string SETUP_AFTER_PAYMENT = 'after_payment';
    final public const string SETUP_MANUAL = 'manual';

    protected ?Pimple\Container $di = null;

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Pimple\Container
    {
        return $this->di;
    }

    public function getTable(): object
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
        if ($this->di && isset($this->di['product_type_registry'])) {
            try {
                return $this->di['product_type_registry']->getHandler($this->type);
            } catch (\Throwable) {
                // Fallback to legacy resolution to avoid hard failures.
            }
        }

        return $this->di['mod_service']('service' . $this->type);
    }
}
