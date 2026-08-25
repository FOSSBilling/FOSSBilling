<?php

declare(strict_types=1);

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

function createCustomAdapter(array $options, ?callable $respond = null): Registrar_Adapter_Custom
{
    $httpClient = new MockHttpClient(fn (string $method, string $url): MockResponse => $respond !== null
        ? $respond($method, $url)
        : new MockResponse('{}'));

    return new class($options, $httpClient) extends Registrar_Adapter_Custom {
        public function __construct(array $options, private readonly MockHttpClient $httpClient)
        {
            parent::__construct($options);
        }

        public function getHttpClient(): Symfony\Contracts\HttpClient\HttpClientInterface
        {
            return $this->httpClient;
        }

        public function requestCount(): int
        {
            return $this->httpClient->getRequestsCount();
        }
    };
}

test('the Custom adapter answers positively without registry lookups unless RDAP is enabled', function (): void {
    $adapter = createCustomAdapter([]);

    expect($adapter->isDomainAvailable((new Registrar_Domain())->setSld('example')->setTld('.com')))->toBeTrue()
        ->and($adapter->requestCount())->toBe(0);
});

test('a legacy use_whois opt-in enables RDAP lookups on the Custom adapter', function (): void {
    $adapter = createCustomAdapter(['use_whois' => '1'], fn (): MockResponse => new MockResponse('', ['http_code' => 404]));

    expect($adapter->isDomainAvailable((new Registrar_Domain())->setSld('example')->setTld('.com')))->toBeTrue();
});

test('an explicit use_rdap setting takes precedence over the legacy use_whois opt-in', function (): void {
    $adapter = createCustomAdapter(['use_whois' => '1', 'use_rdap' => '0']);

    expect($adapter->isDomainAvailable((new Registrar_Domain())->setSld('example')->setTld('.com')))->toBeTrue()
        ->and($adapter->requestCount())->toBe(0);
});

test('the Custom adapter reports a registered domain as unavailable', function (): void {
    $bootstrap = json_encode(['version' => '1.0', 'services' => [[['com'], ['https://rdap.example.com/com/v1/']]]], JSON_THROW_ON_ERROR);
    $adapter = createCustomAdapter(['use_rdap' => '1'], fn (string $method, string $url): MockResponse => str_contains($url, '/domain/')
        ? new MockResponse('{"objectClassName":"domain"}', ['http_code' => 200])
        : new MockResponse($bootstrap));

    expect($adapter->isDomainAvailable((new Registrar_Domain())->setSld('example')->setTld('.com')))->toBeFalse();
});

test('the Custom adapter falls back to a positive answer when RDAP cannot determine availability', function (): void {
    $logger = new Tests\Helpers\TestLogger();
    $bootstrap = json_encode(['version' => '1.0', 'services' => [[['com'], ['https://rdap.example.com/com/v1/']]]], JSON_THROW_ON_ERROR);
    $adapter = createCustomAdapter(['use_rdap' => '1'], fn (string $method, string $url): MockResponse => str_contains($url, '/domain/')
        ? new MockResponse('', ['http_code' => 500])
        : new MockResponse($bootstrap));
    $adapter->setLog($logger);

    $result = $adapter->isDomainAvailable((new Registrar_Domain())->setSld('example')->setTld('.com'));
    $messages = array_map(fn (array $call): string => (string) $call['params'][0], $logger->calls);

    expect($result)->toBeTrue()
        ->and(array_filter($messages, fn (string $message): bool => str_contains($message, 'RDAP request against')))->not->toBeEmpty();
});

test('the Custom adapter configuration form offers an RDAP toggle', function (): void {
    $form = Registrar_Adapter_Custom::getConfig();

    expect(array_keys($form['form']))->toBe(['use_rdap'])
        ->and($form['form']['use_rdap'][1]['label'])->toContain('RDAP');
});
