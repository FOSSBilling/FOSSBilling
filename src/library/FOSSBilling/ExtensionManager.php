<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use Psr\Cache\CacheItemPoolInterface;

class ExtensionManager implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    final public const string TYPE_MOD = 'mod';
    final public const string TYPE_THEME = 'theme';
    final public const string TYPE_PG = 'payment-gateway';
    final public const string TYPE_SM = 'server-manager';
    final public const string TYPE_DR = 'domain-registrar';
    final public const string TYPE_HOOK = 'hook';
    final public const string TYPE_TRANSLATION = 'translation';

    private string $apiUrl = 'https://api.fossbilling.net/extensions/v1/';

    /**
     * How long a fetched directory response is served as-is.
     */
    private const int DIRECTORY_CACHE_TTL = 3600;

    /**
     * How long the last-known-good response is kept as a fallback for directory outages.
     */
    private const int DIRECTORY_STALE_TTL = 48 * 3600;

    /**
     * How long a failed refresh suppresses further directory requests.
     */
    private const int DIRECTORY_UNAVAILABLE_TTL = 5 * 60;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    /**
     * Fetch extension details from the FOSSBilling extension directory.
     *
     * @param string $id The extension identifier (e.g. Example)
     *
     * @return array The extension details
     *
     * @example https://api.fossbilling.net/extensions/v1/Example An example of the API response
     *
     * @throws Exception
     */
    public function getExtension(string $id): array
    {
        $this->assertValidIdentifier($id);

        return $this->makeRequest($id, [], function (array $manifest): void {
            if (empty($manifest)) {
                throw new Exception('Unable to fetch the extension details from the FOSSBilling extension directory.');
            }

            $this->validateMetadata($manifest);
        });
    }

    /**
     * Fetch the list of releases of an extension from the FOSSBilling extension directory.
     *
     * @param string $id The extension identifier (e.g. Example)
     *
     * @return array The list of releases of the extension
     *
     * @example https://api.fossbilling.net/extensions/v1/Example An example of the API response (the "releases" array)
     *
     * @throws Exception
     */
    public function getExtensionReleases(string $id): array
    {
        $releases = $this->getExtension($id)['releases'];

        if (empty($releases) || !is_array($releases)) {
            throw new Exception('An error occurred when fetching the extensions releases');
        }

        return $releases;
    }

    /**
     * Fetch the latest release of an extension from the FOSSBilling extension directory.
     *
     * @param string $id The extension identifier (e.g. Example)
     *
     * @return array The latest release of the extension
     *
     * @example https://api.fossbilling.net/extensions/v1/Example An example of the API response (the first element in the "releases" array)
     *
     * @throws Exception
     */
    public function getLatestExtensionRelease(string $id): array
    {
        $releases = $this->getExtensionReleases($id);
        $latest = reset($releases);

        if (empty($latest) || !is_array($latest)) {
            throw new Exception('Unable to fetch the latest extension release.');
        }

        return $latest;
    }

    /**
     * Fetch the list of extensions from the FOSSBilling extension directory.
     *
     * @param string $type The extension type (e.g. mod) - optional
     *
     * @return array The list of extensions
     *
     * @example https://api.fossbilling.net/extensions/v1/list An example of the API response
     */
    public function getExtensionList(?string $type = null): array
    {
        $params = [];

        if (!empty($type)) {
            $params['type'] = $type;
        }

        return $this->makeRequest('list', $params, function (array $extensions): void {
            foreach ($extensions as $extension) {
                $this->validateMetadata($extension);
            }
        });
    }

    private function assertValidIdentifier(string $id): void
    {
        if (preg_match('/\A[A-Za-z0-9_-]+\z/', $id) !== 1) {
            throw new InformationException('Extension ID contains invalid characters.');
        }
    }

    /** Validate after cache lookup so previously cached metadata is checked too. */
    private function validateMetadata(mixed $extension): void
    {
        if (!is_array($extension) || !is_string($extension['id'] ?? null)
            || preg_match('/\A[A-Za-z0-9_-]+\z/', $extension['id']) !== 1
            || !is_string($extension['name'] ?? null)) {
            throw new Exception('Invalid response from the FOSSBilling extension directory.', null, 746);
        }

        $author = $extension['author'] ?? [];
        if (!is_array($author)) {
            throw new Exception('Invalid response from the FOSSBilling extension directory.', null, 746);
        }

        $url = $author['URL'] ?? null;
        if ($url === null || $url === '') {
            return;
        }

        if (!is_string($url) || preg_match('/[\x00-\x20\x7f]/', $url) === 1
            || filter_var($url, FILTER_VALIDATE_URL) === false
            || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            throw new Exception('Invalid response from the FOSSBilling extension directory.', null, 746);
        }
    }

    /**
     * Check if the latest version of an extension is compatible with the current FOSSBilling version.
     *
     * @param string $extension The extension identifier (e.g. Example)
     *
     * @return bool True if the extension is compatible, false otherwise
     */
    public function isExtensionCompatible(string $extension): bool
    {
        $latest = $this->getLatestExtensionRelease($extension);

        if (Config::getProperty('update_branch', 'release') === 'release') {
            if (version_compare(Version::VERSION, $latest['min_fossbilling_version'], '<')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Make a request to the FOSSBilling extension directory.
     *
     * Successful responses are cached for an hour. When a refresh fails, the
     * last-known-good response is served instead (up to two days old), and
     * further refresh attempts are suppressed for a few minutes so an
     * unreachable directory doesn't slow down every admin page load.
     *
     * @param string        $endpoint       The API endpoint to call (e.g. list)
     * @param array         $params         The array of parameters to pass to the API endpoint
     * @param callable|null $validateResult Optional check receiving the decoded result; must throw
     *                                      when it isn't usable. Applied to cached hits as well as
     *                                      fresh responses, so invalid data never replaces the
     *                                      last-known-good copy.
     *
     * @return array The API response
     *
     * @throws Exception when the directory can't be reached and nothing usable is cached
     */
    public function makeRequest(string $endpoint, array $params = [], ?callable $validateResult = null): array
    {
        $url = $this->apiUrl . $endpoint;
        $query = [...$params, 'fossbilling_version' => Version::VERSION];
        // The installed version is part of the key: the directory filters results by it,
        // so a response cached before an upgrade must not be served afterwards.
        $key = 'extension-manager-' . hash('xxh3', $endpoint . serialize($query));
        $cache = $this->di['cache'];

        $fresh = $cache->getItem($key);
        if ($fresh->isHit() && is_array($fresh->get())) {
            $result = $fresh->get();
            if ($validateResult === null) {
                return $result;
            }

            try {
                $validateResult($result);

                return $result;
            } catch (\Exception) {
                // Cached data no longer validates: fall through and refresh it.
            }
        }

        if ($cache->getItem($key . '-unavailable')->isHit()) {
            return $this->staleOrThrow($cache, $key, new Exception('The FOSSBilling extension directory is temporarily unreachable.', null, 746), $validateResult);
        }

        try {
            $result = $this->fetchDirectoryResult($url, $query);
            if ($validateResult !== null) {
                $validateResult($result);
            }
        } catch (\Exception $e) {
            $unavailable = $cache->getItem($key . '-unavailable');
            $unavailable->set(true);
            $unavailable->expiresAfter(self::DIRECTORY_UNAVAILABLE_TTL);
            $cache->save($unavailable);

            if (isset($this->di['logger'])) {
                $this->di['logger']->warning('Extension directory refresh for "{endpoint}" failed: {error}', [
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage(),
                ]);
            }

            return $this->staleOrThrow($cache, $key, $e instanceof Exception ? $e : new Exception('Unable to fetch the extension details from the FOSSBilling extension directory: :reason.', [':reason' => $e->getMessage()], 746), $validateResult);
        }

        $fresh->set($result);
        $fresh->expiresAfter(self::DIRECTORY_CACHE_TTL);
        $cache->save($fresh);

        $stale = $cache->getItem($key . '-stale');
        $stale->set($result);
        $stale->expiresAfter(self::DIRECTORY_STALE_TTL);
        $cache->save($stale);

        return $result;
    }

    /**
     * Perform the directory HTTP request and validate the response shape.
     *
     * @throws \Exception when the request fails or the response is unusable
     */
    private function fetchDirectoryResult(string $url, array $query): array
    {
        $response = $this->di['http_client']->request('GET', $url, [
            'timeout' => 5,
            'query' => $query,
        ]);

        $json = $response->toArray();

        if (isset($json['error']) && is_array($json['error'])) {
            throw new Exception((string) ($json['error']['message'] ?? 'Unknown error'), null, 746);
        }

        if (!isset($json['result']) || !is_array($json['result'])) {
            throw new Exception('Invalid response from the FOSSBilling extension directory.', null, 746);
        }

        return $json['result'];
    }

    /**
     * Serve the last-known-good response, or throw when nothing usable is cached yet.
     *
     * @throws Exception the given fallback when there is no stale response
     */
    private function staleOrThrow(CacheItemPoolInterface $cache, string $key, Exception $fallback, ?callable $validateResult): array
    {
        $stale = $cache->getItem($key . '-stale');
        if ($stale->isHit() && is_array($stale->get())) {
            $result = $stale->get();
            if ($validateResult !== null) {
                try {
                    $validateResult($result);
                } catch (\Exception) {
                    throw $fallback;
                }
            }

            return $result;
        }

        throw $fallback;
    }
}
