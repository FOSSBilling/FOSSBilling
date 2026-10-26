<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Server_PartialProvisioningException extends Server_Exception
{
    public function __construct(public readonly string $nextStep)
    {
        parent::__construct('The hosting account was created, but :step: failed. Check the server logs and retry activation.', [':step:' => $nextStep]);
    }
}
