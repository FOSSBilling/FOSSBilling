<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\Subscription;
use Box\Mod\Invoice\Entity\Transaction;
use Box\Mod\Invoice\Repository\InvoiceRepository;
use Box\Mod\Invoice\Repository\PayGatewayRepository;
use Box\Mod\Invoice\Repository\SubscriptionRepository;
use Box\Mod\Invoice\Repository\TransactionRepository;
use Box\Mod\Invoice\ServicePayGateway;
use Doctrine\ORM\EntityManagerInterface;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

function payGatewayService(?PayGatewayRepository $repo = null, ?EntityManagerInterface $em = null): ServicePayGateway
{
    $service = new ServicePayGateway();
    $di = container();
    $em ??= Mockery::mock(EntityManagerInterface::class);
    $repo ??= Mockery::mock(PayGatewayRepository::class);
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn($repo);
    $di['em'] = $em;
    $service->setDi($di);

    return $service;
}

test('gets dependency injection container', function (): void {
    $repo = Mockery::mock(PayGatewayRepository::class);
    $service = payGatewayService($repo);

    expect($service->getDi())->toBeInstanceOf(Pimple\Container::class)
        ->and($service->getPayGatewayRepository())->toBe($repo);
});

test('gets pairs', function (): void {
    $expected = [
        1 => 'Custom',
    ];

    $queryResult = [
        [
            'id' => 1,
            'name' => 'Custom',
        ],
    ];

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('fetchAllAssociative')
        ->atLeast()->once()
        ->andReturn($queryResult);

    $service = payGatewayService();
    $service->getDi()['em']->shouldReceive('getConnection')->andReturn($connection);

    $result = $service->getPairs();
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('gets available gateways', function (): void {
    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('fetchAllAssociative')
        ->atLeast()->once()
        ->andReturn([]);

    $service = payGatewayService();
    $service->getDi()['em']->shouldReceive('getConnection')->andReturn($connection);

    $result = $service->getAvailable();
    expect($result)->toBeArray();
});

test('installs a pay gateway', function (): void {
    $code = 'PP';

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial();
    $serviceMock->shouldReceive('getAvailable')
        ->atLeast()->once()
        ->andReturn([$code]);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('persist')->atLeast()->once();
    $em->shouldReceive('flush')->atLeast()->once();
    $repo = Mockery::mock(PayGatewayRepository::class);
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn($repo);

    $di = container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    $result = $serviceMock->install($code);
    expect($result)->toBeBool()->toBeTrue();
});

test('throws exception when installing unavailable gateway', function (): void {
    $code = 'PP';

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial();
    $serviceMock->shouldReceive('getAvailable')
        ->atLeast()->once()
        ->andReturn([]);

    $service = payGatewayService();
    $serviceMock->setDi($service->getDi());

    expect(fn () => $serviceMock->install($code))
        ->toThrow(FOSSBilling\Exception\BaseException::class, 'Payment gateway is not available for installation.');
});

test('converts to api array', function (): void {
    $payGateway = createEntity(PayGateway::class, [
        'id' => 1,
        'name' => 'Custom',
        'gateway' => 'Custom',
        'acceptedCurrencies' => null,
        'enabled' => true,
        'allowSingle' => true,
        'allowRecurrent' => false,
        'testMode' => false,
        'config' => null,
    ]);

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial();
    $serviceMock->shouldReceive('getAdapterConfig')
        ->atLeast()->once()
        ->andReturn([]);
    $serviceMock->shouldReceive('getAcceptedCurrencies')->andReturn([]);
    $serviceMock->shouldReceive('getFormElements')->andReturn([]);
    $serviceMock->shouldReceive('getDescription')->andReturn(null);

    $service = payGatewayService();
    $serviceMock->setDi($service->getDi());

    $result = $serviceMock->toApiArray($payGateway, false, \Tests\Helpers\admin());
    expect($result)->toBeArray();
    expect($result['id'])->toBe(1);
    expect($result['code'])->toBe('Custom');
    expect($result['title'])->toBe('Custom');
    expect($result['allow_single'])->toBeTrue();
    expect($result['allow_recurrent'])->toBeFalse();
    expect($result['enabled'])->toBeTrue();
    expect($result['test_mode'])->toBeFalse();
    expect($result['supports_one_time_payments'])->toBeFalse();
    expect($result['supports_subscriptions'])->toBeFalse();
    expect($result['callback'])->toBe(SYSTEM_URL . 'ipn.php?gateway_id=1');
});

test('copies a gateway', function (): void {
    $payGateway = createEntity(PayGateway::class, [
        'id' => 1,
        'name' => 'Custom',
        'gateway' => 'Custom',
        'acceptedCurrencies' => null,
        'testMode' => false,
        'config' => null,
    ]);

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial();
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('persist')->once();
    $em->shouldReceive('flush')->once();
    $repo = Mockery::mock(PayGatewayRepository::class);
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn($repo);

    $di = container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $result = $serviceMock->copy($payGateway);
    expect($result)->toBeInt();
});

test('updates a gateway', function (): void {
    $payGateway = createEntity(PayGateway::class, [
        'id' => 1,
        'name' => 'Custom',
        'gateway' => 'Custom',
        'config' => null,
    ]);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('flush')->atLeast()->once();
    $repo = Mockery::mock(PayGatewayRepository::class);
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn($repo);

    $service = new ServicePayGateway();
    $di = container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $data = [
        'title' => 'New title',
        'enabled' => true,
        'allow_single' => true,
        'allow_recurrent' => true,
        'test_mode' => false,
    ];
    $result = $service->update($payGateway, $data);
    expect($result)->toBeTrue();
    expect($payGateway->getName())->toBe('New title');
    expect($payGateway->isEnabled())->toBeTrue();
});

test('converts to api array masks secrets for an admin', function (): void {
    $payGateway = createEntity(PayGateway::class, [
        'id' => 1,
        'name' => 'Stripe',
        'gateway' => 'Stripe',
        'acceptedCurrencies' => json_encode(['USD']),
        'enabled' => true,
        'allowSingle' => true,
        'allowRecurrent' => true,
        'testMode' => false,
        'config' => json_encode([
            'pub_key' => 'pk_live_visible',
            'api_key' => 'sk_live_secret',
            'webhook_secret' => 'whsec_secret',
        ]),
    ]);

    $service = payGatewayService();

    $result = $service->toApiArray($payGateway, false, \Tests\Helpers\admin());

    expect($result['secret_fields'])->toContain('api_key');
    expect($result['secret_fields'])->toContain('webhook_secret');
    expect($result['secret_fields'])->toContain('test_api_key');
    expect($result['secret_fields'])->toContain('test_webhook_secret');
    expect($result['secret_fields'])->not->toContain('pub_key');
    expect($result['config']['pub_key'])->toBe('pk_live_visible');
    expect($result['config']['api_key'])->toBeNull();
    expect($result['config']['api_key_set'])->toBeTrue();
    expect($result['config']['webhook_secret'])->toBeNull();
    expect($result['config']['webhook_secret_set'])->toBeTrue();
    expect($result['config']['test_api_key_set'])->toBeFalse();
});

test('update keeps the existing secret gateway value when the incoming value is blank', function (): void {
    $payGateway = createEntity(PayGateway::class, [
        'id' => 1,
        'name' => 'Stripe',
        'gateway' => 'Stripe',
        'enabled' => false,
        'config' => json_encode(['api_key' => 'sk_live_existing', 'pub_key' => 'pk_live_existing']),
    ]);

    $em = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $repo = Mockery::mock(PayGatewayRepository::class);
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn($repo);

    $service = new ServicePayGateway();
    $di = container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['loggedin_admin'] = \Tests\Helpers\admin(['id' => 7]);
    $service->setDi($di);

    $result = $service->update($payGateway, [
        'config' => [
            'api_key' => ServicePayGateway::CREDENTIAL_KEEP_SENTINEL,
            'pub_key' => 'pk_live_new',
        ],
    ]);

    expect($result)->toBeTrue();
    $config = json_decode((string) $payGateway->getConfig(), true);
    expect($config['api_key'])->toBe('sk_live_existing');
    expect($config['pub_key'])->toBe('pk_live_new');
});

test('update rotates a secret gateway value when a new value is submitted', function (): void {
    $payGateway = createEntity(PayGateway::class, [
        'id' => 1,
        'name' => 'Stripe',
        'gateway' => 'Stripe',
        'enabled' => false,
        'config' => json_encode(['api_key' => 'sk_live_old']),
    ]);

    $em = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $repo = Mockery::mock(PayGatewayRepository::class);
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn($repo);

    $service = new ServicePayGateway();
    $di = container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['loggedin_admin'] = \Tests\Helpers\admin(['id' => 7]);
    $service->setDi($di);

    $result = $service->update($payGateway, ['config' => ['api_key' => 'sk_live_new']]);

    expect($result)->toBeTrue();
    $config = json_decode((string) $payGateway->getConfig(), true);
    expect($config['api_key'])->toBe('sk_live_new');
});

test('deletes a gateway', function (): void {
    $payGateway = createEntity(PayGateway::class, ['id' => 7]);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('remove')->atLeast()->once();
    $em->shouldReceive('flush')->atLeast()->once();
    $repo = Mockery::mock(PayGatewayRepository::class);
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn($repo);

    $invoiceRepo = Mockery::mock(InvoiceRepository::class);
    $invoiceRepo->shouldReceive('existsByGatewayId')->with(7)->andReturn(false);
    $em->shouldReceive('getRepository')->with(Invoice::class)->andReturn($invoiceRepo);

    $subscriptionRepo = Mockery::mock(SubscriptionRepository::class);
    $subscriptionRepo->shouldReceive('existsByGatewayId')->with(7)->andReturn(false);
    $em->shouldReceive('getRepository')->with(Subscription::class)->andReturn($subscriptionRepo);

    $transactionRepo = Mockery::mock(TransactionRepository::class);
    $transactionRepo->shouldReceive('existsByGatewayId')->with(7)->andReturn(false);
    $em->shouldReceive('getRepository')->with(Transaction::class)->andReturn($transactionRepo);

    $service = new ServicePayGateway();
    $di = container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->delete($payGateway);
    expect($result)->toBeTrue();
});

test('refuses to delete a gateway with existing invoices', function (): void {
    $payGateway = createEntity(PayGateway::class, ['id' => 7]);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('remove')->never();
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn(Mockery::mock(PayGatewayRepository::class));

    $invoiceRepo = Mockery::mock(InvoiceRepository::class);
    $invoiceRepo->shouldReceive('existsByGatewayId')->with(7)->andReturn(true);
    $em->shouldReceive('getRepository')->with(Invoice::class)->andReturn($invoiceRepo);

    $service = new ServicePayGateway();
    $di = container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    expect(fn () => $service->delete($payGateway))
        ->toThrow(FOSSBilling\Exception\InformationException::class, 'Cannot remove payment gateway with existing invoices');
});

test('refuses to delete a gateway with existing subscriptions', function (): void {
    $payGateway = createEntity(PayGateway::class, ['id' => 7]);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('remove')->never();
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn(Mockery::mock(PayGatewayRepository::class));

    $invoiceRepo = Mockery::mock(InvoiceRepository::class);
    $invoiceRepo->shouldReceive('existsByGatewayId')->with(7)->andReturn(false);
    $em->shouldReceive('getRepository')->with(Invoice::class)->andReturn($invoiceRepo);

    $subscriptionRepo = Mockery::mock(SubscriptionRepository::class);
    $subscriptionRepo->shouldReceive('existsByGatewayId')->with(7)->andReturn(true);
    $em->shouldReceive('getRepository')->with(Subscription::class)->andReturn($subscriptionRepo);

    $service = new ServicePayGateway();
    $di = container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    expect(fn () => $service->delete($payGateway))
        ->toThrow(FOSSBilling\Exception\InformationException::class, 'Cannot remove payment gateway with existing subscriptions');
});

test('refuses to delete a gateway with existing transactions', function (): void {
    $payGateway = createEntity(PayGateway::class, ['id' => 7]);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('remove')->never();
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn(Mockery::mock(PayGatewayRepository::class));

    $invoiceRepo = Mockery::mock(InvoiceRepository::class);
    $invoiceRepo->shouldReceive('existsByGatewayId')->with(7)->andReturn(false);
    $em->shouldReceive('getRepository')->with(Invoice::class)->andReturn($invoiceRepo);

    $subscriptionRepo = Mockery::mock(SubscriptionRepository::class);
    $subscriptionRepo->shouldReceive('existsByGatewayId')->with(7)->andReturn(false);
    $em->shouldReceive('getRepository')->with(Subscription::class)->andReturn($subscriptionRepo);

    $transactionRepo = Mockery::mock(TransactionRepository::class);
    $transactionRepo->shouldReceive('existsByGatewayId')->with(7)->andReturn(true);
    $em->shouldReceive('getRepository')->with(Transaction::class)->andReturn($transactionRepo);

    $service = new ServicePayGateway();
    $di = container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    expect(fn () => $service->delete($payGateway))
        ->toThrow(FOSSBilling\Exception\InformationException::class, 'Cannot remove payment gateway with existing transactions');
});

test('gets active gateways as pairs', function (): void {
    $payGateway = createEntity(PayGateway::class, ['id' => 5, 'name' => 'Custom']);

    $repo = Mockery::mock(PayGatewayRepository::class);
    $repo->shouldReceive('findEnabledOrderedByIdDesc')
        ->once()
        ->andReturn([$payGateway]);

    $service = payGatewayService($repo);

    $result = $service->getActive(['format' => 'pairs']);
    expect($result)->toBe([5 => 'Custom']);
});

test('checks if can perform recurrent payment', function (): void {
    $service = new ServicePayGateway();
    $payGateway = createEntity(PayGateway::class, ['allowRecurrent' => true]);

    expect($service->canPerformRecurrentPayment($payGateway))->toBeTrue();
});

test('checks if can perform single payment', function (): void {
    $service = new ServicePayGateway();
    $payGateway = createEntity(PayGateway::class, ['allowSingle' => false]);

    expect($service->canPerformSinglePayment($payGateway))->toBeFalse();
});

test('gets payment adapter', function (): void {
    $payGateway = createEntity(PayGateway::class, [
        'id' => 1,
        'gateway' => 'Custom',
        'config' => null,
        'testMode' => false,
    ]);
    $invoiceModel = createEntity(Invoice::class);

    $expected = 'Payment_Adapter_Custom';

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial();
    $serviceMock->shouldReceive('getAdapterClassName')
        ->atLeast()->once()
        ->andReturn($expected);

    $urlMock = Mockery::mock(FOSSBilling\Url::class);
    $urlMock->shouldReceive('link')
        ->atLeast()->once();

    $service = payGatewayService();
    $service->getDi()['url'] = $urlMock;
    $service->getDi()['tools'] = $toolsMock;
    $serviceMock->setDi($service->getDi());

    $optional = [
        'auto_redirect' => '',
    ];
    $result = $serviceMock->getPaymentAdapter($payGateway, $invoiceModel, $optional);
    expect($result)->toBeInstanceOf($expected);
});

test('throws exception when payment gateway adapter class is missing', function (): void {
    $payGateway = createEntity(PayGateway::class, [
        'id' => 1,
        'gateway' => 'Custom',
        'config' => null,
        'testMode' => false,
    ]);
    $invoiceModel = createEntity(Invoice::class);

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial();
    $serviceMock->shouldReceive('getAdapterClassName')
        ->atLeast()->once()
        ->andReturn('');

    $urlMock = Mockery::mock(FOSSBilling\Url::class);
    $urlMock->shouldReceive('link')
        ->atLeast()->once();

    $service = payGatewayService();
    $service->getDi()['url'] = $urlMock;
    $service->getDi()['tools'] = $toolsMock;
    $serviceMock->setDi($service->getDi());

    expect(fn () => $serviceMock->getPaymentAdapter($payGateway, $invoiceModel))
        ->toThrow(FOSSBilling\Exception\BaseException::class, 'Payment gateway  was not found.');
});

test('gets adapter config', function (): void {
    $payGateway = createEntity(PayGateway::class, ['gateway' => 'Custom']);
    $expected = '\Payment_Adapter_Custom';

    $filesystemMock = Mockery::mock(Symfony\Component\Filesystem\Filesystem::class);
    $filesystemMock->shouldReceive('exists')
        ->zeroOrMoreTimes()
        ->andReturn(true);

    $serviceMock = Mockery::mock(ServicePayGateway::class, [$filesystemMock])->makePartial();
    $serviceMock->shouldReceive('getAdapterClassName')
        ->atLeast()->once()
        ->andReturn($expected);

    $service = payGatewayService();
    $serviceMock->setDi($service->getDi());

    $result = $serviceMock->getAdapterConfig($payGateway);
    expect($result)->toBeArray();
});

test('logs and returns an empty config when an adapter has no getConfig method', function (): void {
    $payGateway = createEntity(PayGateway::class, ['gateway' => 'Custom']);

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial();
    $serviceMock->shouldReceive('getAdapterClassName')
        ->once()
        ->andReturn(stdClass::class);

    $service = payGatewayService();
    $logger = new Tests\Helpers\TestLogger();
    $service->getDi()['logger'] = $logger;
    $serviceMock->setDi($service->getDi());

    expect($serviceMock->getAdapterConfig($payGateway))->toBe([])
        ->and($logger->calls)->toContain([
            'method' => 'error',
            'params' => ['Payment stdClass gateway does not have getConfig method'],
        ]);
});

test('throws exception when adapter class does not exist', function (): void {
    $payGateway = createEntity(PayGateway::class, ['gateway' => 'Custom']);
    $expected = 'Payment_Adapter_ClassDoesNotExists';

    $filesystemMock = Mockery::mock(Symfony\Component\Filesystem\Filesystem::class);
    $filesystemMock->shouldReceive('exists')
        ->zeroOrMoreTimes()
        ->andReturn(true);

    $serviceMock = Mockery::mock(ServicePayGateway::class, [$filesystemMock])->makePartial();
    $serviceMock->shouldReceive('getAdapterClassName')
        ->atLeast()->once()
        ->andReturn($expected);

    $service = payGatewayService();
    $serviceMock->setDi($service->getDi());

    expect(fn () => $serviceMock->getAdapterConfig($payGateway))
        ->toThrow(FOSSBilling\Exception\BaseException::class, sprintf('Payment gateway %s was not found', $payGateway->getGateway()));
});

test('throws exception when adapter does not exist', function (): void {
    $payGateway = createEntity(PayGateway::class, ['gateway' => 'Unknown']);

    $filesystemMock = Mockery::mock(Symfony\Component\Filesystem\Filesystem::class);
    $filesystemMock->shouldReceive('exists')
        ->zeroOrMoreTimes()
        ->andReturn(false);

    $serviceMock = Mockery::mock(ServicePayGateway::class, [$filesystemMock])->makePartial();
    $serviceMock->shouldReceive('getAdapterClassName')
        ->atLeast()->once()
        ->andReturn('Payment_Adapter_Unknown');

    $service = payGatewayService();
    $serviceMock->setDi($service->getDi());

    expect(fn () => $serviceMock->getAdapterConfig($payGateway))
        ->toThrow(FOSSBilling\Exception\BaseException::class, sprintf('Payment gateway %s was not found', $payGateway->getGateway()));
});

test('gets adapter class name', function (): void {
    $service = new ServicePayGateway();
    $service->setDi(payGatewayService()->getDi());
    $payGateway = createEntity(PayGateway::class, ['gateway' => 'Custom']);

    $expected = 'Payment_Adapter_Custom';

    $result = $service->getAdapterClassName($payGateway);
    expect($result)->toBeString()->toBe($expected);
});

test('gets accepted currencies', function (): void {
    $service = new ServicePayGateway();
    $service->setDi(payGatewayService()->getDi());
    $payGateway = createEntity(PayGateway::class, ['acceptedCurrencies' => '{}']);

    $result = $service->getAcceptedCurrencies($payGateway);
    expect($result)->toBeArray();
});

test('gets form elements', function (): void {
    $payGateway = createEntity(PayGateway::class);

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial();
    $config = ['form' => []];
    $serviceMock->shouldReceive('getAdapterConfig')
        ->atLeast()->once()
        ->andReturn($config);

    $service = payGatewayService();
    $serviceMock->setDi($service->getDi());

    $result = $serviceMock->getFormElements($payGateway);
    expect($result)->toBeArray();
});

test('returns empty array when form config is empty', function (): void {
    $payGateway = createEntity(PayGateway::class);

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial();
    $config = [];
    $serviceMock->shouldReceive('getAdapterConfig')
        ->atLeast()->once()
        ->andReturn($config);

    $service = payGatewayService();
    $serviceMock->setDi($service->getDi());

    $result = $serviceMock->getFormElements($payGateway);
    expect($result)->toBeArray()->toBe([]);
});

test('gets description', function (): void {
    $payGateway = createEntity(PayGateway::class);

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial();
    $config = ['description' => ''];
    $serviceMock->shouldReceive('getAdapterConfig')
        ->atLeast()->once()
        ->andReturn($config);

    $service = payGatewayService();
    $serviceMock->setDi($service->getDi());

    $result = $serviceMock->getDescription($payGateway);
    expect($result)->toBeString();
});

test('returns null when description is empty', function (): void {
    $payGateway = createEntity(PayGateway::class);

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial();
    $config = [];
    $serviceMock->shouldReceive('getAdapterConfig')
        ->atLeast()->once()
        ->andReturn($config);

    $service = payGatewayService();
    $serviceMock->setDi($service->getDi());

    $result = $serviceMock->getDescription($payGateway);
    expect($result)->toBeNull();
});
