<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Stubs the global $filesystem used by checkInstaller() so the test never touches real
 * disk. The installer entry point is reported as missing so the exception-throwing branch,
 * which reads the real config via Config::getProperty(), is skipped.
 */
function runCheckInstallerWithStubbedFilesystem(bool $expectRemoval): void
{
    $configPath = PATH_CONFIG;
    $installerEntryPoint = Path::join('install', 'install.php');
    $installDir = Path::normalize('install');

    $mock = Mockery::mock(Filesystem::class);
    $mock->shouldReceive('exists')->with($configPath)->andReturn(true);
    $mock->shouldReceive('exists')->with($installerEntryPoint)->andReturn(false);
    $mock->shouldReceive('exists')->with($installDir)->andReturn(true);

    if ($expectRemoval) {
        $mock->shouldReceive('remove')->once()->with('install');
    } else {
        $mock->shouldNotReceive('remove');
    }

    $previousFilesystem = $GLOBALS['filesystem'] ?? null;
    $GLOBALS['filesystem'] = $mock;

    try {
        checkInstaller();
    } finally {
        $GLOBALS['filesystem'] = $previousFilesystem;
    }
}

test('checkInstaller does not delete the install directory when APP_ENV is unset', function (): void {
    withAppEnv(null, fn () => runCheckInstallerWithStubbedFilesystem(expectRemoval: false));
});

test('checkInstaller does not delete the install directory when APP_ENV holds an unrecognized value', function (): void {
    withAppEnv('staging', fn () => runCheckInstallerWithStubbedFilesystem(expectRemoval: false));
});

test('checkInstaller does not delete the install directory when APP_ENV is dev or test', function (): void {
    withAppEnv('dev', fn () => runCheckInstallerWithStubbedFilesystem(expectRemoval: false));
    withAppEnv('test', fn () => runCheckInstallerWithStubbedFilesystem(expectRemoval: false));
});

test('checkInstaller deletes the install directory when APP_ENV is explicitly prod', function (): void {
    withAppEnv('prod', fn () => runCheckInstallerWithStubbedFilesystem(expectRemoval: true));
});
