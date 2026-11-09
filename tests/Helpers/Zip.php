<?php

declare(strict_types=1);

namespace Tests\Helpers;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Build archive bytes with native ZIP support; null contents denote directories.
 *
 * @param array<string, ?string> $files
 * @param array<string, int>     $modes
 */
function zipArchive(array $files, array $modes = []): string
{
    $filesystem = new Filesystem();
    $path = $filesystem->tempnam(sys_get_temp_dir(), 'fossbilling-zip-');
    $zip = new \ZipArchive();
    if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
        throw new \RuntimeException('Unable to create a test ZIP archive.');
    }

    // libzip truncates names supplied through the API at NUL. Patch a same-size
    // placeholder after closing to exercise malicious raw archive names as well.
    $replacements = [];

    try {
        foreach ($files as $name => $contents) {
            $name = (string) $name;
            $storedName = str_replace("\0", "\x01", $name);
            if ($storedName !== $name) {
                $replacements[$storedName] = $name;
            }
            if ($contents === null) {
                $zip->addEmptyDir($storedName);
            } else {
                $zip->addFromString($storedName, $contents);
            }
            if (isset($modes[$name])) {
                $zip->setExternalAttributesName($storedName, \ZipArchive::OPSYS_UNIX, $modes[$name] << 16);
            }
        }
    } finally {
        $zip->close();
    }

    try {
        return strtr($filesystem->readFile($path), $replacements);
    } finally {
        $filesystem->remove($path);
    }
}
