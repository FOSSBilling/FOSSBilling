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

    expect(fn (): bool => $adapter->isDomaincanBeTransferred(createResellerclubDomain()))
        ->toThrow(Symfony\Component\HttpClient\Exception\JsonException::class);
});

test('an error-object response still throws a Registrar_Exception', function (): void {
    $httpClient = new MockHttpClient(fn (): MockResponse => new MockResponse(json_encode([
        'status' => 'ERROR',
        'message' => 'Invalid API key',
    ])));
    $adapter = createResellerclubAdapter($httpClient);

    expect(fn (): bool => $adapter->isDomaincanBeTransferred(createResellerclubDomain()))
        ->toThrow(Registrar_Exception::class);
});

function createResellerclubTestContact(): Registrar_Domain_Contact
{
    return (new Registrar_Domain_Contact())
        ->setName('Jane Doe')
        ->setEmail('jane@example.com')
        ->setAddress1('1 Example St')
        ->setCity('Example City')
        ->setZip('12345')
        ->setTelCc('1')
        ->setTel('5551234567')
        ->setCountry('US');
}

test('modifyContact creates a new contact and re-associates it with the domain order instead of calling the deprecated contacts/modify endpoint', function (): void {
    // Regression test for #2365: ResellerClub deprecated contacts/modify in December 2016 due to
    // ICANN's IRTP-C policy - existing contacts can no longer be edited in place. modifyContact must
    // instead create a new contact via contacts/add and re-point the domain order at it via
    // domains/modify-contact. It must never call contacts/search or contacts/delete: that search
    // isn't scoped to this domain's order, so it could delete an unrelated active contact belonging
    // to the same customer.
    $requests = [];
    $responses = [
        json_encode(['customerid' => '555']), // customers/details
        '998877', // contacts/add -> new contact id
        '112233', // domains/orderid
        json_encode(['status' => 'Success']), // domains/modify-contact
    ];
    $lastBody = null;

    $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests, &$responses, &$lastBody): MockResponse {
        $requests[] = $method . ' ' . parse_url($url, PHP_URL_PATH);
        $lastBody = $options['body'] ?? null;

        return new MockResponse(array_shift($responses));
    });
    $adapter = createResellerclubAdapter($httpClient);
    $domain = createResellerclubDomain()->setContactRegistrar(createResellerclubTestContact());

    expect($adapter->modifyContact($domain))->toBeTrue();
    expect($requests)->toBe([
        'GET /api/customers/details.json',
        'POST /api/contacts/add.json',
        'GET /api/domains/orderid.json',
        'POST /api/domains/modify-contact.json',
    ]);

    // a plain .com domain only needs one general contact, shared across all four roles
    parse_str((string) $lastBody, $body);
    expect($body['reg-contact-id'])->toBe('998877');
    expect($body['admin-contact-id'])->toBe('998877');
    expect($body['tech-contact-id'])->toBe('998877');
    expect($body['billing-contact-id'])->toBe('998877');
});

test('modifyContact assigns per-role contact IDs for TLDs that require a dedicated contact type (.co.uk)', function (): void {
    // Regression test for the .uk/.co.uk/.org.uk handling that _getAllContacts() already applies for
    // registerDomain(): ResellerClub doesn't accept a general contact for these roles, so modifyContact
    // must go through _getAllContacts() rather than pointing every role at one general contact ID.
    $requests = [];
    $responses = [
        json_encode(['customerid' => '555']), // customers/details
        '111111', // contacts/add (general Contact) -> id
        '222222', // contacts/add (UkContact) -> id
        '333333', // domains/orderid
        json_encode(['status' => 'Success']), // domains/modify-contact
    ];
    $lastBody = null;

    $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests, &$responses, &$lastBody): MockResponse {
        $requests[] = $method . ' ' . parse_url($url, PHP_URL_PATH);
        $lastBody = $options['body'] ?? null;

        return new MockResponse(array_shift($responses));
    });
    $adapter = createResellerclubAdapter($httpClient);
    $domain = createResellerclubDomain(tld: '.co.uk')->setContactRegistrar(createResellerclubTestContact());

    expect($adapter->modifyContact($domain))->toBeTrue();

    parse_str((string) $lastBody, $body);
    expect($body['reg-contact-id'])->toBe('222222'); // the UkContact, not the general contact
    expect($body['admin-contact-id'])->toBe('-1');
    expect($body['tech-contact-id'])->toBe('-1');
    expect($body['billing-contact-id'])->toBe('-1');
});

