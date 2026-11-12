<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Path;

test('the demo seeder does not accept API keys as command-line arguments', function (): void {
    $command = file_get_contents(Path::join(dirname(PATH_ROOT), 'tools', 'src', 'DemoSeed', 'Command', 'SeedCommand.php'));
    $client = file_get_contents(Path::join(dirname(PATH_ROOT), 'tools', 'src', 'DemoSeed', 'ApiClient.php'));
    $readme = file_get_contents(Path::join(dirname(PATH_ROOT), 'tools', 'README.md'));

    expect($command)
        ->not->toBeFalse()
        ->not->toContain('--key')
        ->and($client)
        ->not->toBeFalse()
        ->not->toContain('--key')
        ->and($readme)
        ->not->toBeFalse()
        ->not->toContain('--key');
});
