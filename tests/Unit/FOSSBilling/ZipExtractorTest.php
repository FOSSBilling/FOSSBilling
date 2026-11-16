<?php

declare(strict_types=1);

use FOSSBilling\Core\Filesystem\ZipExtractor;
use FOSSBilling\Update;
use PhpZip\ZipFile;
use Symfony\Component\Filesystem\Filesystem;

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
        'nul' => 'badXname.txt',
        default => 'other.txt',
    };
    $zip->addFromString($name, 'invalid');
    $contents = $zip->outputAsString();
    $zip->close();
    if ($scenario === 'nul') {
        $contents = str_replace('badXname.txt', "bad\0name.txt", $contents);
    }
    if ($scenario === 'absolute') {
        $contents = str_replace('other.txt', '/ther.txt', $contents);
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
