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
use Box\Mod\Invoice\Repository\PayGatewayRepository;
use Box\Mod\Invoice\ServicePayGateway;
use Doctrine\ORM\EntityManagerInterface;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

/**
 * A stand-in for a gateway still on the legacy path (PayPalEmail, Stripe):
 * it does not implement Gateway, so ServicePayGateway::getPaymentAdapter()
 * must smuggle the return/cancel/notify URLs into its settings array.
 */
class ServicePayGatewayTestLegacyAdapter
{
    public function __construct(public array $config)
    {
    }
}

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

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')
        ->atLeast()->once()
        ->andReturn($queryResult);

    $service = payGatewayService();
    $service->getDi()['db'] = $dbMock;

    $result = $service->getPairs();
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('gets available gateways', function (): void {
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')
        ->atLeast()->once()
        ->andReturn([]);

    $service = payGatewayService();
    $service->getDi()['db'] = $dbMock;

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
        ->toThrow(FOSSBilling\Exception::class, 'Payment gateway is not available for installation.');
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
    $serviceMock->shouldReceive('getAcceptedCurrencies')->andReturn([]);
    $serviceMock->shouldReceive('getFormElements')->andReturn([]);
    $serviceMock->shouldReceive('getDescription')->andReturn(null);

    $service = payGatewayService();
    $serviceMock->setDi($service->getDi());

    $result = $serviceMock->toApiArray($payGateway, false, new Model_Admin());
    expect($result)->toBeArray();
    expect($result['id'])->toBe(1);
    expect($result['code'])->toBe('Custom');
    expect($result['title'])->toBe('Custom');
    expect($result['allow_single'])->toBeTrue();
    expect($result['allow_recurrent'])->toBeFalse();
    expect($result['enabled'])->toBeTrue();
    expect($result['test_mode'])->toBeFalse();
    // Implementing Gateway is itself the claim of one-time-payment support,
    // so this is always true now. The real Custom gateway implements
    // SupportsSubscriptions, so this is true too.
    expect($result['supports_one_time_payments'])->toBeTrue();
    expect($result['supports_subscriptions'])->toBeTrue();
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

test('deletes a gateway', function (): void {
    $payGateway = createEntity(PayGateway::class, ['id' => 7]);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('remove')->atLeast()->once();
    $em->shouldReceive('flush')->atLeast()->once();
    $repo = Mockery::mock(PayGatewayRepository::class);
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn($repo);

    $service = new ServicePayGateway();
    $di = container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->delete($payGateway);
    expect($result)->toBeTrue();
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

test('gets payment adapter for a Gateway-typed adapter without smuggling URLs into its settings', function (): void {
    $payGateway = createEntity(PayGateway::class, [
        'id' => 1,
        'gateway' => 'Custom',
        'config' => null,
        'testMode' => false,
    ]);
    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
    $expected = 'FOSSBilling\\Extension\\Gateway\\Custom\\Custom';

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial();
    $serviceMock->shouldReceive('getAdapterClassName')
        ->atLeast()->once()
        ->andReturn($expected);

    // Custom implements Gateway, so it receives merchant settings only —
    // no return/cancel/notify URLs, hence no calls into url/tools.
    $urlMock = Mockery::mock('\Box_Url');
    $urlMock->shouldNotReceive('link');

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldNotReceive('url');

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

test('gets payment adapter for a legacy adapter, smuggling return/cancel/notify URLs into its settings', function (): void {
    $payGateway = createEntity(PayGateway::class, [
        'id' => 1,
        'gateway' => 'Legacy',
        'config' => null,
        'testMode' => false,
    ]);
    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
    $invoiceModel->hash = 'hashString';

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial();
    $serviceMock->shouldReceive('getAdapterClassName')
        ->atLeast()->once()
        ->andReturn(ServicePayGatewayTestLegacyAdapter::class);

    $urlMock = Mockery::mock('\Box_Url');
    $urlMock->shouldReceive('link')
        ->atLeast()->once()
        ->andReturn('http://example.com/invoice/hashString');

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('url')
        ->atLeast()->once()
        ->andReturn('http://example.com/');

    $service = payGatewayService();
    $service->getDi()['url'] = $urlMock;
    $service->getDi()['tools'] = $toolsMock;
    $serviceMock->setDi($service->getDi());

    $result = $serviceMock->getPaymentAdapter($payGateway, $invoiceModel);
    expect($result)->toBeInstanceOf(ServicePayGatewayTestLegacyAdapter::class)
        ->and($result->config['return_url'])->not->toBeEmpty()
        ->and($result->config['cancel_url'])->not->toBeEmpty()
        ->and($result->config['notify_url'])->not->toBeEmpty()
        ->and($result->config['redirect_url'])->not->toBeEmpty();
});

test('throws exception when payment gateway adapter class is missing', function (): void {
    $payGateway = createEntity(PayGateway::class, [
        'id' => 1,
        'gateway' => 'Custom',
        'config' => null,
        'testMode' => false,
    ]);
    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial();
    $serviceMock->shouldReceive('getAdapterClassName')
        ->atLeast()->once()
        ->andReturn('');

    // The class doesn't exist, so getPaymentAdapter() throws before it
    // would ever need to build return/cancel/notify URLs.
    $urlMock = Mockery::mock('\Box_Url');
    $urlMock->shouldNotReceive('link');

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldNotReceive('url');

    $service = payGatewayService();
    $service->getDi()['url'] = $urlMock;
    $service->getDi()['tools'] = $toolsMock;
    $serviceMock->setDi($service->getDi());

    expect(fn () => $serviceMock->getPaymentAdapter($payGateway, $invoiceModel))
        ->toThrow(FOSSBilling\Exception::class, 'Payment gateway  was not found.');
});

test('gets adapter config', function (): void {
    $payGateway = createEntity(PayGateway::class, ['gateway' => 'Custom']);
    $expected = 'FOSSBilling\\Extension\\Gateway\\Custom\\Custom';

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

test('throws exception when adapter class does not exist', function (): void {
    $payGateway = createEntity(PayGateway::class, ['gateway' => 'Custom']);
    $expected = 'FOSSBilling\\Extension\\Gateway\\ClassDoesNotExist\\ClassDoesNotExist';

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
        ->toThrow(FOSSBilling\Exception::class, sprintf('Payment gateway %s was not found', $payGateway->getGateway()));
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
        ->andReturn('FOSSBilling\\Extension\\Gateway\\Unknown\\Unknown');

    $service = payGatewayService();
    $serviceMock->setDi($service->getDi());

    expect(fn () => $serviceMock->getAdapterConfig($payGateway))
        ->toThrow(FOSSBilling\Exception::class, sprintf('Payment gateway %s was not found', $payGateway->getGateway()));
});

test('gets adapter class name', function (): void {
    $service = new ServicePayGateway();
    $service->setDi(payGatewayService()->getDi());
    $payGateway = createEntity(PayGateway::class, ['gateway' => 'Custom']);

    $expected = 'FOSSBilling\\Extension\\Gateway\\Custom\\Custom';

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
