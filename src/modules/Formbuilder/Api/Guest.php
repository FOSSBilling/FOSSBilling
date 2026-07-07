<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
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

class Guest extends \FOSSBilling\Api\AbstractApi
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

        return $service->getForm((int) $data['id']);
    }
}
