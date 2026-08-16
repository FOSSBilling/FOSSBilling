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

test('getLatestVersionInfo passes the API digest through to the release info', function (): void {
    $downloadUrl = 'https://github.com/FOSSBilling/FOSSBilling/releases/download/0.8.5/FOSSBilling-0.8.5.zip';
    $digest = 'sha256:' . hash('sha256', 'release archive from the version API');
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

    $releaseInfo = $update->getLatestVersionInfo('release', true);

    expect($releaseInfo['digest'])->toBe($digest)
        ->and($requests)->toBe([
            ['method' => 'GET', 'url' => 'https://api.fossbilling.net/versions/v1/latest'],
        ]);
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
