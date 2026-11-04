<?php

declare(strict_types=1);

use FOSSBilling\Core\Remote\ExtensionManager;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

function catalogCacheKey(string $endpoint, array $params = []): string
{
    $query = $params;

    return 'extension-manager-v2-' . hash('xxh3', $endpoint . serialize($query));
}

function catalogManager(array $result, bool $cached = false): ExtensionManager
{
    $di = new Pimple\Container();
    $cache = new ArrayAdapter();
    if ($cached) {
        foreach (['extensions', 'extensions/example'] as $endpoint) {
            $cache->get(catalogCacheKey($endpoint, $endpoint === 'extensions' ? ['limit' => 100] : []), static fn () => $result);
        }
    }
    $di['cache'] = $cache;
    $di['http_client'] = new MockHttpClient(new MockResponse(json_encode(['result' => $result, 'pagination' => ['has_more' => false, 'next_cursor' => null]], JSON_THROW_ON_ERROR)));
    $manager = new ExtensionManager();
    $manager->setDi($di);

    return $manager;
}

function catalogEntry(): array
{
    return [
        'id' => 'Example', 'name' => 'Example', 'type' => 'module',
        'author' => ['name' => 'Publisher', 'url' => 'https://example.com/author'],
        'description' => 'Description', 'icon_url' => null,
        'releases' => [['tag' => '1.0.0']],
    ];
}

it('rejects unsafe directory metadata including cached responses', function (string $field, mixed $value, bool $cached): void {
    $entry = catalogEntry();
    if ($field === 'URL') {
        $entry['author']['url'] = $value;
    } else {
        $entry[$field] = $value;
    }
    expect(fn () => catalogManager([$entry], $cached)->getExtensionList())->toThrow(FOSSBilling\Core\Exception\BaseException::class);
    expect(fn () => catalogManager($entry, $cached)->getExtension('Example'))->toThrow(FOSSBilling\Core\Exception\BaseException::class);
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
    ['URL', 'https://example.com/\" onmouseover=alert(1)', false],
]);

it('preserves valid catalog metadata and optional author links', function (?string $url): void {
    $entry = catalogEntry();
    $entry['id'] = 'Example_2-en';
    $entry['name'] = "Publisher's \"Example\" <module>";
    $entry['author']['url'] = $url;
    expect(catalogManager([$entry])->getExtensionList())->toBe([$entry]);
    expect(catalogManager($entry)->getExtension('Example'))->toBe($entry);
})->with(['https://example.com/author?a=1&b=2', 'http://example.com/author', 'HTTPS://example.com/author', '', null]);

it('renders quoted directory names as inert README data and text', function (): void {
    $entry = catalogEntry();
    $entry['name'] = "');window.__catalog_probe=1;//\"<img src=x onerror=alert(1)>";
    $twig = new Environment(new FilesystemLoader(PATH_THEMES . '/default/admin/html'), ['autoescape' => 'html']);
    $twig->addFilter(new TwigFilter('trans', static fn (string $text): string => $text));
    $twig->addFilter(new TwigFilter('truncate', static fn (string $text): string => $text));
    $twig->addFilter(new TwigFilter('api_url', static fn (string $path, array $query = []): string => '/api/admin/' . $path));
    $twig->addFunction(new TwigFunction('fb_api_link', static fn (array $options): string => ''));
    $admin = new readonly class([$entry]) {
        public function __construct(private array $entries)
        {
        }

        public function extension_get_latest(array $params): array
        {
            return $this->entries;
        }
    };
    $html = $twig->render('partial_extensions.html.twig', ['admin' => $admin]);
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@onclick]')->length)->toBe(0);
    $button = $xpath->query('//*[@data-extension-id]')->item(0);
    expect($button)->toBeInstanceOf(DOMElement::class);
    expect($button->getAttribute('data-extension-name'))->toBe($entry['name']);
    expect($button->getAttribute('data-extension-id'))->toBe('Example');
    expect($xpath->query('//img[@onerror]')->length)->toBe(0);
    expect($xpath->query('//strong')->item(0)->textContent)->toBe($entry['name']);
});

