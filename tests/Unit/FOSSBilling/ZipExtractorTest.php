<?php

declare(strict_types=1);

use FOSSBilling\Core\Filesystem\ZipExtractor;
use FOSSBilling\Update;
use PhpZip\ZipFile;
use Symfony\Component\Filesystem\Filesystem;

require_once PATH_ROOT . '/core/Filesystem/ZipExtractor.php';
require_once PATH_ROOT . '/core/Update/RuntimeLock.php';

test('extracts update files with permissions and validates before writing', function (): void {
    $fs = new Filesystem();
    $root = sys_get_temp_dir() . '/fb-zip-' . bin2hex(random_bytes(8));
    $fs->mkdir($root . '/destination');
    $zip = new ZipFile();
    $zip->addFromString('nested/script.sh', 'verified contents');
    $zip->getEntry('nested/script.sh')->setUnixMode(0o100755);
    $zip->addFromString('empty.txt', '');
    $zip->saveAsFile($root . '/update.zip')->close();
    $started = false;

    try {
        (new ZipExtractor())->extract($root . '/update.zip', $root . '/destination', function (string $name, int $mode) use ($fs, $root): void {
            expect(Update::isSafeArchiveEntry($name))->toBeTrue()
                ->and($fs->exists($root . '/destination/nested/script.sh'))->toBeFalse();
        }, beforeWrite: function () use (&$started): void { $started = true; });
        expect($started)->toBeTrue()
            ->and($fs->readFile($root . '/destination/nested/script.sh'))->toBe('verified contents')
            ->and(fileperms($root . '/destination/nested/script.sh') & 0o777)->toBe(0o755)
            ->and($fs->readFile($root . '/destination/empty.txt'))->toBe('');
    } finally {
        $fs->remove($root);
    }
});

test('rejects unsafe update archives before writes', function (string $scenario): void {
    $fs = new Filesystem();
    $root = sys_get_temp_dir() . '/fb-zip-' . bin2hex(random_bytes(8));
    $fs->mkdir($root . '/destination');
    $zip = new ZipFile();
    $zip->addFromString('valid.txt', 'valid');
    $name = match ($scenario) {
        'traversal' => '../escape.txt',
        'nul' => 'bad-name.txt',
        default => 'a/file.txt',
    };
    $zip->addFromString($name, 'invalid');
    $contents = $zip->outputAsString();
    $zip->close();
    if ($scenario === 'nul') {
        $contents = str_replace('bad-name.txt', "bad\0name.txt", $contents);
    }
    if ($scenario === 'absolute') {
        $contents = str_replace('a/file.txt', '//file.txt', $contents);
    }
    $fs->dumpFile($root . '/update.zip', $contents);
    $started = false;

    try {
        expect(fn () => (new ZipExtractor())->extract($root . '/update.zip', $root . '/destination', static function (string $name, int $mode): void {
            if (!Update::isSafeArchiveEntry($name)) {
                throw new RuntimeException('Unsafe archive path');
            }
        }, maxEntries: $scenario === 'entries' ? 1 : PHP_INT_MAX, maxBytes: $scenario === 'size' ? 1 : PHP_INT_MAX, beforeWrite: function () use (&$started): void { $started = true; }))
            ->toThrow(RuntimeException::class);
        expect($started)->toBeFalse()
            ->and($fs->exists($root . '/destination/valid.txt'))->toBeFalse();
    } finally {
        $fs->remove($root);
    }
})->with(['traversal', 'nul', 'absolute', 'entries', 'size']);

