<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * Custom forms.
 */

namespace Box\Mod\Formbuilder\Api;

use FOSSBilling\Validation\Api\RequiredParams;

class Guest extends \Api_Abstract
{
    /**
     * Get custom order form details for product.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Form ID was not passed'])]
    public function get($data)
    {
        $service = $this->getService();

        return $service->getForm($data['id']);
    }
}