it('rejects invalid requested identifiers before issuing a directory request', function (string $id): void {
    $di = new Pimple\Container();
    $di['cache'] = new ArrayAdapter();
    $di['http_client'] = new MockHttpClient(static function (): never {
        throw new LogicException('No request should be issued.');
    });
    $manager = new ExtensionManager();
    $manager->setDi($di);
    expect(fn () => $manager->getExtension($id))->toThrow(FOSSBilling\Core\Exception\InformationException::class);
})->with(['../Example', 'Example?query', 'Example#fragment', "Example\n", '']);

it('preserves empty lists and metadata with no author URL', function (): void {
    $entry = catalogEntry();
    unset($entry['author']['url']);
    expect(catalogManager([$entry])->getExtensionList())->toBe([$entry]);
    expect(catalogManager([])->getExtensionList())->toBe([]);
});

it('serves stale directory data when a refresh fails', function (): void {
    $entry = catalogEntry();
    $cache = new ArrayAdapter();
    $requests = 0;
    $healthy = true;
    $di = new Pimple\Container();
    $di['cache'] = $cache;
    $di['http_client'] = new MockHttpClient(function () use (&$requests, &$healthy, $entry) {
        ++$requests;
        if (!$healthy) {
            throw new TransportException('Could not resolve host.');
        }

        return new MockResponse(json_encode(['result' => [$entry], 'pagination' => ['has_more' => false, 'next_cursor' => null]], JSON_THROW_ON_ERROR));
    });
    $handler = new TestHandler();
    $di['logger'] = new Logger('test', [$handler]);
    $manager = new ExtensionManager();
    $manager->setDi($di);

    expect($manager->getExtensionList())->toBe([$entry]);

    // The fresh entry expires, then the directory goes down.
    $cache->deleteItem(catalogCacheKey('extensions', ['limit' => 100]));
    $healthy = false;

    expect($manager->getExtensionList())->toBe([$entry]);
    expect($handler->hasWarningRecords())->toBeTrue();
    $failedAttempts = $requests;

    // The failure is remembered briefly: further calls reuse the stale
    // response without issuing another directory request.
    expect($manager->getExtensionList())->toBe([$entry]);
    expect($requests)->toBe($failedAttempts);
});

it('treats a corrupted fresh entry as a miss and falls back to stale data', function (): void {
    $entry = catalogEntry();
    $cache = new ArrayAdapter();
    $cache->get(catalogCacheKey('extensions', ['limit' => 100]), static fn () => 'corrupted');
    $stale = $cache->getItem(catalogCacheKey('extensions', ['limit' => 100]) . '-stale');
    $stale->set([$entry]);
    $stale->expiresAfter(3600);
    $cache->save($stale);

    $di = new Pimple\Container();
    $di['cache'] = $cache;
    $di['http_client'] = new MockHttpClient(static function (): never {
        throw new TransportException('Connection refused.');
    });
    $manager = new ExtensionManager();
    $manager->setDi($di);

    expect($manager->getExtensionList())->toBe([$entry]);
});

it('keeps serving the last-known-good copy when a refresh returns invalid metadata', function (): void {
    $entry = catalogEntry();
    $cache = new ArrayAdapter();
    $stale = $cache->getItem(catalogCacheKey('extensions', ['limit' => 100]) . '-stale');
    $stale->set([$entry]);
    $stale->expiresAfter(3600);
    $cache->save($stale);

    $requests = 0;
    $invalid = catalogEntry();
    $invalid['id'] = '../Traversal';
    $di = new Pimple\Container();
    $di['cache'] = $cache;
    $di['http_client'] = new MockHttpClient(function () use (&$requests, $invalid) {
        ++$requests;

        return new MockResponse(json_encode(['result' => [$invalid], 'pagination' => ['has_more' => false, 'next_cursor' => null]], JSON_THROW_ON_ERROR));
    });
    $manager = new ExtensionManager();
    $manager->setDi($di);

    // The invalid refresh is rejected before either cache slot is written,
    // so the previous response keeps being served instead of poisoning it.
    expect($manager->getExtensionList())->toBe([$entry]);
    expect($manager->getExtensionList())->toBe([$entry]);
    expect($requests)->toBe(1);
});

