<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

test('crypt', function (): void {
    $key = 'le password';
    $text = 'foo bar';

    $crypt = new Box_Crypt();
    $encoded = $crypt->encrypt($text, $key);
    $decoded = $crypt->decrypt($encoded, $key);
    expect($decoded)->toEqual($text);
});
