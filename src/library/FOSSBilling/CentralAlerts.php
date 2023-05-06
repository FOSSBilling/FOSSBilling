<?php declare(strict_types=1);
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc. 
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use \FOSSBilling\InjectionAwareInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\Cache\ItemInterface;

class CentralAlerts implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di;

    private string $_url = 'https://fossbilling.org/api/central-alerts/';

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    /**
     * Fetch the latest alerts from the FOSSBilling Central Alerts System or the cache.
     *
     * The Central Alerts System allows the FOSSBilling team to send alerts to instance administrators.
     * The alerts are only displayed to the administrators of FOSSBilling instances through the admin area.
     *
     * They are useful for notifying administrators of security issues and other important information.
     * The alerts are **never** displayed to the clients.
     *
     * @return array The alerts
     * @throws \Box_Exception
     */
    public function getAlerts(): array
    {
        $alerts = $this->di['cache']->get('CentralAlerts.getAlerts', function (ItemInterface $item) {
            $item->expiresAfter(4 * 60 * 60);

            return $this->makeRequest('list');
        });

        return empty($alerts['alerts']) ? [] : $alerts['alerts'];
    }

    /**
     * Filter the cached alerts by type and version
     *
     * @param array $type The alert types to filter by (e.g. ['info', 'warning']) - defaults to all types
     * @param string $version The version to filter by (e.g. 0.4.2) - defaults to the current version of FOSSBilling
     *
     * @return array The filtered alerts
     * @throws \Box_Exception
     */
    public function filterAlerts(array $type = [], string $version = \FOSSBilling\Version::VERSION): array
    {
        $alerts = $this->getAlerts();

        if (is_array($type) && !empty($type)) {
            $alerts = array_filter($alerts, function($alert) use ($type) {
                return in_array($alert['type'], $type);
            });
        }

        if ($version) {
            if ($this->di['config']['update_branch'] === 'preview') {
                $alerts = array_filter($alerts, function($alert) {
                    return $alert['include_preview_branch'];
                });
            } else {
                $alerts = array_filter($alerts, function($alert) use ($version) {
                    $overThanTheMinimum = version_compare(strtolower($version), strtolower($alert['min_fossbilling_version']), '>=');
                    $lessThanTheMaximum = version_compare(strtolower($version), strtolower($alert['max_fossbilling_version']), '<=');

                    return $overThanTheMinimum && $lessThanTheMaximum;
                });
            }
        }

        return $alerts;
    }

    /**
     * Make a request to the FOSSBilling Central Alerts System API
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
        $client = HttpClient::create();

        $response = $client->request('GET', $url, [
            'timeout' => 5,
            'query' => array_merge($params, [
                'fossbilling_version' => \FOSSBilling\Version::VERSION,
            ]),
        ]);

        $json = $response->toArray();

        if (isset($json['error']) && is_array($json['error'])) {
            throw new \Box_Exception($json['error']['message'], null);
        }

        if (!isset($json['result']) || !is_array($json['result'])) {
            throw new \Box_Exception('Invalid response from the FOSSBilling Central Alerts System.', null);
        }

        return $json['result'];
    }
}
