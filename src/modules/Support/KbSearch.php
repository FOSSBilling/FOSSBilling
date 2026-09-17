<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Support;

final class KbSearch
{
    public const MAX_QUERY_LENGTH = 255;
    public const MAX_TERMS = 10;

    /**
     * @return list<string>
     */
    public static function terms(string $query): array
    {
        $query = mb_strtolower(mb_substr(trim($query), 0, self::MAX_QUERY_LENGTH));
        $terms = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_slice($terms, 0, self::MAX_TERMS);
    }
}
