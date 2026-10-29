<?php

declare(strict_types=1);

use FOSSBilling\Core\Security\Credential;
use FOSSBilling\Core\System\Config;
use FOSSBilling\Core\Utils\Network;
use FOSSBilling\Core\Validation\EmailValidator;
use Pimple\Container;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

test('validate and sanitize email returns the address unescaped', function (): void {
    expect(EmailValidator::validateAndSanitizeEmail('foo&bar@example.com', true, false))->toBe('foo&bar@example.com');
});

test('external IP lookup skips private responses and trims a public response', function (): void {
    $httpClient = new MockHttpClient([
        new MockResponse('192.168.1.10'),
        new MockResponse("8.8.8.8\n"),
    ]);
    $di = new Container();
    $di['http_client'] = $httpClient;
    $di['logger'] = new NullLogger();

    $network = new Network();
    $network->setDi($di);

    expect($network->getExternalIP())->toBe('8.8.8.8')
        ->and($httpClient->getRequestsCount())->toBe(2);
});

test('callback signature matches the HMAC of the gateway and invoice pair', function (): void {
    $previousSalt = Config::getProperty('info.salt');
    Config::setProperty('info.salt', 'test-salt', false);

    try {
        $expected = hash_hmac('sha256', '2|16', 'test-salt');

        expect(Credential::signCallbackParams(2, 16))->toBe($expected)
            ->and(Credential::signCallbackParams('2', '16'))->toBe($expected);
    } finally {
        Config::setProperty('info.salt', $previousSalt, false);
    }
});

test('callback signature verification accepts matching signatures only', function (): void {
    $previousSalt = Config::getProperty('info.salt');
    Config::setProperty('info.salt', 'test-salt', false);

    try {
        $sig = Credential::signCallbackParams(2, 16);

        expect(Credential::verifyCallbackSignature(2, 16, $sig))->toBeTrue()
            ->and(Credential::verifyCallbackSignature(2, 99, $sig))->toBeFalse()
            ->and(Credential::verifyCallbackSignature(7, 16, $sig))->toBeFalse()
            ->and(Credential::verifyCallbackSignature(2, 16, 'tampered'))->toBeFalse()
            ->and(Credential::verifyCallbackSignature(2, 16, ''))->toBeFalse()
            ->and(Credential::verifyCallbackSignature(2, 16, null))->toBeFalse()
            ->and(Credential::verifyCallbackSignature(2, 16, 12345))->toBeFalse();
    } finally {
        Config::setProperty('info.salt', $previousSalt, false);
    }
});
