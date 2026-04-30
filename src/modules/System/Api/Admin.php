<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * System management methods.
 */

namespace Box\Mod\System\Api;

use FOSSBilling\Config;
use FOSSBilling\Tools;
use FOSSBilling\Validation\Api\RequiredParams;

class Admin extends \Api_Abstract
{
    /**
     * Get all defined system params.
     *
     * @return array
     */
    public function get_params($data)
    {
        return $this->getService()->getParams($data);
    }

    /**
     * Updated parameters array with new values. Creates new setting if it was
     * not defined earlier. You can create new parameters using this method.
     * This method accepts any number of parameters you pass.
     *
     * @return bool
     */
    public function update_params($data)
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('system', 'update_params');

        return $this->getService()->updateParams($data);
    }

    /**
     * System messages about working environment.
     *
     * @return array
     */
    public function messages($data)
    {
        $type = $data['type'] ?? 'info';

        return $this->getService()->getMessages($type);
    }

    /**
     * Get Central Alerts System messages sent for this installation.
     *
     * @return array - array of messages
     */
    public function cas_messages()
    {
        return $this->getService()->getCasMessages();
    }

    /**
     * Check if passed file name template exists for admin area.
     *
     * @return bool
     */
    public function template_exists($data)
    {
        if (!isset($data['file'])) {
            return false;
        }

        return $this->getService()->templateExists($data['file'], $this->getIdentity());
    }

    /**
     * Returns system environment information.
     *
     * @return array
     */
    public function env($data)
    {
        $ip = $data['ip'] ?? null;

        return $this->getService()->getEnv($ip);
    }

    /**
     * Method to check if staff member has permission to access module.
     *
     * @optional string $f - module method name
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['mod' => '"mod" key is missing'])]
    public function is_allowed($data)
    {
        $f = $data['f'] ?? null;
        $service = $this->di['mod_service']('Staff');

        return $service->hasPermission($this->getIdentity(), $data['mod'], $f);
    }

    /**
     * Clear system cache.
     *
     * @return bool
     */
    public function clear_cache()
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('system', 'invalidate_cache');

        return $this->getService()->clearCache();
    }

    /**
     * Used to check if there's an update available.
     */
    public function update_available(): bool
    {
        $updater = $this->di['updater'];

        return $updater->isUpdateAvailable();
    }

    /**
     * Returns an array containing the update info.
     */
    public function update_info(): array
    {
        $updater = $this->di['updater'];

        return $updater->getLatestVersionInfo();
    }

    /**
     * Forces the system to clear out the update cache and re-fetch the latest info.
     */
    public function recheck_update(): bool
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('system', 'recheck_update');

        $updater = $this->di['updater'];
        $updater->getLatestVersionInfo(null, true);

        return true;
    }

    /**
     * Update FOSSBilling core.
     *
     * @throws \FOSSBilling\Exception
     */
    public function update_core($data): bool
    {
        $updater = $this->di['updater'];
        if ($updater->getUpdateBranch() !== 'preview' && !$updater->isUpdateAvailable()) {
            throw new \FOSSBilling\InformationException('You have the latest version of FOSSBilling. You do not need to update.');
        }

        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('system', 'system_update');

        $new_version = $updater->getLatestVersion();
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminUpdateCore']);
        $updater->performUpdate();
        $this->di['events_manager']->fire(['event' => 'onAfterAdminUpdateCore']);

        $this->di['logger']->info('Updated FOSSBilling from %s to %s', \FOSSBilling\Version::VERSION, $new_version);

        return true;
    }

    /**
     * Update FOSSBilling config.
     *
     * @throws \FOSSBilling\Exception
     */
    public function manual_update(): bool
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('system', 'system_update');

        $updater = $this->di['updater'];
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminManualUpdate']);
        $updater->performManualUpdate();
        $this->di['events_manager']->fire(['event' => 'onAfterAdminManualUpdate']);
        $this->di['logger']->info('Updated FOSSBilling - applied patches and updated configuration file.');

        return true;
    }

    /**
     * Checks if the database is behind on patches.
     */
    public function is_behind_on_patches(): bool
    {
        $updater = $this->di['updater'];

        return $updater->isBehindOnDBPatches();
    }

    /**
     * Returns the unique instance ID for this FOSSBilling installation.
     */
    public function instance_id(): string
    {
        return INSTANCE_ID;
    }

    /**
     * Returns if error reporting is enabled or not on this FOSSBilling instance.
     */
    public function error_reporting_enabled(): bool
    {
        return (bool) Config::getProperty('debug_and_monitoring.report_errors', false);
    }

    /**
     * Toggles error reporting on this FOSSBilling instance.
     */
    public function toggle_error_reporting(): bool
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('system', 'toggle_error_reporting');

        $current = Config::getProperty('debug_and_monitoring.report_errors', false);
        Config::setProperty('debug_and_monitoring.report_errors', !$current);

        return true;
    }

    /**
     * Returns the last FOSSBilling version number that changed error reporting behavior.
     */
    public function last_error_reporting_change(): string
    {
        return \FOSSBilling\SentryHelper::last_change;
    }

    public function get_interface_ips(): array
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('system', 'manage_network_interface');

        return Tools::listHttpInterfaces();
    }

    public function set_interface_ip($data): bool
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('system', 'manage_network_interface');
        $config = Config::getConfig();

        if (isset($data['interface'])) {
            $interface = $data['interface'];
            if ($interface !== '0' && !filter_var($interface, FILTER_VALIDATE_IP)) {
                throw new \FOSSBilling\Exception('Invalid interface IP address');
            }
            $config['interface_ip'] = $interface;
        }

        if (isset($data['custom_interface'])) {
            $custom = $data['custom_interface'];
            if ($custom !== '' && !Tools::isValidHttpInterface($custom)) {
                throw new \FOSSBilling\Exception('Invalid custom interface. Must be a valid IP address or hostname.');
            }
            $config['custom_interface_ip'] = $custom;
        }

        Config::setConfig($config);

        return true;
    }
}
