<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\Update\Patch;

use FOSSBilling\Core\Update\Patcher;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

class Patch64 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        $this->migrateGatewayAssetsToPublicDirectory($patcher);
        $this->migrateDefaultBrandingAssetsToPublicDirectory($patcher);

        $patcher->executeFileActions([
            Path::join(PATH_LIBRARY, 'Api', 'API.js') => 'unlink',
            Path::join(PATH_MODS, 'Wysiwyg') => 'unlink',
            Path::join(PATH_THEMES, 'admin_default', 'html', 'mod_wysiwyg_js.html.twig') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'html', 'mod_wysiwyg_js.html.twig') => 'unlink',
            Path::join(PATH_THEMES, 'admin_default', 'assets', 'js', 'wysiwyg.js') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'js', 'wysiwyg.js') => 'unlink',
            Path::join(PATH_THEMES, 'admin_default', 'assets', 'build', 'js', 'wysiwyg.js') => 'unlink',
            Path::join(PATH_THEMES, 'admin_default', 'assets', 'build', 'js', 'wysiwyg.js.map') => 'unlink',
            Path::join(PATH_THEMES, 'admin_default', 'assets', 'build', 'js', 'wysiwyg.css') => 'unlink',
            Path::join(PATH_THEMES, 'admin_default', 'assets', 'build', 'js', 'wysiwyg.css.map') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'build', 'js', 'wysiwyg.js') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'build', 'js', 'wysiwyg.js.map') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'build', 'js', 'wysiwyg.css') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'build', 'js', 'wysiwyg.css.map') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'css', 'markdown.css') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'build', 'css', 'markdown.css') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'build', 'css', 'markdown.css.map') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'img', 'logo.png') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'img', 'logo.svg') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'img', 'logo_white.svg') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'favicon.ico') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'build', 'img', 'logo.png') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'build', 'img', 'logo.svg') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'build', 'img', 'logo_white.svg') => 'unlink',
            Path::join(PATH_THEMES, 'huraga', 'assets', 'build', 'favicon.ico') => 'unlink',
        ]);
    }

    private function migrateDefaultBrandingAssetsToPublicDirectory(Patcher $patcher): void
    {
        $settings = [
            'company_logo' => [
                'public/branding/logo.svg',
                'themes/huraga/assets/img/logo.svg',
                'themes/huraga/assets/build/img/logo.svg',
            ],
            'company_logo_dark' => [
                'public/branding/logo-dark.svg',
                'themes/huraga/assets/img/logo_white.svg',
                'themes/huraga/assets/build/img/logo_white.svg',
            ],
            'company_favicon' => [
                'public/branding/favicon.ico',
                'themes/huraga/assets/favicon.ico',
                'themes/huraga/assets/build/favicon.ico',
            ],
        ];

        foreach ($settings as $param => $values) {
            $newValue = $values[0];
            $oldValues = array_slice($values, 1);

            foreach ($oldValues as $oldValue) {
                $patcher->executeSql('UPDATE setting SET value = :new_value WHERE param = :param AND value = :old_value', [
                    'new_value' => $newValue,
                    'param' => $param,
                    'old_value' => $oldValue,
                ]);
            }
        }
    }

    private function migrateGatewayAssetsToPublicDirectory(Patcher $patcher): void
    {
        $publicGatewayAssetsPath = Path::join(PATH_ROOT, 'public', 'gateways');
        $oldGatewayAssetPaths = array_unique([
            Path::join(PATH_ROOT, 'data', 'assets', 'gateways'),
            Path::join(PATH_DATA, 'assets', 'gateways'),
            Path::join(PATH_ROOT, 'public', 'assets', 'gateways'),
        ]);

        foreach ($oldGatewayAssetPaths as $oldGatewayAssetsPath) {
            if (!$patcher->filesystem->exists($oldGatewayAssetsPath)) {
                continue;
            }

            $patcher->filesystem->mkdir($publicGatewayAssetsPath, 0o755);

            $finder = new Finder();
            $finder->files()->in($oldGatewayAssetsPath)->depth('== 0');

            foreach ($finder as $file) {
                $target = Path::join($publicGatewayAssetsPath, $file->getFilename());
                if (!$patcher->filesystem->exists($target)) {
                    $patcher->filesystem->copy($file->getPathname(), $target);
                }
            }

            $patcher->executeFileActions([
                $oldGatewayAssetsPath => 'unlink',
            ]);
        }
    }
}
