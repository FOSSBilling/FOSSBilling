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

use FOSSBilling\Exception\BaseException;
use FOSSBilling\Update\Patcher;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

class Patch56 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        $length = $patcher->getColumnLength('tld', 'tld');

        if ($length !== null && $length < 64) {
            $patcher->executeSql('ALTER TABLE `tld` MODIFY `tld` VARCHAR(64) DEFAULT NULL;');
        }

        $patcher->executeSql("UPDATE `setting` SET `public` = 0 WHERE `param` = 'last_patch';");

        try {
            $finder = new Finder();
            $finder->directories()->in(PATH_MODS)->depth('== 1')->name('/^html_(admin|client|email)$/');

            foreach ($finder as $dir) {
                $modulePath = Path::getDirectory($dir->getPathname());
                $area = substr($dir->getFilename(), 5);
                $replacementPath = Path::join($modulePath, 'templates', $area);

                if (!$patcher->filesystem->exists($replacementPath)) {
                    continue;
                }

                try {
                    $patcher->filesystem->remove($dir->getPathname());
                } catch (IOException $e) {
                    $patcher->logUpdate('error', $e->getMessage());
                }
            }
        } catch (\Symfony\Component\Finder\Exception\DirectoryNotFoundException) {
            throw new BaseException('The modules directory does not exist. Cannot apply patch 56.');
        }

        $patcher->executeFileActions([
            Path::join(PATH_LIBRARY, 'Box', 'TwigLoader.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'TwigExtensions.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'TwigExtensions', 'DebugBar.php') => 'unlink',
        ]);
    }
}
