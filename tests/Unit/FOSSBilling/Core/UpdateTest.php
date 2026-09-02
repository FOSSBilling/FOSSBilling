<?php

declare(strict_types=1);

use FOSSBilling\Core\Exception\InformationException;
use FOSSBilling\Core\System\Version;
use FOSSBilling\Core\Update\Finalization;
use FOSSBilling\Core\Update\Updater;
use PhpZip\ZipFile;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

function createUpdateTestArchive(string $content): string
{
    $archive = tempnam(sys_get_temp_dir(), 'fossbilling-update-');
    if ($archive === false) {
        throw new RuntimeException('Unable to create a temporary update archive.');
    }

    file_put_contents($archive, $content);

    return $archive;
}

test('does not finalize an update in the request that extracts it', function (): void {
    $filesystem = new Filesystem();
    $latestVersion = '0.8.5-test-' . bin2hex(random_bytes(8));
    $entryName = '.fossbilling-update-test-' . bin2hex(random_bytes(8)) . '.txt';
    $extractedFile = Path::join(PATH_ROOT, $entryName);
    $archiveFile = Path::join(PATH_CACHE, $latestVersion . '.zip');
    $lockFile = Path::join(PATH_ROOT, Updater::LOCK_FILENAME);
    $lockExisted = $filesystem->exists($lockFile);

    $zip = new ZipFile();
    $zip->addFromString($entryName, 'update handoff test');
    $archiveContent = $zip->outputAsString();

    $releaseInfo = [
        'version' => $latestVersion,
        'minimum_php_version' => '8.3',
        'download_url' => 'https://github.com/FOSSBilling/FOSSBilling/releases/download/test/update.zip',
        'digest' => 'sha256:' . hash('sha256', $archiveContent),
        'update_type' => 0,
    ];

    $finalization = Mockery::mock(Finalization::class);
    $finalization->shouldReceive('isRequired')->once()->andReturnFalse();
    $finalization->shouldReceive('createPendingState')->once()->with(
        Version::VERSION,
        $latestVersion,
        [
            'branch' => 'release',
            'update_type' => 0,
            'source' => 'auto-update',
        ]
    )->andReturn(['status' => 'pending']);
    $finalization->shouldNotReceive('finalizeUpdate');

    $readiness = Mockery::mock();
    $readiness->shouldReceive('check')->once()->andReturn(['can_update' => true]);

    $session = Mockery::mock();
    $session->shouldReceive('destroy')->once()->with('admin');

    $di = new Pimple\Container();
    $di['filesystem'] = $filesystem;
    $di['http_client'] = new MockHttpClient(new MockResponse($archiveContent));
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['session'] = $session;
    $di['update_finalization'] = $finalization;
    $di['update_readiness'] = $readiness;

    $update = new class($releaseInfo) extends Updater {
        public function __construct(private readonly array $releaseInfo)
        {
            parent::__construct();
        }

        public function getUpdateBranch(): string
        {
            return 'release';
        }

        public function getLatestVersion(): string
        {
            return $this->releaseInfo['version'];
        }

        public function getLatestVersionInfo(?string $branch = null, bool $refetch = false): array
        {
            return $this->releaseInfo;
        }

        public function isUpdateAvailable(): bool
        {
            return true;
        }
    };
    $update->setDi($di);

    try {
        $update->performUpdate();

        expect($filesystem->exists($extractedFile))->toBeTrue();
    } finally {
        $filesystem->remove($extractedFile);
        $filesystem->remove($archiveFile);
        if (!$lockExisted) {
            $filesystem->remove($lockFile);
        }
    }
});

