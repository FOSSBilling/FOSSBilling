<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace FOSSBilling\ProductType\ApiKey\Api;

final class Guest extends \Api_Abstract
{
    public function check($data)
    {
        return $this->getService()->isValid($data);
    }

    public function get_info($data)
    {
        return $this->getService()->getInfo($data);
    }
}
