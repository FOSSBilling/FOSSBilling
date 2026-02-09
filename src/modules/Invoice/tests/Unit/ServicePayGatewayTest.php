<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;
use Box\Mod\Invoice\ServicePayGateway;

beforeEach(function () {
    $this->service = new ServicePayGateway();
});

test('gets dependency injection container', function () {
    $di = container();
    $this->service->setDi($di);
    $getDi = $this->service->getDi();
    expect($getDi)->toBe($di);
});

test('gets search query', function () {
    $di = container();

    $this->service->setDi($di);
    $data = [];
    $result = $this->service->getSearchQuery($data);
    expect($result)->toBeArray();
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray()->toBe([]);
});

test('gets search query with additional params', function () {
    $di = container();

    $this->service->setDi($di);
    $data = ['search' => 'keyword'];
    $expectedParams = [':search' => "%$data[search]%"];

    $result = $this->service->getSearchQuery($data);
    expect($result)->toBeArray();
    expect($result[0])->toBeString();
    expect(strpos($result[0], 'AND name LIKE :search') > 0)->toBeTrue();
    expect($result[1])->toBeArray();
    expect($result[1])->toBe($expectedParams);
});

test('gets pairs', function () {
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

    $di = container();
    $di['db'] = $dbMock;

    $this->service->setDi($di);

    $result = $this->service->getPairs();
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('gets available gateways', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->getAvailable();
    expect($result)->toBeArray();
});

test('installs a pay gateway', function () {
    $code = 'PP';

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getAvailable')
        ->atLeast()->once()
        ->andReturn([$code]);

    $payGatewayModel = new \Model_PayGateway();
    $payGatewayModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($payGatewayModel);
    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    $result = $serviceMock->install($code);
    expect($result)->toBeBool()->toBeTrue();
});

test('throws exception when installing unavailable gateway', function () {
    $code = 'PP';

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getAvailable')
        ->atLeast()->once()
        ->andReturn([]);

    expect(fn () => $serviceMock->install($code))
        ->toThrow(\FOSSBilling\Exception::class, 'Payment gateway is not available for installation.');
});

test('converts to api array', function () {
    $payGatewayModel = new \Model_PayGateway();
    $payGatewayModel->loadBean(new \Tests\Helpers\DummyBean());

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
        'allow_single' => null,
        'allow_recurrent' => null,
        'accepted_currencies' => [],
        'supports_one_time_payments' => false,
        'supports_subscriptions' => false,
        'config' => [],
        'form' => [],
        'description' => null,
        'enabled' => null,
        'test_mode' => null,
        'callback' => SYSTEM_URL . 'ipn.php?',
    ];

    $di = container();

    $serviceMock->setDi($di);

    $result = $serviceMock->toApiArray($payGatewayModel, false, new \Model_Admin());
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('copies a gateway', function () {
    $payGatewayModel = new \Model_PayGateway();
    $payGatewayModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($payGatewayModel);

    $expected = 2;
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($expected);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->service->setDi($di);

    $result = $this->service->copy($payGatewayModel);
    expect($result)->toBeInt()->toBe($expected);
});

test('updates a gateway', function () {
    $payGatewayModel = new \Model_PayGateway();
    $payGatewayModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->service->setDi($di);

    $data = [
        'title' => '',
        'config' => '',
        'accepted_currencies' => [],
        'enabled' => '',
        'allow_single' => '',
        'allow_recurrent' => '',
        'test_mode' => '',
    ];
    $result = $this->service->update($payGatewayModel, $data);
    expect($result)->toBeTrue();
});

test('deletes a gateway', function () {
    $payGatewayModel = new \Model_PayGateway();
    $payGatewayModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('trash')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->service->setDi($di);

    $result = $this->service->delete($payGatewayModel);
    expect($result)->toBeTrue();
});

test('gets active gateways', function () {
    $payGatewayModel = new \Model_PayGateway();
    $payGatewayModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([$payGatewayModel]);

    $di = container();
    $di['db'] = $dbMock;

    $this->service->setDi($di);

    $data = ['format' => 'pairs'];
    $result = $this->service->getActive($data);
    expect($result)->toBeArray();
});

test('checks if can perform recurrent payment', function () {
    $payGatewayModel = new \Model_PayGateway();
    $payGatewayModel->loadBean(new \Tests\Helpers\DummyBean());

    $expected = true;
    $payGatewayModel->allow_recurrent = $expected;

    $result = $this->service->canPerformRecurrentPayment($payGatewayModel);
    expect($result)->toBeBool()->toBe($expected);
});

test('gets payment adapter', function () {
    $payGatewayModel = new \Model_PayGateway();
    $payGatewayModel->loadBean(new \Tests\Helpers\DummyBean());
    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());
    $expected = 'Payment_Adapter_Custom';

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getAdapterClassName')
        ->atLeast()->once()
        ->andReturn($expected);

    $urlMock = Mockery::mock('\Box_Url');
    $urlMock->shouldReceive('link')
        ->atLeast()->once();

    $toolsMock = Mockery::mock(\FOSSBilling\Tools::class);
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

test('throws exception when payment gateway is not found', function () {
    $payGatewayModel = new \Model_PayGateway();
    $payGatewayModel->loadBean(new \Tests\Helpers\DummyBean());
    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getAdapterClassName')
        ->atLeast()->once()
        ->andReturn('');

    $urlMock = Mockery::mock('\Box_Url');
    $urlMock->shouldReceive('link')
        ->atLeast()->once();

    $toolsMock = Mockery::mock(\FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('url')
        ->atLeast()->once()
        ->andReturn('http://example.com/');

    $di = container();
    $di['url'] = $urlMock;
    $di['tools'] = $toolsMock;
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->getPaymentAdapter($payGatewayModel, $invoiceModel))
        ->toThrow(\FOSSBilling\Exception::class, 'Payment gateway  was not found.');
});

test('gets adapter config', function () {
    $payGatewayModel = new \Model_PayGateway();
    $payGatewayModel->loadBean(new \Tests\Helpers\DummyBean());
    $payGatewayModel->gateway = 'Custom';

    $expected = '\Payment_Adapter_Custom';
    $filesystemMock = Mockery::mock(\Symfony\Component\Filesystem\Filesystem::class);
    $filesystemMock->shouldReceive('exists')
        ->atLeast()->once()
        ->andReturn(true);

    $serviceMock = Mockery::mock(ServicePayGateway::class, [$filesystemMock])->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getAdapterClassName')
        ->atLeast()->once()
        ->andReturn($expected);

    $result = $serviceMock->getAdapterConfig($payGatewayModel);
    expect($result)->toBeArray();
});

test('throws exception when adapter class does not exist', function () {
    $payGatewayModel = new \Model_PayGateway();
    $payGatewayModel->loadBean(new \Tests\Helpers\DummyBean());
    $payGatewayModel->gateway = 'Custom';

    $expected = 'Payment_Adapter_ClassDoesNotExists';
    $filesystemMock = Mockery::mock(\Symfony\Component\Filesystem\Filesystem::class);
    $filesystemMock->shouldReceive('exists')
        ->atLeast()->once()
        ->andReturn(true);

    $serviceMock = Mockery::mock(ServicePayGateway::class, [$filesystemMock])->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getAdapterClassName')
        ->atLeast()->once()
        ->andReturn($expected);

    expect(fn () => $serviceMock->getAdapterConfig($payGatewayModel))
        ->toThrow(\FOSSBilling\Exception::class, sprintf('Payment gateway class %s was not found', $expected));
});

test('throws exception when adapter does not exist', function () {
    $payGatewayModel = new \Model_PayGateway();
    $payGatewayModel->loadBean(new \Tests\Helpers\DummyBean());
    $payGatewayModel->gateway = 'Unknown';

    $filesystemMock = Mockery::mock(\Symfony\Component\Filesystem\Filesystem::class);
    $filesystemMock->shouldReceive('exists')
        ->atLeast()->once()
        ->andReturn(false);

    $serviceMock = Mockery::mock(ServicePayGateway::class, [$filesystemMock])->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getAdapterClassName')
        ->atLeast()->once();

    expect(fn () => $serviceMock->getAdapterConfig($payGatewayModel))
        ->toThrow(\FOSSBilling\Exception::class, sprintf('Payment gateway %s was not found', $payGatewayModel->gateway));
});

test('gets adapter class name', function () {
    $payGatewayModel = new \Model_PayGateway();
    $payGatewayModel->loadBean(new \Tests\Helpers\DummyBean());
    $payGatewayModel->gateway = 'Custom';

    $expected = 'Payment_Adapter_Custom';

    $result = $this->service->getAdapterClassName($payGatewayModel);
    expect($result)->toBeString()->toBe($expected);
});

test('gets accepted currencies', function () {
    $payGatewayModel = new \Model_PayGateway();
    $payGatewayModel->loadBean(new \Tests\Helpers\DummyBean());
    $payGatewayModel->accepted_currencies = '{}';

    $result = $this->service->getAcceptedCurrencies($payGatewayModel);
    expect($result)->toBeArray();
});

test('gets form elements', function () {
    $payGatewayModel = new \Model_PayGateway();
    $payGatewayModel->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $config = ['form' => []];
    $serviceMock->shouldReceive('getAdapterConfig')
        ->atLeast()->once()
        ->andReturn($config);

    $result = $serviceMock->getFormElements($payGatewayModel);
    expect($result)->toBeArray();
});

test('returns empty array when form config is empty', function () {
    $payGatewayModel = new \Model_PayGateway();
    $payGatewayModel->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $config = [];
    $serviceMock->shouldReceive('getAdapterConfig')
        ->atLeast()->once()
        ->andReturn($config);

    $result = $serviceMock->getFormElements($payGatewayModel);
    expect($result)->toBeArray()->toBe([]);
});

test('gets description', function () {
    $payGatewayModel = new \Model_PayGateway();
    $payGatewayModel->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $config = ['description' => ''];
    $serviceMock->shouldReceive('getAdapterConfig')
        ->atLeast()->once()
        ->andReturn($config);

    $result = $serviceMock->getDescription($payGatewayModel);
    expect($result)->toBeString();
});

test('returns null when description is empty', function () {
    $payGatewayModel = new \Model_PayGateway();
    $payGatewayModel->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $config = [];
    $serviceMock->shouldReceive('getAdapterConfig')
        ->atLeast()->once()
        ->andReturn($config);

    $result = $serviceMock->getDescription($payGatewayModel);
    expect($result)->toBeNull();
});
