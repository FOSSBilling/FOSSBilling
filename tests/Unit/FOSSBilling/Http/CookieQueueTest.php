<?php

declare(strict_types=1);

use FOSSBilling\Http\CookieQueue;
use Symfony\Component\HttpFoundation\Response;

function queuedCookies(Response $response): array
{
    $queued = [];
    foreach ($response->headers->getCookies() as $cookie) {
        $queued[$cookie->getName()] = $cookie;
    }

    return $queued;
}

test('queued cookies default to SameSite Lax', function (): void {
    $cookies = new CookieQueue();
    $cookies->queue('fb_locale', 'en_US', strtotime('+1 month'), '/');

    $response = new Response();
    $cookies->applyToResponse($response);

    expect(queuedCookies($response)['fb_locale']->getSameSite())->toBe('lax');
});

test('queued cookies can pin another policy or omit the attribute', function (): void {
    $cookies = new CookieQueue();
    $cookies->queue('fb_strict', 'value', 0, '/', null, false, false, 'Strict');
    $cookies->queue('fb_plain', 'value', 0, '/', null, false, false, null);

    $response = new Response();
    $cookies->applyToResponse($response);

    $queued = queuedCookies($response);

    expect($queued['fb_strict']->getSameSite())->toBe('strict')
        ->and($queued['fb_plain']->getSameSite())->toBeNull();
});
