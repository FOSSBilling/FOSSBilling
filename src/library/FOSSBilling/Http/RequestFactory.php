<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Http;

use FOSSBilling\Config;
use Symfony\Component\HttpFoundation\Request;

final class RequestFactory
{
    public const ROUTE_PATH_ATTRIBUTE = '_fossbilling_route_path';

    public static function createFromGlobals(array $proxyConfig = []): Request
    {
        return self::configure(Request::createFromGlobals(), $proxyConfig);
    }

    public static function createFromConfig(): Request
    {
        return self::createFromGlobals(self::getProxyConfigFromAppConfig());
    }

    public static function configure(Request $request, array $proxyConfig = []): Request
    {
        Request::setTrustedProxies(
            self::getTrustedProxies($proxyConfig),
            self::getTrustedHeaderSet($proxyConfig)
        );

        return $request;
    }

    public static function configureFromConfig(Request $request): Request
    {
        return self::configure($request, self::getProxyConfigFromAppConfig());
    }

    public static function normalizeRoutePath(Request $request): string
    {
        $rawPath = $request->query->get('_url');
        if (!is_string($rawPath)) {
            $rawPath = $request->getPathInfo();
        }

        $path = $rawPath !== '' ? $rawPath : '/';
        if ($path[0] !== '/' || preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            $path = '/';
        }

        if (str_starts_with($path, '/page/')) {
            $path = substr_replace($path, '/custompages/', 0, strlen('/page/'));
        }

        $request->attributes->set(self::ROUTE_PATH_ATTRIBUTE, $path);
        $request->query->set('_url', $path);

        return $path;
    }

    public static function getRoutePath(Request $request): string
    {
        $routePath = $request->attributes->get(self::ROUTE_PATH_ATTRIBUTE);
        if (is_string($routePath) && $routePath !== '') {
            return $routePath;
        }

        return self::normalizeRoutePath($request);
    }

    private static function getProxyConfigFromAppConfig(): array
    {
        return [
            'enabled' => Config::getProperty('security.trusted_proxies.enabled', false),
            'proxies' => Config::getProperty('security.trusted_proxies.proxies', []),
            'headers' => Config::getProperty('security.trusted_proxies.headers', 'x_forwarded'),
        ];
    }

    private static function getTrustedProxies(array $proxyConfig): array
    {
        if (!($proxyConfig['enabled'] ?? false)) {
            return [];
        }

        $trustedProxies = $proxyConfig['proxies'] ?? [];
        if (is_string($trustedProxies)) {
            $trustedProxies = array_filter(array_map('trim', explode(',', $trustedProxies)));
        }

        if (!is_array($trustedProxies)) {
            throw new \InvalidArgumentException('Trusted proxies must be configured as a string or an array.');
        }

        return array_values(array_filter(array_map(
            static fn (mixed $proxy): string => trim((string) $proxy),
            $trustedProxies
        )));
    }

    private static function getTrustedHeaderSet(array $proxyConfig): int
    {
        if (!($proxyConfig['enabled'] ?? false)) {
            return 0;
        }

        return match ($proxyConfig['headers'] ?? 'x_forwarded') {
            'forwarded' => Request::HEADER_FORWARDED,
            'x_forwarded' => Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PREFIX,
            'aws_elb' => Request::HEADER_X_FORWARDED_AWS_ELB,
            'traefik' => Request::HEADER_X_FORWARDED_TRAEFIK,
            default => throw new \InvalidArgumentException('Invalid trusted proxy header configuration.'),
        };
    }
}
