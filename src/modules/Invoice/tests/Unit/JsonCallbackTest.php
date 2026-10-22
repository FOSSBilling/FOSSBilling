<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\Transaction;
use Box\Mod\Invoice\Repository\PayGatewayRepository;
use Box\Mod\Invoice\ServicePayGateway;
use Box\Mod\Invoice\ServiceTransaction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;
use function Tests\Helpers\setEntityId;

function jsonCallbackFixture(bool $accept = false, bool $knownGateway = true, bool $supportedAdapter = true, string $secret = 'whsec_callback_test'): ServiceTransaction
{
    $di = container();
    $em = Mockery::mock(EntityManagerInterface::class);
    $gateway = createEntity(PayGateway::class, ['id' => 1, 'gateway' => 'Stripe']);
    $repository = Mockery::mock(PayGatewayRepository::class);
    $repository->shouldReceive('find')->with(1)->andReturn($knownGateway ? $gateway : null);
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn($repository);
    if ($accept) {
        $em->shouldReceive('persist')->once()->with(Mockery::on(static function (Transaction $tx): bool {
            setEntityId($tx, 42);

            return $tx->getGateway()?->getId() === 1 && $tx->getInvoice() === null;
        }));
        $em->shouldReceive('flush')->once();
    } else {
        $em->shouldNotReceive('persist');
        $em->shouldNotReceive('flush');
        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $dispatcher->shouldNotReceive('dispatch');
        $di['event_dispatcher'] = $dispatcher;
    }
    $di['em'] = $em;
    $adapter = $supportedAdapter ? new Payment_Adapter_Stripe([
        'test_mode' => true,
        'test_api_key' => 'sk_test_dummy',
        'test_pub_key' => 'pk_test_dummy',
        'test_webhook_secret' => $secret,
    ]) : new stdClass();
    $gateways = Mockery::mock(ServicePayGateway::class);
    $gateways->shouldReceive('getPaymentAdapter')->with($gateway)->andReturn($adapter);
    $di['mod_service'] = $di->protect(static fn (): ServicePayGateway => $gateways);
    $service = new ServiceTransaction();
    $service->setDi($di);

    return $service;
}

function jsonCallbackInput(string $body = '{"id":"evt_callback","type":"charge.succeeded","object":"event"}', ?string $secret = null, ?int $timestamp = null): array
{
    $server = ['CONTENT_TYPE' => 'application/json'];
    if ($secret !== null) {
        $timestamp ??= time();
        $server['HTTP_STRIPE_SIGNATURE'] = 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }

    return ['gateway_id' => 1, 'skip_validation' => true, 'http_raw_post_data' => $body, 'server' => $server];
}

test('rejects unauthenticated JSON before transaction persistence events and logging', function (string $case): void {
    $service = jsonCallbackFixture(knownGateway: $case !== 'unknown gateway', supportedAdapter: $case !== 'unsupported adapter', secret: $case === 'missing secret' ? '' : 'whsec_callback_test');
    $data = jsonCallbackInput();
    if ($case === 'missing gateway') {
        unset($data['gateway_id']);
    } elseif ($case === 'invalid signature') {
        $data = jsonCallbackInput(secret: 'whsec_wrong');
    } elseif ($case === 'expired signature') {
        $data = jsonCallbackInput(secret: 'whsec_callback_test', timestamp: time() - 600);
    } elseif ($case === 'malformed JSON') {
        $data = jsonCallbackInput(body: '{broken', secret: 'whsec_callback_test');
    } elseif ($case === 'oversized body') {
        $data = jsonCallbackInput(body: str_repeat('x', ServiceTransaction::MAX_CALLBACK_BODY_SIZE + 1));
    } elseif ($case === 'scalar JSON') {
        $data['server'] = ['CONTENT_TYPE' => 'text/plain'];
        $data['http_raw_post_data'] = '123';
        $data['invoice_id'] = 2;
        $data['skip_validation'] = false;
    } elseif ($case === 'alternate representation') {
        $data['server'] = [];
        $data['skip_validation'] = false;
        $data['source'] = 'admin';
        $data['http_raw_post_data'] = " \n[{}]";
    }
    expect(fn () => $service->create($data))->toThrow(FOSSBilling\Exception::class);
    expect($service->getDi()['logger']->calls)->toBe([]);
})->with(['missing gateway', 'unknown gateway', 'unsupported adapter', 'missing signature', 'invalid signature', 'expired signature', 'missing secret', 'malformed JSON', 'oversized body', 'alternate representation', 'scalar JSON']);

test('accepts a signed Stripe callback without an invoice', function (): void {
    $service = jsonCallbackFixture(accept: true);
    expect($service->create(jsonCallbackInput(secret: 'whsec_callback_test')))->toBe(42);
});
