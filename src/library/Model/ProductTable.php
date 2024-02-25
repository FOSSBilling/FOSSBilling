<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Model_ProductTable implements FOSSBilling\InjectionAwareInterface
{
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

    public function getUnit(Model_Product $model)
    {
        return $model->unit;
    }

    /**
     * @return string
     */
    private function _getPeriodKey(Box_Period $period)
    {
        $code = $period->getCode();

        try {
            return match ($code) {
                '1W' => 'w',
                '1M' => 'm',
                '3M' => 'q',
                '6M' => 'b',
                '12M' => 'a',
                '1Y' => 'a',
                '2Y' => 'bia',
                '3Y' => 'tria'
            };
        } catch (UnhandledMatchError) {
            throw new FOSSBilling\Exception('Unknown period selected ' . $code);
        }
    }

    public function getPricingArray(Model_Product $model)
    {
        if ($model->product_payment_id) {
            $service = $this->di['mod_service']('product');
            $productPayment = $this->di['db']->load('ProductPayment', $model->product_payment_id);

            return $service->toProductPaymentApiArray($productPayment);
        }

        throw new FOSSBilling\Exception('Product pricing could not be determined. ' . static::class);
    }

    /**
     * Price for one unit.
     *
     * @return float
     */
    public function getProductSetupPrice(Model_Product $product, array $config = null)
    {
        $pp = $this->di['db']->load('ProductPayment', $product->product_payment_id);

        if ($pp->type == Model_ProductPayment::FREE) {
            $price = 0;
        }

        if ($pp->type == Model_ProductPayment::ONCE) {
            $price = (float) $pp->once_setup_price;
        }

        if ($pp->type == Model_ProductPayment::RECURRENT) {
            $period = new Box_Period($config['period']);
            $key = $this->_getPeriodKey($period);
            $price = (float) $pp->{$key . '_setup_price'};
        }

        if (isset($price)) {
            return $price;
        }

        throw new FOSSBilling\Exception('Unknown period selected for setup price');
    }

    /**
     * Price for one unit.
     *
     * @return float
     */
    public function getProductPrice(Model_Product $product, array $config = null)
    {
        $pp = $this->di['db']->load('ProductPayment', $product->product_payment_id);

        if ($pp->type == Model_ProductPayment::FREE) {
            $price = 0;
        }

        if ($pp->type == Model_ProductPayment::ONCE) {
            $price = $pp->once_price;
        }

        if ($pp->type == Model_ProductPayment::RECURRENT) {
            if (!isset($config['period'])) {
                throw new FOSSBilling\Exception('Product :id payment type is recurrent, but period was not selected', [':id' => $product->id]);
            }

            $period = new Box_Period($config['period']);
            $key = $this->_getPeriodKey($period);
            $price = $pp->{$key . '_price'};
        }

        if (isset($price)) {
            return $price;
        }

        throw new FOSSBilling\Exception('Unknown Period selected for price');
    }
}
