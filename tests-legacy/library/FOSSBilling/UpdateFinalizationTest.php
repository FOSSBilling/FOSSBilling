<?php

declare(strict_types=1);

use FOSSBilling\Config;
use FOSSBilling\UpdateFinalization;
use FOSSBilling\Version;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

#[Group('Core')]
final class UpdateFinalizationTest extends PHPUnit\Framework\TestCase
{
    private Filesystem $filesystem;
    private string $originalConfig;
    private ?string $originalState = null;
    private string $statePath;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->statePath = Path::join(PATH_DATA, UpdateFinalization::STATE_FILENAME);
        $this->originalConfig = $this->filesystem->readFile(PATH_CONFIG);
        $this->originalState = $this->readOptionalFile($this->statePath);

        $this->filesystem->remove($this->statePath);
    }

    protected function tearDown(): void
    {
        $this->filesystem->dumpFile(PATH_CONFIG, $this->originalConfig);
        clearstatcache(true, PATH_CONFIG);
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate(PATH_CONFIG, true);
        }

        $this->restoreOptionalFile($this->statePath, $this->originalState);
        $this->filesystem->remove(Path::join(PATH_CACHE, 'downloaded-update.zip'));
        $this->filesystem->remove(Path::changeExtension(PATH_CONFIG, 'old.php'));
    }

    public function testMissingFinalizationStateCreatesPendingStateAndEnablesMaintenance(): void
    {
        $config = Config::getConfig();
        $config['maintenance_mode'] = [
            'enabled' => false,
            'allowed_urls' => [],
            'allowed_ips' => [],
        ];
        Config::setConfig($config);

        $cacheFile = Path::join(PATH_CACHE, 'downloaded-update.zip');
        $this->filesystem->dumpFile($cacheFile, 'archive contents');

        $finalization = new UpdateFinalization();
        $state = $finalization->ensureCurrentVersionFinalization();

        self::assertIsArray($state);
        self::assertSame('pending', $state['status']);
        self::assertSame(Version::VERSION, $state['target_version']);
        self::assertSame('missing-finalization-state', $state['source']);
        self::assertFileExists($this->statePath);

        $updatedConfig = Config::getConfig();
        self::assertTrue($updatedConfig['maintenance_mode']['enabled']);
        self::assertContains(rtrim(ADMIN_PREFIX, '/') . '/system/update/finalize', $updatedConfig['maintenance_mode']['allowed_urls']);
        self::assertFileExists($cacheFile);
    }

    public function testCompletingFinalizationRestoresMaintenanceAndWritesCompleteState(): void
    {
        $config = Config::getConfig();
        $config['maintenance_mode'] = [
            'enabled' => true,
            'allowed_urls' => ['/temporary-finalization-url'],
            'allowed_ips' => [],
        ];
        Config::setConfig($config);

        $state = [
            'status' => 'finalized',
            'from_version' => '0.0.0',
            'target_version' => Version::VERSION,
            'branch' => 'release',
            'update_type' => null,
            'source' => 'test',
            'created_at' => date(DATE_ATOM),
            'finalized_at' => date(DATE_ATOM),
            'completed_at' => null,
            'maintenance_mode' => [
                'enabled' => false,
                'allowed_urls' => ['/existing-maintenance-exception'],
                'allowed_ips' => ['127.0.0.1'],
            ],
        ];
        $this->filesystem->dumpFile($this->statePath, json_encode($state, JSON_THROW_ON_ERROR));

        $finalization = new UpdateFinalization();
        $finalization->completeFinalization();

        self::assertFileExists($this->statePath);

        $completedState = json_decode($this->filesystem->readFile($this->statePath), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('complete', $completedState['status']);
        self::assertSame(Version::VERSION, $completedState['version']);
        self::assertFalse($finalization->isRequired());

        $updatedConfig = Config::getConfig();
        self::assertFalse($updatedConfig['maintenance_mode']['enabled']);
        self::assertSame(['/existing-maintenance-exception'], $updatedConfig['maintenance_mode']['allowed_urls']);
        self::assertSame(['127.0.0.1'], $updatedConfig['maintenance_mode']['allowed_ips']);
    }

    private function readOptionalFile(string $path): ?string
    {
        if (!$this->filesystem->exists($path)) {
            return null;
        }

        return $this->filesystem->readFile($path);
    }

    private function restoreOptionalFile(string $path, ?string $contents): void
    {
        if ($contents === null) {
            $this->filesystem->remove($path);

            return;
        }

        $this->filesystem->dumpFile($path, $contents);
    }
}
