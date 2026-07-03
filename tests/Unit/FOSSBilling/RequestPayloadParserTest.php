<?php

declare(strict_types=1);

use FOSSBilling\Http\RequestPayloadParser;
use Symfony\Component\HttpFoundation\Request;

test('parser returns form request parameters', function (): void {
    $request = Request::create('/api/client/profile/update', 'POST', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ]);

    $payload = (new RequestPayloadParser())->all($request);

    expect($payload)->toBe([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ]);
});

test('parser returns JSON request parameters', function (): void {
    $request = Request::create(
        '/api/client/profile/update',
        'POST',
        server: ['CONTENT_TYPE' => 'application/json'],
        content: '{"first_name":"Ada","flags":{"newsletter":true}}',
    );

    $payload = (new RequestPayloadParser())->all($request);

    expect($payload)->toBe([
        'first_name' => 'Ada',
        'flags' => ['newsletter' => true],
    ]);
});

test('parser returns an empty payload for empty request bodies', function (): void {
    $request = Request::create('/api/client/profile/update', 'POST');

    expect((new RequestPayloadParser())->all($request))->toBe([]);
});

test('parser wraps malformed JSON as a FOSSBilling API exception', function (): void {
    $request = Request::create(
        '/api/client/profile/update',
        'POST',
        server: ['CONTENT_TYPE' => 'application/json'],
        content: '{"first_name"',
    );

    expect(fn (): array => (new RequestPayloadParser())->all($request))
        ->toThrow(FOSSBilling\Exception::class, 'Malformed JSON input: Syntax error');
});