it('never serves stale data that fails validation', function (): void {
    $cache = new ArrayAdapter();
    $healthy = true;
    $requests = 0;
    $invalid = catalogEntry();
    $invalid['id'] = '../Traversal';
    $di = new Pimple\Container();
    $di['cache'] = $cache;
    $di['http_client'] = new MockHttpClient(function () use (&$requests, &$healthy, $invalid) {
        ++$requests;
        if (!$healthy) {
            throw new TransportException('Connection refused.');
        }

        return new MockResponse(json_encode(['result' => [$invalid], 'pagination' => ['has_more' => false, 'next_cursor' => null]], JSON_THROW_ON_ERROR));
    });
    $manager = new ExtensionManager();
    $manager->setDi($di);

    // A direct call without validation caches the invalid payload in both slots.
    expect($manager->makeRequest('extensions', ['limit' => 100]))->toBe([$invalid]);

    // The validating reader still rejects it everywhere instead of serving it.
    $healthy = false;
    expect(fn () => $manager->getExtensionList())->toThrow(FOSSBilling\Core\Exception\BaseException::class);
});

it('throws without retrying while the directory is marked unavailable', function (): void {
    $requests = 0;
    $di = new Pimple\Container();
    $di['cache'] = new ArrayAdapter();
    $di['http_client'] = new MockHttpClient(static function () use (&$requests): never {
        ++$requests;

        throw new TransportException('Connection refused.');
    });
    $manager = new ExtensionManager();
    $manager->setDi($di);

    expect(fn () => $manager->getExtension('Example'))->toThrow(FOSSBilling\Core\Exception\BaseException::class);
    expect(fn () => $manager->getExtension('Example'))->toThrow(FOSSBilling\Core\Exception\BaseException::class);
    expect($requests)->toBe(1);
});

it('ignores cached v1 projections', function (): void {
    $staleEntry = catalogEntry();
    $staleEntry['name'] = 'Stale Name';
    $cache = new ArrayAdapter();
    $cache->get('extension-manager-' . hash('xxh3', 'list' . serialize([])), static fn () => [$staleEntry]);

    $entry = catalogEntry();
    $requests = 0;
    $di = new Pimple\Container();
    $di['cache'] = $cache;
    $di['http_client'] = new MockHttpClient(function () use (&$requests, $entry) {
        ++$requests;

        return new MockResponse(json_encode(['result' => [$entry], 'pagination' => ['has_more' => false, 'next_cursor' => null]], JSON_THROW_ON_ERROR));
    });
    $manager = new ExtensionManager();
    $manager->setDi($di);

    expect($manager->getExtensionList())->toBe([$entry]);
    expect($requests)->toBe(1);
});

it('evaluates complete release constraints before installation', function (?string $constraint, bool $expected): void {
    $branch = FOSSBilling\Core\System\Config::getProperty('update_branch', 'release');
    FOSSBilling\Core\System\Config::setProperty('update_branch', 'release', false);

    try {
        $entry = catalogEntry();
        $entry['releases'][0]['requires'] = $constraint === null ? [] : ['fossbilling' => $constraint];
        expect(catalogManager($entry)->isExtensionCompatible('Example'))->toBe($expected);
    } finally {
        FOSSBilling\Core\System\Config::setProperty('update_branch', $branch, false);
    }
})->with([
    ['>=0.0.0 <1.0', true],
    ['^0.0.1', true],
    ['~0.0.1', true],
    ['<0.0.1', false],
    ['>=0.0.0 !=0.0.1', false],
    ['>=0.0.0 <0.0.1 || >=1.0', false],
    ['>=1.0 || =0.0.1', true],
    ['not a constraint', false],
    ['', false],
    [null, false],
]);

