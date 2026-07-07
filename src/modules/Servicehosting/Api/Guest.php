<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicehosting\Api;

use Box\Mod\Product\Entity\Product;
use FOSSBilling\Validation\Api\RequiredParams;

/**
 * Hosting service management.
 */
class Guest extends \FOSSBilling\Api\AbstractApi
{
    /**
     * @param array $data
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['product_id' => 'Product ID is missing'])]
    public function free_tlds($data = [])
    {
        $product_id = $data['product_id'] ?? 0;
        $product = $this->di['mod_service']('product')->findProductById((int) $product_id);

        if (!$product instanceof Product || $product->getType() !== \Box\Mod\Product\Service::HOSTING) {
            $friendlyName = ucfirst(__trans('Product type'));

            throw new \FOSSBilling\Exception(':friendlyName: is invalid', [':friendlyName:' => $friendlyName]);
        }

        return $this->getService()->getFreeTlds($product);
    }
}