test('does not create pending state when archive extraction fails', function (): void {
    $filesystem = new Filesystem();
    $latestVersion = '0.8.5-test-' . bin2hex(random_bytes(8));
    $archiveFile = Path::join(PATH_CACHE, $latestVersion . '.zip');
    $lockFile = Path::join(PATH_ROOT, Updater::LOCK_FILENAME);
    $lockExisted = $filesystem->exists($lockFile);
    $archiveContent = 'not a zip archive';

    $finalization = Mockery::mock(Finalization::class);
    $finalization->shouldReceive('isRequired')->once()->andReturnFalse();
    $finalization->shouldNotReceive('createPendingState');

    $readiness = Mockery::mock();
    $readiness->shouldReceive('check')->once()->andReturn(['can_update' => true]);

    $di = new Pimple\Container();
    $di['filesystem'] = $filesystem;
    $di['http_client'] = new MockHttpClient(new MockResponse($archiveContent));
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['session'] = Mockery::mock();
    $di['update_finalization'] = $finalization;
    $di['update_readiness'] = $readiness;

    $update = new class($latestVersion) extends Updater {
        public function __construct(private readonly string $latestVersion)
        {
            parent::__construct();
        }

        public function getUpdateBranch(): string
        {
            return 'release';
        }

        public function getLatestVersion(): string
        {
            return $this->latestVersion;
        }

        public function getLatestVersionInfo(?string $branch = null, bool $refetch = false): array
        {
            return [
                'version' => $this->latestVersion,
                'minimum_php_version' => '8.3',
                'download_url' => 'https://github.com/FOSSBilling/FOSSBilling/releases/download/test/update.zip',
                'digest' => 'sha256:' . hash('sha256', 'not a zip archive'),
                'update_type' => 0,
            ];
        }

        public function isUpdateAvailable(): bool
        {
            return true;
        }
    };
    $update->setDi($di);

    try {
        expect(fn (): mixed => $update->performUpdate())->toThrow(FOSSBilling\Core\Exception\BaseException::class, 'Failed to extract file');
    } finally {
        $filesystem->remove($archiveFile);
        if (!$lockExisted) {
            $filesystem->remove($lockFile);
        }
    }
});

test('uses the API digest for archive verification without querying GitHub', function (): void {
    $content = 'release archive from the version API';
    $digest = 'sha256:' . hash('sha256', $content);
    $downloadUrl = 'https://github.com/FOSSBilling/FOSSBilling/releases/download/0.8.5/FOSSBilling-0.8.5.zip';
    $requests = [];
    $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requests, $digest, $downloadUrl): MockResponse {
        $requests[] = ['method' => $method, 'url' => $url];

        return new MockResponse(json_encode([
            'result' => [
                'version' => '0.8.5',
                'released_on' => '2026-07-20T02:10:43Z',
                'minimum_php_version' => '8.3',
                'download_url' => $downloadUrl,
                'digest' => $digest,
            ],
        ], JSON_THROW_ON_ERROR));
    });
    $di = new Pimple\Container();
    $cache = new ArrayAdapter();
    $cache->get('changelog_from_' . Version::VERSION, static fn (): string => 'test changelog');
    $di['cache'] = $cache;
    $di['http_client'] = $httpClient;
    $update = new Updater();
    $update->setDi($di);
    $archive = createUpdateTestArchive($content);

    try {
        $releaseInfo = $update->getLatestVersionInfo('release', true);

        (new ReflectionMethod(Updater::class, 'validateDownloadedArchive'))->invoke($update, $archive, $releaseInfo);

        expect($releaseInfo['digest'])->toBe($digest)
            ->and($requests)->toBe([
                ['method' => 'GET', 'url' => 'https://api.fossbilling.net/versions/v1/latest'],
            ]);
    } finally {
        (new Filesystem())->remove($archive);
    }
});

test('uses the previews API for the current preview build', function (): void {
    $requests = [];
    $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requests): MockResponse {
        $requests[] = ['method' => $method, 'url' => $url];

        return new MockResponse(json_encode([
            'result' => [
                'commit_sha' => '0123456789abcdef0123456789abcdef01234567',
                'short_sha' => '0123456',
                'digest' => 'sha256:' . str_repeat('a', 64),
                'created_at' => '2026-08-15T07:54:48Z',
                'last_modified' => '2026-08-15T07:54:46.671Z',
                'download_url' => 'https://download.fossbilling.org/FOSSBilling-preview.zip',
            ],
        ], JSON_THROW_ON_ERROR));
    });
    $di = new Pimple\Container();
    $di['cache'] = new ArrayAdapter();
    $di['http_client'] = $httpClient;
    $update = new Updater();
    $update->setDi($di);

    $info = $update->getLatestVersionInfo('preview', true);

    expect($info)->toMatchArray([
        'version' => '0123456',
        'download_url' => 'https://download.fossbilling.org/FOSSBilling-preview.zip',
        'branch' => 'preview',
        'minimum_php_version' => 'unknown',
        'digest' => 'sha256:' . str_repeat('a', 64),
        'commit_sha' => '0123456789abcdef0123456789abcdef01234567',
        'short_sha' => '0123456',
        'release_date' => '2026-08-15T07:54:46.671Z',
    ])->and($requests)->toBe([
        ['method' => 'GET', 'url' => 'https://api.fossbilling.net/previews/v1/main'],
    ]);
});

