<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Extension\Contract;

/**
 * Extension-level lifecycle hooks, shared by gateways, registrars and
 * managers alike (and eventually modules, which already have a worse,
 * `method_exists`-dispatched version of this).
 *
 * These are extension-level events, not instance-level ones: "the Stripe
 * extension was activated" is not the same event as "a Stripe gateway was
 * configured" — FOSSBilling allows several configured instances of one
 * extension (`ServicePayGateway::copy()`), each with its own credentials.
 * Instance-level hooks (e.g. registering a webhook endpoint for one
 * configured gateway) are a separate, not-yet-built feature.
 *
 * Detected with `instanceof`, like every other capability — never
 * `method_exists`.
 */
interface HasLifecycle
{
    public function activated(): void;

    public function deactivated(): void;

    public function upgraded(string $fromVersion): void;
}
