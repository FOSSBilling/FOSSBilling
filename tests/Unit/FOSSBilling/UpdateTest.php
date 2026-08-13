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

        (new ReflectionMethod(Update::class, 'validateDownloadedArchive'))->invoke($update, $archive, $releaseInfo, 'release');

        expect($releaseInfo['digest'])->toBe($digest)
            ->and($requests)->toBe([
                ['method' => 'GET', 'url' => 'https://api.fossbilling.net/versions/v1/latest'],
            ]);
    } finally {
        (new Filesystem())->remove($archive);
    }
});

test('validates a downloaded archive against a SHA-256 digest', function (): void {
    $content = 'valid update archive';
    $archive = createUpdateTestArchive($content);

    try {
        (new ReflectionMethod(Update::class, 'validateDownloadedArchive'))->invoke(
            new Update(),
            $archive,
            ['digest' => 'sha256:' . hash('sha256', $content)],
            'release',
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
        'release',
    ))->toThrow(InformationException::class, 'integrity verification');

    expect((new Filesystem())->exists($archive))->toBeFalse();
});

test('rejects release metadata without an API digest', function (): void {
    $archive = createUpdateTestArchive('release archive');

    expect(fn (): mixed => (new ReflectionMethod(Update::class, 'validateDownloadedArchive'))->invoke(
        new Update(),
        $archive,
        [],
        'release',
    ))->toThrow(InformationException::class, 'version API did not provide a SHA-256 digest');

    expect((new Filesystem())->exists($archive))->toBeFalse();
});

test('skips archive verification for preview updates until the API provides a digest', function (): void {
    $archive = createUpdateTestArchive('preview archive without a digest');

    try {
        (new ReflectionMethod(Update::class, 'validateDownloadedArchive'))->invoke(
            new Update(),
            $archive,
            [],
            'preview',
        );

        expect((new Filesystem())->exists($archive))->toBeTrue();
    } finally {
        (new Filesystem())->remove($archive);
    }
});
