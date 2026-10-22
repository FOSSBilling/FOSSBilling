<?php

declare(strict_types=1);

use FOSSBilling\Http\PaymentReturn;
use FOSSBilling\Url;
use Symfony\Component\HttpFoundation\Request;

test('payment return only continues to fixed invoice destinations without setting cookies', function (string $status, ?string $hash, string $destination): void {
    $url = new Url();
    $url->setBaseUri('https://billing.example/subdir/');
    $request = Request::create('/invoice/payment-return', 'GET', [
        'status' => $status,
        'hash' => $hash,
        'restore_token' => 'untrusted-legacy-token',
        'next' => 'https://attacker.example/',
    ]);
    $response = PaymentReturn::createResponse($request, $url);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->getCookies())->toBeEmpty()
        ->and($response->headers->get('Referrer-Policy'))->toBe('no-referrer')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->getContent())->toContain('href="https://billing.example/subdir/' . $destination . '"')
        ->and($response->getContent())->toContain('window.location.replace(')
        ->and($response->getContent())->not->toContain('restore_token', 'untrusted-legacy-token', 'attacker.example');
})->with([
    ['ok', 'abc123', 'invoice/abc123?status=ok'],
    ['cancel', 'abc123', 'invoice/abc123?status=cancel'],
    ['thankyou', 'abc123', 'invoice/thank-you/abc123'],
    ['ok', null, 'invoice?status=ok'],
    ['cancel', null, 'invoice?status=cancel'],
]);

test('payment return rejects malformed destination parameters without starting a session', function (array $params): void {
    $response = PaymentReturn::createResponse(Request::create('/invoice/payment-return', 'GET', $params), new Url());

    expect($response->getStatusCode())->toBe(400)
        ->and($response->headers->getCookies())->toBeEmpty();
})->with([
    [['hash' => '../admin']],
    [['hash' => ['abc123']]],
    [['hash' => 'abc123/extra']],
    [['hash' => "abc123\n"]],
    [['status' => ['ok']]],
    [['status' => 'https://attacker.example']],
]);

test('payment return leaves ordinary routes to normal authorization', function (): void {
    expect(PaymentReturn::createResponse(Request::create('/invoice/abc123?restore_token=legacy'), new Url()))->toBeNull();
});

test('payment return supports provider POST returns without interpreting payment data', function (): void {
    $request = Request::create('/invoice/payment-return?hash=abc123&status=thankyou', 'POST', ['payment_status' => 'Completed']);
    $response = PaymentReturn::createResponse($request, new Url());

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toContain('href="invoice/thank-you/abc123"')
        ->and($response->getContent())->not->toContain('Completed');
});
