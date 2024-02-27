<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Cart\Api;

/**
 * Shopping cart management.
 */
class Guest extends \Api_Abstract
{
    /**
     * Get the contents of the current shopping cart.
     *
     * @return array Contents of the shopping cart
     */
    public function get()
    {
        $cart = $this->getService()->getSessionCart();

        return $this->getService()->toApiArray($cart);
    }

    /**
     * Completely remove shopping cart contents.
     *
     * @return bool
     */
    public function reset()
    {
        $cart = $this->getService()->getSessionCart();

        return $this->getService()->resetCart($cart);
    }

    /**
     * Set shopping cart currency.
     *
     * @return bool
     */
    public function set_currency($data)
    {
        $required = [
            'currency' => 'Currency code not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $currencyService = $this->di['mod_service']('currency');
        $currency = $currencyService->getByCode($data['currency']);
        if (!$currency instanceof \Model_Currency) {
            throw new \FOSSBilling\Exception('Currency not found');
        }
        $cart = $this->getService()->getSessionCart();

        return $this->getService()->changeCartCurrency($cart, $currency);
    }

    /**
     * Retrieve information about currently selected shopping cart currency.
     *
     * @return array Currency details
     */
    public function get_currency()
    {
        $cart = $this->getService()->getSessionCart();

        $currencyService = $this->di['mod_service']('currency');
        $currency = $this->di['db']->load('Currency', $cart->currency_id);
        if (!$currency instanceof \Model_Currency) {
            $currency = $currencyService->getDefault();
        }

        return $currencyService->toApiArray($currency);
    }

    /**
     * Apply Promo code to shopping cart.
     *
     * @return bool
     */
    public function apply_promo($data)
    {
        $required = [
            'promocode' => 'Promo code not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $promo = $this->getService()->findActivePromoByCode($data['promocode']);
        if (!$promo instanceof \Model_Promo) {
            throw new \FOSSBilling\InformationException('The promo code has expired or does not exist');
        }

        if (!$this->getService()->isPromoAvailableForClientGroup($promo)) {
            throw new \FOSSBilling\InformationException('Promo code cannot be applied to your account');
        }

        if (!$this->getService()->promoCanBeApplied($promo)) {
            throw new \FOSSBilling\InformationException('The promo code has expired or does not exist');
        }

        $cart = $this->getService()->getSessionCart();

        return $this->getService()->applyPromo($cart, $promo);
    }

    /**
     * Removes promo from shopping cart and resets discounted prices if any.
     *
     * @return bool
     */
    public function remove_promo()
    {
        $cart = $this->getService()->getSessionCart();

        return $this->getService()->removePromo($cart);
    }

    /**
     * Removes product from shopping cart.
     *
     * @return bool
     */
    public function remove_item($data)
    {
        $required = [
            'id' => 'Cart item id not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $cart = $this->getService()->getSessionCart();

        return $this->getService()->removeProduct($cart, $data['id'], true);
    }

    /**
     * Add a product to the shopping cart.
     *
     * @param array $data Product data
     *
     * @return bool
     */
    public function add_item($data)
    {
        $required = [
            'id' => 'Product id not passed',
        ];

        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $cart = $this->getService()->getSessionCart();

        $product = $this->di['db']->getExistingModelById('Product', $data['id'], 'Product not found');

        if ($product->is_addon) {
            throw new \FOSSBilling\InformationException('Addon products cannot be added separately.');
        }

        if (is_array($data['addons'] ?? '')) {
            $validAddons = json_decode($product->addons ?? '');
            if (empty($validAddons)) {
                $validAddons = [];
            }

            foreach ($data['addons'] as $addon => $properties) {
                if ($properties['selected']) {
                    $addonModel = $this->di['db']->getExistingModelById('Product', $addon, 'Addon not found');

                    if ($addonModel->status !== 'enabled' || !in_array($addon, $validAddons)) {
                        throw new \FOSSBilling\InformationException('One or more of your selected add-ons are invalid for the associated product.');
                    }
                }
            }
        }

        // reset cart by default
        if (!isset($data['multiple']) || !$data['multiple']) {
            $this->reset();
        }

        return $this->getService()->addItem($cart, $product, $data);
    }
}
