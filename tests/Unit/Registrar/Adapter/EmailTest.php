<?php

declare(strict_types=1);

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

function createEmailAdapter(array $options, ?callable $respond = null): Registrar_Adapter_Email
{
    $httpClient = new MockHttpClient(fn (string $method, string $url): MockResponse => $respond !== null
        ? $respond($method, $url)
        : new MockResponse('{}'));

    return new class(['email' => 'registrar@example.com', ...$options], $httpClient) extends Registrar_Adapter_Email {
        public function __construct(array $options, private readonly MockHttpClient $httpClient)
        {
            parent::__construct($options);
        }

        public function getHttpClient(): Symfony\Contracts\HttpClient\HttpClientInterface
        {
            return $this->httpClient;
        }
    };
}

function createEmailDomain(): Registrar_Domain
{
    return (new Registrar_Domain())->setSld('example')->setTld('.com');
}

test('the Email adapter refuses availability checks when RDAP lookups are disabled', function (): void {
    $adapter = createEmailAdapter([]);

    $adapter->isDomainAvailable(createEmailDomain());
})->throws(Registrar_Exception::class);

function createEmailRdapResponder(callable $domainResponse): callable
{
    $bootstrap = json_encode(['version' => '1.0', 'services' => [[['com'], ['https://rdap.example.com/com/v1/']]]], JSON_THROW_ON_ERROR);

    return fn (string $method, string $url): MockResponse => str_contains($url, '/domain/')
        ? $domainResponse()
        : new MockResponse($bootstrap);
}

test('the Email adapter reports an unregistered domain as available via RDAP', function (): void {
    $adapter = createEmailAdapter(['use_rdap' => '1'], createEmailRdapResponder(fn (): MockResponse => new MockResponse('', ['http_code' => 404])));

    expect($adapter->isDomainAvailable(createEmailDomain()))->toBeTrue();
});

test('the Email adapter reports a registered domain as unavailable via RDAP', function (): void {
    $adapter = createEmailAdapter(['use_whois' => '1'], createEmailRdapResponder(fn (): MockResponse => new MockResponse('{"objectClassName":"domain"}', ['http_code' => 200])));

    expect($adapter->isDomainAvailable(createEmailDomain()))->toBeFalse();
});

test('the Email adapter throws when RDAP cannot determine availability', function (): void {
    $adapter = createEmailAdapter(['use_rdap' => '1'], createEmailRdapResponder(fn (): MockResponse => new MockResponse('', ['http_code' => 429])));

    $adapter->isDomainAvailable(createEmailDomain());
})->throws(Registrar_Exception::class);

test('the Email adapter configuration form offers an RDAP toggle', function (): void {
    $form = Registrar_Adapter_Email::getConfig();

    expect(array_keys($form['form']))->toBe(['email', 'use_rdap'])
        ->and($form['form']['use_rdap'][1]['label'])->toContain('RDAP');
});
