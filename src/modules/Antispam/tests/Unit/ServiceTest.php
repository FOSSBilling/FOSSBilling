<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Client\Event\BeforeAdminClientUpdateEvent;
use Box\Mod\Client\Event\BeforeClientLoginEvent;
use Box\Mod\Client\Event\BeforeClientSignUpEvent;
use Box\Mod\Profile\Event\BeforeClientProfileUpdateEvent;
use Box\Mod\Staff\Event\BeforeAdminLoginEvent;
use Box\Mod\Support\Event\BeforeGuestTicketCreateEvent;
use Symfony\Component\HttpFoundation\Request;

use function Tests\Helpers\container;

test('dependency injection', function (): void {
    $service = new Box\Mod\Antispam\Service();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toEqual($di);
});

test('before client signup checks spam and disposable email without re-verifying captcha', function (): void {
    $input = [
        'ip' => '1.2.3.4',
        'email' => 'test@example.com',
        'bio' => '',
        'g-recaptcha-response' => 'already-verified-token',
    ];
    $spamCheckerService = Mockery::mock(Box\Mod\Antispam\Service::class);
    $spamCheckerService->shouldReceive('isInStopForumSpamDatabase')
        ->once()
        ->with(['ip' => $input['ip'], 'email' => $input['email']])
        ->ordered()
        ->andReturnFalse();
    $spamCheckerService->shouldReceive('isInAbuseIPDBDatabase')
        ->once()
        ->with($input['ip'])
        ->ordered()
        ->andReturnFalse();
    $spamCheckerService->shouldReceive('isATempEmail')
        ->once()
        ->with($input['email'], true)
        ->ordered()
        ->andReturnFalse();

    $service = Mockery::mock(Box\Mod\Antispam\Service::class)->makePartial();
    $service->shouldReceive('checkCaptcha')->never();
    $di = container();
    $di['mod_config'] = $di->protect(fn (): array => [
        'block_ips' => false,
        'sfs' => true,
        'abuseipdb_enabled' => true,
        'check_temp_emails' => true,
        'honeypot_enabled' => true,
        'captcha_enabled' => true,
    ]);
    $di['request'] = Request::create('http://localhost', server: ['REMOTE_ADDR' => $input['ip']]);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $spamCheckerService);
    $service->setDi($di);

    $service->onBeforeClientSignUp(new BeforeClientSignUpEvent($input));
});

test('before client profile update rejects a blocked IP', function (): void {
    $service = new Box\Mod\Antispam\Service();
    $di = container();
    $di['mod_config'] = $di->protect(fn (): array => [
        'block_ips' => true,
        'blocked_ips' => '1.2.3.4',
    ]);
    $di['request'] = Request::create('http://localhost', server: ['REMOTE_ADDR' => '1.2.3.4']);
    $service->setDi($di);

    expect(fn () => $service->onBeforeClientProfileUpdate(new BeforeClientProfileUpdateEvent(42, [])))
        ->toThrow(FOSSBilling\InformationException::class, 'Your IP address (1.2.3.4) is blocked');
});

test('before admin client update rejects a blocked IP', function (): void {
    $service = new Box\Mod\Antispam\Service();
    $di = container();
    $di['mod_config'] = $di->protect(fn (): array => [
        'block_ips' => true,
        'blocked_ips' => '1.2.3.4',
    ]);
    $di['request'] = Request::create('http://localhost', server: ['REMOTE_ADDR' => '1.2.3.4']);
    $service->setDi($di);

    expect(fn () => $service->onBeforeAdminClientUpdate(new BeforeAdminClientUpdateEvent(42, [])))
        ->toThrow(FOSSBilling\InformationException::class, 'Your IP address (1.2.3.4) is blocked');
});

