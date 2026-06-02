<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

final readonly class PaginationOptions
{
    public const int MAX_PER_PAGE = 500;
    public const int DEFAULT_PER_PAGE = 100;

    /**
     * @param int    $page         current page number, starting from 1
     * @param int    $perPage      number of items to return per page
     * @param string $pageParam    key used to read the page number
     * @param string $perPageParam key used to read the per page number
     *
     * @throws InformationException if page or per-page values are out of range
     */
    public function __construct(
        public int $page = 1,
        public int $perPage = self::DEFAULT_PER_PAGE,
        public string $pageParam = 'page',
        public string $perPageParam = 'per_page',
    ) {
        if ($this->page < 1) {
            throw new InformationException("Page number ({$this->pageParam}) must be a positive integer.");
        }
        if ($this->perPage < 1) {
            throw new InformationException("The number of items per page ({$this->perPageParam}) must be a positive integer.");
        }
        if ($this->perPage > self::MAX_PER_PAGE) {
            throw new InformationException("The number of items per page ({$this->perPageParam}) is too large. Please specify a smaller number.");
        }
    }

    /**
     * Build pagination options from an API/request data array.
     *
     * Non-integer values fall back to defaults. Valid integers below 1 still fail validation.
     *
     * @param array  $data         source data containing pagination values
     * @param string $pageParam    key used to read the page number, 'page' by default
     * @param string $perPageParam key used to read the per-page count, 'per_page' by default
     *
     * @throws InformationException if page or per-page values are out of range
     */
    public static function fromArray(array $data, string $pageParam = 'page', string $perPageParam = 'per_page'): self
    {
        return new self(
            page: self::resolveInteger($data, $pageParam, 1),
            perPage: self::resolveInteger($data, $perPageParam, self::DEFAULT_PER_PAGE),
            pageParam: $pageParam,
            perPageParam: $perPageParam,
        );
    }

    /**
     * Resolve a positive integer candidate from source data, falling back for missing or non-integer values.
     */
    private static function resolveInteger(array $data, string $param, int $default): int
    {
        if (!array_key_exists($param, $data)) {
            return $default;
        }

        $value = filter_var($data[$param], FILTER_VALIDATE_INT, ['flags' => FILTER_NULL_ON_FAILURE]);

        return is_int($value) ? $value : $default;
    }
}
