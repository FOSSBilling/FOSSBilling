<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

class Model_ProductTable implements \FOSSBilling\InjectionAwareInterface
{
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

    public function getUnit(Model_Product $model)
    {
        return $model->unit;
    }

    /**
     * @param Box_Period $period
     * @return string
     */
    private function _getPeriodKey(Box_Period $period)
    {
        $code = $period->getCode();
        try {
            return match ($code) {
                '1W'  => 'w',
                '1M'  => 'm',
                '3M'  => 'q',
                '6M'  => 'b',
                '12M' => 'a',
                '1Y'  => 'a',
                '2Y'  => 'bia',
                '3Y'  => 'tria'
            };
        } catch (\UnhandledMatchError) {
            throw new Box_Exception('Unknown period selected ' . $code);
        }
    }

    public function getPricingArray(Model_Product $model)
    {
        if ($model->product_payment_id) {
            $service = $this->di['mod_service']('product');
            $productPayment = $this->di['db']->load('ProductPayment', $model->product_payment_id);
            return $service->toProductPaymentApiArray($productPayment);
        }

        throw new \Box_Exception('Product pricing could not be determined. ' . get_class($this));
    }

    /**
     * Price for one unit
     *
     * @param Model_Product $product
     * @param array $config
     * @return float
     */
    public function getProductSetupPrice(Model_Product $product, array $config = null)
    {
        $pp = $this->di['db']->load('ProductPayment', $product->product_payment_id);

        if ($pp->type == Model_ProductPayment::FREE) {
            $price = 0;
        }

        if ($pp->type == Model_ProductPayment::ONCE) {
            $price = (float)$pp->once_setup_price;
        }

        if ($pp->type == Model_ProductPayment::RECURRENT) {
            $period = new Box_Period($config['period']);
            $key = $this->_getPeriodKey($period);
            $price = (float)$pp->{$key . '_setup_price'};
        }

        if (isset($price)) {
            return $price;
        }

        throw new \Box_Exception('Unknown period selected for setup price');
    }

    /**
     * Price for one unit
     *
     * @param Model_Product $product
     * @param array $config
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
                throw new \Box_Exception('Product :id payment type is recurrent, but period was not selected', array(':id' => $product->id));
            }

            $period = new Box_Period($config['period']);
            $key = $this->_getPeriodKey($period);
            $price = $pp->{$key . '_price'};
        }

        if (isset($price)) {
            return $price;
        }

        throw new \Box_Exception('Unknown Period selected for price');
    }
}
