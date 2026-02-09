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
use Box\Mod\Formbuilder\Api\Admin;
use Box\Mod\Formbuilder\Service;

beforeEach(function () {
    $this->service = new Service();
    $this->api = new Admin();
});

test('gets dependency injection container', function () {
    $di = container();
    $this->api->setDi($di);
    $getDi = $this->api->getDi();
    expect($getDi)->toBe($di);
});

test('creates a form', function () {
    $data = ['name' => 'testForm'];
    $createdFormId = 1;

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('addNewForm')
        ->atLeast()->once()
        ->andReturn($createdFormId);

    $this->api->setService($serviceMock);

    $di = container();
    $this->api->setDi($di);

    $result = $this->api->create_form($data);
    expect($result)->toBeInt()->toBe($createdFormId);
});

test('throws exception when form type is not in predefined list', function () {
    $data = [
        'name' => 'testName',
        'type' => 'custom',
    ];

    $di = container();
    $this->api->setDi($di);

    expect(fn () => $this->api->create_form($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Form style was not found in predefined list');
});

test('adds a field to a form', function () {
    $data = [
        'type' => 'text',
        'options' => ['sameValue'],
        'form_id' => 1,
    ];
    $newFieldId = 2;

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('typeValidation')
        ->atLeast()->once()
        ->andReturn(true);
    $serviceMock->shouldReceive('isArrayUnique')
        ->atLeast()->once()
        ->andReturn(true);
    $serviceMock->shouldReceive('addNewField')
        ->atLeast()->once()
        ->andReturn($newFieldId);

    $this->api->setService($serviceMock);

    $result = $this->api->add_field($data);
    expect($result)->toBeInt()->toBe($newFieldId);
});

test('throws exception when adding field with missing type', function () {
    $data = [];

    expect(fn () => $this->api->add_field($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Form field type is invalid');
});

test('throws exception when field options are not unique', function () {
    $data = [
        'type' => 'text',
        'options' => ['sameValue', 'sameValue'],
    ];

    $this->api->setService($this->service);

    expect(fn () => $this->api->add_field($data))
        ->toThrow(\FOSSBilling\InformationException::class, 'This input type must have unique values');
});

test('throws exception when adding field without form id', function () {
    $data = [
        'type' => 'text',
        'options' => ['sameValue'],
    ];

    $this->api->setService($this->service);

    expect(fn () => $this->api->add_field($data))
        ->toThrow(\FOSSBilling\InformationException::class, 'Form id was not passed');
});

test('gets a form', function () {
    $data['id'] = 1;

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getForm')
        ->atLeast()->once()
        ->andReturn([]);

    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray')->atLeast()->once();

    $di = container();
    $di['validator'] = $validatorMock;
    $this->api->setDi($di);

    $this->api->setService($serviceMock);
    $result = $this->api->get_form($data);
    expect($result)->toBeArray();
});

test('gets form fields', function () {
    $data['form_id'] = 1;

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getFormFields')
        ->atLeast()->once()
        ->andReturn([]);

    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray')->atLeast()->once();

    $di = container();
    $di['validator'] = $validatorMock;
    $this->api->setDi($di);

    $this->api->setService($serviceMock);
    $result = $this->api->get_form_fields($data);
    expect($result)->toBeArray();
});

test('gets a field', function () {
    $data['id'] = 3;

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getField')
        ->atLeast()->once()
        ->andReturn([]);

    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray')->atLeast()->once();

    $di = container();
    $di['validator'] = $validatorMock;
    $this->api->setDi($di);

    $this->api->setService($serviceMock);

    $result = $this->api->get_field($data);
    expect($result)->toBeArray();
});

test('gets all forms', function () {
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getForms')
        ->atLeast()->once()
        ->andReturn([]);

    $this->api->setService($serviceMock);

    $result = $this->api->get_forms();
    expect($result)->toBeArray();
});

test('deletes a form', function () {
    $data['id'] = 1;

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('removeForm')
        ->atLeast()->once()
        ->andReturn(true);

    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray')->atLeast()->once();

    $di = container();
    $di['validator'] = $validatorMock;
    $this->api->setDi($di);

    $this->api->setService($serviceMock);

    $result = $this->api->delete_form($data);
    expect($result)->toBeTrue();
});

test('deletes a field', function () {
    $data['id'] = 1;

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('removeField')
        ->atLeast()->once()
        ->andReturn(true);

    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray')->atLeast()->once();

    $di = container();
    $di['validator'] = $validatorMock;
    $this->api->setDi($di);

    $this->api->setService($serviceMock);

    $result = $this->api->delete_field($data);
    expect($result)->toBeTrue();
});

test('updates a field', function () {
    $updatedFieldId = 1;
    $data = [
        'id' => $updatedFieldId,
        'options' => ['sameValue'],
    ];

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateField')
        ->atLeast()->once()
        ->andReturn($updatedFieldId);
    $serviceMock->shouldReceive('isArrayUnique')
        ->atLeast()->once()
        ->andReturn(true);

    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray')->atLeast()->once();

    $di = container();
    $di['validator'] = $validatorMock;
    $this->api->setDi($di);

    $this->api->setService($serviceMock);

    $result = $this->api->update_field($data);
    expect($result)->toBeInt()->toBe($updatedFieldId);
});

test('gets form pairs', function () {
    $data = [];
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getFormPairs')
        ->atLeast()->once()
        ->andReturn([]);

    $this->api->setService($serviceMock);

    $result = $this->api->get_pairs($data);
    expect($result)->toBeArray();
});

test('copies a form', function () {
    $newFormId = 2;
    $data = [
        'form_id' => 1,
        'name' => 'testForm',
    ];

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('duplicateForm')
        ->atLeast()->once()
        ->andReturn($newFormId);

    $this->api->setService($serviceMock);
    $result = $this->api->copy_form($data);
    expect($result)->toBeInt()->toBe($newFormId);
});

test('throws exception when copying form without id', function () {
    $data = [];

    expect(fn () => $this->api->copy_form($data))
        ->toThrow(\FOSSBilling\InformationException::class, 'Form id was not passed');
});

test('throws exception when copying form without name', function () {
    $data = ['form_id' => 1];

    expect(fn () => $this->api->copy_form($data))
        ->toThrow(\FOSSBilling\InformationException::class, 'Form name was not passed');
});

test('updates form settings', function () {
    $data = [
        'form_id' => 1,
        'form_name' => 'testForm',
        'type' => 'default',
    ];

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateFormSettings')
        ->atLeast()->once()
        ->andReturn(true);

    $this->api->setService($serviceMock);
    $result = $this->api->update_form_settings($data);
    expect($result)->toBeTrue();
});

test('throws exception when updating form settings with missing fields', function ($missingField, $exceptionMessage) {
    $data = [
        'form_id' => 1,
        'form_name' => 'testForm',
        'type' => 'customType',
    ];
    unset($data[$missingField]);

    expect(fn () => $this->api->update_form_settings($data))
        ->toThrow(\FOSSBilling\Exception::class, $exceptionMessage);
})->with([
    ['form_id', 'Form id was not passed'],
    ['form_name', 'Form name was not passed'],
    ['type', 'Form type was not passed'],
    ['', 'Field type not supported'],
]);
