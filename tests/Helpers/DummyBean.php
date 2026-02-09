<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace Tests\Helpers;

/**
 * Define a dummy class for RedBeanPHP, so test can easily be adjusted for future versions of RedBeanPHP.
 */
class DummyBean extends \RedBeanPHP\OODBBean
{
    public function __construct()
    {
        $this->__info['changelist'] = [];
    }
}
