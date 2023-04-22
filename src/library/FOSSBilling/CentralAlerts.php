<?php

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

use Box\InjectionAwareInterface;

class FOSSBilling_CentralAlerts implements InjectionAwareInterface
{
    /**
     * @var \Box_Di
     */
    protected $di = null;

    private $_url = 'https://fossbilling.org/api/central-alerts/';

    /**
     * @param \Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return \Box_Di
     */
    public function getDi()
    {
        return $this->di;
    }

    /**
     * Fetch the latest alerts from the FOSSBilling Central Alerts System and save them to the database.
     * The Central Alerts System allows the FOSSBilling team to send alerts to instance administrators.
     * The alerts are only displayed to the administrators of FOSSBilling instances through the admin area.
     * 
     * They are useful for notifying administrators of security issues and other important information.
     * The alerts are **never** displayed to the clients.
     * 
     * @return array The alerts
     */
    public function updateAlerts() {
        $alerts = $this->makeRequest('list');

        if (!is_array($alerts) || empty($alerts) || !isset($alerts['alerts'])) {
            return [];
        }

        $this->flushAlerts();
        $this->saveAlerts($alerts['alerts']);

        return $alerts['alerts'];
    }

    /**
     * Fetch the cached alerts from the database.
     * 
     * @return array The alerts
     */
    public function getAlerts() {
        $alerts = $this->di['db']->getAll('SELECT * FROM central_alerts');

        if (!is_array($alerts) || empty($alerts)) {
            return [];
        }

        return array_map(function($alert) {
            return json_decode($alert['details'], true);
        }, $alerts);
    }

    /**
     * Filter the cached alerts by type and version
     * 
     * @param array $type The alert types to filter by (e.g. ['info', 'warning']) - defaults to all types
     * @param string $version The version to filter by (e.g. 0.4.2) - defaults to the current version of FOSSBilling
     * 
     * @return array The filtered alerts
     */
    public function filterAlerts($type = [], $version = \Box_Version::VERSION) {
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
     * Flush all alerts from the database.
     * 
     * @return void
     */
    public function flushAlerts() {
        $this->di['db']->exec('TRUNCATE TABLE central_alerts');
    }

    /**
     * Save alerts to the database.
     * 
     * @param array $alerts The alerts to save
     * 
     * @return void
     */
    public function saveAlerts($alerts) {
        foreach ($alerts as $alert) {
            $sql = "INSERT INTO central_alerts (id, details) VALUES (:id, :details) ON DUPLICATE KEY UPDATE details = :details";

            $this->di['db']->exec($sql, [
                ':id' => $alert['id'],
                ':details' => json_encode($alert),
            ]);
        }
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
    public function makeRequest($endpoint, array $params = [])
    {
        $url = $this->_url . $endpoint;

        $response = $this->di['http_client']->request('GET', $url, [
            'timeout' => 5,
            'query' => array_merge($params, [
                'fossbilling_version' => Box_Version::VERSION,
            ]),
        ]);

        $json = $response->toArray();

        if (is_null($json)) {
            throw new \Box_Exception('Unable to connect to the FOSSBilling Central Alerts System.', null);
        }

        if (isset($json['error']) && is_array($json['error'])) {
            throw new \Box_Exception($json['error']['message'], null);
        }

        if (!isset($json['result']) || !is_array($json['result'])) {
            throw new \Box_Exception('Invalid response from the FOSSBilling Central Alerts System.', null);
        }

        return $json['result'];
    }
}