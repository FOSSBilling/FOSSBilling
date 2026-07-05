<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Http;

final readonly class RouteDefinition
{
    public function __construct(
        public string $httpMethod,
        public string $path,
        public string $methodName,
        public ?array $conditions = [],
        public ?string $controllerClass = null,
    ) {
    }
}
