<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Theme\Api;

use FOSSBilling\Tools;
use FOSSBilling\Validation\Api\RequiredParams;

class Admin extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Get list of available client area themes.
     */
    public function get_list($data): array
    {
        $this->checkPermissions('theme', 'view');

        $themes = $this->getService()->getThemes();

        return ['list' => $themes];
    }

    /**
     * Get list of available admin area themes.
     */
    public function get_admin_list($data): array
    {
        $this->checkPermissions('theme', 'view');

        $themes = $this->getService()->getThemes(false);

        return ['list' => $themes];
    }

    /**
     * Get the current client or admin area theme.
     */
    public function get_current(array $data): array
    {
        $this->checkPermissions('theme', 'view');

        if ($this->isInvalidClientParameter($data['client'] ?? null)) {
            throw new \FOSSBilling\InformationException('Invalid "client" parameter.');
        }

        $client = Tools::normalizeBoolean($data['client'] ?? true, true);

        return $this->getService()->getThemeConfig($client, null);
    }

    /**
     * Determine whether the client selector contains a boolean-compatible value.
     */
    private function isInvalidClientParameter(mixed $client): bool
    {
        if ($client === null || is_bool($client) || is_int($client) || is_float($client)) {
            return false;
        }

        if (!is_string($client)) {
            return true;
        }

        return filter_var($client, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === null;
    }

    /**
     * Get theme by code.
     *
     * @return array
     */
    #[RequiredParams(['code' => 'Theme code was not passed'])]
    public function get($data)
    {
        $this->checkPermissions('theme', 'view');

        return $this->getService()->loadTheme($data['code']);
    }

    /**
     * Set new theme as default for an area. The caller must state the area
     * via `client` rather than having it inferred from the theme code, since
     * a package-shaped code (e.g. `default/admin`) can't be reliably
     * classified by name alone.
     */
    #[RequiredParams(['code' => 'Theme code was not passed'])]
    public function select($data): bool
    {
        $this->checkPermissions('theme', 'manage');

        if (!array_key_exists('client', $data)) {
            throw new \FOSSBilling\InformationException('The "client" parameter is required.');
        }
        if ($this->isInvalidClientParameter($data['client'])) {
            throw new \FOSSBilling\InformationException('Invalid "client" parameter.');
        }
        $client = Tools::normalizeBoolean($data['client'], true);

        // Confirm the code is actually available for the target area before
        // persisting it. Merely existing on disk isn't enough - a package's
        // `shared` tier or the opposite area's code would otherwise pass,
        // leaving the area's Twig environment pointed at a directory with no
        // layouts of its own.
        $isAvailableForArea = false;
        foreach ($this->getService()->getThemes($client) as $theme) {
            if ($theme['code'] === $data['code']) {
                $isAvailableForArea = true;

                break;
            }
        }
        if (!$isAvailableForArea) {
            throw new \FOSSBilling\InformationException('Theme ":code" is not available for the selected area.', [':code' => $data['code']]);
        }

        $systemService = $this->getDi()['mod_service']('system');
        $systemService->setParamValue($client ? 'theme' : 'admin_theme', $data['code']);

        // Clear theme cache so subsequent calls get the updated theme
        \Box\Mod\Theme\Service::clearThemeCache();

        $this->getDi()['logger']->info('Changed default theme');

        return true;
    }

    /**
     * Delete theme preset.
     */
    #[RequiredParams(['code' => 'Theme code was not passed', 'preset' => 'Preset name is missing'])]
    public function preset_delete($data): bool
    {
        $this->checkPermissions('theme', 'manage');

        $service = $this->getService();

        $theme = $service->getTheme($data['code']);
        $service->deletePreset($theme, $data['preset']);

        return true;
    }

    /**
     * Select new theme preset.
     */
    #[RequiredParams(['code' => 'Theme code was not passed', 'preset' => 'Preset name is missing'])]
    public function preset_select($data): bool
    {
        $this->checkPermissions('theme', 'manage');

        $service = $this->getService();
        $theme = $service->getTheme($data['code']);
        $service->setCurrentThemePreset($theme, $data['preset']);

        return true;
    }
}
