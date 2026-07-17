<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

test('declares support for one-time payments only', function (): void {
    $config = Payment_Adapter_ClientBalance::getConfig();

    expect($config)
        ->toHaveKey('supports_one_time_payments', true)
        ->toHaveKey('supports_subscriptions', false);
});
