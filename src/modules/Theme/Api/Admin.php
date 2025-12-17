<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Theme\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get list of available client area themes.
     */
    public function get_list($data): array
    {
        $themes = $this->getService()->getThemes();

        return ['list' => $themes];
    }

    /**
     * Get list of available admin area themes.
     */
    public function get_admin_list($data): array
    {
        $themes = $this->getService()->getThemes(false);

        return ['list' => $themes];
    }

    /**
     * Get theme by code.
     *
     * @return array
     */
    public function get($data)
    {
        $required = [
            'code' => 'Theme code is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->loadTheme($data['code']);
    }

    /**
     * Set new theme as default.
     */
    public function select($data): bool
    {
        $required = [
            'code' => 'Theme code is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $theme = $this->getService()->getTheme($data['code']);

        $systemService = $this->di['mod_service']('system');
        if ($theme->isAdminAreaTheme()) {
            $systemService->setParamValue('admin_theme', $data['code']);
        } else {
            $systemService->setParamValue('theme', $data['code']);
        }

        // Clear theme cache so subsequent calls get the updated theme
        \Box\Mod\Theme\Service::clearThemeCache();

        $this->di['logger']->info('Changed default theme');

        return true;
    }

    /**
     * Delete theme preset.
     */
    public function preset_delete($data): bool
    {
        $required = [
            'code' => 'Theme code is missing',
            'preset' => 'Theme preset name is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->getService();

        $theme = $service->getTheme($data['code']);
        $service->deletePreset($theme, $data['preset']);

        return true;
    }

    /**
     * Select new theme preset.
     */
    public function preset_select($data): bool
    {
        $required = [
            'code' => 'Theme code is missing',
            'preset' => 'Theme preset name is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->getService();
        $theme = $service->getTheme($data['code']);
        $service->setCurrentThemePreset($theme, $data['preset']);

        return true;
    }
}
