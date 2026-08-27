<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * RDAP (RFC 7480-7484) client for domain availability lookups.
 *
 * The authoritative RDAP service for a TLD is resolved through the IANA DNS
 * bootstrap registry (RFC 9224). Availability is determined from the HTTP
 * status of the domain query: 404 means the domain is unregistered, any other
 * successful status means it is registered. Null is returned when no RDAP
 * service exists for the TLD or every query fails, so callers can decide how
 * to handle an indeterminate result.
 */
class Registrar_Rdap
{
    final public const BOOTSTRAP_URL = 'https://data.iana.org/rdap/dns.json';

    /**
     * Bounds applied to every request so a slow registry cannot occupy a worker indefinitely.
     */
    private const REQUEST_OPTIONS = [
        'timeout' => 10,
        'max_duration' => 15,
    ];

    private HttpClientInterface $httpClient;

    /**
     * Map of lowercase TLD labels to their RDAP base URLs.
     *
     * @var array<string, list<string>>|null
     */
    private ?array $bootstrap = null;

    public function __construct(
        ?HttpClientInterface $httpClient = null,
        private readonly ?Box_Log $logger = null,
    ) {
        $this->httpClient = ($httpClient ?? HttpClient::create(['bindto' => BIND_TO]))->withOptions(self::REQUEST_OPTIONS);
    }

    /**
     * Checks if a domain name is available for registration.
     *
     * @param string $domain a fully qualified domain name, e.g. "example.com"
     *
     * @return bool true when the domain appears unregistered, false when it is registered, null when availability cannot be determined
     */
    public function isDomainAvailable(string $domain): ?bool
    {
        $domain = strtolower(trim($domain));
        $ascii = $domain === '' ? false : idn_to_ascii($domain, IDNA_NONTRANSITIONAL_TO_ASCII | IDNA_USE_STD3_RULES);
        if ($ascii === false) {
            return null;
        }

        $domain = rtrim($ascii, '.');

        $labels = explode('.', $domain);
        if (count($labels) < 2 || in_array('', $labels, true)) {
            return null;
        }

        foreach ($this->resolveServers($domain) ?? [] as $server) {
            try {
                $statusCode = $this->httpClient
                    ->request('GET', rtrim($server, '/') . '/domain/' . rawurlencode($domain), [
                        'headers' => ['Accept' => 'application/rdap+json'],
                    ])
                    ->getStatusCode();
            } catch (HttpExceptionInterface $e) {
                $this->logger?->warning('RDAP request against ' . $server . ' failed: ' . $e->getMessage());

                continue;
            }

            if ($statusCode === 404) {
                return true;
            }

            if ($statusCode < 300) {
                return false;
            }

            $this->logger?->warning('RDAP request against ' . $server . ' returned the unexpected status code ' . $statusCode);
        }

        return null;
    }

    /**
     * Returns the RDAP base URLs serving registrations under the given domain,
     * most specific zone first, or null when no zone of the domain is covered
     * by the bootstrap registry.
     *
     * @return list<string>|null
     */
    private function resolveServers(string $domain): ?array
    {
        $bootstrap = $this->getBootstrap();

        $labels = explode('.', $domain);
        while (count($labels) > 1) {
            array_shift($labels);

            $servers = $bootstrap[implode('.', $labels)] ?? null;
            if ($servers !== null && $servers !== []) {
                return self::preferHttps($servers);
            }
        }

        if ($bootstrap !== []) {
            $this->logger?->warning('No RDAP service is known for the ' . implode('.', $labels) . ' zone');
        }

        return null;
    }

    /**
     * Tries HTTPS servers before HTTP ones, per RFC 9224 §4.1, so an on-path attacker can't force
     * an unencrypted query by having an HTTP entry ordered first in the bootstrap registry.
     * Otherwise preserves the registry's original ordering within each group.
     *
     * @param list<string> $servers
     *
     * @return list<string>
     */
    private static function preferHttps(array $servers): array
    {
        $isHttps = static fn (string $server): bool => str_starts_with($server, 'https://');

        return [
            ...array_values(array_filter($servers, $isHttps)),
            ...array_values(array_filter($servers, static fn (string $server): bool => !$isHttps($server))),
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function getBootstrap(): array
    {
        if ($this->bootstrap !== null) {
            return $this->bootstrap;
        }

        try {
            $body = $this->httpClient->request('GET', self::BOOTSTRAP_URL)->getContent();
            $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (HttpExceptionInterface|JsonException $e) {
            $this->logger?->warning('Unable to fetch the RDAP bootstrap registry: ' . $e->getMessage());

            return $this->bootstrap = [];
        }

        $services = is_array($data) ? $data['services'] ?? [] : null;
        if (!is_array($services)) {
            return $this->rejectBootstrap('The RDAP bootstrap registry response is malformed');
        }

        $map = [];
        foreach ($services as $service) {
            if (!$this->isValidServiceEntry($service)) {
                return $this->rejectBootstrap('The RDAP bootstrap registry response is malformed');
            }

            foreach ($service[0] as $label) {
                $map[strtolower($label)] = array_values($service[1]);
            }
        }

        return $this->bootstrap = $map;
    }

    private function rejectBootstrap(string $message): array
    {
        $this->logger?->warning($message);

        return $this->bootstrap = [];
    }

    /**
     * Validates one [labels, servers] entry of the bootstrap registry.
     */
    private function isValidServiceEntry($entry): bool
    {
        if (!is_array($entry) || count($entry) !== 2 || !is_array($entry[0]) || !is_array($entry[1])) {
            return false;
        }

        foreach ($entry[0] as $label) {
            if (!is_string($label) || $label === '') {
                return false;
            }
        }

        foreach ($entry[1] as $server) {
            if (!self::isValidServerUrl($server)) {
                return false;
            }
        }

        return true;
    }

    private static function isValidServerUrl($server): bool
    {
        return is_string($server)
            && filter_var($server, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($server, PHP_URL_SCHEME), ['http', 'https'], true);
    }
}
