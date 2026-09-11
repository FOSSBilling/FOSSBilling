<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use FOSSBilling\Core\GeoIP\Reader;

test('bundled country database exists at the path the reader returns', function (): void {
    expect(is_file(Reader::getCountryDatabase()))->toBeTrue();
});

test('bundled ASN database exists at the path the reader returns', function (): void {
    expect(is_file(Reader::getAsnDatabase()))->toBeTrue();
});
