<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\Filesystem;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

final class ZipExtractor
{
    /**
     * Validate the entire archive before writing, then restore its Unix modes.
     * The caller supplies the path/type policy for core or extension packages.
     *
     * @param callable(string, int): void $validateEntry
     */
    public function extract(string $archive, string $destination, callable $validateEntry): void
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('The PHP ZIP extension must be installed and enabled to extract archives.');
        }

        $zip = new \ZipArchive();
        $result = @$zip->open($archive, \ZipArchive::RDONLY | \ZipArchive::CHECKCONS);
        if ($result !== true) {
            throw new \RuntimeException(sprintf('Unable to open ZIP archive (error %s).', $result));
        }

        $filesystem = new Filesystem();

        try {
            $this->validateRawNames($archive, $zip);
            $entries = [];
            for ($index = 0; $index < $zip->numFiles; ++$index) {
                $entry = $zip->statIndex($index);
                if ($entry === false || !$zip->getExternalAttributesIndex($index, $platform, $attributes)) {
                    throw new \RuntimeException('Unable to read ZIP entry metadata.');
                }

                $name = $entry['name'];
                $directory = str_ends_with(str_replace('\\', '/', $name), '/') || ($attributes & 0x10) !== 0;
                $mode = $platform === \ZipArchive::OPSYS_UNIX ? ($attributes >> 16) & 0xFFFF : 0;
                $mode = $mode ?: ($directory ? 0o040755 : 0o100644);
                $directory = $directory || ($mode & 0o170000) === 0o040000;
                $validateEntry($name, $mode);
                $entries[] = $entry + ['mode' => $mode, 'directory' => $directory];
            }

            if (!is_dir($destination) || !is_writable($destination)) {
                throw new \RuntimeException('The ZIP extraction destination must be a writable directory.');
            }

            $directories = [];
            foreach ($entries as $entry) {
                // Core updates skip symlinks; extension validation rejects them.
                if (($entry['mode'] & 0o170000) === 0o120000) {
                    continue;
                }

                $path = $destination . '/' . str_replace('\\', '/', $entry['name']);
                $directory = $entry['directory'] ? rtrim($path, '/') : Path::getDirectory($path);
                $this->createDirectory($directory, rtrim($destination, '/'), $filesystem, $directories);

                if ($entry['directory']) {
                    if (isset($directories[$directory])) {
                        $directories[$directory] = $entry['mode'] & 0o7777;
                    }

                    continue;
                }

                if (is_link($path)) {
                    throw new \RuntimeException('Cannot extract a ZIP entry over a symbolic link.');
                }

                $stream = @$zip->getStreamIndex($entry['index']);
                if ($stream === false) {
                    throw new \RuntimeException('Unable to read ZIP entry contents.');
                }

                try {
                    $filesystem->dumpFile($path, $stream);
                } finally {
                    fclose($stream);
                }

                // Native ZIP streams can report a corrupt/truncated entry while
                // reading. Check both size and CRC before accepting the file.
                if (filesize($path) !== $entry['size'] || hash_file('crc32b', $path) !== sprintf('%08x', $entry['crc'])) {
                    $filesystem->remove($path);

                    throw new \RuntimeException('ZIP entry failed its size or CRC check.');
                }

                $filesystem->chmod($path, $entry['mode'] & 0o7777);
                $filesystem->touch($path, $entry['mtime']);
            }

            // Apply directory modes last so a read-only archived directory does
            // not prevent its children being extracted. Existing dirs keep theirs.
            uksort($directories, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));
            foreach ($directories as $directory => $mode) {
                $filesystem->chmod($directory, $mode);
            }
        } finally {
            $zip->close();
        }
    }

    /** Older libzip versions replace NUL bytes with spaces before exposing names. */
    private function validateRawNames(string $archive, \ZipArchive $zip): void
    {
        $stream = @fopen($archive, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Unable to read ZIP entry names.');
        }

        try {
            $comment = $zip->getArchiveComment(\ZipArchive::FL_ENC_RAW);
            if (fseek($stream, -22 - strlen($comment), SEEK_END) !== 0) {
                throw new \RuntimeException('Unable to read the ZIP central directory.');
            }
            $footer = fread($stream, 22);
            if ($footer === false || strlen($footer) !== 22 || !str_starts_with($footer, "PK\x05\x06")) {
                throw new \RuntimeException('Invalid ZIP central directory footer.');
            }
            $offset = unpack('Voffset', substr($footer, 16, 4))['offset'];

            if ($offset === 0xFFFFFFFF) {
                // ZIP64 stores the directory offset in its extended footer.
                fseek($stream, -42 - strlen($comment), SEEK_END);
                $locator = fread($stream, 20);
                if ($locator === false || strlen($locator) !== 20 || !str_starts_with($locator, "PK\x06\x07")) {
                    throw new \RuntimeException('Invalid ZIP64 central directory locator.');
                }
                $footerOffset = unpack('Poffset', substr($locator, 8, 8))['offset'];
                if (fseek($stream, $footerOffset) !== 0) {
                    throw new \RuntimeException('Unable to read the ZIP64 footer.');
                }
                $footer = fread($stream, 56);
                if ($footer === false || strlen($footer) !== 56 || !str_starts_with($footer, "PK\x06\x06")) {
                    throw new \RuntimeException('Invalid ZIP64 central directory footer.');
                }
                $offset = unpack('Poffset', substr($footer, 48, 8))['offset'];
            }

            if (fseek($stream, $offset) !== 0) {
                throw new \RuntimeException('Unable to read the ZIP central directory.');
            }
            for ($index = 0; $index < $zip->numFiles; ++$index) {
                $header = fread($stream, 46);
                if ($header === false || strlen($header) !== 46 || !str_starts_with($header, "PK\x01\x02")) {
                    throw new \RuntimeException('Invalid ZIP central directory entry.');
                }
                $lengths = unpack('vname/vextra/vcomment', substr($header, 28, 6));
                $name = $lengths['name'] > 0 ? fread($stream, $lengths['name']) : '';
                if ($name === false || strlen($name) !== $lengths['name'] || str_contains($name, "\0")) {
                    throw new \RuntimeException('The ZIP archive contains an invalid file name.');
                }
                if (fseek($stream, $lengths['extra'] + $lengths['comment'], SEEK_CUR) !== 0) {
                    throw new \RuntimeException('Unable to read the next ZIP directory entry.');
                }
            }
        } finally {
            fclose($stream);
        }
    }

    /** @param array<string, int> $directories */
    private function createDirectory(string $path, string $root, Filesystem $filesystem, array &$directories): void
    {
        $missing = [];
        for ($directory = $path; $directory !== $root && $directory !== ''; $directory = $parent) {
            if (is_link($directory)) {
                throw new \RuntimeException('Cannot extract a ZIP entry through a symbolic link.');
            }
            if (!is_dir($directory)) {
                $missing[] = $directory;
            }
            $parent = Path::getDirectory($directory);
            if ($parent === $directory) {
                break;
            }
        }

        foreach (array_reverse($missing) as $directory) {
            $filesystem->mkdir($directory, 0o755);
            $filesystem->chmod($directory, 0o755);
            $directories[$directory] = 0o755;
        }
    }
}
