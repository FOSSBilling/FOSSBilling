<?php

declare(strict_types=1);

test('the demo seeder does not accept API keys as command-line arguments', function (): void {
    $script = file_get_contents(PATH_ROOT . '/../tools/demo-seed/demo-seed.php');
    $readme = file_get_contents(PATH_ROOT . '/../tools/demo-seed/README.md');

    expect($script)
        ->not->toBeFalse()
        ->not->toContain('--key')
        ->and($readme)
        ->not->toBeFalse()
        ->not->toContain('--key');
});
