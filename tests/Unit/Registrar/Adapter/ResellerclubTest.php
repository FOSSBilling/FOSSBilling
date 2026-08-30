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

test('isDomaincanBeTransferred returns true for a quoted JSON string response', function (): void {
    // The registrar isn't consistent about formatting: a properly JSON-encoded string like
    // '"true"' must decode to the same result as the bare, unquoted 'true' response covered above,
    // not fail the strtolower(...) == 'true' comparison because the quotes were never stripped.
    $httpClient = new MockHttpClient(fn (): MockResponse => new MockResponse('"true"'));
    $adapter = createResellerclubAdapter($httpClient);

    expect($adapter->isDomaincanBeTransferred(createResellerclubDomain()))->toBeTrue();
});

test('a bare "null" response is not silently treated as a scalar', function (): void {
    // Regression guard for the scalar short-circuit above: json_decode('null') also returns
    // null with no decode error, but null isn't a usable scalar result (e.g. getDomainDetails()
    // would go on to index it like an array), so it must fall through to toArray() instead,
    // which now surfaces as a clean Registrar_Exception rather than a leaked Symfony one.
    $httpClient = new MockHttpClient(fn (): MockResponse => new MockResponse('null'));
    $adapter = createResellerclubAdapter($httpClient);

    expect(fn (): bool => $adapter->isDomaincanBeTransferred(createResellerclubDomain()))
        ->toThrow(Registrar_Exception::class);
});

test('a non-JSON response (e.g. an HTML error/rate-limit page) throws a Registrar_Exception instead of leaking a JsonException', function (): void {
    // Regression test for FOSSBILLING-N7M: a 2xx response whose body isn't valid JSON at all (an
    // HTML error page, a WAF block page, a truncated response) made toArray() throw Symfony's raw
    // JsonException uncaught - only the 4xx/5xx and bare-scalar cases were handled. See #4220 for
    // the same class of issue fixed elsewhere (PSL download).
    $httpClient = new MockHttpClient(fn (): MockResponse => new MockResponse('<html>Rate limit exceeded</html>'));
    $adapter = createResellerclubAdapter($httpClient);

    expect(fn (): bool => $adapter->isDomaincanBeTransferred(createResellerclubDomain()))
        ->toThrow(Registrar_Exception::class);
});

test('a non-JSON response never logs the request URL, since it carries auth-userid and api-key in the query string', function (): void {
    // CodeRabbit finding on #4254: Symfony's DecodingExceptionInterface message embeds the full
    // request URL, which for a GET request (as used here) includes ResellerClub credentials in the
    // query string - the error handler must log a fixed message instead of the raw exception message.
    // Scoped to error-level messages: the pre-existing debug-level "API REQUEST" log already includes
    // the full URL for every call, which is a separate, pre-existing issue outside this fix's scope.
    $httpClient = new MockHttpClient(fn (): MockResponse => new MockResponse('<html>Rate limit exceeded</html>'));
    $adapter = createResellerclubAdapter($httpClient);
    $logger = new Tests\Helpers\TestLogger();
    $adapter->setLog($logger);

    expect(fn (): bool => $adapter->isDomaincanBeTransferred(createResellerclubDomain()))
        ->toThrow(Registrar_Exception::class);

    $errorMessages = array_map(
        fn (array $call): string => (string) $call['params'][0],
        array_filter($logger->calls, fn (array $call): bool => $call['method'] === 'error'),
    );
    $logged = implode("\n", $errorMessages);
    expect($logged)->not->toContain('secret') // the api-key configured in createResellerclubAdapter()
        ->and($logged)->not->toContain('auth-userid')
        ->and($logged)->not->toContain('api-key');
});

