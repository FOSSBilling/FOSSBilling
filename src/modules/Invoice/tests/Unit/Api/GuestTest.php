<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\Api\Guest;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Service;
use Box\Mod\Invoice\ServicePayGateway;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;
use function Tests\Helpers\moduleService;

test('gets dependency injection container', function (): void {
    $api = apiEndpoint(new Guest());
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toBe($di);
});

test('gets an invoice', function (): void {
    $api = apiEndpoint(new Guest());
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('checkInvoiceAuth')
        ->atLeast()->once();
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);
    $serviceMock->shouldReceive('getDebitingInvoiceIds')
        ->atLeast()->once()
        ->andReturn([]);
    $serviceMock->shouldReceive('getRelatedInvoiceReferences')
        ->atLeast()->once()
        ->andReturn([]);

    $model = createEntity(Invoice::class);

    $di = container();
    $invoiceRepo = $di['em']->getRepository(Invoice::class);
    $invoiceRepo->shouldReceive('findByHash')
        ->atLeast()->once()
        ->andReturn($model);
    $serviceMock->shouldReceive('getInvoiceRepository')->andReturn($invoiceRepo);

    $api->setDi($di);
    $api->setService($serviceMock);
    $api->setIdentity(\Tests\Helpers\admin());

    $data['hash'] = md5('1');
    $result = $api->get($data);
    expect($result)->toBeArray();
});

test('throws exception when invoice is not found', function (): void {
    $api = apiEndpoint(new Guest());
    $model = createEntity(Invoice::class);

    $di = container();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getInvoiceRepository')->andReturn($di['em']->getRepository(Invoice::class));
    $api->setService($serviceMock);

    $api->setDi($di);
    $api->setIdentity(\Tests\Helpers\admin());

    $data['hash'] = md5('1');
    expect(fn () => $api->get($data))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Invoice was not found');
});

test('gets active gateways', function (): void {
    $api = apiEndpoint(new Guest());
    $gatewayServiceMock = Mockery::mock(ServicePayGateway::class);
    $gatewayServiceMock->shouldReceive('getActive')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(moduleService(['invoice:paygateway' => $gatewayServiceMock]));

    $api->setDi($di);

    $result = $api->gateways([]);
    expect($result)->toBeArray();
});

test('processes payment', function (): void {
    $api = apiEndpoint(new Guest());
    $data = [
        'hash' => str_repeat('a', 32),
        'gateway_id' => 1,
    ];
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('processInvoice')
        ->atLeast()->once()
        ->andReturn([]);

    $api->setService($serviceMock);

    $result = $api->payment($data);
    expect($result)->toBeArray();
});

test('throws exception when payment hash is missing', function (): void {
    $api = apiEndpoint(new Guest());
    $data = [
        'gateway_id' => '',
    ];

    expect(fn () => $api->payment($data))
        ->toThrow(FOSSBilling\Core\Exception\BaseException::class, 'Invoice hash not passed. Missing param hash');
});

test('throws exception when payment gateway id is missing', function (): void {
    $api = apiEndpoint(new Guest());
    $data = [
        'hash' => str_repeat('a', 32),
    ];

    expect(fn () => $api->payment($data))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Payment method not found. Missing param gateway_id');
});

test('gets whether add funds is enabled', function (): void {
    $api = apiEndpoint(new Guest());
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('isFundsEnabled')
        ->atLeast()->once()
        ->andReturn(false);

    $api->setService($serviceMock);

    $result = $api->funds_enabled();
    expect($result)->toBeFalse();
});

test('generates PDF', function (): void {
    $api = apiEndpoint(new Guest());
    $data = [
        'hash' => str_repeat('a', 32),
    ];
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('generatePDF')
        ->atLeast()->once();

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);

    $api->pdf($data);
});

/**
 * A rate limiter that records every consumption (and what happened around it) so tests can assert
 * ORDER, not just that a bucket was used.
 */
function invoiceRecordingLimiter(array &$log, array $exhausted = []): object
{
    return new class($log, $exhausted) {
        public function __construct(private array &$log, private readonly array $exhausted)
        {
        }

        public function consume(string $policyName, string $subject, int $tokens = 1): FOSSBilling\Core\Security\RateLimitResult
        {
            $limited = in_array($policyName, $this->exhausted, true);
            $this->log[] = $tokens === 0 ? "probe:{$policyName}" : "consume:{$policyName}";

            return new FOSSBilling\Core\Security\RateLimitResult($policyName, $limited, null, null);
        }

        public function consumeOrThrow(string $policyName, string $subject, int $tokens = 1): FOSSBilling\Core\Security\RateLimitResult
        {
            $result = $this->consume($policyName, $subject, $tokens);
            if ($result->isLimited()) {
                throw new FOSSBilling\Core\Security\RateLimitException($result);
            }

            return $result;
        }
    };
}

test('a rejected invoice read does not spend the invoice per-hash budget', function (): void {
    $log = [];
    $di = container();
    $di['rate_limiter'] = invoiceRecordingLimiter($log);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('checkInvoiceAuth')->andThrow(new FOSSBilling\Core\Exception\InformationException('Not allowed'));
    $invoiceRepo = $di['em']->getRepository(Invoice::class);
    $invoiceRepo->shouldReceive('findByHash')->andReturn(createEntity(Invoice::class));
    $serviceMock->shouldReceive('getInvoiceRepository')->andReturn($invoiceRepo);

    $api = apiEndpoint(new Guest());
    $api->setDi($di);
    $api->setService($serviceMock);

    // Whoever holds the link must not be able to lock the owner out by sending requests that are refused.
    // The probe spends nothing, so only the per-IP bucket is charged.
    expect(fn () => $api->get(['hash' => md5('1')]))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Not allowed')
        ->and($log)->toBe(['consume:invoice_get_ip', 'probe:invoice_get_hash']);
});