// Build stored entries directly so malformed sizes and host platforms are not
// normalized by either ZIP library before the extractor sees them.
function storedExtractorArchive(int $count = 1, int $platform = 3, int $mode = 0o100755, bool $shortSize = false, bool $badCrc = false): string
{
    $files = '';
    $directory = '';
    for ($index = 0; $index < $count; ++$index) {
        $name = 'entry-' . $index . '.txt';
        $contents = $count > 1 ? '' : 'contents';
        $size = strlen($contents);
        $declaredSize = $shortSize ? $size - 1 : $size;
        $crc = $badCrc ? 0 : crc32(substr($contents, 0, $declaredSize));
        $offset = strlen($files);
        $files .= pack('VvvvvvVVVvv', 0x04034B50, 20, 0, 0, 0, 33, $crc, $size, $declaredSize, strlen($name), 0) . $name . $contents;
        $directory .= pack('VvvvvvvVVVvvvvvVV', 0x02014B50, ($platform << 8) | 20, 20, 0, 0, 0, 33, $crc, $size, $declaredSize, strlen($name), 0, 0, 0, 0, $mode << 16, $offset) . $name;
    }

    return $files . $directory . pack('VvvvvVVv', 0x06054B50, 0, 0, $count, $count, strlen($directory), strlen($files), 0);
}

test('corrupt ZIP errors retain their cause on the extraction error path', function (bool $badEntry): void {
    $fs = new Filesystem();
    $root = $fs->tempnam(sys_get_temp_dir(), 'fb-zip-error-');
    $fs->remove($root);
    $fs->mkdir($root . '/destination');
    $fs->dumpFile($root . '/update.zip', $badEntry ? storedExtractorArchive(badCrc: true) : 'invalid archive');
    $started = false;

    try {
        (new ZipExtractor())->extract($root . '/update.zip', $root . '/destination', static function (): void {}, beforeWrite: function () use (&$started): void { $started = true; });
        test()->fail('Corrupt archive was accepted');
    } catch (RuntimeException $e) {
        if (!class_exists(ZipArchive::class)) {
            expect($e->getPrevious())->toBeInstanceOf(PhpZip\Exception\ZipException::class);
        }
        expect($fs->exists($root . '/destination/entry-0.txt'))->toBeFalse();
        if (!$badEntry) {
            expect($started)->toBeFalse();
        }
    } finally {
        $fs->remove($root);
    }
})->with([false, true]);

test('stored entries with unequal sizes are rejected before writing', function (): void {
    $fs = new Filesystem();
    $root = sys_get_temp_dir() . '/fb-zip-size-' . bin2hex(random_bytes(8));
    $fs->mkdir($root . '/destination');
    $fs->dumpFile($root . '/update.zip', storedExtractorArchive(shortSize: true));
    $started = false;

    try {
        expect(fn () => (new ZipExtractor())->extract($root . '/update.zip', $root . '/destination', static function (): void {}, beforeWrite: function () use (&$started): void { $started = true; }))
            ->toThrow(RuntimeException::class);
        expect($started)->toBeFalse()->and($fs->exists($root . '/destination/entry-0.txt'))->toBeFalse();
    } finally {
        $fs->remove($root);
    }
});

test('Darwin entry modes preserve executable bits and symbolic link types', function (int $mode): void {
    $fs = new Filesystem();
    $root = sys_get_temp_dir() . '/fb-zip-mode-' . bin2hex(random_bytes(8));
    $fs->mkdir($root . '/destination');
    $fs->dumpFile($root . '/update.zip', storedExtractorArchive(platform: 19, mode: $mode));

    try {
        $seen = null;
        (new ZipExtractor())->extract($root . '/update.zip', $root . '/destination', function (string $name, int $entryMode) use (&$seen): void { $seen = $entryMode; });
        expect($seen)->toBe($mode);
        if (($mode & 0o170000) === 0o120000) {
            expect($fs->exists($root . '/destination/entry-0.txt'))->toBeFalse()
                ->and(is_link($root . '/destination/entry-0.txt'))->toBeFalse();
        } else {
            expect(fileperms($root . '/destination/entry-0.txt') & 0o777)->toBe(0o755)
                ->and($fs->readFile($root . '/destination/entry-0.txt'))->toBe('contents');
        }
    } finally {
        $fs->remove($root);
    }
})->with([0o100755, 0o120777]);

