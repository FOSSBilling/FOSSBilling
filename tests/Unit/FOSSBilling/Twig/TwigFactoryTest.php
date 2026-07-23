<?php

declare(strict_types=1);

use FOSSBilling\Http\CookieNames;
use FOSSBilling\Http\CookieQueue;
use FOSSBilling\Twig\TwigFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

test('configureCsrf expires the legacy cookie with matching attributes', function (): void {
    $di = Tests\Helpers\container();
    $request = Request::create('https://localhost/');
    $request->cookies->set(CookieNames::LEGACY_CSRF, 'legacy-token');
    $di['request'] = $request;
    $di['cookie_queue'] = new CookieQueue();

    $session = Mockery::mock(FOSSBilling\Session::class);
    $session->shouldReceive('get')->once()->with('csrf_token')->andReturn('current-token');
    $di['session'] = $session;

    (new TwigFactory($di))->configureCsrf();

    $response = new Response();
    $di['cookie_queue']->applyToResponse($response);
    $cookies = [];
    foreach ($response->headers->getCookies() as $cookie) {
        $cookies[$cookie->getName()] = $cookie;
    }

    expect($cookies[CookieNames::CSRF]->getValue())->toBe('current-token')
        ->and($cookies[CookieNames::LEGACY_CSRF]->getExpiresTime())->toBeLessThan(time())
        ->and($cookies[CookieNames::LEGACY_CSRF]->isSecure())->toBeTrue()
        ->and($cookies[CookieNames::LEGACY_CSRF]->isHttpOnly())->toBeFalse()
        ->and($cookies[CookieNames::LEGACY_CSRF]->getSameSite())->toBe($cookies[CookieNames::CSRF]->getSameSite());
});
