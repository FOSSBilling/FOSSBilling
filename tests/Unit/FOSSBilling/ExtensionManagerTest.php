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

function catalogManager(array $result, bool $cached = false): ExtensionManager
{
    $cache = new ArrayAdapter();
    if ($cached) {
        foreach (['list', 'Example'] as $endpoint) {
            $cache->get(catalogCacheKey($endpoint), static fn () => $result);
        }
    }
    $di = new Pimple\Container();
    $di['cache'] = $cache;
    $di['http_client'] = new MockHttpClient(new MockResponse(json_encode(['result' => $result], JSON_THROW_ON_ERROR)));
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

it('rejects unsafe directory metadata including cached responses', function (string $field, mixed $value, bool $cached): void {
    $entry = catalogEntry();
    if ($field === 'URL') {
        $entry['author']['URL'] = $value;
    } else {
        $entry[$field] = $value;
    }
    expect(fn () => catalogManager([$entry], $cached)->getExtensionList())->toThrow(FOSSBilling\Exception::class);
    expect(fn () => catalogManager($entry, $cached)->getExtension('Example'))->toThrow(FOSSBilling\Exception::class);
})->with([
    ['id', "Example');alert(1);//", false],
    ['id', '../Example', true],
    ['id', "Example\n", false],
    ['name', ['invalid'], true],
    ['URL', 'javascript:alert(1)', false],
    ['URL', 'JaVaScRiPt:alert(1)', true],
    ['URL', "java\tscript:alert(1)", false],
    ['URL', 'data:text/html,<script>alert(1)</script>', true],
    ['URL', '//example.com', false],
    ['URL', ['https://example.com'], true],
    ['URL', 'https://example.com/" onmouseover=alert(1)', false],
]);

it('preserves valid catalog metadata and optional author links', function (?string $url): void {
    $entry = catalogEntry();
    $entry['id'] = 'Example_2-en';
    $entry['name'] = "Publisher's \"Example\" <module>";
    $entry['author']['URL'] = $url;
    expect(catalogManager([$entry])->getExtensionList())->toBe([$entry]);
    expect(catalogManager($entry)->getExtension('Example'))->toBe($entry);
})->with(['https://example.com/author?a=1&b=2', 'http://example.com/author', 'HTTPS://example.com/author', '', null]);

it('rejects invalid requested identifiers before issuing a directory request', function (string $id): void {
    $di = new Pimple\Container();
    $di['cache'] = new ArrayAdapter();
    $di['http_client'] = new MockHttpClient(static function (): never {
        throw new LogicException('No request should be issued.');
    });
    $manager = new ExtensionManager();
    $manager->setDi($di);
    expect(fn () => $manager->getExtension($id))->toThrow(FOSSBilling\InformationException::class);
})->with(['../Example', 'Example?query', 'Example#fragment', "Example\n", '']);

it('preserves empty lists and metadata with no author URL', function (): void {
    $entry = catalogEntry();
    unset($entry['author']['URL']);
    expect(catalogManager([$entry])->getExtensionList())->toBe([$entry]);
    expect(catalogManager([])->getExtensionList())->toBe([]);
});
