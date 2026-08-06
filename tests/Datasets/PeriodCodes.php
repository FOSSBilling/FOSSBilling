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
        ['45D', 'D', 45, 45.0],
        ['90D', 'D', 90, 90.0], // upper bound for the day unit
        ['2W', 'W', 2, 15.0],   // (2/4) * 30 = 15 days
        ['52W', 'W', 52, 390.0], // upper bound for the week unit: (52/4) * 30 = 390 days
        ['1M', 'M', 1, 30.0],
        ['3M', 'M', 3, 90.0],
        ['24M', 'M', 24, 720.0], // upper bound for the month unit
        ['1Y', 'Y', 1, 360.0],  // 12 * 30 = 360 days
        ['2Y', 'Y', 2, 720.0],  // 24 * 30 = 720 days
        ['5Y', 'Y', 5, 1800.0], // upper bound for the year unit
    ];
}
