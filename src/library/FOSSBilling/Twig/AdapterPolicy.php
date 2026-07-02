<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Twig;

use Twig\Sandbox\SecurityPolicy;

final class AdapterPolicy extends EmailPolicy
{
    public static function create(): SecurityPolicy
    {
        return new SecurityPolicy(
            [...self::allowedTags(), 'spaceless'],
            [
                ...self::allowedFilters(),
                'number_format',
                'round', 'abs', 'json_encode',
                'trim', 'lower', 'upper', 'nl2br', 'striptags',
                'join', 'split', 'sort', 'merge', 'reverse',
                'keys', 'length',
            ],
            [],
            [],
            self::allowedFunctions(),
        );
    }
}
