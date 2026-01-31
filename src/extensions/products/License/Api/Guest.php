<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace FOSSBilling\ProductType\License\Api;

class Guest extends \Api_Abstract
{
    public function check($data)
    {
        return $this->getService()->checkLicenseDetails($data);
    }
}