test('treats a different preview commit as an available update', function (): void {
    $update = new class extends Updater {
        public function getUpdateBranch(): string
        {
            return 'preview';
        }

        public function getLatestVersion(): string
        {
            return '0123456';
        }
    };

    expect($update->isUpdateAvailable())->toBeTrue();
});

test('validates a downloaded archive against a SHA-256 digest', function (): void {
    $content = 'valid update archive';
    $archive = createUpdateTestArchive($content);

    try {
        (new ReflectionMethod(Updater::class, 'validateDownloadedArchive'))->invoke(
            new Updater(),
            $archive,
            ['digest' => 'sha256:' . hash('sha256', $content)],
        );

        expect((new Filesystem())->exists($archive))->toBeTrue();
    } finally {
        (new Filesystem())->remove($archive);
    }
});

test('rejects and removes a downloaded archive with the wrong digest', function (): void {
    $archive = createUpdateTestArchive('tampered update archive');

    expect(fn (): mixed => (new ReflectionMethod(Updater::class, 'validateDownloadedArchive'))->invoke(
        new Updater(),
        $archive,
        ['digest' => hash('sha256', 'different archive')],
    ))->toThrow(InformationException::class, 'integrity verification');

    expect((new Filesystem())->exists($archive))->toBeFalse();
});

test('rejects update metadata with an invalid digest', function (): void {
    $archive = createUpdateTestArchive('update archive');

    expect(fn (): mixed => (new ReflectionMethod(Updater::class, 'validateDownloadedArchive'))->invoke(
        new Updater(),
        $archive,
        ['digest' => 'not-a-sha256-digest'],
    ))->toThrow(InformationException::class, 'invalid SHA-256 digest');

    expect((new Filesystem())->exists($archive))->toBeFalse();
});

test('rejects update metadata without an API digest', function (): void {
    $archive = createUpdateTestArchive('release archive');

    expect(fn (): mixed => (new ReflectionMethod(Updater::class, 'validateDownloadedArchive'))->invoke(
        new Updater(),
        $archive,
        [],
    ))->toThrow(InformationException::class, 'update API did not provide a SHA-256 digest');

    expect((new Filesystem())->exists($archive))->toBeFalse();
});

test('isSafeArchiveEntry accepts a normal relative entry', function (): void {
    expect(Updater::isSafeArchiveEntry('src/core/Update/Updater.php'))->toBeTrue();
});

test('isSafeArchiveEntry rejects a forward-slash traversal segment', function (): void {
    expect(Updater::isSafeArchiveEntry('../../etc/passwd'))->toBeFalse();
});

test('isSafeArchiveEntry rejects a backslash traversal segment', function (): void {
    expect(Updater::isSafeArchiveEntry('..\\..\\poc.php'))->toBeFalse();
});

test('isSafeArchiveEntry rejects a mixed-separator traversal segment', function (): void {
    expect(Updater::isSafeArchiveEntry('src\\..\\..\\poc.php'))->toBeFalse();
});

test('isSafeArchiveEntry rejects a Unix absolute path', function (): void {
    expect(Updater::isSafeArchiveEntry('/etc/passwd'))->toBeFalse();
});

test('isSafeArchiveEntry rejects a Windows drive-letter absolute path', function (): void {
    expect(Updater::isSafeArchiveEntry('C:\\Windows\\System32\\evil.dll'))->toBeFalse();
});

test('isSafeArchiveEntry rejects a trailing-space-and-dot segment Windows normalizes to a parent traversal', function (): void {
    expect(Updater::isSafeArchiveEntry('.. .\\outside.php'))->toBeFalse();
});

test('isSafeArchiveEntry rejects an all-dots segment', function (): void {
    expect(Updater::isSafeArchiveEntry('.../outside.php'))->toBeFalse();
});

test('isSafeArchiveEntry accepts a filename that legitimately contains spaces and periods', function (): void {
    expect(Updater::isSafeArchiveEntry('src/data/my file v1.2.3.txt'))->toBeTrue();
});
