<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use FOSSBilling\Core\Url;

test('link accepts null query parameters', function (): void {
    $url = new Url();
    $url->setBaseUri('/billing/');

    expect($url->link('login', null))->toBe('/billing/login');
});

test('adminLink accepts null query parameters', function (): void {
    $url = new Url();
    $url->setBaseUri('/billing/');

    expect($url->adminLink('staff/login', null))
        ->toBe('/billing/' . trim(ADMIN_PREFIX, '/') . '/staff/login');
});
