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

test('a bare "null" response is not silently treated as a scalar', function (): void {
    // Regression guard for the scalar short-circuit above: json_decode('null') also returns
    // null with no decode error, but null isn't a usable scalar result (e.g. getDomainDetails()
    // would go on to index it like an array), so it must fall through to toArray() instead.
    $httpClient = new MockHttpClient(fn (): MockResponse => new MockResponse('null'));
    $adapter = createResellerclubAdapter($httpClient);

    expect(fn () => $adapter->isDomaincanBeTransferred(createResellerclubDomain()))
        ->toThrow(Symfony\Component\HttpClient\Exception\JsonException::class);
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

test('modifyContact creates a new contact and re-associates it with the domain order instead of calling the deprecated contacts/modify endpoint', function (): void {
    // Regression test for #2365: ResellerClub deprecated contacts/modify in December 2016 due to
    // ICANN's IRTP-C policy - existing contacts can no longer be edited in place. modifyContact must
    // instead create a new contact via contacts/add and re-point the domain order at it via
    // domains/modify-contact.
    $requests = [];
    $responses = [
        json_encode(['customerid' => '555']), // customers/details
        '998877', // contacts/add -> new contact id
        '112233', // domains/orderid
        json_encode(['status' => 'Success']), // domains/modify-contact
    ];

    $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requests, &$responses): MockResponse {
        $requests[] = $method . ' ' . parse_url($url, PHP_URL_PATH);

        return new MockResponse((string) array_shift($responses));
    });
    $adapter = createResellerclubAdapter($httpClient);

    $contact = (new Registrar_Domain_Contact())
        ->setName('Jane Doe')
        ->setEmail('jane@example.com')
        ->setAddress1('1 Example St')
        ->setCity('Example City')
        ->setZip('12345')
        ->setTelCc('1')
        ->setTel('5551234567')
        ->setCountry('US');

    $domain = createResellerclubDomain()->setContactRegistrar($contact);

    expect($adapter->modifyContact($domain))->toBeTrue();
    expect($requests)->toBe([
        'GET /api/customers/details.json',
        'POST /api/contacts/add.json',
        'GET /api/domains/orderid.json',
        'POST /api/domains/modify-contact.json',
    ]);
});
