<?php

declare(strict_types=1);

use FOSSBilling\Core\Filesystem\ZipExtractor;
use FOSSBilling\Core\Update\Updater;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

use function Tests\Helpers\zipArchive;

// cspell:ignore ventries Vsize VVPPPP
function zip64TestArchive(string $archive): string
{
    $footer = unpack('ventriesDisk/ventries/Vsize/Voffset', substr($archive, -14, 12));
    $body = substr($archive, 0, -22);
    $zip64Footer = pack('a4PvvVVPPPP', "PK\x06\x06", 44, 45, 45, 0, 0, $footer['entries'], $footer['entries'], $footer['size'], $footer['offset']);
    $locator = pack('a4VPV', "PK\x06\x07", 0, strlen($body), 1);
    $footer = pack('a4vvvvVVv', "PK\x05\x06", 0, 0, 0xFFFF, 0xFFFF, 0xFFFFFFFF, 0xFFFFFFFF, 0);

    return $body . $zip64Footer . $locator . $footer;
}

beforeEach(function (): void {
    $this->filesystem = new Filesystem();
    $this->directory = Path::join(sys_get_temp_dir(), 'fossbilling-zip-' . bin2hex(random_bytes(8)));
    $this->destination = $this->directory . '/destination';
    $this->archive = $this->directory . '/package.zip';
    $this->filesystem->mkdir($this->destination, 0o700);
    $this->validate = static function (string $name): void {
        if (!Updater::isSafeArchiveEntry($name)) {
            throw new RuntimeException('Unsafe entry');
        }
    };
});

afterEach(function (): void {
    $this->filesystem->remove($this->directory);
});

test('native extraction restores archived and default modes despite a restrictive umask', function (): void {
    if (DIRECTORY_SEPARATOR === '\\') {
        $this->markTestSkipped('Unix mode assertions require a Unix filesystem.');
    }

    $files = ['private/' => null, 'private/run.sh' => 'executable', 'private/read-only.txt' => 'readonly', 'implicit/file.txt' => 'default', 'empty/' => null, 'existing/file.txt' => 'existing', 'mode-directory' => ''];
    $modes = ['private/' => 0o040750, 'private/run.sh' => 0o100755, 'private/read-only.txt' => 0o100440, 'implicit/file.txt' => 0, 'empty/' => 0, 'mode-directory' => 0o040700];
    $this->filesystem->dumpFile($this->archive, zipArchive($files, $modes));
    $this->filesystem->mkdir($this->destination . '/existing', 0o711);
    $this->filesystem->chmod($this->destination . '/existing', 0o711);
    $umask = umask(0o077);

    try {
        (new ZipExtractor())->extract($this->archive, $this->destination, $this->validate);
    } finally {
        umask($umask);
    }

    foreach (['private' => 0o750, 'private/run.sh' => 0o755, 'private/read-only.txt' => 0o440, 'implicit' => 0o755, 'implicit/file.txt' => 0o644, 'empty' => 0o755, 'existing' => 0o711, 'mode-directory' => 0o700] as $path => $mode) {
        expect(fileperms($this->destination . '/' . $path) & 0o7777)->toBe($mode);
    }
    expect(is_dir($this->destination . '/mode-directory'))->toBeTrue();
    foreach (array_filter($files, static fn (?string $content): bool => $content !== null && $content !== '') as $path => $content) {
        expect(file_get_contents($this->destination . '/' . $path))->toBe($content);
    }
});

test('all entries are validated before any extraction write', function (): void {
    $this->filesystem->dumpFile($this->archive, zipArchive(['valid.txt' => 'valid', '../outside.txt' => 'invalid']));

    expect(fn () => (new ZipExtractor())->extract($this->archive, $this->destination, $this->validate))->toThrow(RuntimeException::class, 'Unsafe entry')
        ->and(scandir($this->destination))->toBe(['.', '..'])
        ->and($this->filesystem->exists($this->directory . '/outside.txt'))->toBeFalse();
});

test('native extraction normalizes ordinary backslash paths and skips core archive symlinks', function (): void {
    $this->filesystem->dumpFile($this->archive, zipArchive(['nested\\file.txt' => 'content', 'link' => '../outside'], ['link' => 0o120777]));

    (new ZipExtractor())->extract($this->archive, $this->destination, $this->validate);

    expect(file_get_contents($this->destination . '/nested/file.txt'))->toBe('content')
        ->and(is_link($this->destination . '/link'))->toBeFalse()
        ->and($this->filesystem->exists($this->destination . '/link'))->toBeFalse();
});

test('native extraction fails on invalid archives without writing files', function (string $archive): void {
    $this->filesystem->dumpFile($this->archive, $archive);

    expect(fn () => (new ZipExtractor())->extract($this->archive, $this->destination, $this->validate))->toThrow(RuntimeException::class)
        ->and(scandir($this->destination))->toBe(['.', '..']);
})->with([
    'not a ZIP' => ['not a zip'],
    'truncated ZIP' => [substr(zipArchive(['file.txt' => 'content']), 0, -15)],
    'raw NUL filename' => [zipArchive(["null\0.txt" => 'content'])],
    'ZIP64 raw NUL filename' => [zip64TestArchive(zipArchive(["null\0.txt" => 'content']))],
]);

