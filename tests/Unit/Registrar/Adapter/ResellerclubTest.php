<?php

declare(strict_types=1);

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

function createResellerclubAdapter(HttpClientInterface $httpClient): Registrar_Adapter_Resellerclub
{
    return new class(['userid' => '12345', 'api-key' => 'secret'], $httpClient) extends Registrar_Adapter_Resellerclub {
        public function __construct(array $options, private readonly HttpClientInterface $httpClient)
        {
            parent::__construct($options);
        }

        public function getHttpClient(): HttpClientInterface
        {
            return $this->httpClient;
        }
    };
}

function createResellerclubDomain(string $sld = 'example', string $tld = '.com'): Registrar_Domain
{
    return (new Registrar_Domain())->setSld($sld)->setTld($tld);
}

test('isDomaincanBeTransferred returns false for a bare "false" response instead of throwing', function (): void {
    // Regression test for #2939: the ResellerClub-style API responds to domains/validate-transfer.json
    // with a bare JSON boolean (no wrapping object). Symfony's toArray() throws "JSON content was
    // expected to decode to an array" for a non-array JSON body, so _makeRequest() must short-circuit
    // before reaching toArray() for this case.
    $httpClient = new MockHttpClient(fn (): MockResponse => new MockResponse('false'));
    $adapter = createResellerclubAdapter($httpClient);

    expect($adapter->isDomaincanBeTransferred(createResellerclubDomain()))->toBeFalse();
});

test('isDomaincanBeTransferred returns true for a bare "true" response', function (): void {
    $httpClient = new MockHttpClient(fn (): MockResponse => new MockResponse('true'));
    $adapter = createResellerclubAdapter($httpClient);

    expect($adapter->isDomaincanBeTransferred(createResellerclubDomain()))->toBeTrue();
});

test('isDomaincanBeTransferred tolerates trailing whitespace and mixed casing in the raw response', function (): void {
    $httpClient = new MockHttpClient(fn (): MockResponse => new MockResponse("True\n"));
    $adapter = createResellerclubAdapter($httpClient);

    expect($adapter->isDomaincanBeTransferred(createResellerclubDomain()))->toBeTrue();
});

test('a bare numeric response (e.g. domains/orderid) is still returned as-is', function (): void {
    $httpClient = new MockHttpClient(fn (): MockResponse => new MockResponse('98765'));
    $adapter = createResellerclubAdapter($httpClient);

    $reflection = new ReflectionMethod($adapter, '_getDomainOrderId');
    $result = $reflection->invoke($adapter, createResellerclubDomain());

    expect($result)->toBe('98765');
});

test('an error-object response still throws a Registrar_Exception', function (): void {
    $httpClient = new MockHttpClient(fn (): MockResponse => new MockResponse(json_encode([
        'status' => 'ERROR',
        'message' => 'Invalid API key',
    ])));
    $adapter = createResellerclubAdapter($httpClient);

    expect(fn () => $adapter->isDomaincanBeTransferred(createResellerclubDomain()))
        ->toThrow(Registrar_Exception::class);
});
