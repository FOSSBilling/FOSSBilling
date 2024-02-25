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
class Client extends \Api_Abstract
{
    /**
     * Checkout a shopping cart that has products in it.
     *
     * @param array $data Checkout data
     */
    public function checkout($data)
    {
        $gateway_id = $data['gateway_id'] ?? null;
        $cart = $this->getService()->getSessionCart();
        $client = $this->getIdentity();

        return $this->getService()->checkoutCart($cart, $client, $gateway_id);
    }
}
