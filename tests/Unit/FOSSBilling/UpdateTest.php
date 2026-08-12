<?php

declare(strict_types=1);

use FOSSBilling\InformationException;
use FOSSBilling\Update;
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

test('validates a downloaded archive against a SHA-256 digest', function (): void {
    $content = 'valid update archive';
    $archive = createUpdateTestArchive($content);

    try {
        (new ReflectionMethod(Update::class, 'validateDownloadedArchive'))->invoke(
            new Update(),
            $archive,
            ['sha256' => 'sha256:' . hash('sha256', $content)],
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
        ['sha256' => hash('sha256', 'different archive')],
    ))->toThrow(InformationException::class, 'integrity verification');

    expect((new Filesystem())->exists($archive))->toBeFalse();
});

test('gets the digest from the exact GitHub release asset when metadata does not include one', function (): void {
    $content = 'release archive';
    $archive = createUpdateTestArchive($content);
    $downloadUrl = 'https://github.com/FOSSBilling/FOSSBilling/releases/download/0.8.5/FOSSBilling-0.8.5.zip';
    $requests = [];
    $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests, $downloadUrl, $content): MockResponse {
        $requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

        return new MockResponse(json_encode([
            'assets' => [
                [
                    'browser_download_url' => 'https://github.com/FOSSBilling/FOSSBilling/releases/download/0.8.5/other.zip',
                    'digest' => 'sha256:' . hash('sha256', 'other archive'),
                ],
                [
                    'browser_download_url' => $downloadUrl,
                    'digest' => 'sha256:' . hash('sha256', $content),
                ],
            ],
        ], JSON_THROW_ON_ERROR));
    });
    $di = new Pimple\Container();
    $di['http_client'] = $httpClient;
    $update = new Update();
    $update->setDi($di);

    try {
        (new ReflectionMethod(Update::class, 'validateDownloadedArchive'))->invoke(
            $update,
            $archive,
            [
                'version' => '0.8.5',
                'github_release_id' => 352974509,
                'download_url' => $downloadUrl,
            ],
        );

        expect($requests)->toHaveCount(1)
            ->and($requests[0]['method'])->toBe('GET')
            ->and($requests[0]['url'])->toBe('https://api.github.com/repos/FOSSBilling/FOSSBilling/releases/352974509')
            ->and($requests[0]['options']['normalized_headers']['accept'][0])->toBe('Accept: application/vnd.github+json');
    } finally {
        (new Filesystem())->remove($archive);
    }
});

test('rejects a GitHub release asset without a digest', function (): void {
    $archive = createUpdateTestArchive('release archive');
    $downloadUrl = 'https://github.com/FOSSBilling/FOSSBilling/releases/download/0.8.5/FOSSBilling-0.8.5.zip';
    $httpClient = new MockHttpClient(new MockResponse(json_encode([
        'assets' => [['browser_download_url' => $downloadUrl]],
    ], JSON_THROW_ON_ERROR)));
    $di = new Pimple\Container();
    $di['http_client'] = $httpClient;
    $update = new Update();
    $update->setDi($di);

    expect(fn (): mixed => (new ReflectionMethod(Update::class, 'validateDownloadedArchive'))->invoke(
        $update,
        $archive,
        [
            'version' => '0.8.5',
            'github_release_id' => 352974509,
            'download_url' => $downloadUrl,
        ],
    ))->toThrow(InformationException::class);

    expect((new Filesystem())->exists($archive))->toBeFalse();
});
