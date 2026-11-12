<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * Server managers that bind their SSO login session to the visitor's IP address.
 */
interface Server_SsoClientIpInterface
{
    /**
     * Sets the visitor IP address the SSO session should be bound to.
     *
     * @param string|null $value the visitor IP address
     */
    public function setSsoClientIp(?string $value): static;
}
