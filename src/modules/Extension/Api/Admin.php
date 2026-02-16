<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Extension\Api;

use Box\Mod\Extension\Event\AfterAdminDeactivateExtensionEvent;
use Box\Mod\Extension\Event\AfterAdminInstallExtensionEvent;
use Box\Mod\Extension\Event\AfterAdminUninstallExtensionEvent;
use Box\Mod\Extension\Event\AfterAdminUpdateExtensionEvent;
use Box\Mod\Extension\Event\BeforeAdminDeactivateExtensionEvent;
use Box\Mod\Extension\Event\BeforeAdminInstallExtensionEvent;
use Box\Mod\Extension\Event\BeforeAdminUninstallExtensionEvent;
use Box\Mod\Extension\Event\BeforeAdminUpdateExtensionEvent;
use FOSSBilling\Validation\Api\RequiredParams;

class Admin extends \Api_Abstract
{
    /**
     * Get list of active and inactive extensions on system.
     *
     * @optional bool $installed - return installed only extensions
     * @optional bool $active - return installed and core extensions
     * @optional bool $has_settings - return extensions with configuration pages only
     * @optional string $search - filter extensions by search keyword
     * @optional string $type - filter extensions by type
     *
     * @return array
     */
    public function get_list($data)
    {
        $service = $this->getService();

        return $service->getExtensionsList($data);
    }

    /**
     * Get list of extensions from extensions.fossbilling.org
     * which can be installed on current version of FOSSBilling.
     *
     * @return array - list of extensions
     */
    public function get_latest($data)
    {
        $type = $data['type'] ?? null;

        try {
            $list = $this->di['extension_manager']->getExtensionList($type);
        } catch (\Exception $e) {
            $this->di['logger']->warn(sprintf('Failed to fetch extension list for type "%s": %s', $type ?? 'all', $e->getMessage()));

            $list = [];
        }

        return $list;
    }

    /**
     * Gets the readme as HTML for a given extension.
     */
    #[RequiredParams(['extension_id' => 'Extension ID was not passed'])]
    public function get_extension_readme($data): string
    {
        $extensionInfo = $this->di['extension_manager']->getExtension($data['extension_id']);

        return $this->di['parse_markdown']($extensionInfo['readme']);
    }

    /**
     * Get admin area navigation.
     *
     * @return array
     */
    public function get_navigation($data)
    {
        $url = null;
        if (isset($data['url'])) {
            $url = $data['url'];
        }
        $service = $this->getService();

        return $service->getAdminNavigation($this->identity, $url);
    }

    /**
     * Get list of available languages on the system.
     */
    public function languages(array $data): array
    {
        $data['disabled'] ??= false;
        $data['details'] ??= true;

        return \FOSSBilling\i18n::getLocales($data['details'], $data['disabled']);
    }

    /**
     * Toggles a given locale to either enable or disable it depending on it's current status.
     *
     * @param array $data The post data sent to the API. Should contain a key named `locale_id` which is set to the locale ID to change. (`en_US` for example)
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['locale_id' => 'Locale ID was not passed'])]
    public function toggle_language(array $data): bool
    {
        return \FOSSBilling\i18n::toggleLocale($data['locale_id']);
    }

    /**
     * Returns how complete a given locale is.
     *
     * @param array $data $data The post data sent to the API. Should contain a key named `locale_id` which is set to the locale ID to get the completion percentage for. (`en_US` for example)
     */
    #[RequiredParams(['locale_id' => 'Locale ID was not passed'])]
    public function locale_completion(array $data): int
    {
        return \FOSSBilling\i18n::getLocaleCompletionPercent($data['locale_id']);
    }

    /**
     * Update existing extension.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    public function update($data)
    {
        $ext = $this->_getExtension($data);
        $service = $this->getService();

        $this->di['event_dispatcher']->dispatch(new BeforeAdminUpdateExtensionEvent(extension: $ext));
        $ext2 = $service->update($ext);
        $this->di['event_dispatcher']->dispatch(new AfterAdminUpdateExtensionEvent(extension: $ext2));

        return $ext2;
    }

    /**
     * Activate existing extension.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Extension ID was not passed', 'type' => 'Extension type was not passed'])]
    public function activate($data)
    {
        $service = $this->getService();

        return $service->activateExistingExtension($data);
    }

    /**
     * Deactivate existing extension.
     *
     * @return bool - true
     *
     * @throws \FOSSBilling\Exception
     */
    public function deactivate($data): bool
    {
        $ext = $this->_getExtension($data);

        $this->di['event_dispatcher']->dispatch(new BeforeAdminDeactivateExtensionEvent(extensionId: (string) $ext->id));

        $service = $this->getService();
        $service->deactivate($ext);

        $this->di['event_dispatcher']->dispatch(new AfterAdminDeactivateExtensionEvent(extensionId: (string) $data['id'], type: $data['type']));

        $this->di['logger']->info('Deactivated extension "%s"', $data['type'] . ' ' . $data['id']);

        return true;
    }

    /**
     * Completely remove extension from FOSSBilling.
     */
    public function uninstall($data): bool
    {
        $ext = $this->_getExtension($data);
        $this->di['event_dispatcher']->dispatch(new BeforeAdminUninstallExtensionEvent(extensionId: (string) $ext->id));

        $this->getService()->uninstall($data['type'], $data['id']);

        $this->di['event_dispatcher']->dispatch(new AfterAdminUninstallExtensionEvent(extensionId: (string) $data['id'], type: $data['type']));

        return true;
    }

    /**
     * Install new extension from extensions site.
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Extension ID was not passed', 'type' => 'Extension type was not passed'])]
    public function install($data): array
    {
        $this->di['event_dispatcher']->dispatch(new BeforeAdminInstallExtensionEvent(data: $data));

        $service = $this->getService();
        $service->downloadAndExtract($data['type'], $data['id']);

        $this->di['event_dispatcher']->dispatch(new AfterAdminInstallExtensionEvent(data: $data));

        return [
            'success' => true,
            'id' => $data['id'],
            'type' => $data['type'],
        ];
    }

    /**
     * Universal method for FOSSBilling extensions
     * to retrieve configuration from database
     * It is recommended to store your extension configuration
     * using this method. Automatic decryption is available
     * All parameters in "public" array will be accessible by guest API.
     *
     * @return array - configuration parameters
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['ext' => 'Parameter "ext" was not passed'])]
    public function config_get($data)
    {
        $service = $this->getService();

        return $service->getConfig($data['ext']);
    }

    /**
     * Universal method for FOSSBilling extensions
     * to update or save extension configuration to database
     * Always pass all configuration parameters to this method.
     *
     * Config is automatically encrypted and stored in database
     *
     * @optional string $any - Any variable passed to this method is config parameter
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['ext' => 'Parameter "ext" was not passed'])]
    public function config_save($data)
    {
        return $this->getService()->setConfig($data);
    }

    #[RequiredParams(['id' => 'Extension ID was not passed', 'type' => 'Extension type was not passed'])]
    private function _getExtension($data)
    {
        $service = $this->getService();
        $ext = $service->findExtension($data['type'], $data['id']);
        if (!$ext instanceof \Model_Extension) {
            throw new \FOSSBilling\Exception('Extension not found');
        }

        return $ext;
    }
}
