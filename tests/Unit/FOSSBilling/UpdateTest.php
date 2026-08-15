<?php

declare(strict_types=1);

use FOSSBilling\InformationException;
use FOSSBilling\Update;
use FOSSBilling\Version;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Filesystem\Filesystem;
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

test('verifies preview archives against the API digest', function (): void {
    $content = 'preview archive';
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

test('rejects preview metadata without an API digest', function (): void {
    $archive = createUpdateTestArchive('preview archive');

    expect(fn (): mixed => (new ReflectionMethod(Update::class, 'validateDownloadedArchive'))->invoke(
        new Update(),
        $archive,
        [],
    ))->toThrow(InformationException::class, 'update API did not provide a SHA-256 digest');

    expect((new Filesystem())->exists($archive))->toBeFalse();
});
