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

class EmailPolicy
{
    public static function create(): SecurityPolicy
    {
        return new SecurityPolicy(
            static::allowedTags(),
            static::allowedFilters(),
            [],
            [],
            static::allowedFunctions(),
        );
    }

    public static function allowedTags(): array
    {
        return ['if', 'for', 'block', 'apply', 'set'];
    }

    public static function allowedFilters(): array
    {
        return [
            'escape', 'e',
            'default', 'title', 'length', 'date', 'first',
            'format_currency', 'format_date', 'format_datetime', 'format_number', 'format_time',
            'currency_name', 'currency_symbol',
            'country_name',
            'url', 'daysleft', 'trans',
            'period_title',
            'markdown_to_html',
        ];
    }

    public static function allowedFunctions(): array
    {
        return ['country_names'];
    }
}
