<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
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
     * Checkout cart which has products.
     *
     * @optional int $gateway_id - payment gateway id. Which payment gateway will be used to make payment
     *
     * @return string array
     */
    public function checkout($data)
    {
        $gateway_id = $this->di['array_get']($data, 'gateway_id');
        $cart = $this->getService()->getSessionCart();
        $client = $this->getIdentity();

        return $this->getService()->checkoutCart($cart, $client, $gateway_id);
    }
}
