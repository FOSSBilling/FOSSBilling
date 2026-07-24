<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Cart\Api;

use Box\Mod\Cart\Entity\Cart;
use Box\Mod\Currency\Entity\Currency;
use Box\Mod\Product\Entity\Promo;
use FOSSBilling\Validation\Api\RequiredParams;

/**
 * Shopping cart management.
 */
class Guest extends \FOSSBilling\Api\AbstractApi
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
    #[RequiredParams(['currency' => 'Currency code was not passed'])]
    public function set_currency($data)
    {
        $currencyService = $this->getDi()['mod_service']('currency');
        /** @var \Box\Mod\Currency\Repository\CurrencyRepository $currencyRepository */
        $currencyRepository = $currencyService->getCurrencyRepository();
        $currency = $currencyRepository->findOneByCode($data['currency']);
        if (!$currency instanceof Currency) {
            throw new \FOSSBilling\InformationException('Currency not found');
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

        $currencyService = $this->getDi()['mod_service']('currency');
        /** @var \Box\Mod\Currency\Repository\CurrencyRepository $currencyRepository */
        $currencyRepository = $currencyService->getCurrencyRepository();
        $currency = $currencyRepository->find($cart instanceof Cart ? $cart->getCurrencyId() : $cart->currency_id);
        if (!$currency instanceof Currency) {
            $currency = $currencyRepository->findDefault();
            if (!$currency instanceof Currency) {
                throw new \FOSSBilling\Exception('No currency available');
            }
        }

        return $currency->toApiArray();
    }

    /**
     * Apply Promo code to shopping cart.
     *
     * @return bool
     */
    #[RequiredParams(['promocode' => 'Promo code was not passed'])]
    public function apply_promo($data)
    {
        $this->getDi()['rate_limiter']->consumeOrThrow('cart_promo_apply_ip', (string) $this->getIp());

        $promo = $this->getService()->findActivePromoByCode($data['promocode']);
        if (!$promo instanceof Promo) {
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
    #[RequiredParams(['id' => 'Cart item ID was not passed'])]
    public function remove_item($data)
    {
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
    #[RequiredParams(['id' => 'Product ID was not passed'])]
    public function add_item($data)
    {
        $cart = $this->getService()->getSessionCart();
        $productService = $this->di['mod_service']('product');

        $product = $productService->findOneActiveById((int) $data['id']);
        if (!$product instanceof \Box\Mod\Product\Entity\Product) {
            throw new \FOSSBilling\InformationException('Product not found');
        }

        if ($product->isAddon()) {
            throw new \FOSSBilling\InformationException('Addon products cannot be added separately.');
        }

        if (is_array($data['addons'] ?? '')) {
            $productService->validateSelectedAddonsForProduct($product, $data['addons']);
        }

        // reset cart by default
        if (!isset($data['multiple']) || !$data['multiple']) {
            $this->reset();
        }

        return $this->getService()->addItem($cart, $product, $data);
    }
}