test('a GET request never logs its auth-userid/api-key in the debug-level "API REQUEST" log', function (): void {
    // includeAuthorizationParams() appends auth-userid/api-key to every request, and GET requests
    // put them straight into the query string. _makeRequest() logs that query string at debug
    // level on every single call (unlike the narrower error-path leak fixed for #4254, which only
    // covered the JSON-decoding-failure log line), so the debug log must redact both params too.
    $httpClient = new MockHttpClient(fn (): MockResponse => new MockResponse('true'));
    $adapter = createResellerclubAdapter($httpClient);
    $logger = new Tests\Helpers\TestLogger();
    $adapter->setLog($logger);

    $adapter->isDomaincanBeTransferred(createResellerclubDomain());

    $messages = array_map(fn (array $call): string => (string) $call['params'][0], $logger->calls);
    $debugMessages = array_filter($messages, fn (string $message): bool => str_starts_with($message, 'API REQUEST: '));

    expect($debugMessages)->not->toBeEmpty();
    foreach ($debugMessages as $message) {
        expect($message)->not->toContain('secret') // the api-key configured in createResellerclubAdapter()
            ->and($message)->not->toContain('auth-userid=12345')
            ->and($message)->toContain('api-key=');
    }
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
    $bodies = [];
    $responses = [
        json_encode(['customerid' => '555']), // customers/details
        '111111', // contacts/add (general Contact) -> id
        '222222', // contacts/add (FrContact) -> id
        '333333', // domains/orderid
        json_encode(['status' => 'Success']), // domains/modify-contact
    ];

    $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests, &$responses, &$bodies): MockResponse {
        $requests[] = $method . ' ' . parse_url($url, PHP_URL_PATH);
        $bodies[] = $options['body'] ?? null;

        return new MockResponse(array_shift($responses));
    });
    $adapter = createResellerclubAdapter($httpClient);
    $domain = createResellerclubDomain(tld: '.fr')->setContactRegistrar(createResellerclubTestContact());

    expect($adapter->modifyContact($domain))->toBeTrue();

    // the second contacts/add call must actually be creating the FrContact, not just landing on the
    // right id by coincidence
    parse_str((string) $bodies[2], $frContactBody);
    expect($frContactBody['type'])->toBe('FrContact');

    parse_str((string) end($bodies), $body);
    expect($body['reg-contact-id'])->toBe('222222'); // the FrContact, not the general contact
    expect($body['admin-contact-id'])->toBe('222222'); // real contact, NOT -1
    expect($body['tech-contact-id'])->toBe('-1');
    expect($body['billing-contact-id'])->toBe('-1');
});

test('renewDomain fetches fresh domain details to fill in exp-date when it is not already cached', function (): void {
    // Regression test for #4229: FOSSBilling only learns a domain's expiration time via a prior
    // WHOIS sync. When that sync never completed (e.g. it ran too soon after registration), the
    // domain reaches renewDomain() with no expiration time, and ResellerClub rejects the renewal
    // outright with "Required parameter missing: exp-date" - which then also prevents the WHOIS
    // sync that would fix it for next time, since that only runs after a successful renewal. The
    // adapter must fetch the expiry itself instead of sending an incomplete request.
    $requests = [];
    $bodies = [];
    $responses = [
        '112233', // domains/orderid (via getDomainDetails)
        json_encode([ // domains/details
            'creationtime' => 1577836800,
            'endtime' => 1893456000,
            'domsecret' => 'epp-code',
            'isprivacyprotected' => 'false',
            'admincontact' => [
                'contactid' => '998877',
                'name' => 'Jane Doe',
                'emailaddr' => 'jane@example.com',
                'company' => '',
                'telno' => '5551234567',
                'telnocc' => '1',
                'address1' => '1 Example St',
                'city' => 'Example City',
                'country' => 'US',
                'state' => '',
                'zip' => '12345',
            ],
        ]),
        '112233', // domains/orderid (for the renew request itself)
        json_encode(['actionstatus' => 'Success']), // domains/renew
    ];

    $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests, &$responses, &$bodies): MockResponse {
        $requests[] = $method . ' ' . parse_url($url, PHP_URL_PATH);
        $bodies[] = $options['body'] ?? null;

        return new MockResponse(array_shift($responses));
    });
    $adapter = createResellerclubAdapter($httpClient);
    $domain = createResellerclubDomain()->setRegistrationPeriod(1);

    expect($adapter->renewDomain($domain))->toBeTrue();
    expect($requests)->toBe([
        'GET /api/domains/orderid.json',
        'GET /api/domains/details.json',
        'GET /api/domains/orderid.json',
        'POST /api/domains/renew.json',
    ]);

    parse_str((string) end($bodies), $body);
    expect($body['exp-date'])->toBe('1893456000');
    expect($domain->getExpirationTime())->toBe(1893456000);
});

