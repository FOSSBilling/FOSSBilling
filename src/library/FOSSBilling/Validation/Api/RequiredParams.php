<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Validation\Api;

/**
 * Attribute to declare required parameters for API methods.
 * Validates that parameters exist in the $data array passed to the method.
 *
 * Example usage:
 * #[RequiredParams(['code' => 'Currency code is missing', 'format' => 'Format is required'])]
 * public function create(array $data): string { }
 *
 * @Annotation
 * @Target({"METHOD"})
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class RequiredParams
{
    /**
     * @param array<string, string> $params Map of parameter names to error messages
     */
    public function __construct(
        public readonly array $params
    ) {
    }
}