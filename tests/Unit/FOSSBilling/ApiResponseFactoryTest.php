<?php

declare(strict_types=1);

use FOSSBilling\Http\ApiResponseFactory;
use FOSSBilling\Security\RateLimitException;
use FOSSBilling\Security\RateLimitResult;
use Symfony\Component\HttpFoundation\Response;

test('API response factory creates the standard success envelope', function (): void {
    $response = (new ApiResponseFactory())->create(['id' => 42]);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($response->headers->hasCacheControlDirective('no-cache'))->toBeTrue()
        ->and($response->headers->hasCacheControlDirective('must-revalidate'))->toBeTrue()
        ->and($response->headers->get('Expires'))->toBe('Mon, 26 Jul 1997 05:00:00 GMT')
        ->and(json_decode((string) $response->getContent(), true))->toBe([
            'result' => ['id' => 42],
            'error' => null,
        ]);
});

test('API response factory maps authentication errors to unauthorized responses', function (): void {
    $response = (new ApiResponseFactory())->create(null, new FOSSBilling\Exception('Authentication Failed', null, 201));

    expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and(json_decode((string) $response->getContent(), true))->toBe([
            'result' => null,
            'error' => [
                'message' => 'Authentication Failed',
                'code' => 201,
            ],
        ]);
});

test('API response factory preserves legacy ok status for unmapped application errors', function (): void {
    $response = (new ApiResponseFactory())->create(null, new FOSSBilling\Exception('Unexpected API error', null, 9999));

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);
});

test('API response factory adds retry after header for rate limit errors', function (): void {
    $retryAfter = new DateTimeImmutable('+60 seconds');
    $exception = new RateLimitException(new RateLimitResult('api_guest', true, 10, 0, $retryAfter));

    $response = (new ApiResponseFactory())->create(null, $exception);

    expect($response->getStatusCode())->toBe(Response::HTTP_TOO_MANY_REQUESTS)
        ->and((int) $response->headers->get('Retry-After'))->toBeGreaterThan(0);
});
