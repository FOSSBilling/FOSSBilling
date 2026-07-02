<?php

declare(strict_types=1);

use FOSSBilling\ErrorPage;
use FOSSBilling\Http\ExceptionResponseFactory;
use Symfony\Component\HttpFoundation\Response;

test('exception response factory creates plain text responses while testing', function (): void {
    putenv('APP_ENV=test');
    $exception = new RuntimeException('Broken loader state', 500);

    $response = (new ExceptionResponseFactory())->create($exception);

    expect($response->getStatusCode())->toBe(Response::HTTP_INTERNAL_SERVER_ERROR)
        ->and($response->headers->get('Content-Type'))->toContain('text/plain')
        ->and($response->getContent())->toContain('RuntimeException: Broken loader state');
});

test('exception response factory creates FOSSBilling error pages outside testing', function (): void {
    $previousEnv = getenv('APP_ENV');
    putenv('APP_ENV=prod');

    try {
        $response = (new ExceptionResponseFactory())->create(new RuntimeException('Config missing', 3));
    } finally {
        putenv($previousEnv === false ? 'APP_ENV' : 'APP_ENV=' . $previousEnv);
    }

    expect($response->getStatusCode())->toBe(Response::HTTP_INTERNAL_SERVER_ERROR)
        ->and($response->getContent())->toContain('FOSSBilling Error')
        ->and($response->getContent())->toContain('Your Configuration is Empty');
});

test('error page can render without sending output', function (): void {
    $page = (new ErrorPage())->renderPage(404, 'Missing route');

    expect($page)->toContain('FOSSBilling Error')
        ->and($page)->toContain('Error Code: #404')
        ->and($page)->toContain('Missing route');
});