test('before guest ticket creation checks blocked IP, captcha, spam, and disposable email', function (): void {
    $input = [
        'author_role' => 'guest',
        'email' => 'guest@example.com',
        'ip' => '1.2.3.4',
        'g-recaptcha-response' => 'token',
    ];
    $service = Mockery::mock(Box\Mod\Antispam\Service::class)->makePartial();
    $service->shouldReceive('checkCaptcha')->once()->with($input);
    $service->shouldReceive('isInStopForumSpamDatabase')
        ->once()
        ->with(['ip' => '1.2.3.4', 'email' => 'guest@example.com'])
        ->andReturnTrue();
    $service->shouldReceive('isInAbuseIPDBDatabase')
        ->once()
        ->with('1.2.3.4')
        ->andReturnFalse();
    $service->shouldReceive('isATempEmail')
        ->once()
        ->with('guest@example.com', true)
        ->andReturnFalse();

    $di = container();
    $di['mod_config'] = $di->protect(fn (): array => [
        'block_ips' => false,
        'sfs' => true,
        'abuseipdb_enabled' => true,
        'check_temp_emails' => true,
    ]);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $service);
    $service->setDi($di);

    $event = new BeforeGuestTicketCreateEvent($input, 'open', 'subject', 'message');
    $service->onBeforeGuestTicketCreate($event);

    expect($event->getStatus())->toBe('open')
        ->and($event->getSubject())->toBe('subject')
        ->and($event->getMessage())->toBe('message');
});

test('before guest ticket creation skips non-guest submissions', function (): void {
    $service = Mockery::mock(Box\Mod\Antispam\Service::class)->makePartial();
    $service->shouldReceive('checkCaptcha')->never();
    $service->shouldReceive('isInStopForumSpamDatabase')->never();
    $service->shouldReceive('isInAbuseIPDBDatabase')->never();
    $service->shouldReceive('isATempEmail')->never();

    $di = container();
    $di['mod_config'] = $di->protect(fn (): array => [
        'block_ips' => true,
        'blocked_ips' => '1.2.3.4',
        'sfs' => true,
        'check_temp_emails' => true,
    ]);
    $di['request'] = Request::create('http://localhost', server: ['REMOTE_ADDR' => '1.2.3.4']);
    $service->setDi($di);

    $service->onBeforeGuestTicketCreate(new BeforeGuestTicketCreateEvent(
        ['author_role' => 'client', 'client_id' => 1],
        'open',
        'subject',
        'message',
    ));
});

test('before guest ticket creation propagates blocked IP exceptions', function (): void {
    $service = Mockery::mock(Box\Mod\Antispam\Service::class)->makePartial();
    $service->shouldReceive('checkCaptcha')->never();

    $di = container();
    $di['mod_config'] = $di->protect(fn (): array => [
        'block_ips' => true,
        'blocked_ips' => '1.2.3.4',
    ]);
    $di['request'] = Request::create('http://localhost', server: ['REMOTE_ADDR' => '1.2.3.4']);
    $service->setDi($di);

    expect(fn () => $service->onBeforeGuestTicketCreate(new BeforeGuestTicketCreateEvent(
        ['author_role' => 'guest', 'email' => 'guest@example.com'],
        'open',
        'subject',
        'message',
    )))->toThrow(FOSSBilling\InformationException::class, 'Your IP address (1.2.3.4) is blocked');
});

test('client login allows an IP absent from the block list', function (): void {
    $service = new Box\Mod\Antispam\Service();
    $modConfig = [
        'block_ips' => true,
        'blocked_ips' => '1.1.1.1' . PHP_EOL . '2.2.2.2',
    ];

    $di = container();
    $di['request'] = Request::create('http://localhost', server: ['REMOTE_ADDR' => '214.1.4.99']);
    $di['mod_config'] = $di->protect(function ($modName) use ($modConfig) {
        if ($modName == 'Antispam') {
            return $modConfig;
        }
    });
    $service->setDi($di);

    expect(fn () => $service->onBeforeClientLogin(new BeforeClientLoginEvent('214.1.4.99')))
        ->not->toThrow(Throwable::class);
});

test('admin login allows an IP when blocking is disabled', function (): void {
    $service = new Box\Mod\Antispam\Service();
    $modConfig = [
        'block_ips' => false,
    ];

    $di = container();
    $di['request'] = Request::create('http://localhost', server: ['REMOTE_ADDR' => '1.2.3.4']);
    $di['mod_config'] = $di->protect(function ($modName) use ($modConfig) {
        if ($modName == 'Antispam') {
            return $modConfig;
        }
    });
    $service->setDi($di);

    expect(fn () => $service->onBeforeAdminLogin(new BeforeAdminLoginEvent('1.2.3.4')))
        ->not->toThrow(Throwable::class);
});

