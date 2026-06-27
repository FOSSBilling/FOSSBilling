<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Security;

use FOSSBilling\Environment;

class RandomizedTimeFloor
{
    /**
     * Enforces a randomized minimum execution time for sensitive operations.
     * This helps mask processing time differences to mitigate user enumeration
     * and timing attacks.
     *
     * @param float $startedAt the microtime(true) timestamp of when the operation started
     * @param int   $minMs     the minimum total execution time in milliseconds
     * @param int   $maxMs     the maximum total execution time in milliseconds
     */
    public static function apply(float $startedAt, int $minMs = 75, int $maxMs = 125): void
    {
        // Avoid adding arbitrary delays to tests or command-line scripts
        if (Environment::isCLI() || Environment::isTesting()) {
            return;
        }

        if ($minMs < 0 || $maxMs < $minMs) {
            throw new \InvalidArgumentException('Randomized time floor bounds are invalid.');
        }

        $minimumMs = random_int($minMs, $maxMs);
        $elapsedMs = (microtime(true) - $startedAt) * 1000;

        if ($elapsedMs < $minimumMs) {
            usleep((int) (($minimumMs - $elapsedMs) * 1000));
        }
    }
}
