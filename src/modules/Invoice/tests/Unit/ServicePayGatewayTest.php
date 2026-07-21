<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\ServicePayGateway;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

test('gets dependency injection container', function (): void {
    $service = new ServicePayGateway();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toBe($di);
});

test('gets search query', function (): void {
    $service = new ServicePayGateway();
    $di = container();

    $service->setDi($di);
    $data = [];
    $result = $service->getSearchQuery($data);
    expect($result)->toBeArray();
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray()->toBe([]);
});

test('gets search query with additional params', function (): void {
    $service = new ServicePayGateway();
    $di = container();

    $service->setDi($di);
    $data = ['search' => 'keyword'];
    $expectedParams = ['search' => "%$data[search]%"];

    $result = $service->getSearchQuery($data);
    expect($result)->toBeArray();
    expect($result[0])->toBeString();
    expect(strpos((string) $result[0], 'AND (name LIKE :search OR gateway LIKE :search)') > 0)->toBeTrue();
    expect($result[1])->toBeArray();
    expect($result[1])->toBe($expectedParams);
});

test('gets pairs', function (): void {
    $service = new ServicePayGateway();
    $expected = [
        1 => 'Custom',
    ];

    $queryResult = [
        [
            'id' => 1,
            'name' => 'Custom',
        ],
    ];

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('fetchAllAssociative')
        ->atLeast()->once()
        ->andReturn($queryResult);

    $di = container();
    $di['dbal'] = $dbalMock;

    $service->setDi($di);

    $result = $service->getPairs();
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('gets available gateways', function (): void {
    $service = new ServicePayGateway();
    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('fetchAllAssociative')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    $result = $service->getAvailable();
    expect($result)->toBeArray();
});

test('installs a pay gateway', function (): void {
    $service = new ServicePayGateway();
    $code = 'PP';

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getAvailable')
        ->atLeast()->once()
        ->andReturn([$code]);

    $di = container();
    $di['em'] = Tests\Helpers\entityManagerWithIds($di);
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    $result = $serviceMock->install($code);
    expect($result)->toBeBool()->toBeTrue();
});

test('throws exception when installing unavailable gateway', function (): void {
    $service = new ServicePayGateway();
    $code = 'PP';

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getAvailable')
        ->atLeast()->once()
        ->andReturn([]);

    expect(fn () => $serviceMock->install($code))
        ->toThrow(FOSSBilling\Exception::class, 'Payment gateway is not available for installation.');
});

test('converts to api array', function (): void {
    $service = new ServicePayGateway();
    $payGatewayModel = createEntity(\Box\Mod\Invoice\Entity\PayGateway::class);

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getAdapterConfig')
        ->atLeast()->once()
        ->andReturn([]);
    $serviceMock->shouldReceive('getAcceptedCurrencies');
    $serviceMock->shouldReceive('getFormElements');
    $serviceMock->shouldReceive('getDescription');

    $expected = [
        'id' => null,
        'code' => null,
        'title' => null,
        'allow_single' => true,
        'allow_recurrent' => true,
        'accepted_currencies' => [],
        'supports_one_time_payments' => false,
        'supports_subscriptions' => false,
        'config' => [],
        'form' => [],
        'description' => null,
        'enabled' => true,
        'test_mode' => false,
        'callback' => SYSTEM_URL . 'ipn.php?',
    ];

    $di = container();

    $serviceMock->setDi($di);

    $result = $serviceMock->toApiArray($payGatewayModel, false, createEntity(\Box\Mod\Staff\Entity\Admin::class));
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('copies a gateway', function (): void {
    $service = new ServicePayGateway();
    $payGatewayModel = createEntity(\Box\Mod\Invoice\Entity\PayGateway::class);

    $di = container();
    $di['em'] = Tests\Helpers\entityManagerWithIds($di);
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    $result = $service->copy($payGatewayModel);
    expect($result)->toBeInt()->toBe(1);
});

test('updates a gateway', function (): void {
    $service = new ServicePayGateway();
    $payGatewayModel = createEntity(\Box\Mod\Invoice\Entity\PayGateway::class);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    $data = [
        'title' => '',
        'config' => '',
        'accepted_currencies' => [],
        'enabled' => '',
        'allow_single' => '',
        'allow_recurrent' => '',
        'test_mode' => '',
    ];
    $result = $service->update($payGatewayModel, $data);
    expect($result)->toBeTrue();
});

test('deletes a gateway', function (): void {
    $service = new ServicePayGateway();
    $payGatewayModel = createEntity(\Box\Mod\Invoice\Entity\PayGateway::class);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    $result = $service->delete($payGatewayModel);
    expect($result)->toBeTrue();
});

test('gets active gateways', function (): void {
    $service = new ServicePayGateway();
    $payGatewayEntity = new Box\Mod\Invoice\Entity\PayGateway();
    $payGatewayEntity->setName('Test Gateway');

    $payGatewayRepoMock = Mockery::mock(Box\Mod\Invoice\Repository\PayGatewayRepository::class);
    $payGatewayRepoMock->shouldReceive('findEnabledOrderedByIdDesc')
        ->atLeast()->once()
        ->andReturn([$payGatewayEntity]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\PayGateway::class)
        ->andReturn($payGatewayRepoMock);

    $di = container();
    $di['em'] = $emMock;

    $service->setDi($di);

    $data = ['format' => 'pairs'];
    $result = $service->getActive($data);
    expect($result)->toBeArray();
});

test('checks if can perform recurrent payment', function (): void {
    $service = new ServicePayGateway();
    $payGatewayModel = createEntity(\Box\Mod\Invoice\Entity\PayGateway::class, ['allow_recurrent' => true]);

    $result = $service->canPerformRecurrentPayment($payGatewayModel);
    expect($result)->toBeBool()->toBeTrue();
});

test('gets payment adapter', function (): void {
    $service = new ServicePayGateway();
    $payGatewayModel = createEntity(\Box\Mod\Invoice\Entity\PayGateway::class);
    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class);
    $expected = 'Payment_Adapter_Custom';

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getAdapterClassName')
        ->atLeast()->once()
        ->andReturn($expected);

    $urlMock = Mockery::mock('\Box_Url');
    $urlMock->shouldReceive('link')
        ->atLeast()->once();

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('url')
        ->atLeast()->once()
        ->andReturn('http://example.com/');

    $di = container();
    $di['url'] = $urlMock;
    $di['tools'] = $toolsMock;
    $serviceMock->setDi($di);

    $optional = [
        'auto_redirect' => '',
    ];
    $result = $serviceMock->getPaymentAdapter($payGatewayModel, $invoiceModel, $optional);
    expect($result)->toBeInstanceOf($expected);
});

test('throws exception when payment gateway is not found', function (): void {
    $service = new ServicePayGateway();
    $payGatewayModel = createEntity(\Box\Mod\Invoice\Entity\PayGateway::class);
    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class);

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getAdapterClassName')
        ->atLeast()->once()
        ->andReturn('');

    $urlMock = Mockery::mock('\Box_Url');
    $urlMock->shouldReceive('link')
        ->atLeast()->once();

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('url')
        ->atLeast()->once()
        ->andReturn('http://example.com/');

    $di = container();
    $di['url'] = $urlMock;
    $di['tools'] = $toolsMock;
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->getPaymentAdapter($payGatewayModel, $invoiceModel))
        ->toThrow(FOSSBilling\Exception::class, 'Payment gateway  was not found.');
});

test('gets adapter config', function (): void {
    $service = new ServicePayGateway();
    $payGatewayModel = createEntity(\Box\Mod\Invoice\Entity\PayGateway::class, ['gateway' => 'Custom']);

    $expected = '\Payment_Adapter_Custom';
    $filesystemMock = Mockery::mock(Symfony\Component\Filesystem\Filesystem::class);
    $filesystemMock->shouldReceive('exists')
        ->zeroOrMoreTimes()
        ->andReturn(true);

    $serviceMock = Mockery::mock(ServicePayGateway::class, [$filesystemMock])->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getAdapterClassName')
        ->atLeast()->once()
        ->andReturn($expected);

    $result = $serviceMock->getAdapterConfig($payGatewayModel);
    expect($result)->toBeArray();
});

test('throws exception when adapter class does not exist', function (): void {
    $service = new ServicePayGateway();
    $payGatewayModel = createEntity(\Box\Mod\Invoice\Entity\PayGateway::class, ['gateway' => 'Custom']);

    $expected = 'Payment_Adapter_ClassDoesNotExists';
    $filesystemMock = Mockery::mock(Symfony\Component\Filesystem\Filesystem::class);
    $filesystemMock->shouldReceive('exists')
        ->zeroOrMoreTimes()
        ->andReturn(true);

    $serviceMock = Mockery::mock(ServicePayGateway::class, [$filesystemMock])->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getAdapterClassName')
        ->atLeast()->once()
        ->andReturn($expected);

    expect(fn () => $serviceMock->getAdapterConfig($payGatewayModel))
        ->toThrow(FOSSBilling\Exception::class, sprintf('Payment gateway %s was not found', $payGatewayModel->gateway));
});

test('throws exception when adapter does not exist', function (): void {
    $service = new ServicePayGateway();
    $payGatewayModel = createEntity(\Box\Mod\Invoice\Entity\PayGateway::class, ['gateway' => 'Unknown']);

    $filesystemMock = Mockery::mock(Symfony\Component\Filesystem\Filesystem::class);
    $filesystemMock->shouldReceive('exists')
        ->zeroOrMoreTimes()
        ->andReturn(false);

    $serviceMock = Mockery::mock(ServicePayGateway::class, [$filesystemMock])->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getAdapterClassName')
        ->atLeast()->once();

    expect(fn () => $serviceMock->getAdapterConfig($payGatewayModel))
        ->toThrow(FOSSBilling\Exception::class, sprintf('Payment gateway %s was not found', $payGatewayModel->gateway));
});

test('gets adapter class name', function (): void {
    $service = new ServicePayGateway();
    $payGatewayModel = createEntity(\Box\Mod\Invoice\Entity\PayGateway::class, ['gateway' => 'Custom']);

    $expected = 'Payment_Adapter_Custom';

    $result = $service->getAdapterClassName($payGatewayModel);
    expect($result)->toBeString()->toBe($expected);
});

test('gets accepted currencies', function (): void {
    $service = new ServicePayGateway();
    $payGatewayModel = createEntity(\Box\Mod\Invoice\Entity\PayGateway::class, ['accepted_currencies' => '{}']);

    $result = $service->getAcceptedCurrencies($payGatewayModel);
    expect($result)->toBeArray();
});

test('gets form elements', function (): void {
    $service = new ServicePayGateway();
    $payGatewayModel = createEntity(\Box\Mod\Invoice\Entity\PayGateway::class);

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $config = ['form' => []];
    $serviceMock->shouldReceive('getAdapterConfig')
        ->atLeast()->once()
        ->andReturn($config);

    $result = $serviceMock->getFormElements($payGatewayModel);
    expect($result)->toBeArray();
});

test('returns empty array when form config is empty', function (): void {
    $service = new ServicePayGateway();
    $payGatewayModel = createEntity(\Box\Mod\Invoice\Entity\PayGateway::class);

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $config = [];
    $serviceMock->shouldReceive('getAdapterConfig')
        ->atLeast()->once()
        ->andReturn($config);

    $result = $serviceMock->getFormElements($payGatewayModel);
    expect($result)->toBeArray()->toBe([]);
});

test('gets description', function (): void {
    $service = new ServicePayGateway();
    $payGatewayModel = createEntity(\Box\Mod\Invoice\Entity\PayGateway::class);

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $config = ['description' => ''];
    $serviceMock->shouldReceive('getAdapterConfig')
        ->atLeast()->once()
        ->andReturn($config);

    $result = $serviceMock->getDescription($payGatewayModel);
    expect($result)->toBeString();
});

test('returns null when description is empty', function (): void {
    $service = new ServicePayGateway();
    $payGatewayModel = createEntity(\Box\Mod\Invoice\Entity\PayGateway::class);

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $config = [];
    $serviceMock->shouldReceive('getAdapterConfig')
        ->atLeast()->once()
        ->andReturn($config);

    $result = $serviceMock->getDescription($payGatewayModel);
    expect($result)->toBeNull();
});
