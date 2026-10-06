<?php

declare(strict_types=1);

use FOSSBilling\ExtensionManager;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

function catalogCacheKey(string $endpoint, array $params = []): string
{
    $query = [...$params, 'fossbilling_version' => FOSSBilling\Version::VERSION];

    return 'extension-manager-' . hash('xxh3', $endpoint . serialize($query));
}

function catalogEntry(): array
{
    return [
        'id' => 'Example', 'name' => 'Example', 'type' => 'mod',
        'author' => ['id' => 'Publisher', 'URL' => 'https://example.com/author'],
        'description' => 'Description', 'icon_url' => null,
        'releases' => [['tag' => '1.0.0']],
    ];
}

function catalogManagerWith(ArrayAdapter $cache, MockHttpClient $httpClient, ?TestHandler $handler = null): ExtensionManager
{
    $di = new Pimple\Container();
    $di['cache'] = $cache;
    $di['http_client'] = $httpClient;
    if ($handler !== null) {
        $di['logger'] = new Logger('test', [$handler]);
    }
    $manager = new ExtensionManager();
    $manager->setDi($di);

    return $manager;
}

it('caches successful directory responses', function (): void {
    $entry = catalogEntry();
    $requests = 0;
    $manager = catalogManagerWith(new ArrayAdapter(), new MockHttpClient(function () use (&$requests, $entry) {
        ++$requests;

        return new MockResponse(json_encode(['result' => [$entry]], JSON_THROW_ON_ERROR));
    }));

    expect($manager->getExtensionList())->toBe([$entry]);
    expect($manager->getExtensionList())->toBe([$entry]);
    expect($requests)->toBe(1);
});

it('serves stale directory data when a refresh fails', function (): void {
    $entry = catalogEntry();
    $cache = new ArrayAdapter();
    $requests = 0;
    $healthy = true;
    $handler = new TestHandler();
    $manager = catalogManagerWith($cache, new MockHttpClient(function () use (&$requests, &$healthy, $entry) {
        ++$requests;
        if (!$healthy) {
            throw new TransportException('Could not resolve host.');
        }

        return new MockResponse(json_encode(['result' => [$entry]], JSON_THROW_ON_ERROR));
    }), $handler);

    expect($manager->getExtensionList())->toBe([$entry]);

    // The fresh entry expires, then the directory goes down.
    $cache->deleteItem(catalogCacheKey('list'));
    $healthy = false;

    expect($manager->getExtensionList())->toBe([$entry]);
    expect($handler->hasWarningRecords())->toBeTrue();
    $failedAttempts = $requests;

    // The failure is remembered briefly: further calls reuse the stale
    // response without issuing another directory request.
    expect($manager->getExtensionList())->toBe([$entry]);
    expect($requests)->toBe($failedAttempts);
});

it('throws without retrying while the directory is marked unavailable', function (): void {
    $requests = 0;
    $manager = catalogManagerWith(new ArrayAdapter(), new MockHttpClient(static function () use (&$requests): never {
        ++$requests;

        throw new TransportException('Connection refused.');
    }));

    expect(fn () => $manager->getExtension('Example'))->toThrow(FOSSBilling\Exception::class);
    expect(fn () => $manager->getExtension('Example'))->toThrow(FOSSBilling\Exception::class);
    expect($requests)->toBe(1);
});

it('ignores directory cache entries stored without a version scope', function (): void {
    $staleEntry = catalogEntry();
    $staleEntry['name'] = 'Stale Name';
    $cache = new ArrayAdapter();
    $cache->get('extension-manager-' . hash('xxh3', 'list' . serialize([])), static fn () => [$staleEntry]);

    $entry = catalogEntry();
    $requests = 0;
    $manager = catalogManagerWith($cache, new MockHttpClient(function () use (&$requests, $entry) {
        ++$requests;

        return new MockResponse(json_encode(['result' => [$entry]], JSON_THROW_ON_ERROR));
    }));

    expect($manager->getExtensionList())->toBe([$entry]);
    expect($requests)->toBe(1);
});

it('treats a corrupted fresh entry as a miss and falls back to stale data', function (): void {
    $entry = catalogEntry();
    $cache = new ArrayAdapter();
    $cache->get(catalogCacheKey('list'), static fn () => 'corrupted');
    $stale = $cache->getItem(catalogCacheKey('list') . '-stale');
    $stale->set([$entry]);
    $stale->expiresAfter(3600);
    $cache->save($stale);

    $manager = catalogManagerWith($cache, new MockHttpClient(static function (): never {
        throw new TransportException('Connection refused.');
    }));

    expect($manager->getExtensionList())->toBe([$entry]);
});
