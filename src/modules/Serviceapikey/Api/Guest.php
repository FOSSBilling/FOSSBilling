<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Serviceapikey\Api;

class Guest extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Checks if an API key is valid or not.
     *
     * @param array $data
     *                    - 'key' What API key to check
     */
    public function check($data)
    {
        return $this->getService()->isValid($data);
    }
}
