<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicehosting\Api;

/**
 * Hosting service management.
 */
class Guest extends \Api_Abstract
{
    /**
     * @param array $data
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    public function free_tlds($data = [])
    {
        $required = [
            'product_id' => 'Product id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $product_id = $data['product_id'] ?? 0;
        $product = $this->di['db']->getExistingModelById('Product', $product_id, 'Product was not found');

        if ($product->type !== \Model_Product::HOSTING) {
            $friendlyName = ucfirst(__trans('Product type'));

            throw new \FOSSBilling\Exception(':friendlyName: is invalid', [':friendlyName:' => $friendlyName]);
        }

        return $this->getService()->getFreeTlds($product);
    }
}