it('preserves preview-branch behavior but rejects malformed ranges', function (): void {
    $branch = FOSSBilling\Core\System\Config::getProperty('update_branch', 'release');
    FOSSBilling\Core\System\Config::setProperty('update_branch', 'preview', false);

    try {
        $entry = catalogEntry();
        $entry['releases'][0]['requires'] = ['fossbilling' => '>=99.0'];
        expect(catalogManager($entry)->isExtensionCompatible('Example'))->toBeTrue();
        $entry['releases'][0]['requires']['fossbilling'] = 'invalid';
        expect(catalogManager($entry)->isExtensionCompatible('Example'))->toBeFalse();
    } finally {
        FOSSBilling\Core\System\Config::setProperty('update_branch', $branch, false);
    }
});

it('uses v2 detail and follows opaque list cursors without a v1 version filter', function (): void {
    $entry = catalogEntry();
    unset($entry['releases']);
    $entry['version'] = '1.2.3';
    $requests = [];
    $di = new Pimple\Container();
    $di['cache'] = new ArrayAdapter();
    $di['http_client'] = new MockHttpClient(function ($method, $url) use (&$requests, $entry) {
        $requests[] = $url;
        if (str_contains($url, '/extensions/example')) {
            return new MockResponse(json_encode(['result' => [...$entry, 'releases' => [['tag' => '1.2.3', 'requires' => ['fossbilling' => '^1.0']]]]], JSON_THROW_ON_ERROR));
        }
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        expect($query['type'])->toBe('module');
        expect($query)->not->toHaveKey('fossbilling_version');
        $later = isset($query['cursor']);
        if ($later) {
            expect($query['cursor'])->toBe('opaque/?+cursor');
        }

        return new MockResponse(json_encode(['result' => [$entry], 'pagination' => ['has_more' => !$later, 'next_cursor' => $later ? null : 'opaque/?+cursor']], JSON_THROW_ON_ERROR));
    });
    $manager = new ExtensionManager();
    $manager->setDi($di);
    expect($manager->getExtensionList('module'))->toBe([$entry, $entry]);
    expect($manager->getExtension('Example')['releases'][0]['requires']['fossbilling'])->toBe('^1.0');
    expect($requests[0])->toStartWith('https://api.fossbilling.net/extensions/v2/extensions?');
    expect($requests[2])->toBe('https://api.fossbilling.net/extensions/v2/extensions/example');
});

it('rejects repeating cursors and never caches a partial list', function (): void {
    $entry = catalogEntry();
    $di = new Pimple\Container();
    $di['cache'] = new ArrayAdapter();
    $di['http_client'] = new MockHttpClient(new MockResponse(json_encode(['result' => [$entry], 'pagination' => ['has_more' => true, 'next_cursor' => 'repeat']], JSON_THROW_ON_ERROR)));
    $manager = new ExtensionManager();
    $manager->setDi($di);
    expect(fn () => $manager->getExtensionList())->toThrow(FOSSBilling\Core\Exception\BaseException::class);
    expect($di['cache']->getItem(catalogCacheKey('extensions', ['limit' => 100]))->isHit())->toBeFalse();
});

it('normalizes legacy module filters before sending them to v2', function (string $type, string $expected): void {
    $di = new Pimple\Container();
    $di['cache'] = new ArrayAdapter();
    $di['http_client'] = new MockHttpClient(function ($method, $url) use ($expected) {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        expect($query['type'])->toBe($expected);

        return new MockResponse(json_encode(['result' => [], 'pagination' => ['has_more' => false, 'next_cursor' => null]], JSON_THROW_ON_ERROR));
    });
    $manager = new ExtensionManager();
    $manager->setDi($di);
    expect($manager->getExtensionList($type))->toBe([]);
})->with([['mod', 'module'], ['module', 'module'], ['theme', 'theme']]);

it('bounds unique pagination cursors and preserves the stale list', function (): void {
    $entry = catalogEntry();
    $di = new Pimple\Container();
    $cache = new ArrayAdapter();
    $key = catalogCacheKey('extensions', ['limit' => 100]);
    $cache->get($key . '-stale', static fn () => [$entry]);
    $di['cache'] = $cache;
    $requests = 0;
    $di['http_client'] = new MockHttpClient(function () use (&$requests) {
        ++$requests;

        return new MockResponse(json_encode(['result' => [], 'pagination' => ['has_more' => true, 'next_cursor' => 'cursor-' . $requests]], JSON_THROW_ON_ERROR));
    });
    $manager = new ExtensionManager();
    $manager->setDi($di);
    expect($manager->getExtensionList())->toBe([$entry]);
    expect($requests)->toBe(100);
    expect($cache->getItem($key)->isHit())->toBeFalse();
    expect($cache->getItem($key . '-unavailable')->isHit())->toBeTrue();
});

