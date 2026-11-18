<?php

declare(strict_types=1);

use FOSSBilling\Update as Updater;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

require dirname(__DIR__, 2) . '/src/vendor/autoload.php';
require dirname(__DIR__) . '/Helpers/Zip.php';

define('DEBUG', false);
ini_set('display_errors', 'stderr');
define('PATH_ROOT', $argv[1]);
define('PATH_DATA', PATH_ROOT . '/data');
define('PATH_CACHE', PATH_DATA . '/cache');
define('PATH_CONFIG', PATH_ROOT . '/config.php');
$scenario = $argv[2];
$filesystem = new Filesystem();
$filesystem->mkdir(PATH_CACHE);
$filesystem->mkdir(PATH_DATA . '/uploads');
$filesystem->dumpFile(PATH_CONFIG, '<?php return [];');
$content = $scenario === 'extraction_failure' ? 'invalid zip' : Tests\Helpers\zipArchive(['updated.txt' => 'updated']);
if ($scenario === 'protected_runtime') {
    $content = Tests\Helpers\zipArchive(['updated.txt' => 'updated', 'data/uploads/.update-runtime.lock' => 'must not replace']);
}
if ($scenario === 'partial_extraction') {
    $content = Tests\Helpers\zipArchive(['updated.txt' => 'updated', 'blocked.txt' => 'cannot replace symlink']);
    $filesystem->dumpFile(PATH_ROOT . '/target.txt', 'original');
    $filesystem->symlink(PATH_ROOT . '/target.txt', PATH_ROOT . '/blocked.txt');
}
if ($scenario === 'protected_extraction_marker') {
    $content = Tests\Helpers\zipArchive(['updated.txt' => 'updated', '.update-extraction-pending' => 'must not replace']);
}
if ($scenario === 'recovery_pending') {
    $filesystem->touch(PATH_ROOT . '/.update-extraction-pending', time() - 86400);
}
$release = [
    'version' => '0.8.5-test',
    'minimum_php_version' => '8.3',
    'download_url' => 'https://github.com/FOSSBilling/FOSSBilling/releases/download/test/update.zip',
    'digest' => 'sha256:' . hash('sha256', $scenario === 'integrity_failure' ? 'different archive' : $content),
    'update_type' => 0,
];
$finalization = new class {
    public bool $pending = false;
    public bool $created = false;

    public function withUpdateLock(Closure $callback): mixed
    {
        return $callback();
    }

    public function isRequired(bool $ensure = true): bool
    {
        return $this->pending;
    }

    public function createPendingState(...$args): void
    {
        if ($GLOBALS['scenario'] === 'pending_state_failure') {
            throw new RuntimeException('Finalization state unavailable');
        }
        $this->pending = true;
        $this->created = true;
    }
};
$di = new Pimple\Container();
$di['filesystem'] = $filesystem;
$di['update_finalization'] = $finalization;
$di['update_readiness'] = new class {
    public function check(): array
    {
        return ['can_update' => true];
    }
};
$di['session'] = new class {
    public function save(): void
    {
    }

    public function destroy(string $area): void
    {
    }
};
$di['logger'] = new class extends Psr\Log\NullLogger {
    public function setChannel(string $channel): static
    {
        return $this;
    }

    public function withChannel(string $channel): static
    {
        return $this;
    }
};
$di['http_client'] = new MockHttpClient(function () use ($filesystem, $scenario, $content, $finalization): MockResponse {
    if ($scenario === 'before_download') {
        $filesystem->remove(PATH_CACHE);
    }

    return new MockResponse((function () use ($filesystem, $scenario, $content, $finalization): Generator {
        yield substr($content, 0, 10);
        if ($scenario === 'pending_during_download') {
            $finalization->pending = true;
        }
        if ($scenario === 'during_download') {
            $filesystem->remove(PATH_CACHE);
            $filesystem->mkdir(PATH_CACHE);
        }
        if ($scenario === 'transport_failure') {
            throw new RuntimeException('Interrupted test download');
        }
        yield substr($content, 10);
    })());
});
require_once PATH_ROOT . '/core/Update/RuntimeLock.php';
require PATH_ROOT . '/library/FOSSBilling/Update.php';
class_alias(Updater::class, 'UpdateFixtureBase');
$updater = new class($release) extends UpdateFixtureBase {
    public function __construct(private readonly array $release)
    {
        parent::__construct();
    }

    public function getUpdateBranch(): string
    {
        return 'release';
    }

    public function getLatestVersion(): string
    {
        return $this->release['version'];
    }

    public function getLatestVersionInfo(?string $branch = null, bool $refetch = false): array
    {
        return $this->release;
    }

    public function isUpdateAvailable(): bool
    {
        return true;
    }
};
$updater->setDi($di);
if ($scenario === 'stale_lock') {
    $filesystem->touch(PATH_ROOT . '/' . Updater::LOCK_FILENAME, time() - 700);
}
if ($scenario === 'locked') {
    $filesystem->touch(PATH_ROOT . '/' . Updater::LOCK_FILENAME);
}
$error = null;

try {
    $updater->performUpdate();
} catch (Throwable $e) {
    $error = $e->getMessage();
}
echo json_encode([
    'error' => $error,
    'pending' => $finalization->created,
    'extracted' => $filesystem->exists(PATH_ROOT . '/updated.txt'),
    'locked' => $filesystem->exists(PATH_ROOT . '/' . Updater::LOCK_FILENAME),
    'incomplete' => $filesystem->exists(PATH_ROOT . '/.update-extraction-pending'),
    'archives' => array_keys(iterator_to_array(Finder::create()->files()->in(PATH_DATA)->name('*.zip'))),
], JSON_THROW_ON_ERROR);
