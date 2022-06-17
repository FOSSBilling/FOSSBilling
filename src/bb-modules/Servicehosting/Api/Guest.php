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
     * @throws \Box_Exception
     */
    public function free_tlds($data = [])
    {
        $required = [
            'product_id' => 'Product id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $product_id = $this->di['array_get']($data, 'product_id', 0);
        $product = $this->di['db']->getExistingModelById('Product', $product_id, 'Product was not found');

        if (\Model_Product::HOSTING !== $product->type) {
            throw new \Box_Exception('Product type is invalid');
        }

        return $this->getService()->getFreeTlds($product);
    }
}
