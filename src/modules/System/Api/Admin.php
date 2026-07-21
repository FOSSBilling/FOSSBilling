<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
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

class Admin extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Get all defined system params.
     *
     * @return array
     */
    public function get_params($data)
    {
        $this->checkPermissions('system', 'manage_settings');

        return $this->getService()->getParams($data);
    }

    /**
     * Returns localization settings stored in the FOSSBilling config file.
     */
    public function localization_settings(): array
    {
        $this->checkPermissions('system', 'manage_settings');

        return [
            'locale' => (string) Config::getProperty('i18n.locale', 'en_US'),
            'auto_detect_locale' => Tools::normalizeBoolean(Config::getProperty('i18n.auto_detect_locale', true), true),
        ];
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
        $this->checkPermissions('system', 'update_params');

        return $this->getService()->updateParams($data);
    }

    /**
     * Updates localization settings stored in the FOSSBilling config file.
     *
     * @throws \FOSSBilling\Exception
     */
    public function update_localization_settings($data): bool
    {
        $this->checkPermissions('system', 'update_params');

        if (isset($data['locale']) && $data['locale'] !== '') {
            Config::setProperty('i18n.locale', $data['locale']);
        }

        Config::setProperty('i18n.auto_detect_locale', Tools::normalizeBoolean($data['auto_detect_locale'] ?? true, true));

        return true;
    }

    /**
     * System messages about working environment.
     *
     * @return array
     */
    public function messages($data)
    {
        try {
            $this->checkPermissions('system', 'manage_settings');
        } catch (\Throwable) {
            return [];
        }

        $type = $data['type'] ?? null;

        return $this->getService()->getMessages($type);
    }

    /**
     * Get Central Alerts System messages sent for this installation.
     *
     * @return array - array of messages
     */
    public function cas_messages()
    {
        try {
            $this->checkPermissions('system', 'manage_settings');
        } catch (\Throwable) {
            return [];
        }

        return $this->getService()->getCasMessages();
    }

    /**
     * Check if passed file name template exists for admin area.
     *
     * @return bool
     */
    public function template_exists($data)
    {
        $this->checkPermissions('system', 'manage_settings');

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
        $this->checkPermissions('system', 'manage_settings');

        $fetchExternalIp = Tools::normalizeBoolean($data['ip'] ?? false);

        return $this->getService()->getEnv($fetchExternalIp);
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
        $service = $this->getDi()['mod_service']('Staff');

        return $service->hasPermission($this->getIdentity(), $data['mod'], $f);
    }

    /**
     * Clear system cache.
     *
     * @return bool
     */
    public function clear_cache()
    {
        $this->checkPermissions('system', 'invalidate_cache');

        return $this->getService()->clearCache();
    }

    /**
     * Used to check if there's an update available.
     */
    public function update_available(): bool
    {
        $this->checkPermissions('system', 'view');

        $updater = $this->getDi()['updater'];

        return $updater->isUpdateAvailable();
    }

    /**
     * Returns an array containing the update info.
     */
    public function update_info(): array
    {
        $this->checkPermissions('system', 'view');

        $updater = $this->getDi()['updater'];

        $info = $updater->getLatestVersionInfo();
        $requiredPhpVersion = $info['minimum_php_version'] ?? 'unknown';
        if (!is_string($requiredPhpVersion) || $requiredPhpVersion === '') {
            $requiredPhpVersion = 'unknown';
        }

        $info['minimum_php_version'] = $requiredPhpVersion;
        $info['current_php_version'] = PHP_VERSION;
        $info['php_version_supported'] = $requiredPhpVersion === 'unknown' || version_compare(PHP_VERSION, $requiredPhpVersion, '>=');
        $info['readiness'] = $this->getDi()['update_readiness']->check();

        return $info;
    }

    /**
     * Forces the system to clear out the update cache and re-fetch the latest info.
     */
    public function recheck_update(): bool
    {
        $this->checkPermissions('system', 'recheck_update');

        $updater = $this->getDi()['updater'];
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
        $updater = $this->getDi()['updater'];
        if ($updater->getUpdateBranch() !== 'preview' && !$updater->isUpdateAvailable()) {
            throw new \FOSSBilling\InformationException('You have the latest version of FOSSBilling. You do not need to update.');
        }

        $this->checkPermissions('system', 'system_update');

        if (function_exists('set_time_limit')) {
            set_time_limit(300);
        }

        $new_version = $updater->getLatestVersion();
        $this->getDi()['events_manager']->fire(['event' => 'onBeforeAdminUpdateCore']);
        $updater->performUpdate();

        $this->getDi()['logger']->info('Installed FOSSBilling update files from %s to %s. Update finalization is pending.', \FOSSBilling\Version::VERSION, $new_version);

        return true;
    }

    public function update_finalization_status(): array
    {
        $this->checkUpdateFinalizationPermissions();

        return $this->getDi()['update_finalization']->getStatus();
    }

    public function finalize_update(): bool
    {
        $this->checkUpdateFinalizationPermissions();

        if (function_exists('set_time_limit')) {
            set_time_limit(180);
        }

        $this->getDi()['update_finalization']->finalizeUpdate();
        $this->getDi()['events_manager']->fire(['event' => 'onAfterAdminUpdateCore']);
        $this->getDi()['logger']->info('Finalized FOSSBilling update to %s.', \FOSSBilling\Version::VERSION);

        return true;
    }

    public function complete_update_finalization(): bool
    {
        $this->checkUpdateFinalizationPermissions();

        $this->getDi()['update_finalization']->completeFinalization();
        $this->getDi()['logger']->info('Completed FOSSBilling update finalization for %s.', \FOSSBilling\Version::VERSION);

        return true;
    }

    private function checkUpdateFinalizationPermissions(): void
    {
        if (!$this->getDi()['update_finalization']->isRequired()) {
            $this->checkPermissions('system', 'system_update');

            return;
        }

        if ($this->identity instanceof \Box\Mod\Staff\Entity\Admin) {
            try {
                if ($this->getDi()['mod_service']('Staff')->isSuperAdministrator($this->identity->getId())) {
                    return;
                }
            } catch (\Doctrine\DBAL\Exception) {
                // If the installation hasn't migrated to the new group structure yet,
                // Manually look for the old "admin" role
                if (
                    $this->getDi()['em']->getConnection()->fetchOne("SHOW COLUMNS FROM `admin` LIKE 'role'")
                    && $this->getDi()['em']->getConnection()->fetchOne('SELECT role FROM admin WHERE id = :id', ['id' => (int) $this->identity->getId()]) === 'admin'
                ) {
                    return;
                }
            }
        }

        throw new \FOSSBilling\InformationException('You need to be a Super Administrator to finalize this update.', [], 403);
    }

    /**
     * Update FOSSBilling config.
     *
     * @throws \FOSSBilling\Exception
     */
    public function manual_update(): bool
    {
        $this->checkPermissions('system', 'system_update');

        if (function_exists('set_time_limit')) {
            set_time_limit(180);
        }

        $updater = $this->getDi()['updater'];
        $this->getDi()['events_manager']->fire(['event' => 'onBeforeAdminManualUpdate']);
        $updater->performManualUpdate();
        $this->getDi()['events_manager']->fire(['event' => 'onAfterAdminManualUpdate']);
        $this->getDi()['logger']->info('Updated FOSSBilling - applied patches and updated configuration file.');

        return true;
    }

    /**
     * Checks if the database is behind on patches.
     */
    public function is_behind_on_patches(): bool
    {
        $this->checkPermissions('system', 'view');

        $updater = $this->getDi()['updater'];

        return $updater->isBehindOnDBPatches();
    }

    /**
     * Returns the unique instance ID for this FOSSBilling installation.
     */
    public function instance_id(): string
    {
        $this->checkPermissions('system', 'view');

        return INSTANCE_ID;
    }

    /**
     * Returns if error reporting is enabled or not on this FOSSBilling instance.
     */
    public function error_reporting_enabled(): bool
    {
        $this->checkPermissions('system', 'view');

        return (bool) Config::getProperty('debug_and_monitoring.report_errors', false);
    }

    /**
     * Toggles error reporting on this FOSSBilling instance.
     */
    public function toggle_error_reporting(): bool
    {
        $this->checkPermissions('system', 'toggle_error_reporting');

        $current = Config::getProperty('debug_and_monitoring.report_errors', false);
        Config::setProperty('debug_and_monitoring.report_errors', !$current);

        return true;
    }

    /**
     * Returns the last FOSSBilling version number that changed error reporting behavior.
     */
    public function last_error_reporting_change(): string
    {
        $this->checkPermissions('system', 'view');

        return \FOSSBilling\SentryHelper::last_change;
    }

    public function get_interface_ips(): array
    {
        $this->checkPermissions('system', 'manage_network_interface');

        return $this->di['tools']->listHttpInterfaces();
    }

    public function set_interface_ip($data): bool
    {
        $this->checkPermissions('system', 'manage_network_interface');
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