test('login listeners reject a blocked IP', function (string $listener, string $eventClass): void {
    $service = new Box\Mod\Antispam\Service();
    $di = container();
    $di['request'] = Request::create('http://localhost', server: ['REMOTE_ADDR' => '1.2.3.4']);
    $di['mod_config'] = $di->protect(fn (): array => [
        'block_ips' => true,
        'blocked_ips' => '1.2.3.4',
    ]);
    $service->setDi($di);

    expect(fn () => $service->{$listener}(new $eventClass('1.2.3.4')))
        ->toThrow(FOSSBilling\InformationException::class, 'Your IP address (1.2.3.4) is blocked');
})->with([
    ['onBeforeClientLogin', BeforeClientLoginEvent::class],
    ['onBeforeAdminLogin', BeforeAdminLoginEvent::class],
]);

dataset('spam responses', fn (): array => [
    [
        '{"success" : "true", "username" : {"appears" : "true" }}', 'Your username is blacklisted in the Stop Forum Spam database',
    ],
    [
        '{"success" : "true", "email" : {"appears" : "true" }}', 'Your e-mail is blacklisted in the Stop Forum Spam database',
    ],
    [
        '{"success" : "true", "ip" : {"appears" : "true" }}', 'Your IP address is blacklisted in the Stop Forum Spam database',
    ],
]);

function abuseIPDBServiceWithCachedScore(array $config, mixed $cachedScore): Box\Mod\Antispam\Service
{
    $service = new Box\Mod\Antispam\Service();
    $di = container();
    $di['mod_config'] = $di->protect(fn (): array => $config);
    $cache = Mockery::mock();
    $cache->shouldReceive('get')->andReturn($cachedScore);
    $di['cache'] = $cache;
    $service->setDi($di);

    return $service;
}

test('abuseipdb check is skipped when disabled', function (): void {
    $httpClient = Mockery::mock();
    $httpClient->shouldReceive('request')->never();

    $service = new Box\Mod\Antispam\Service();
    $di = container();
    $di['mod_config'] = $di->protect(fn (): array => ['abuseipdb_enabled' => false, 'abuseipdb_api_key' => 'key']);
    $di['http_client'] = $httpClient;
    $service->setDi($di);

    expect($service->isInAbuseIPDBDatabase('8.8.8.8'))->toBeFalse();
});

test('abuseipdb check is skipped without an api key', function (): void {
    $httpClient = Mockery::mock();
    $httpClient->shouldReceive('request')->never();

    $service = new Box\Mod\Antispam\Service();
    $di = container();
    $di['mod_config'] = $di->protect(fn (): array => ['abuseipdb_enabled' => true]);
    $di['http_client'] = $httpClient;
    $service->setDi($di);

    expect($service->isInAbuseIPDBDatabase('8.8.8.8'))->toBeFalse();
});

test('abuseipdb check skips private and invalid ips without calling the api', function (string $ip): void {
    $httpClient = Mockery::mock();
    $httpClient->shouldReceive('request')->never();

    $service = new Box\Mod\Antispam\Service();
    $di = container();
    $di['mod_config'] = $di->protect(fn (): array => ['abuseipdb_enabled' => true, 'abuseipdb_api_key' => 'key']);
    $di['http_client'] = $httpClient;
    $service->setDi($di);

    expect($service->isInAbuseIPDBDatabase($ip))->toBeFalse();
})->with([
    ['127.0.0.1'],
    ['10.0.0.1'],
    ['192.168.1.1'],
    ['not-an-ip'],
    [''],
]);

test('abuseipdb check allows ips below the threshold', function (): void {
    $service = abuseIPDBServiceWithCachedScore(
        ['abuseipdb_enabled' => true, 'abuseipdb_api_key' => 'key', 'abuseipdb_threshold' => 75, 'abuseipdb_max_age' => 30],
        10
    );

    expect($service->isInAbuseIPDBDatabase('8.8.8.8'))->toBeTrue();
});

test('abuseipdb check blocks ips at or above the threshold', function (): void {
    $service = abuseIPDBServiceWithCachedScore(
        ['abuseipdb_enabled' => true, 'abuseipdb_api_key' => 'key', 'abuseipdb_threshold' => 75, 'abuseipdb_max_age' => 30],
        100
    );

    expect(fn () => $service->isInAbuseIPDBDatabase('8.8.8.8'))
        ->toThrow(FOSSBilling\InformationException::class, 'Your IP address is blacklisted in the AbuseIPDB database');
});

