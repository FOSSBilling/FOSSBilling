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

use FOSSBilling\Validation\Api\RequiredParams;

class Admin extends \Api_Abstract
{
    /**
     * Get list of available client area themes.
     *
     * @return array
     */
    public function get_list($data)
    {
        $themes = $this->getService()->getThemes();

        return ['list' => $themes];
    }

    /**
     * Get list of available admin area themes.
     *
     * @return array
     */
    public function get_admin_list($data)
    {
        $themes = $this->getService()->getThemes(false);

        return ['list' => $themes];
    }

    /**
     * Get theme by code.
     *
     * @return array
     */
    #[RequiredParams(['code' => 'Theme code was not passed'])]
    public function get($data)
    {
        return $this->getService()->loadTheme($data['code']);
    }

    /**
     * Set new theme as default.
     *
     * @return bool
     */
    #[RequiredParams(['code' => 'Theme code was not passed'])]
    public function select($data)
    {
        $theme = $this->getService()->getTheme($data['code']);

        $systemService = $this->di['mod_service']('system');
        if ($theme->isAdminAreaTheme()) {
            $systemService->setParamValue('admin_theme', $data['code']);
        } else {
            $systemService->setParamValue('theme', $data['code']);
        }

        $this->di['logger']->info('Changed default theme');

        return true;
    }

    /**
     * Delete theme preset.
     *
     * @return bool
     */
    #[RequiredParams(['code' => 'Theme code was not passed', 'preset' => 'Preset name is missing'])]
    public function preset_delete($data)
    {
        $service = $this->getService();

        $theme = $service->getTheme($data['code']);
        $service->deletePreset($theme, $data['preset']);

        return true;
    }

    /**
     * Select new theme preset.
     *
     * @return bool
     */
    #[RequiredParams(['code' => 'Theme code was not passed', 'preset' => 'Preset name is missing'])]
    public function preset_select($data)
    {
        $service = $this->getService();
        $theme = $service->getTheme($data['code']);
        $service->setCurrentThemePreset($theme, $data['preset']);

        return true;
    }
}
