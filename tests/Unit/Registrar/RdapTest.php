<?php

declare(strict_types=1);

use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RdapRequestTracker
{
    /** @var list<string> */
    public array $urls = [];
}

/**
 * @return array{0: Registrar_Rdap, 1: RdapRequestTracker}
 */
function createRdapClient(array $bootstrapServices, ?callable $respond = null): array
{
    $tracker = new RdapRequestTracker();
    $httpClient = new MockHttpClient(function (string $method, string $url) use ($tracker, $bootstrapServices, $respond): MockResponse {
        if ($url === Registrar_Rdap::BOOTSTRAP_URL) {
            $tracker->urls[] = $url;

            return new MockResponse(json_encode(['version' => '1.0', 'services' => $bootstrapServices], JSON_THROW_ON_ERROR));
        }

        $tracker->urls[] = $url;
        if ($respond !== null) {
            return $respond($method, $url);
        }

        return new MockResponse('{}');
    });

    return [new Registrar_Rdap($httpClient), $tracker];
}

test('a domain query answered with 404 means the domain is available', function (): void {
    [$rdap, $tracker] = createRdapClient([[['com'], ['https://rdap.example.com/com/v1/']]], fn (): MockResponse => new MockResponse('', ['http_code' => 404]));

    expect($rdap->isDomainAvailable('example.com'))->toBeTrue()
        ->and($tracker->urls)->toBe([
            Registrar_Rdap::BOOTSTRAP_URL,
            'https://rdap.example.com/com/v1/domain/example.com',
        ]);
});

test('a domain query answered with a success status means the domain is registered', function (int $statusCode): void {
    [$rdap] = createRdapClient([[['com'], ['https://rdap.example.com/com/v1']]], fn (): MockResponse => new MockResponse('{"objectClassName":"domain"}', ['http_code' => $statusCode]));

    expect($rdap->isDomainAvailable('example.com'))->toBeFalse();
})->with([200, 204, 299]);

test('an unexpected status code leaves availability undetermined', function (int $statusCode): void {
    [$rdap] = createRdapClient([[['com'], ['https://rdap.example.com/com/v1/']]], fn (): MockResponse => new MockResponse('', ['http_code' => $statusCode]));

    expect($rdap->isDomainAvailable('example.com'))->toBeNull();
})->with([403, 429, 500]);

test('the most specific known zone of a multi-label domain is queried', function (): void {
    [$rdap, $tracker] = createRdapClient(
        [
            [['uk'], ['https://rdap.nominet.uk/']],
            [['co.uk'], ['https://registry.nominet.uk/rdap/']],
        ],
        fn (): MockResponse => new MockResponse('', ['http_code' => 404]),
    );

    expect($rdap->isDomainAvailable('example.co.uk'))->toBeTrue()
        ->and($tracker->urls[1])->toBe('https://registry.nominet.uk/rdap/domain/example.co.uk');
});

test('domains under zones without an RDAP service are left undetermined without issuing a domain query', function (): void {
    [$rdap, $tracker] = createRdapClient([[['com'], ['https://rdap.example.com/com/v1/']]]);

    expect($rdap->isDomainAvailable('example.mars'))->toBeNull()
        ->and($tracker->urls)->toBe([Registrar_Rdap::BOOTSTRAP_URL]);
});

test('a failed bootstrap fetch disables all lookups and logs a warning', function (): void {
    $httpClient = new MockHttpClient(function (): never {
        throw new TransportException('connection refused');
    });
    $logger = new class extends Psr\Log\AbstractLogger {
        /** @var list<array{string, string}> */
        public array $records = [];

        public function log($level, string|Stringable $message, array $context = []): void
        {
            $this->records[] = [$level, (string) $message];
        }
    };
    $rdap = new Registrar_Rdap($httpClient, $logger);

    expect($rdap->isDomainAvailable('example.com'))->toBeNull()
        ->and($logger->records)->toHaveCount(1)
        ->and($logger->records[0][0])->toBe('warning')
        ->and($logger->records[0][1])->toContain('RDAP bootstrap registry');
});

test('an unparseable bootstrap response disables all lookups for the instance', function (): void {
    $httpClient = new MockHttpClient(fn (): MockResponse => new MockResponse('not-json'));
    $rdap = new Registrar_Rdap($httpClient);

    expect($rdap->isDomainAvailable('example.com'))->toBeNull();
});

test('a failing domain query falls through to the next RDAP server of the zone', function (): void {
    $handlers = [
        static function (): never {
            throw new TransportException('first server unreachable');
        },
        static fn (): MockResponse => new MockResponse('', ['http_code' => 404]),
    ];
    [$rdap] = createRdapClient(
        [[['com'], ['https://rdap-one.example.com/com/v1/', 'https://rdap-two.example.com/com/v1/']]],
        static function () use (&$handlers): MockResponse {
            return array_shift($handlers)();
        },
    );

    expect($rdap->isDomainAvailable('example.com'))->toBeTrue();
});

test('names are normalized before being queried', function (string $input, string $expectedUrl): void {
    [$rdap, $tracker] = createRdapClient([[['com'], ['https://rdap.example.com/com/v1/']]], fn (): MockResponse => new MockResponse('', ['http_code' => 404]));

    expect($rdap->isDomainAvailable($input))->toBeTrue()
        ->and($tracker->urls[1])->toBe($expectedUrl);
})->with([
    [' EXAMPLE.com ', 'https://rdap.example.com/com/v1/domain/example.com'],
    ['exämple.com', 'https://rdap.example.com/com/v1/domain/xn--exmple-cua.com'],
]);

test('inputs that cannot be a domain name are rejected without any HTTP traffic', function (string $input): void {
    [$rdap, $tracker] = createRdapClient([[['com'], ['https://rdap.example.com/com/v1/']]]);

    expect($rdap->isDomainAvailable($input))->toBeNull()
        ->and($tracker->urls)->toBe([]);
})->with(['', '.com', 'nodot']);

test('the bootstrap registry is only fetched once per instance', function (): void {
    [$rdap, $tracker] = createRdapClient([[['com'], ['https://rdap.example.com/com/v1/']]], fn (): MockResponse => new MockResponse('', ['http_code' => 404]));

    expect($rdap->isDomainAvailable('example.com'))->toBeTrue()
        ->and($rdap->isDomainAvailable('example-two.com'))->toBeTrue()
        ->and(count(array_filter($tracker->urls, fn (string $url): bool => $url === Registrar_Rdap::BOOTSTRAP_URL)))->toBe(1);
});