test('modifyContact assigns per-role contact IDs for .fr, which only forces tech and billing to -1', function (): void {
    // Regression test for #77: .fr needs its own FrContact type, but - unlike .uk/.ru/.eu - the
    // registry still expects a real admin-contact-id rather than -1. See
    // https://manage.resellerclub.com/kb/answer/752 and https://manage.resellerclub.com/kb/answer/790
    $requests = [];
    $responses = [
        json_encode(['customerid' => '555']), // customers/details
        '111111', // contacts/add (general Contact) -> id
        '222222', // contacts/add (FrContact) -> id
        '333333', // domains/orderid
        json_encode(['status' => 'Success']), // domains/modify-contact
    ];
    $lastBody = null;

    $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests, &$responses, &$lastBody): MockResponse {
        $requests[] = $method . ' ' . parse_url($url, PHP_URL_PATH);
        $lastBody = $options['body'] ?? null;

        return new MockResponse(array_shift($responses));
    });
    $adapter = createResellerclubAdapter($httpClient);
    $domain = createResellerclubDomain(tld: '.fr')->setContactRegistrar(createResellerclubTestContact());

    expect($adapter->modifyContact($domain))->toBeTrue();

    parse_str((string) $lastBody, $body);
    expect($body['reg-contact-id'])->toBe('222222'); // the FrContact, not the general contact
    expect($body['admin-contact-id'])->toBe('222222'); // real contact, NOT -1
    expect($body['tech-contact-id'])->toBe('-1');
    expect($body['billing-contact-id'])->toBe('-1');
});

test('registerDomain sends the .fr registry consent attribute alongside the FrContact IDs', function (): void {
    // Regression test for #77: .fr requires accepting the registry's data-sharing terms via a tnc
    // attribute on domains/register, on top of the FrContact type/contact-id handling above. See
    // https://manage.resellerclub.com/kb/answer/752
    $requests = [];
    $responses = [
        json_encode(['status' => 'ERROR', 'message' => 'Order not found']), // domains/orderid (via _hasCompletedOrder)
        json_encode(['customerid' => '555']), // customers/details
        json_encode(['recsonpage' => 0]), // contacts/search (general Contact) -> none found
        '111111', // contacts/add (general Contact) -> id
        json_encode(['recsonpage' => 0]), // contacts/search (FrContact) -> none found
        '222222', // contacts/add (FrContact) -> id
        json_encode(['status' => 'Success']), // domains/register
    ];
    $lastBody = null;

    $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests, &$responses, &$lastBody): MockResponse {
        $requests[] = $method . ' ' . parse_url($url, PHP_URL_PATH);
        $lastBody = $options['body'] ?? null;

        return new MockResponse(array_shift($responses));
    });
    $adapter = createResellerclubAdapter($httpClient);
    $domain = createResellerclubDomain(tld: '.fr')
        ->setContactRegistrar(createResellerclubTestContact())
        ->setRegistrationPeriod(1)
        ->setNs1('ns1.example.com')
        ->setNs2('ns2.example.com');

    expect($adapter->registerDomain($domain))->toBeTrue();
    expect($requests[6])->toBe('POST /api/domains/register.json');

    parse_str((string) $lastBody, $body);
    expect($body['reg-contact-id'])->toBe('222222');
    expect($body['admin-contact-id'])->toBe('222222');
    expect($body['tech-contact-id'])->toBe('-1');
    expect($body['billing-contact-id'])->toBe('-1');
    expect($body['attr-name1'])->toBe('tnc');
    expect($body['attr-value1'])->toBe('Y');
});