test('an allowed invoice read spends the per-hash budget after authorization', function (): void {
    $log = [];
    $di = container();
    $di['rate_limiter'] = invoiceRecordingLimiter($log);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('checkInvoiceAuth')->andReturnUsing(function () use (&$log): void {
        $log[] = 'auth';
    });
    $serviceMock->shouldReceive('toApiArray')->andReturn([]);
    $serviceMock->shouldReceive('getDebitingInvoiceIds')->andReturn([]);
    $serviceMock->shouldReceive('getRelatedInvoiceReferences')->andReturn([]);
    $invoiceRepo = $di['em']->getRepository(Invoice::class);
    $invoiceRepo->shouldReceive('findByHash')->andReturn(createEntity(Invoice::class));
    $serviceMock->shouldReceive('getInvoiceRepository')->andReturn($invoiceRepo);

    $api = apiEndpoint(new Guest());
    $api->setDi($di);
    $api->setService($serviceMock);
    $api->setIdentity(Tests\Helpers\admin());

    $api->get(['hash' => md5('1')]);

    expect($log)->toBe(['consume:invoice_get_ip', 'probe:invoice_get_hash', 'auth', 'consume:invoice_get_hash']);
});

test('an exhausted invoice read budget is refused before the database is touched', function (): void {
    $log = [];
    $di = container();
    $di['rate_limiter'] = invoiceRecordingLimiter($log, ['invoice_get_hash']);

    $invoiceRepo = $di['em']->getRepository(Invoice::class);
    $invoiceRepo->shouldReceive('findByHash')->never();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getInvoiceRepository')->andReturn($invoiceRepo);
    $serviceMock->shouldReceive('checkInvoiceAuth')->never();

    $api = apiEndpoint(new Guest());
    $api->setDi($di);
    $api->setService($serviceMock);

    expect(fn () => $api->get(['hash' => md5('1')]))->toThrow(FOSSBilling\Core\Security\RateLimitException::class)
        ->and($log)->toBe(['consume:invoice_get_ip', 'probe:invoice_get_hash']);
});

test('a refused PDF request does not spend the per-hash budget', function (): void {
    $log = [];
    $di = container();
    $di['rate_limiter'] = invoiceRecordingLimiter($log);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('generatePDF')->andThrow(new FOSSBilling\Core\Exception\InformationException('Not allowed'));

    $api = apiEndpoint(new Guest());
    $api->setDi($di);
    $api->setService($serviceMock);

    expect(fn () => $api->pdf(['hash' => str_repeat('a', 32)]))->toThrow(FOSSBilling\Core\Exception\InformationException::class)
        ->and($log)->toBe(['consume:invoice_pdf_ip', 'probe:invoice_pdf_hash']);
});

test('an exhausted PDF budget is refused before the PDF is rendered', function (): void {
    $log = [];
    $di = container();
    $di['rate_limiter'] = invoiceRecordingLimiter($log, ['invoice_pdf_hash']);
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('generatePDF')->never();

    $api = apiEndpoint(new Guest());
    $api->setDi($di);
    $api->setService($serviceMock);

    expect(fn () => $api->pdf(['hash' => str_repeat('a', 32)]))->toThrow(FOSSBilling\Core\Security\RateLimitException::class)
        ->and($log)->toBe(['consume:invoice_pdf_ip', 'probe:invoice_pdf_hash']);
});

test('a refused payment does not spend the per-hash budget', function (): void {
    $log = [];
    $di = container();
    $di['rate_limiter'] = invoiceRecordingLimiter($log);
    $refused = Mockery::mock(Service::class);
    $refused->shouldReceive('processInvoice')->andThrow(new FOSSBilling\Core\Exception\InformationException('Not allowed'));
    $api = apiEndpoint(new Guest());
    $api->setDi($di);
    $api->setService($refused);

    expect(fn () => $api->payment(['hash' => str_repeat('a', 32), 'gateway_id' => 1]))->toThrow(FOSSBilling\Core\Exception\InformationException::class)
        ->and($log)->toBe(['consume:invoice_payment_ip', 'probe:invoice_payment_hash']);
});

test('a successful payment spends the per-hash budget after authorization', function (): void {
    $log = [];
    $di = container();
    $di['rate_limiter'] = invoiceRecordingLimiter($log);
    $ok = Mockery::mock(Service::class);
    $ok->shouldReceive('processInvoice')->andReturnUsing(function () use (&$log): array {
        $log[] = 'process';

        return ['type' => 'html'];
    });
    $api = apiEndpoint(new Guest());
    $api->setDi($di);
    $api->setService($ok);

    $api->payment(['hash' => str_repeat('a', 32), 'gateway_id' => 1]);
    expect($log)->toBe(['consume:invoice_payment_ip', 'probe:invoice_payment_hash', 'process', 'consume:invoice_payment_hash']);
});

test('an exhausted payment budget is refused before the gateway is contacted', function (): void {
    $log = [];
    $di = container();
    $di['rate_limiter'] = invoiceRecordingLimiter($log, ['invoice_payment_hash']);
    $service = Mockery::mock(Service::class);
    $service->shouldReceive('processInvoice')->never();

    $api = apiEndpoint(new Guest());
    $api->setDi($di);
    $api->setService($service);

    expect(fn () => $api->payment(['hash' => str_repeat('a', 32), 'gateway_id' => 1]))->toThrow(FOSSBilling\Core\Security\RateLimitException::class);
});
