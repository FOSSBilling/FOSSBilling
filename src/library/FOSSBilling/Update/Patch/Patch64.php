<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Update\Patch;

use FOSSBilling\Update\Patcher;
use Symfony\Component\Filesystem\Path;

class Patch64 implements PatchInterface
{
    public function getVersion(): int
    {
        return 64;
    }

    public function apply(Patcher $patcher): void
    {
        $patcher->migrateGatewayAssetsToPublicDirectory();
        $patcher->migrateDefaultBrandingAssetsToPublicDirectory();

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
}
