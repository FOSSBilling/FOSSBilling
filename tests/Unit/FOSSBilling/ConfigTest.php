<?php

declare(strict_types=1);

use FOSSBilling\Config;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

beforeEach(function (): void {
    $this->filesystem = new Filesystem();
    $this->original = $this->filesystem->readFile(PATH_CONFIG);
    $this->legacyBackup = Path::changeExtension(PATH_CONFIG, 'old.php');
    $this->backup = Path::join(PATH_DATA, 'backup', 'config.old.php');
    $this->filesystem->remove([$this->legacyBackup, $this->backup]);
});

afterEach(function (): void {
    $this->filesystem->dumpFile(PATH_CONFIG, $this->original);
    $this->filesystem->remove([$this->legacyBackup, $this->backup]);
    clearstatcache(true, PATH_CONFIG);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate(PATH_CONFIG, true);
    }
});

test('the config backup is kept in the data directory, not next to config.php', function (): void {
    Config::setConfig(Config::getConfig(), false);

    expect($this->backup)->toBeFile()
        ->and($this->filesystem->readFile($this->backup))->toBe($this->original)
        ->and(file_exists($this->legacyBackup))->toBeFalse();
});

test('the config backup is readable by the owner only', function (): void {
    Config::setConfig(Config::getConfig(), false);

    expect(fileperms($this->backup) & 0o777)->toBe(0o600);
})->skipOnWindows();

test('a backup left next to config.php by an older version is removed', function (): void {
    $this->filesystem->dumpFile($this->legacyBackup, '<?php return [];');

    Config::setConfig(Config::getConfig(), false);

    expect(file_exists($this->legacyBackup))->toBeFalse();
});