test('classic maximum entry count does not require a ZIP64 locator', function (): void {
    $fs = new Filesystem();
    $path = $fs->tempnam(sys_get_temp_dir(), 'fb-zip-count-');

    try {
        $fs->dumpFile($path, storedExtractorArchive(count: 65535));
        // Test the raw directory boundary without allocating 65,535 decoder
        // objects or destination files in every test worker.
        $zip = Mockery::mock(ZipFile::class);
        $zip->shouldReceive('getArchiveComment')->andReturn('');
        $zip->shouldReceive('getEntries')->andReturn(array_fill(0, 65535, null));
        $method = new ReflectionMethod(ZipExtractor::class, 'validateRawNames');
        expect($method->invoke(new ZipExtractor(), $path, $zip))->toHaveCount(65535);
        $contents = $fs->readFile($path);
        $contents = substr_replace($contents, pack('V', 0xFFFFFFFF), -6, 4);
        $fs->dumpFile($path, $contents);
        expect(fn (): mixed => $method->invoke(new ZipExtractor(), $path, $zip))->toThrow(RuntimeException::class, 'locator');
    } finally {
        $fs->remove($path);
    }
});

test('fallback extraction stays within a small worker memory limit', function (): void {
    if (class_exists(ZipArchive::class)) {
        test()->markTestSkipped('Covered by the dedicated CI run without ext-zip.');
    }
    $fs = new Filesystem();
    $root = sys_get_temp_dir() . '/fb-zip-memory-' . bin2hex(random_bytes(8));
    $fs->mkdir($root . '/destination');
    $zip = new ZipFile();
    $contents = str_repeat('a', 1024 * 1024);
    for ($index = 0; $index < 80; ++$index) {
        $zip->addFromString('entry-' . $index . '.txt', $contents);
    }
    $zip->saveAsFile($root . '/update.zip')->close();
    unset($zip);

    try {
        $ini = php_ini_loaded_file();
        $configuration = $ini === false ? ['-n'] : ['-c', $ini];
        $process = new Symfony\Component\Process\Process([PHP_BINARY, ...$configuration, '-d', 'memory_limit=48M', PATH_TESTS . '/Fixtures/zip-memory.php', $root]);
        $process->mustRun();
        expect($fs->readFile($root . '/destination/entry-79.txt'))->toHaveLength(1024 * 1024);
    } finally {
        $fs->remove($root);
    }
});

// cspell:ignore Vsize VVPPPP VVPV
test('ZIP64 directory counts and offsets remain supported', function (): void {
    $fs = new Filesystem();
    $root = sys_get_temp_dir() . '/fb-zip-wide-' . bin2hex(random_bytes(8));
    $fs->mkdir($root . '/destination');
    $contents = storedExtractorArchive();
    $footer = substr($contents, -22);
    $size = unpack('Vsize', substr($footer, 12, 4))['size'];
    $offset = unpack('Voffset', substr($footer, 16, 4))['offset'];
    $directoryEnd = strlen($contents) - 22;
    $wide = pack('VPvvVVPPPP', 0x06064B50, 44, 45, 45, 0, 0, 1, 1, $size, $offset);
    $locator = pack('VVPV', 0x07064B50, 0, $directoryEnd, 1);
    $footer = substr_replace($footer, pack('vv', 65535, 65535), 8, 4);
    $footer = substr_replace($footer, pack('V', 0xFFFFFFFF), 16, 4);
    $fs->dumpFile($root . '/update.zip', substr($contents, 0, -22) . $wide . $locator . $footer);

    try {
        (new ZipExtractor())->extract($root . '/update.zip', $root . '/destination', static function (): void {});
        expect($fs->readFile($root . '/destination/entry-0.txt'))->toBe('contents');
    } finally {
        $fs->remove($root);
    }
});
