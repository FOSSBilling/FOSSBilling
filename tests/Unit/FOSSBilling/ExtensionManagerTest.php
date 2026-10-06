<?php

declare(strict_types=1);

use FOSSBilling\ExtensionManager;
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
    $query = [...$params, 'fossbilling_version' => FOSSBilling\Version::VERSION];

    return 'extension-manager-' . hash('xxh3', $endpoint . serialize($query));
}

function catalogManager(array $result, bool $cached = false): ExtensionManager
{
    $di = new Pimple\Container();
    $cache = new ArrayAdapter();
    if ($cached) {
        foreach (['list', 'Example'] as $endpoint) {
            $cache->get(catalogCacheKey($endpoint), static fn () => $result);
        }
    }
    $di['cache'] = $cache;
    $di['http_client'] = new MockHttpClient(new MockResponse(json_encode(['result' => $result], JSON_THROW_ON_ERROR)));
    $manager = new ExtensionManager();
    $manager->setDi($di);

    return $manager;
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
    ['URL', 'https://example.com/\" onmouseover=alert(1)', false],
]);

it('preserves valid catalog metadata and optional author links', function (?string $url): void {
    $entry = catalogEntry();
    $entry['id'] = 'Example_2-en';
    $entry['name'] = "Publisher's \"Example\" <module>";
    $entry['author']['URL'] = $url;
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
    expect(fn () => $manager->getExtension($id))->toThrow(FOSSBilling\InformationException::class);
})->with(['../Example', 'Example?query', 'Example#fragment', "Example\n", '']);

it('preserves empty lists and metadata with no author URL', function (): void {
    $entry = catalogEntry();
    unset($entry['author']['URL']);
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

        return new MockResponse(json_encode(['result' => [$entry]], JSON_THROW_ON_ERROR));
    });
    $handler = new TestHandler();
    $di['logger'] = new Logger('test', [$handler]);
    $manager = new ExtensionManager();
    $manager->setDi($di);

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
    $di = new Pimple\Container();
    $di['cache'] = new ArrayAdapter();
    $di['http_client'] = new MockHttpClient(static function () use (&$requests): never {
        ++$requests;

        throw new TransportException('Connection refused.');
    });
    $manager = new ExtensionManager();
    $manager->setDi($di);

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
    $di = new Pimple\Container();
    $di['cache'] = $cache;
    $di['http_client'] = new MockHttpClient(function () use (&$requests, $entry) {
        ++$requests;

        return new MockResponse(json_encode(['result' => [$entry]], JSON_THROW_ON_ERROR));
    });
    $manager = new ExtensionManager();
    $manager->setDi($di);

    expect($manager->getExtensionList())->toBe([$entry]);
    expect($requests)->toBe(1);
});
