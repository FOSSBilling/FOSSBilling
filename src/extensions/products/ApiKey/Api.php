<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\ProductType\ApiKey;

use FOSSBilling\Validation\Api\RequiredRole;

class Api extends \Api_Abstract
{
    #[RequiredRole(['admin'])]
    public function admin_update($data): bool
    {
        return $this->getService()->updateApiKey($data);
    }

    #[RequiredRole(['admin'])]
    public function admin_reset($data): bool
    {
        return $this->getService()->resetApiKey($data);
    }

    #[RequiredRole(['client'])]
    public function client_reset($data): bool
    {
        return $this->getService()->resetApiKey($data);
    }

    #[RequiredRole(['guest'])]
    public function guest_check($data)
    {
        return $this->getService()->isValid($data);
    }

    #[RequiredRole(['guest'])]
    public function guest_get_info($data)
    {
        return $this->getService()->getInfo($data);
    }
}
