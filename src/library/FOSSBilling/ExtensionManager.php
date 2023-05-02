<?php declare(strict_types=1);
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace FOSSBilling;

use \FOSSBilling\InjectionAwareInterface;
use Symfony\Component\HttpClient\HttpClient;

class ExtensionManager implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di;

    const TYPE_MOD = 'mod';
    const TYPE_THEME = 'theme';
    const TYPE_PG = 'payment-gateway';
    const TYPE_SM = 'server-manager';
    const TYPE_DR = 'domain-registrar';
    const TYPE_HOOK = 'hook';
    const TYPE_TRANSLATION = 'translation';

    private string $_url = 'https://extensions.fossbilling.org/api/';

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    /**
     * Fetch extension details from the FOSSBilling extension directory
     *
     * @param string $id The extension identifier (e.g. Example)
     *
     * @return array The extension details
     * @example https://extensions.fossbilling.org/api/extension/Example An example of the API response
     * @throws \Box_Exception
     */
    public function getExtension(string $id): array
    {
        $manifest = $this->makeRequest('extension/' . $id);

        if (empty($manifest)) {
            throw new \Box_Exception('Unable to fetch the extension details from the FOSSBilling extension directory.');
        }

        return $manifest;
    }

    /**
     * Fetch the list of releases of an extension from the FOSSBilling extension directory
     *
     * @param string $id The extension identifier (e.g. Example)
     *
     * @return array The list of releases of the extension
     * @example https://extensions.fossbilling.org/api/extension/Example An example of the API response (the "releases" array)
     * @throws \Box_Exception
     */
    public function getExtensionReleases(string $id): array
    {
        $releases = $this->getExtension($id)['releases'];

        if (empty($releases) || !is_array($releases)) {
            throw new \Box_Exception('Unable to fetch the releases of the extension from the FOSSBilling extension directory.');
        }

        return $releases;
    }

    /**
     * Fetch the latest release of an extension from the FOSSBilling extension directory
     *
     * @param string $id The extension identifier (e.g. Example)
     *
     * @return array The latest release of the extension
     * @example https://extensions.fossbilling.org/api/extension/Example An example of the API response (the first element in the "releases" array)
     * @throws \Box_Exception
     */
    public function getLatestExtensionRelease(string $id): array
    {
        $releases = $this->getExtensionReleases($id);
        $latest = reset($releases);

        if (empty($latest) || !is_array($latest)) {
            throw new \Box_Exception('Unable to fetch the latest release of the extension.');
        }

        return $latest;
    }

    /**
     * Fetch the list of extensions from the FOSSBilling extension directory
     *
     * @param string $type The extension type (e.g. mod) - optional
     *
     * @return array The list of extensions
     * @example https://extensions.fossbilling.org/api/list An example of the API response
     */
    public function getExtensionList($type = null): array
    {
        $params = [];

        if (!empty($type)) {
            $params['type'] = $type;
        }

        return $this->makeRequest('list', $params);
    }

    /**
     * Check if the latest version of an extension is compatible with the current FOSSBilling version
     *
     * @param string $extension The extension identifier (e.g. Example)
     *
     * @return bool True if the extension is compatible, false otherwise
     */
    public function isExtensionCompatible(string $extension): bool
    {
        $latest = $this->getLatestExtensionRelease($extension);

        if ($this->di['config']['update_branch'] === 'release') {
            if (version_compare(\FOSSBilling\Version::VERSION, $latest['min_fossbilling_version'], '<')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Make a request to the FOSSBilling extension directory
     *
     * @param string $endpoint The API endpoint to call (e.g. list)
     * @param array $params The array of parameters to pass to the API endpoint
     *
     * @return array The API response
     * @throws \Box_Exception
     */
    public function makeRequest(string $endpoint, array $params = []): array
    {
        $url = $this->_url . $endpoint;

        $httpClient = \Symfony\Component\HttpClient\HttpClient::create();
        $response = $httpClient->request('GET', $url, [
            'timeout' => 5,
            'query' => array_merge($params, [
                'fossbilling_version' => \FOSSBilling\Version::VERSION,
            ]),
        ]);

        $json = $response->toArray();

        if (is_null($json)) {
            throw new \Box_Exception('Unable to connect to the FOSSBilling extension directory.', null, 1545);
        }

        if (isset($json['error']) && is_array($json['error'])) {
            throw new \Box_Exception($json['error']['message'], null, 746);
        }

        if (!isset($json['result']) || !is_array($json['result'])) {
            throw new \Box_Exception('Invalid response from the FOSSBilling extension directory.', null, 746);
        }

        return $json['result'];
    }
}