it('rejects an oversized result before caching or accepting a terminal page', function (): void {
    $di = new Pimple\Container();
    $di['cache'] = new ArrayAdapter();
    $di['http_client'] = new MockHttpClient(new MockResponse(json_encode(['result' => array_fill(0, 10_001, catalogEntry()), 'pagination' => ['has_more' => false, 'next_cursor' => null]], JSON_THROW_ON_ERROR)));
    $manager = new ExtensionManager();
    $manager->setDi($di);
    expect(fn () => $manager->getExtensionList())->toThrow(FOSSBilling\Core\Exception\BaseException::class);
    expect($di['cache']->getItem(catalogCacheKey('extensions', ['limit' => 100]))->isHit())->toBeFalse();
});

it('shares one total HTTP budget across pages', function (): void {
    $di = new Pimple\Container();
    $di['cache'] = new ArrayAdapter();
    $requests = 0;
    $budgets = [];
    $di['http_client'] = new MockHttpClient(function ($method, $url, $options) use (&$requests, &$budgets) {
        $budgets[] = $options['max_duration'];
        expect($options['timeout'])->toBeLessThanOrEqual(5);
        usleep(20_000);
        ++$requests;

        return new MockResponse(json_encode(['result' => [], 'pagination' => ['has_more' => $requests < 2, 'next_cursor' => $requests < 2 ? 'next' : null]], JSON_THROW_ON_ERROR));
    });
    $manager = new ExtensionManager();
    $manager->setDi($di);
    expect($manager->getExtensionList())->toBe([]);
    expect($budgets[0])->toBeGreaterThan(0)->toBeLessThanOrEqual(10);
    expect($budgets[1])->toBeGreaterThan(0)->toBeLessThan($budgets[0]);
});

it('rejects a response that arrives after the overall deadline', function (): void {
    $di = new Pimple\Container();
    $di['cache'] = new ArrayAdapter();
    $di['http_client'] = new MockHttpClient(function () {
        // MockHttpClient does not enforce max_duration itself.
        usleep(10_050_000);

        return new MockResponse(json_encode(['result' => [catalogEntry()], 'pagination' => ['has_more' => false, 'next_cursor' => null]], JSON_THROW_ON_ERROR));
    });
    $manager = new ExtensionManager();
    $manager->setDi($di);
    expect(fn () => $manager->getExtensionList())->toThrow(FOSSBilling\Core\Exception\BaseException::class);
    expect($di['cache']->getItem(catalogCacheKey('extensions', ['limit' => 100]))->isHit())->toBeFalse();
});

it('enforces the cumulative item limit across pages', function (int $lastPageSize, bool $accepted): void {
    $di = new Pimple\Container();
    $di['cache'] = new ArrayAdapter();
    $requests = 0;
    $di['http_client'] = new MockHttpClient(function () use (&$requests, $lastPageSize) {
        ++$requests;

        return new MockResponse(json_encode(['result' => array_fill(0, $requests === 1 ? 5_000 : $lastPageSize, catalogEntry()), 'pagination' => ['has_more' => $requests === 1, 'next_cursor' => $requests === 1 ? 'next' : null]], JSON_THROW_ON_ERROR));
    });
    $manager = new ExtensionManager();
    $manager->setDi($di);
    if ($accepted) {
        expect($manager->getExtensionList())->toHaveCount(10_000);
    } else {
        expect(fn () => $manager->getExtensionList())->toThrow(FOSSBilling\Core\Exception\BaseException::class);
    }
    expect($requests)->toBe(2);
    expect($di['cache']->getItem(catalogCacheKey('extensions', ['limit' => 100]))->isHit())->toBe($accepted);
})->with([[5_000, true], [5_001, false]]);
