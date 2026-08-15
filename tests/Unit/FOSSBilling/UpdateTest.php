<?php

declare(strict_types=1);

use FOSSBilling\InformationException;
use FOSSBilling\Update;
use FOSSBilling\UpdateFinalization;
use FOSSBilling\Version;
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
    $lockFile = Path::join(PATH_ROOT, Update::LOCK_FILENAME);
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

    $finalization = Mockery::mock(UpdateFinalization::class);
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

    $update = new class($releaseInfo) extends Update {
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
    $update = new Update();
    $update->setDi($di);
    $archive = createUpdateTestArchive($content);

    try {
        $releaseInfo = $update->getLatestVersionInfo('release', true);

        (new ReflectionMethod(Update::class, 'validateDownloadedArchive'))->invoke($update, $archive, $releaseInfo);

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
    $update = new Update();
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
    $update = new class extends Update {
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
        (new ReflectionMethod(Update::class, 'validateDownloadedArchive'))->invoke(
            new Update(),
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

    expect(fn (): mixed => (new ReflectionMethod(Update::class, 'validateDownloadedArchive'))->invoke(
        new Update(),
        $archive,
        ['digest' => hash('sha256', 'different archive')],
    ))->toThrow(InformationException::class, 'integrity verification');

    expect((new Filesystem())->exists($archive))->toBeFalse();
});

test('rejects update metadata with an invalid digest', function (): void {
    $archive = createUpdateTestArchive('update archive');

    expect(fn (): mixed => (new ReflectionMethod(Update::class, 'validateDownloadedArchive'))->invoke(
        new Update(),
        $archive,
        ['digest' => 'not-a-sha256-digest'],
    ))->toThrow(InformationException::class, 'invalid SHA-256 digest');

    expect((new Filesystem())->exists($archive))->toBeFalse();
});

test('rejects update metadata without an API digest', function (): void {
    $archive = createUpdateTestArchive('release archive');

    expect(fn (): mixed => (new ReflectionMethod(Update::class, 'validateDownloadedArchive'))->invoke(
        new Update(),
        $archive,
        [],
    ))->toThrow(InformationException::class, 'update API did not provide a SHA-256 digest');

    expect((new Filesystem())->exists($archive))->toBeFalse();
});

test('isSafeArchiveEntry accepts a normal relative entry', function (): void {
    expect(Update::isSafeArchiveEntry('src/library/FOSSBilling/Update.php'))->toBeTrue();
});

test('isSafeArchiveEntry rejects a forward-slash traversal segment', function (): void {
    expect(Update::isSafeArchiveEntry('../../etc/passwd'))->toBeFalse();
});

test('isSafeArchiveEntry rejects a backslash traversal segment', function (): void {
    expect(Update::isSafeArchiveEntry('..\\..\\poc.php'))->toBeFalse();
});

test('isSafeArchiveEntry rejects a mixed-separator traversal segment', function (): void {
    expect(Update::isSafeArchiveEntry('src\\..\\..\\poc.php'))->toBeFalse();
});

test('isSafeArchiveEntry rejects a Unix absolute path', function (): void {
    expect(Update::isSafeArchiveEntry('/etc/passwd'))->toBeFalse();
});

test('isSafeArchiveEntry rejects a Windows drive-letter absolute path', function (): void {
    expect(Update::isSafeArchiveEntry('C:\\Windows\\System32\\evil.dll'))->toBeFalse();
});

test('isSafeArchiveEntry rejects a trailing-space-and-dot segment Windows normalizes to a parent traversal', function (): void {
    expect(Update::isSafeArchiveEntry('.. .\\outside.php'))->toBeFalse();
});

test('isSafeArchiveEntry rejects an all-dots segment', function (): void {
    expect(Update::isSafeArchiveEntry('.../outside.php'))->toBeFalse();
});

test('isSafeArchiveEntry accepts a filename that legitimately contains spaces and periods', function (): void {
    expect(Update::isSafeArchiveEntry('src/data/my file v1.2.3.txt'))->toBeTrue();
});
