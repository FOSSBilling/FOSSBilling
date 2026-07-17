<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

test('default PDF fonts cover Thai and Lao text', function (): void {
    $filesystem = new Filesystem();
    $templatePath = Path::join(PATH_ROOT, 'modules', 'Invoice', 'templates', 'pdf');
    $css = $filesystem->readFile(Path::join($templatePath, 'default-invoice.css'));

    $fontCachePath = Path::join(sys_get_temp_dir(), 'fossbilling-pdf-font-test-' . bin2hex(random_bytes(6)));
    $filesystem->mkdir($fontCachePath);

    try {
        $options = new Options();
        $options->setFontDir($fontCachePath);
        $options->setFontCache($fontCachePath);
        $options->setChroot(PATH_ROOT);

        $pdf = new Dompdf($options);
        $pdf->setBasePath($templatePath);
        $pdf->loadHtml("<style>{$css}</style><p>ภูเก็ต ພູເກັດ</p>");
        $pdf->render();

        $cases = [
            ['ภูเก็ต', ['DejaVu Sans', 'Noto Sans Thai'], 'noto_sans_thai_normal'],
            ['ພູເກັດ', ['Noto Sans Lao'], 'noto_sans_lao_normal'],
        ];

        foreach ($cases as [$text, $families, $expectedFont]) {
            $mapping = $pdf->getFontMetrics()->mapTextToFonts($text, $families, 'normal', 1);
            $font = $mapping[0]['font'] ?? null;

            expect($font)->toBeString();
            expect(basename($font))->toStartWith($expectedFont);
        }
    } finally {
        $filesystem->remove($fontCachePath);
    }
});
