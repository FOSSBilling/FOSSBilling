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
    $service->shouldReceive('isATempEmail')
        ->once()
        ->with('guest@example.com', true)
        ->andReturnFalse();

    $di = container();
    $di['mod_config'] = $di->protect(fn (): array => [
        'block_ips' => false,
        'sfs' => true,
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
