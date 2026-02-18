<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace Tests\Datasets;

/**
 * Valid period code test data.
 *
 * @return array<int, array{0: string, 1: string, 2: int, 3: float}>
 */
function periodCodes(): array
{
    return [
        ['1D', 'D', 1, 1.0],
        ['7D', 'D', 7, 7.0],
        ['2W', 'W', 2, 15.0],   // (2/4) * 30 = 15 days
        ['1M', 'M', 1, 30.0],
        ['3M', 'M', 3, 90.0],
        ['1Y', 'Y', 1, 360.0],  // 12 * 30 = 360 days
        ['2Y', 'Y', 2, 720.0],  // 24 * 30 = 720 days
    ];
}
