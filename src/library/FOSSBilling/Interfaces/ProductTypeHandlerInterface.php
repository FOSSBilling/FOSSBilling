<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Interfaces;

interface ProductTypeHandlerInterface extends \FOSSBilling\InjectionAwareInterface
{
    public function create(\Model_ClientOrder $order);

    public function activate(\Model_ClientOrder $order);

    public function renew(\Model_ClientOrder $order);

    public function suspend(\Model_ClientOrder $order);

    public function unsuspend(\Model_ClientOrder $order);

    public function cancel(\Model_ClientOrder $order);

    public function uncancel(\Model_ClientOrder $order);

    public function delete(\Model_ClientOrder $order);
}
