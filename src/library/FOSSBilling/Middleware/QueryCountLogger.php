<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Middleware;

use FOSSBilling\Environment;

/**
 * Query count logger middleware (dev environment only).
 *
 * Logs the number of database queries executed per route and warns
 * if the performance budget is exceeded.
 *
 * Performance budget: < 15 queries for list endpoints, < 5 for details.
 *
 * Note: This is a placeholder for Doctrine 3.x middleware.
 * Full implementation requires Doctrine DBAL middleware system.
 */
class QueryCountLogger
{
    private int $queryCount = 0;
    private ?string $currentRoute = null;

    /**
     * Set the current route for logging context.
     *
     * @param string $route The route being executed
     */
    public function setRoute(string $route): void
    {
        $this->currentRoute = $route;
        $this->queryCount = 0;
    }

    /**
     * Increment query count (called manually for now).
     *
     * TODO: Integrate with Doctrine 3.x middleware system properly.
     */
    public function incrementQueryCount(): void
    {
        if (Environment::isDevelopment()) {
            ++$this->queryCount;
        }
    }

    /**
     * Log query count at end of request.
     *
     * Warns if query count exceeds performance budget.
     */
    public function logQueryCount(): void
    {
        if (!Environment::isDevelopment()) {
            return;
        }

        $route = $this->currentRoute ?? 'unknown';

        // Performance budget thresholds
        $listBudget = 15;
        $detailBudget = 5;

        // Determine if this is a list endpoint (heuristic)
        $isList = str_contains($route, 'get_list') || str_contains($route, 'list');
        $budget = $isList ? $listBudget : $detailBudget;

        if ($this->queryCount > $budget) {
            error_log(sprintf(
                'PERFORMANCE WARNING: %s executed %d queries (budget: %d for %s endpoints)',
                $route,
                $this->queryCount,
                $budget,
                $isList ? 'list' : 'detail'
            ));
        } elseif ($this->queryCount > 0) {
            error_log(sprintf(
                'PERFORMANCE: %s executed %d queries (within budget: %d)',
                $route,
                $this->queryCount,
                $budget
            ));
        }
    }

    /**
     * Get current query count.
     *
     * @return int Number of queries executed
     */
    public function getQueryCount(): int
    {
        return $this->queryCount;
    }

    /**
     * Reset query counter.
     */
    public function reset(): void
    {
        $this->queryCount = 0;
        $this->currentRoute = null;
    }
}
