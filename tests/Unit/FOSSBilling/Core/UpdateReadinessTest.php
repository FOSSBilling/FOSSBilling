<?php

declare(strict_types=1);

use FOSSBilling\Core\Update\ReadinessCheck;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    $this->filesystem = new Filesystem();
    $this->root = Path::join(sys_get_temp_dir(), 'fossbilling-readiness-' . bin2hex(random_bytes(8)));
    foreach (['data/cache', 'data/log', 'data/uploads', 'vendor', 'core', 'library', 'modules', 'themes', 'public', 'locale'] as $path) {
        $this->filesystem->mkdir($this->root . '/' . $path);
    }
    $this->filesystem->dumpFile($this->root . '/config.php', '<?php return [];');
});

afterEach(function (): void {
    $this->filesystem->remove($this->root);
});

test('a writable installation with native ZIP support is ready to update', function (): void {
    $result = (new ReadinessCheck($this->root, $this->root . '/data', $this->root . '/config.php'))->check();

    expect($result)->toBe(['can_update' => true, 'issues' => []]);
});

test('update readiness reports missing ZIP support', function (): void {
    // Keep the function override out of the main test process.
    $code = <<<'PHP'
        namespace FOSSBilling\Core\Update;

        function extension_loaded(string $extension): bool {
            return $extension !== 'zip' && \extension_loaded($extension);
        }

        require $argv[1] . '/src/vendor/autoload.php';
        $root = $argv[2];
        echo json_encode((new ReadinessCheck($root, $root . '/data', $root . '/config.php'))->check(), JSON_THROW_ON_ERROR);
        PHP;
    $process = new Process([PHP_BINARY, '-r', $code, dirname(PATH_TESTS), $this->root]);
    $process->mustRun();
    $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

    expect($result['can_update'])->toBeFalse()
        ->and($result['issues'])->toHaveCount(1)
        ->and($result['issues'][0])->toMatchArray(['path' => 'ext-zip', 'type' => 'extension', 'reason' => 'missing']);
});
