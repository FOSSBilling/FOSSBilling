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
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Cart\Api;

/**
 * Shopping cart management.
 */
class Client extends \Api_Abstract
{
    /**
     * Checkout a shopping cart that has products in it.
     * 
     * @param array $data Checkout data
     * 
     * @param int $data['gateway_id'] ID of the payment gateway to use for the payment
     * 
     * @return mixed
    */
    public function checkout($data)
    {
        $gateway_id = $data['gateway_id'] ?? null;
        $cart = $this->getService()->getSessionCart();
        $client = $this->getIdentity();

        return $this->getService()->checkoutCart($cart, $client, $gateway_id);
    }
}
