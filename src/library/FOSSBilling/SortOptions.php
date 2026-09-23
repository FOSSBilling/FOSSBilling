<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

/**
 * Server-side sorting options for paginated list endpoints.
 *
 * Each list query builder declares its own allowlist mapping public sort keys
 * to real ORDER BY expressions — request input is never used as an ORDER BY
 * expression directly. Unknown keys fall back to the caller's default ordering.
 */
final readonly class SortOptions
{
    public const string DEFAULT_DIRECTION = 'ASC';

    /**
     * @param string|null $expression     resolved ORDER BY expression from the allowlist, null when no valid sort was requested
     * @param string      $direction      resolved direction, 'ASC' or 'DESC'
     * @param string      $sortParam      key used to read the sort column
     * @param string      $directionParam key used to read the sort direction
     */
    public function __construct(
        public ?string $expression = null,
        public string $direction = self::DEFAULT_DIRECTION,
        public string $sortParam = 'sort',
        public string $directionParam = 'direction',
    ) {
    }

    /**
     * Build sorting options from an API/request data array.
     *
     * @param array                $data           source data containing sorting values
     * @param array<string,string> $allowedColumns map of public sort key => ORDER BY expression (DQL path or raw SQL fragment)
     * @param string               $sortParam      key used to read the sort column, 'sort' by default
     * @param string               $directionParam key used to read the sort direction, 'direction' by default
     */
    public static function fromArray(array $data, array $allowedColumns, string $sortParam = 'sort', string $directionParam = 'direction'): self
    {
        $expression = null;
        if (isset($data[$sortParam]) && is_string($data[$sortParam])) {
            $key = strtolower(trim($data[$sortParam]));
            $expression = $allowedColumns[$key] ?? null;
        }

        return new self(
            expression: $expression,
            direction: self::resolveDirection($data[$directionParam] ?? null),
            sortParam: $sortParam,
            directionParam: $directionParam,
        );
    }

    /**
     * Whether a valid sort was requested.
     */
    public function isSorted(): bool
    {
        return $this->expression !== null;
    }

    /**
     * Render a raw SQL ORDER BY clause, e.g. "tld ASC". Returns null when no
     * valid sort was requested so callers keep their default ordering.
     *
     * The optional tie-breaker (usually the table's primary key) is appended
     * in the same direction unless it duplicates the primary expression, so
     * tied rows keep a stable order across paginated requests.
     */
    public function toOrderByClause(?string $tieBreaker = null): ?string
    {
        if ($this->expression === null) {
            return null;
        }

        $clause = $this->expression . ' ' . $this->direction;
        if ($tieBreaker !== null && $tieBreaker !== $this->expression) {
            $clause .= ', ' . $tieBreaker . ' ' . $this->direction;
        }

        return $clause;
    }

    /**
     * Resolve the direction, accepting 'ASC'/'DESC' case-insensitively.
     * Anything else falls back to the default.
     */
    private static function resolveDirection(mixed $value): string
    {
        if (is_string($value) && strtoupper(trim($value)) === 'DESC') {
            return 'DESC';
        }

        return self::DEFAULT_DIRECTION;
    }
}
