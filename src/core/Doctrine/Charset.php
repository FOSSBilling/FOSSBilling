<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\Doctrine;

enum Charset: string
{
    case Utf8 = 'utf8';
    case Utf8mb4 = 'utf8mb4';
    case Latin1 = 'latin1';
}