test('renewDomain does not re-fetch domain details when an expiration time is already known', function (): void {
    $requests = [];
    $bodies = [];
    $responses = [
        '112233', // domains/orderid (for the renew request)
        json_encode(['actionstatus' => 'Success']), // domains/renew
    ];

    $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests, &$responses, &$bodies): MockResponse {
        $requests[] = $method . ' ' . parse_url($url, PHP_URL_PATH);
        $bodies[] = $options['body'] ?? null;

        return new MockResponse(array_shift($responses));
    });
    $adapter = createResellerclubAdapter($httpClient);
    $domain = createResellerclubDomain()->setRegistrationPeriod(1)->setExpirationTime(1893456000);

    expect($adapter->renewDomain($domain))->toBeTrue();
    expect($requests)->toBe([
        'GET /api/domains/orderid.json',
        'POST /api/domains/renew.json',
    ]);

    parse_str((string) end($bodies), $body);
    expect($body['exp-date'])->toBe('1893456000');
});

test('registerDomain sends the .fr registry consent attribute alongside the FrContact IDs', function (): void {
    // Regression test for #77: .fr requires accepting the registry's data-sharing terms via a tnc
    // attribute on domains/register, on top of the FrContact type/contact-id handling above. See
    // https://manage.resellerclub.com/kb/answer/752
    $requests = [];
    $bodies = [];
    $responses = [
        json_encode(['status' => 'ERROR', 'message' => 'Order not found']), // domains/orderid (via _hasCompletedOrder)
        json_encode(['customerid' => '555']), // customers/details
        json_encode(['recsonpage' => 0]), // contacts/search (general Contact) -> none found
        '111111', // contacts/add (general Contact) -> id
        json_encode(['recsonpage' => 0]), // contacts/search (FrContact) -> none found
        '222222', // contacts/add (FrContact) -> id
        json_encode(['status' => 'Success']), // domains/register
    ];

    $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests, &$responses, &$bodies): MockResponse {
        $requests[] = $method . ' ' . parse_url($url, PHP_URL_PATH);
        $bodies[] = $options['body'] ?? null;

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

    // the second contacts/add call must actually be creating the FrContact, not just landing on the
    // right id by coincidence
    parse_str((string) $bodies[5], $frContactBody);
    expect($frContactBody['type'])->toBe('FrContact');

    parse_str((string) end($bodies), $body);
    expect($body['reg-contact-id'])->toBe('222222');
    expect($body['admin-contact-id'])->toBe('222222');
    expect($body['tech-contact-id'])->toBe('-1');
    expect($body['billing-contact-id'])->toBe('-1');
    expect($body['attr-name1'])->toBe('tnc');
    expect($body['attr-value1'])->toBe('Y');
});

test('registerDomain applies the .fr handling for an uppercase .FR tld', function (): void {
    // Regression test for a case-sensitivity gap: the TLD is normally lowercased before it ever
    // reaches this adapter (Servicedomain\Service::normalizeTld()), but a legacy or manually
    // entered uppercase TLD must still trigger the same FrContact/tnc handling as '.fr'.
    $responses = [
        json_encode(['status' => 'ERROR', 'message' => 'Order not found']), // domains/orderid (via _hasCompletedOrder)
        json_encode(['customerid' => '555']), // customers/details
        json_encode(['recsonpage' => 0]), // contacts/search (general Contact) -> none found
        '111111', // contacts/add (general Contact) -> id
        json_encode(['recsonpage' => 0]), // contacts/search (FrContact) -> none found
        '222222', // contacts/add (FrContact) -> id
        json_encode(['status' => 'Success']), // domains/register
    ];
    $bodies = [];

    $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses, &$bodies): MockResponse {
        $bodies[] = $options['body'] ?? null;

        return new MockResponse(array_shift($responses));
    });
    $adapter = createResellerclubAdapter($httpClient);
    $domain = createResellerclubDomain(tld: '.FR')
        ->setContactRegistrar(createResellerclubTestContact())
        ->setRegistrationPeriod(1)
        ->setNs1('ns1.example.com')
        ->setNs2('ns2.example.com');

    expect($adapter->registerDomain($domain))->toBeTrue();

    parse_str((string) $bodies[5], $frContactBody);
    expect($frContactBody['type'])->toBe('FrContact');

    parse_str((string) end($bodies), $body);
    expect($body['tech-contact-id'])->toBe('-1');
    expect($body['billing-contact-id'])->toBe('-1');
    expect($body['attr-name1'])->toBe('tnc');
    expect($body['attr-value1'])->toBe('Y');
});
