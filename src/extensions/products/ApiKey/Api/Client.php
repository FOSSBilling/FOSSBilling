<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace FOSSBilling\ProductType\ApiKey\Api;

final class Client extends \Api_Abstract
{
    public function reset($data): bool
    {
        return $this->getService()->resetApiKey($data);
    }
}
