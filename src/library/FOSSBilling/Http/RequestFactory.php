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
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;

final class RequestFactory
{
    public const string ROUTE_PATH_ATTRIBUTE = '_fossbilling_route_path';

    public static function createFromGlobals(array $proxyConfig = []): Request
    {
        return self::configure(Request::createFromGlobals(), $proxyConfig);
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

    public static function getPreConfigProxyConfig(array $server): array
    {
        // Forwarded headers are client-controlled until trusted proxies are explicitly configured.
        return [];
    }

    public static function getPreConfigProxyCandidate(array $server): array
    {
        $remoteAddress = trim((string) ($server['REMOTE_ADDR'] ?? ''));
        if ($remoteAddress === '' || !self::hasForwardedHeaders($server)) {
            return [];
        }

        $headers = self::getForwardedHeaderValues($server);
        $forwardedHeader = trim((string) ($server['HTTP_FORWARDED'] ?? ''));
        $headerMode = $forwardedHeader !== '' ? 'forwarded' : 'x_forwarded';
        $suggestedUrl = self::getForwardedUrlSuggestion($server, $headerMode);

        return [
            'detected' => true,
            'remote_addr' => $remoteAddress,
            'remote_addr_is_private' => self::isLocalNetworkAddress($remoteAddress),
            'proxies' => [$remoteAddress],
            'headers' => $headerMode,
            'header_values' => $headers,
            'suggested_url' => $suggestedUrl,
        ];
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
            $trustedProxies = array_filter(array_map(trim(...), explode(',', $trustedProxies)));
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

    private static function hasForwardedHeaders(array $server): bool
    {
        return self::getForwardedHeaderValues($server) !== [];
    }

    private static function getForwardedHeaderValues(array $server): array
    {
        $headers = [
            'Forwarded' => $server['HTTP_FORWARDED'] ?? null,
            'X-Forwarded-For' => $server['HTTP_X_FORWARDED_FOR'] ?? null,
            'X-Forwarded-Host' => $server['HTTP_X_FORWARDED_HOST'] ?? null,
            'X-Forwarded-Proto' => $server['HTTP_X_FORWARDED_PROTO'] ?? null,
            'X-Forwarded-Port' => $server['HTTP_X_FORWARDED_PORT'] ?? null,
            'X-Forwarded-Prefix' => $server['HTTP_X_FORWARDED_PREFIX'] ?? null,
        ];

        return array_filter($headers, static fn (mixed $value): bool => $value !== null && trim((string) $value) !== '');
    }

    private static function getForwardedUrlSuggestion(array $server, string $headerMode): ?string
    {
        $scheme = null;
        $host = null;

        if ($headerMode === 'forwarded') {
            $forwardedValues = self::parseForwardedHeader((string) ($server['HTTP_FORWARDED'] ?? ''));
            $scheme = $forwardedValues['proto'] ?? null;
            $host = $forwardedValues['host'] ?? null;
        } else {
            $scheme = self::getFirstHeaderValue($server['HTTP_X_FORWARDED_PROTO'] ?? null);
            $host = self::getFirstHeaderValue($server['HTTP_X_FORWARDED_HOST'] ?? null);
            $port = self::getFirstHeaderValue($server['HTTP_X_FORWARDED_PORT'] ?? null);
            if ($host !== null && $port !== null && !str_contains($host, ':') && !in_array($port, ['80', '443'], true)) {
                $host .= ':' . $port;
            }
        }

        if ($scheme === null || $host === null || !in_array(strtolower($scheme), ['http', 'https'], true)) {
            return null;
        }

        $prefix = self::getFirstHeaderValue($server['HTTP_X_FORWARDED_PREFIX'] ?? null) ?? '';
        $path = trim($prefix, '/');

        return strtolower($scheme) . '://' . trim($host, " \t\n\r\0\x0B\"'") . ($path === '' ? '/' : '/' . $path . '/');
    }

    private static function parseForwardedHeader(string $header): array
    {
        $firstForwardedValue = explode(',', $header)[0];
        $values = [];

        foreach (explode(';', $firstForwardedValue) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, null);
            $key = strtolower(trim((string) $key));
            $value = trim((string) $value, " \t\n\r\0\x0B\"");
            if ($key !== '' && $value !== '') {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    private static function getFirstHeaderValue(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return trim(explode(',', $value)[0]);
    }

    private static function isLocalNetworkAddress(string $address): bool
    {
        return IpUtils::checkIp($address, [
            '127.0.0.0/8',
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '::1/128',
            'fc00::/7',
        ]);
    }
}
