<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Extension\Contract\Payment;

use FOSSBilling\Exception;

/**
 * Cross-field settings validation the manifest's JSON schema can't express —
 * "at least one of the live/test key pairs must be complete", or probing
 * that a credential actually works.
 *
 * The manifest's `settings` schema (extension.json) already validates shape
 * (required fields, types); this is for semantics on top of that.
 */
interface ValidatesSettings
{
    /**
     * @param array<string, mixed> $settings the merchant settings about to be saved
     *
     * @throws Exception if the settings are not acceptable
     */
    public function validateSettings(array $settings): void;
}
