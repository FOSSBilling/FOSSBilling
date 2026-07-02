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

final readonly class RouteMatcher
{
    public function match(string $routeMethod, string $routePath, ?array $conditions, string $requestPath, string $requestMethod): RouteMatch
    {
        if ($requestMethod === 'HEAD') {
            $requestMethod = 'GET';
        }

        if (strtoupper($routeMethod) !== $requestMethod) {
            return new RouteMatch(false);
        }

        $paramNames = [];
        $paramValues = [];

        preg_match_all('@:([a-zA-Z_\-]+)@', $routePath, $paramNames, PREG_PATTERN_ORDER);
        $paramNames = $paramNames[1];
        $conditions ??= [];

        $regexedRoute = preg_replace_callback(
            '@:[a-zA-Z_\-]+@',
            static function (array $matches) use ($conditions): string {
                $key = str_replace(':', '', $matches[0]);
                if (array_key_exists($key, $conditions)) {
                    return '(' . $conditions[$key] . ')';
                }

                return '([a-zA-Z0-9_\-]+)';
            },
            $routePath,
        );

        if (!is_string($regexedRoute) || preg_match('@^' . $regexedRoute . '$@', $requestPath, $paramValues) !== 1) {
            return new RouteMatch(false);
        }

        array_shift($paramValues);
        $params = [];
        $counted = count($paramNames);
        for ($i = 0; $i < $counted; ++$i) {
            $params[$paramNames[$i]] = $paramValues[$i];
        }

        return new RouteMatch(true, $params);
    }
}