test('native extraction reads ZIP64 directories and binary archive comments', function (bool $zip64): void {
    $archive = zipArchive(['file.txt' => 'content']);
    if ($zip64) {
        $archive = zip64TestArchive($archive);
    }
    $comment = "comment PK\x05\x06 with a NUL \0";
    $archive = substr($archive, 0, -2) . pack('v', strlen($comment)) . $comment;
    $this->filesystem->dumpFile($this->archive, $archive);

    (new ZipExtractor())->extract($this->archive, $this->destination, $this->validate);

    expect(file_get_contents($this->destination . '/file.txt'))->toBe('content');
})->with([false, true]);

test('native extraction detects corrupt file contents', function (): void {
    // A short entry is stored uncompressed, so changing its bytes leaves the
    // structure valid and specifically exercises the content integrity check.
    $archive = zipArchive(['file.txt' => 'original data']);
    expect(substr_count($archive, 'original data'))->toBe(1);
    $this->filesystem->dumpFile($this->archive, str_replace('original data', 'modified data', $archive));
    $this->filesystem->dumpFile($this->destination . '/file.txt', 'installed copy');

    expect(fn () => (new ZipExtractor())->extract($this->archive, $this->destination, $this->validate))->toThrow(RuntimeException::class)
        ->and(file_get_contents($this->destination . '/file.txt'))->toBe('installed copy')
        ->and(scandir($this->destination))->toBe(['.', '..', 'file.txt']);
});

test('replacement is verified independently of the installed file permissions', function (): void {
    $path = $this->destination . '/file.txt';
    $this->filesystem->dumpFile($path, 'installed copy');
    $this->filesystem->chmod($path, 0o044);
    $this->filesystem->dumpFile($this->archive, zipArchive(['file.txt' => 'replacement'], ['file.txt' => 0o100640]));

    (new ZipExtractor())->extract($this->archive, $this->destination, $this->validate);

    expect(file_get_contents($path))->toBe('replacement')
        ->and(scandir($this->destination))->toBe(['.', '..', 'file.txt']);
    if (DIRECTORY_SEPARATOR !== '\\') {
        expect(fileperms($path) & 0o777)->toBe(0o640);
    }
});

test('extraction limits reject the complete archive before writing', function (int $maxEntries, int $maxBytes, string $message): void {
    $this->filesystem->dumpFile($this->archive, zipArchive(['first.txt' => 'first', 'second.txt' => 'second']));

    expect(fn () => (new ZipExtractor())->extract($this->archive, $this->destination, $this->validate, $maxEntries, $maxBytes))
        ->toThrow(RuntimeException::class, $message)
        ->and(scandir($this->destination))->toBe(['.', '..']);
})->with([
    'entry count' => [1, 100, 'entry limit'],
    'expanded bytes' => [2, 10, 'extracted size limit'],
]);

test('extraction accepts archives at the configured limits', function (): void {
    $this->filesystem->dumpFile($this->archive, zipArchive(['first.txt' => 'first', 'second.txt' => 'second', 'empty.txt' => '']));

    (new ZipExtractor())->extract($this->archive, $this->destination, $this->validate, maxEntries: 3, maxBytes: 11);

    expect(file_get_contents($this->destination . '/first.txt'))->toBe('first')
        ->and(file_get_contents($this->destination . '/second.txt'))->toBe('second')
        ->and(file_get_contents($this->destination . '/empty.txt'))->toBe('');
});

test('a false declared size cannot replace an installed file or leave staged bytes', function (): void {
    $archive = zipArchive(['file.txt' => str_repeat('compressible content', 1000)]);
    $central = strpos($archive, "PK\x01\x02");
    $archive = substr_replace($archive, pack('V', 1), 22, 4);
    $archive = substr_replace($archive, pack('V', 1), $central + 24, 4);
    $this->filesystem->dumpFile($this->archive, $archive);
    $this->filesystem->dumpFile($this->destination . '/file.txt', 'installed copy');

    expect(fn () => (new ZipExtractor())->extract($this->archive, $this->destination, $this->validate, maxBytes: 1))
        ->toThrow(RuntimeException::class)
        ->and(file_get_contents($this->destination . '/file.txt'))->toBe('installed copy')
        ->and(scandir($this->destination))->toBe(['.', '..', 'file.txt']);
});

test('native extraction does not write through existing destination symlinks', function (bool $directory): void {
    if (DIRECTORY_SEPARATOR === '\\') {
        $this->markTestSkipped('Creating symlinks requires special privileges on Windows.');
    }

    $this->filesystem->dumpFile($this->directory . '/outside/file.txt', 'original');
    $this->filesystem->symlink($this->directory . '/outside' . ($directory ? '' : '/file.txt'), $this->destination . '/link');
    $this->filesystem->dumpFile($this->archive, zipArchive([($directory ? 'link/file.txt' : 'link') => 'replacement']));

    expect(fn () => (new ZipExtractor())->extract($this->archive, $this->destination, $this->validate))->toThrow(RuntimeException::class, 'symbolic link')
        ->and(file_get_contents($this->directory . '/outside/file.txt'))->toBe('original');
})->with([true, false]);
