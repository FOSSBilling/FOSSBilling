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

class Patch51 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        $oldDir = Path::join(PATH_MODS, 'Invoice', 'pdf_template');
        $newDir = Path::join(PATH_MODS, 'Invoice', 'templates', 'pdf');

        if (!$patcher->filesystem->exists($oldDir)) {
            return;
        }

        $fileActions = [
            Path::join($oldDir, 'custom-pdf.twig') => Path::join($newDir, 'custom-invoice.twig'),
            Path::join($oldDir, 'custom-pdf.css') => Path::join($newDir, 'custom-invoice.css'),
            Path::join($oldDir, 'default-pdf.twig') => 'unlink',
            Path::join($oldDir, 'default-pdf.css') => 'unlink',
        ];
        $patcher->executeFileActions($fileActions);

        $finder = new Finder();
        if (!$finder->in($oldDir)->depth('== 0')->hasResults()) {
            $patcher->filesystem->remove($oldDir);
        }
    }
}
