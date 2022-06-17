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

/**
 * Custom forms.
 */

namespace Box\Mod\Formbuilder\Api;

class Guest extends \Api_Abstract
{
    /**
     * Get custom order form details for product.
     *
     * @param int $product_id - Product id
     *
     * @return array
     *
     * @throws Box_Exception
     */
    public function get($data)
    {
        $required = [
            'id' => 'Form id was not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->getService();

        return $service->getForm($data['id']);
    }
}
