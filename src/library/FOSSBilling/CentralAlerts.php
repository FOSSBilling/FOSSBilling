<?php

declare(strict_types=1);
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use Pimple\Container;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class CentralAlerts implements InjectionAwareInterface
{
    protected ?Container $di = null;

    private string $_url = 'https://fossbilling.org/api/central-alerts/';

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
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
     *
     * @throws Exception
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
     * Filter the cached alerts by type and version.
     *
     * @param array  $type    The alert types to filter by (e.g. ['info', 'warning']) - defaults to all types
     * @param string $version The version to filter by (e.g. 0.4.2) - defaults to the current version of FOSSBilling
     *
     * @return array The filtered alerts
     *
     * @throws Exception
     */
    public function filterAlerts(array $type = [], string $version = Version::VERSION): array
    {
        $alerts = $this->getAlerts();

        if (!empty($type)) {
            $alerts = array_filter($alerts, fn ($alert): bool => in_array($alert['type'], $type));
        }

        if ($version) {
            if (Config::getProperty('update_branch', 'release') === 'preview') {
                $alerts = array_filter($alerts, fn ($alert) => $alert['include_preview_branch']);
            } else {
                $alerts = array_filter($alerts, function ($alert) use ($version) {
                    $overThanTheMinimum = version_compare(strtolower($version), strtolower($alert['min_fossbilling_version']), '>=');
                    $lessThanTheMaximum = version_compare(strtolower($version), strtolower($alert['max_fossbilling_version']), '<=');

                    return $overThanTheMinimum && $lessThanTheMaximum;
                });
            }
        }

        return $alerts;
    }

    /**
     * Make a request to the FOSSBilling Central Alerts System API.
     *
     * @param string $endpoint The API endpoint to call (e.g. list)
     * @param array  $params   The array of parameters to pass to the API endpoint
     *
     * @return array The API response
     *
     * @throws Exception
     */
    public function makeRequest(string $endpoint, array $params = []): array
    {
        $url = $this->_url . $endpoint;

        try {
            $httpClient = HttpClient::create(['bindto' => BIND_TO]);
            $response = $httpClient->request('GET', $url, [
                'timeout' => 5,
                'query' => [...$params, 'fossbilling_version' => Version::VERSION],
            ]);
            $json = $response->toArray();
        } catch (TransportExceptionInterface|HttpExceptionInterface $e) {
            error_log($e->getMessage());

            throw new Exception('Unable to fetch alerts from Central Alerts System. See the error log for more information.', null);
        }

        if (isset($json['error']) && is_array($json['error'])) {
            throw new Exception($json['error']['message'], null);
        }

        if (!isset($json['result']) || !is_array($json['result'])) {
            throw new Exception('Invalid response from the FOSSBilling Central Alerts System.', null);
        }

        return $json['result'];
    }
}
