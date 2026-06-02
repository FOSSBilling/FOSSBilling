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

/**
 * Calculates pagination metadata for rendering page navigation controls.
 *
 * Produces a compact page range (e.g., 1 2 3 ... 7 8 9) given a total
 * item count, current page, and items per page. Does not perform any
 * database queries — use the `Pagination` class for data retrieval.
 */
class Paginator
{
    /**
     * @param int $itemsCount  total number of items across all pages
     * @param int $currentPage current page number (1-based)
     * @param int $limit       items per page (0 or less means unlimited)
     * @param int $midRange    number of page links to show in the middle
     */
    public function __construct(
        private readonly int $itemsCount = 0,
        private int $currentPage = 1,
        private int $limit = 20,
        private readonly int $midRange = 7,
    ) {
        if ($this->currentPage < 1) {
            $this->currentPage = 1;
        }
        if ($this->limit < 1) {
            $this->limit = 0;
        }
    }

    public function getNumPages(): int
    {
        if ($this->limit < 1 || $this->limit > $this->itemsCount) {
            return 1;
        }

        $restItemsNum = $this->itemsCount % $this->limit;

        return $restItemsNum > 0 ? intdiv($this->itemsCount, $this->limit) + 1 : intdiv($this->itemsCount, $this->limit);
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getOffset(): int
    {
        return ($this->currentPage - 1) * $this->limit;
    }

    public function getMidRange(): int
    {
        return $this->midRange;
    }

    /**
     * Returns the range of page numbers to display.
     *
     * Centers around the current page, bounded by 1 and the total number of pages.
     */
    public function getRange(): array
    {
        $numPages = $this->getNumPages();
        $startRange = $this->currentPage - (int) floor($this->midRange / 2);
        $endRange = $this->currentPage + (int) floor($this->midRange / 2);

        if ($startRange <= 0) {
            $endRange += abs($startRange) + 1;
            $startRange = 1;
        }

        if ($endRange > $numPages) {
            $endRange = $numPages;
            $startRange = max(1, $endRange - $this->midRange + 1);
        }

        return range($startRange, $endRange);
    }

    public function getStartingPoint(): int
    {
        $range = $this->getRange();

        return $range[0];
    }

    public function getEndingPoint(): int
    {
        $range = $this->getRange();

        return $range[array_key_last($range)];
    }

    /**
     * Returns all pagination metadata as an associative array.
     *
     * Suitable for returning from API endpoints or passing to templates.
     */
    public function toArray(): array
    {
        return [
            'currentpage' => $this->getCurrentPage(),
            'numpages' => $this->getNumPages(),
            'midrange' => $this->getMidRange(),
            'range' => $this->getRange(),
            'start' => $this->getStartingPoint(),
            'end' => $this->getEndingPoint(),
        ];
    }
}