test('abuseipdb check fails open when the cached score is unavailable', function (): void {
    $service = abuseIPDBServiceWithCachedScore(
        ['abuseipdb_enabled' => true, 'abuseipdb_api_key' => 'key'],
        null
    );

    expect($service->isInAbuseIPDBDatabase('8.8.8.8'))->toBeFalse();
});

test('abuseipdb check clamps an out of range threshold', function (): void {
    $service = abuseIPDBServiceWithCachedScore(
        ['abuseipdb_enabled' => true, 'abuseipdb_api_key' => 'key', 'abuseipdb_threshold' => 150],
        100
    );

    expect(fn () => $service->isInAbuseIPDBDatabase('8.8.8.8'))
        ->toThrow(FOSSBilling\InformationException::class, 'Your IP address is blacklisted in the AbuseIPDB database');
});

function abuseIPDBServiceWithHttpMock(array $config, mixed $httpCallback): Box\Mod\Antispam\Service
{
    $service = new Box\Mod\Antispam\Service();
    $di = container();
    $di['mod_config'] = $di->protect(fn (): array => $config);

    $httpClient = Mockery::mock();
    $httpCallback($httpClient);
    $di['http_client'] = $httpClient;

    $cache = Mockery::mock();
    $cache->shouldReceive('get')->andReturnUsing(function (string $key, callable $callback) {
        $item = Mockery::mock(Symfony\Contracts\Cache\ItemInterface::class);
        $item->shouldReceive('expiresAfter')->andReturnSelf();

        return $callback($item);
    });
    $di['cache'] = $cache;
    $service->setDi($di);

    return $service;
}

test('abuseipdb check fails open on http errors', function (): void {
    $service = abuseIPDBServiceWithHttpMock(
        ['abuseipdb_enabled' => true, 'abuseipdb_api_key' => 'key'],
        function ($httpClient): void {
            $httpClient->shouldReceive('request')->once()->andThrow(new RuntimeException('connection failed'));
        }
    );

    expect($service->isInAbuseIPDBDatabase('8.8.8.8'))->toBeFalse();
});

test('abuseipdb check fails open on non-200 responses', function (): void {
    $service = abuseIPDBServiceWithHttpMock(
        ['abuseipdb_enabled' => true, 'abuseipdb_api_key' => 'key'],
        function ($httpClient): void {
            $response = Mockery::mock(Symfony\Contracts\HttpClient\ResponseInterface::class);
            $response->shouldReceive('getStatusCode')->andReturn(401);
            $httpClient->shouldReceive('request')->once()->andReturn($response);
        }
    );

    expect($service->isInAbuseIPDBDatabase('8.8.8.8'))->toBeFalse();
});

test('abuseipdb check fails open on malformed responses', function (): void {
    $service = abuseIPDBServiceWithHttpMock(
        ['abuseipdb_enabled' => true, 'abuseipdb_api_key' => 'key'],
        function ($httpClient): void {
            $response = Mockery::mock(Symfony\Contracts\HttpClient\ResponseInterface::class);
            $response->shouldReceive('getStatusCode')->andReturn(200);
            $response->shouldReceive('toArray')->with(false)->andReturn(['unexpected' => 'shape']);
            $httpClient->shouldReceive('request')->once()->andReturn($response);
        }
    );

    expect($service->isInAbuseIPDBDatabase('8.8.8.8'))->toBeFalse();
});

test('abuseipdb check sends the api key and query parameters', function (): void {
    $service = abuseIPDBServiceWithHttpMock(
        ['abuseipdb_enabled' => true, 'abuseipdb_api_key' => 'test-key', 'abuseipdb_threshold' => 75, 'abuseipdb_max_age' => 30],
        function ($httpClient): void {
            $response = Mockery::mock(Symfony\Contracts\HttpClient\ResponseInterface::class);
            $response->shouldReceive('getStatusCode')->andReturn(200);
            $response->shouldReceive('toArray')->with(false)->andReturn(['data' => ['abuseConfidenceScore' => 0]]);
            $httpClient->shouldReceive('request')
                ->once()
                ->with('GET', 'https://api.abuseipdb.com/api/v2/check', Mockery::on(fn (array $options): bool => ($options['headers']['Key'] ?? null) === 'test-key'
                    && ($options['query']['ipAddress'] ?? null) === '8.8.8.8'
                    && ($options['query']['maxAgeInDays'] ?? null) === 30))
                ->andReturn($response);
        }
    );

    expect($service->isInAbuseIPDBDatabase('8.8.8.8'))->toBeTrue();
});
