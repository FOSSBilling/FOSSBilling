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
    $expectedParams = [':search' => "%$data[search]%"];

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

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')
        ->atLeast()->once()
        ->andReturn($queryResult);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $result = $service->getPairs();
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('gets available gateways', function (): void {
    $service = new ServicePayGateway();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
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

    $payGatewayModel = new Model_PayGateway();
    $payGatewayModel->loadBean(new Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($payGatewayModel);
    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
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
    $payGatewayModel = new Model_PayGateway();
    $payGatewayModel->loadBean(new Tests\Helpers\DummyBean());

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
        'secret_fields' => [],
        'form' => [],
        'description' => null,
        'enabled' => null,
        'test_mode' => null,
        'callback' => SYSTEM_URL . 'ipn.php?',
    ];

    $di = container();

    $serviceMock->setDi($di);

    $result = $serviceMock->toApiArray($payGatewayModel, false, new Model_Admin());
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('copies a gateway', function (): void {
    $service = new ServicePayGateway();
    $payGatewayModel = new Model_PayGateway();
    $payGatewayModel->loadBean(new Tests\Helpers\DummyBean());
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
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    $result = $service->copy($payGatewayModel);
    expect($result)->toBeInt()->toBe($expected);
});

test('updates a gateway', function (): void {
    $service = new ServicePayGateway();
    $payGatewayModel = new Model_PayGateway();
    $payGatewayModel->loadBean(new Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
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

test('converts to api array masks secrets for an admin', function (): void {
    $payGatewayModel = new Model_PayGateway();
    $payGatewayModel->loadBean(new Tests\Helpers\DummyBean());
    $payGatewayModel->gateway = 'Stripe';
    $payGatewayModel->accepted_currencies = json_encode(['USD']);
    $payGatewayModel->config = json_encode([
        'pub_key' => 'pk_live_visible',
        'api_key' => 'sk_live_secret',
        'webhook_secret' => 'whsec_secret',
    ]);

    $service = new ServicePayGateway();
    $di = container();
    $service->setDi($di);

    $result = $service->toApiArray($payGatewayModel, false, new Model_Admin());

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
    $payGatewayModel = new Model_PayGateway();
    $payGatewayModel->loadBean(new Tests\Helpers\DummyBean());
    $payGatewayModel->gateway = 'Stripe';
    $payGatewayModel->config = json_encode(['api_key' => 'sk_live_existing', 'pub_key' => 'pk_live_existing']);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['loggedin_admin'] = new Model_Admin();

    $service = new ServicePayGateway();
    $service->setDi($di);

    $result = $service->update($payGatewayModel, [
        'config' => [
            'api_key' => ServicePayGateway::CREDENTIAL_KEEP_SENTINEL,
            'pub_key' => 'pk_live_new',
        ],
    ]);

    expect($result)->toBeTrue();
    $config = json_decode((string) $payGatewayModel->config, true);
    expect($config['api_key'])->toBe('sk_live_existing');
    expect($config['pub_key'])->toBe('pk_live_new');
});

test('update rotates a secret gateway value when a new value is submitted', function (): void {
    $payGatewayModel = new Model_PayGateway();
    $payGatewayModel->loadBean(new Tests\Helpers\DummyBean());
    $payGatewayModel->gateway = 'Stripe';
    $payGatewayModel->config = json_encode(['api_key' => 'sk_live_old']);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['loggedin_admin'] = new Model_Admin();

    $service = new ServicePayGateway();
    $service->setDi($di);

    $result = $service->update($payGatewayModel, ['config' => ['api_key' => 'sk_live_new']]);

    expect($result)->toBeTrue();
    $config = json_decode((string) $payGatewayModel->config, true);
    expect($config['api_key'])->toBe('sk_live_new');
});

test('deletes a gateway', function (): void {
    $service = new ServicePayGateway();
    $payGatewayModel = new Model_PayGateway();
    $payGatewayModel->loadBean(new Tests\Helpers\DummyBean());
    $payGatewayModel->id = 5;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('trash')
        ->atLeast()->once();

    $connectionMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connectionMock->shouldReceive('executeStatement')
        ->once()
        ->with('DELETE FROM pay_gateway_customer WHERE pay_gateway_id = :id', ['id' => 5]);
    $connectionMock->shouldReceive('executeStatement')
        ->once()
        ->with('DELETE FROM pay_gateway_product WHERE pay_gateway_id = :id', ['id' => 5]);

    $di = container();
    $di['db'] = $dbMock;
    $di['em']->shouldReceive('getConnection')->andReturn($connectionMock);
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    $result = $service->delete($payGatewayModel);
    expect($result)->toBeTrue();
});

test('gets active gateways', function (): void {
    $service = new ServicePayGateway();
    $payGatewayModel = new Model_PayGateway();
    $payGatewayModel->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([$payGatewayModel]);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $data = ['format' => 'pairs'];
    $result = $service->getActive($data);
    expect($result)->toBeArray();
});

test('checks if can perform recurrent payment', function (): void {
    $service = new ServicePayGateway();
    $payGatewayModel = new Model_PayGateway();
    $payGatewayModel->loadBean(new Tests\Helpers\DummyBean());

    $expected = true;
    $payGatewayModel->allow_recurrent = $expected;

    $result = $service->canPerformRecurrentPayment($payGatewayModel);
    expect($result)->toBeBool()->toBe($expected);
});

test('gets payment adapter', function (): void {
    $service = new ServicePayGateway();
    $payGatewayModel = new Model_PayGateway();
    $payGatewayModel->loadBean(new Tests\Helpers\DummyBean());
    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
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
    $payGatewayModel = new Model_PayGateway();
    $payGatewayModel->loadBean(new Tests\Helpers\DummyBean());
    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

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
    $payGatewayModel = new Model_PayGateway();
    $payGatewayModel->loadBean(new Tests\Helpers\DummyBean());
    $payGatewayModel->gateway = 'Custom';

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
    $payGatewayModel = new Model_PayGateway();
    $payGatewayModel->loadBean(new Tests\Helpers\DummyBean());
    $payGatewayModel->gateway = 'Custom';

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
    $payGatewayModel = new Model_PayGateway();
    $payGatewayModel->loadBean(new Tests\Helpers\DummyBean());
    $payGatewayModel->gateway = 'Unknown';

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
    $payGatewayModel = new Model_PayGateway();
    $payGatewayModel->loadBean(new Tests\Helpers\DummyBean());
    $payGatewayModel->gateway = 'Custom';

    $expected = 'Payment_Adapter_Custom';

    $result = $service->getAdapterClassName($payGatewayModel);
    expect($result)->toBeString()->toBe($expected);
});

test('gets accepted currencies', function (): void {
    $service = new ServicePayGateway();
    $payGatewayModel = new Model_PayGateway();
    $payGatewayModel->loadBean(new Tests\Helpers\DummyBean());
    $payGatewayModel->accepted_currencies = '{}';

    $result = $service->getAcceptedCurrencies($payGatewayModel);
    expect($result)->toBeArray();
});

test('gets form elements', function (): void {
    $service = new ServicePayGateway();
    $payGatewayModel = new Model_PayGateway();
    $payGatewayModel->loadBean(new Tests\Helpers\DummyBean());

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
    $payGatewayModel = new Model_PayGateway();
    $payGatewayModel->loadBean(new Tests\Helpers\DummyBean());

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
    $payGatewayModel = new Model_PayGateway();
    $payGatewayModel->loadBean(new Tests\Helpers\DummyBean());

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
    $payGatewayModel = new Model_PayGateway();
    $payGatewayModel->loadBean(new Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(ServicePayGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $config = [];
    $serviceMock->shouldReceive('getAdapterConfig')
        ->atLeast()->once()
        ->andReturn($config);

    $result = $serviceMock->getDescription($payGatewayModel);
    expect($result)->toBeNull();
});
