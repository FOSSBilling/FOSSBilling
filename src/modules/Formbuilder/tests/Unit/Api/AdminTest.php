<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Formbuilder\Api\Admin;
use Box\Mod\Formbuilder\Service;

use function Tests\Helpers\container;

test('gets dependency injection container', function (): void {
    $api = new Admin();
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toBe($di);
});

test('creates a form', function (): void {
    $api = new Admin();
    $data = ['name' => 'testForm'];
    $createdFormId = 1;

    $serviceMock = Mockery::mock(Service::class);
    /** @var Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('addNewForm');
    $expectation->atLeast()->once();
    $expectation->andReturn($createdFormId);

    $api->setService($serviceMock);

    $di = container();
    $api->setDi($di);

    $result = $api->create_form($data);
    expect($result)->toBeInt()->toBe($createdFormId);
});

test('throws exception when form type is not in predefined list', function (): void {
    $api = new Admin();
    $data = [
        'name' => 'testName',
        'type' => 'custom',
    ];

    $di = container();
    $api->setDi($di);

    expect(fn () => $api->create_form($data))
        ->toThrow(FOSSBilling\Exception::class, 'Form style was not found in predefined list');
});

test('adds a field to a form', function (): void {
    $api = new Admin();
    $data = [
        'type' => 'text',
        'options' => ['sameValue'],
        'form_id' => 1,
    ];
    $newFieldId = 2;

    $serviceMock = Mockery::mock(Service::class);
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $serviceMock->shouldReceive('typeValidation');
    $expectation1->atLeast()->once();
    $expectation1->andReturn(true);
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $serviceMock->shouldReceive('isArrayUnique');
    $expectation2->atLeast()->once();
    $expectation2->andReturn(true);
    /** @var Mockery\Expectation $expectation3 */
    $expectation3 = $serviceMock->shouldReceive('addNewField');
    $expectation3->atLeast()->once();
    $expectation3->andReturn($newFieldId);

    $api->setService($serviceMock);

    $result = $api->add_field($data);
    expect($result)->toBeInt()->toBe($newFieldId);
});

test('throws exception when adding field with missing type', function (): void {
    $api = new Admin();
    $data = [];

    expect(fn () => $api->add_field($data))
        ->toThrow(FOSSBilling\Exception::class, 'Form field type is invalid');
});

test('throws exception when field options are not unique', function (): void {
    $api = new Admin();
    $service = new Service();
    $data = [
        'type' => 'text',
        'options' => ['sameValue', 'sameValue'],
    ];

    $api->setService($service);

    expect(fn () => $api->add_field($data))
        ->toThrow(FOSSBilling\InformationException::class, 'This input type must have unique values');
});

test('throws exception when adding field without form id', function (): void {
    $api = new Admin();
    $service = new Service();
    $data = [
        'type' => 'text',
        'options' => ['sameValue'],
    ];

    $api->setService($service);

    expect(fn () => $api->add_field($data))
        ->toThrow(FOSSBilling\InformationException::class, 'Form id was not passed');
});

test('gets a form', function (): void {
    $api = new Admin();
    $data['id'] = 1;

    $serviceMock = Mockery::mock(Service::class);
    /** @var Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('getForm');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    /** @var Mockery\Expectation $validatorExpectation */
    $validatorExpectation = $validatorMock->shouldReceive('checkRequiredParamsForArray');
    $validatorExpectation->atLeast()->once();

    $di = container();
    $di['validator'] = $validatorMock;
    $api->setDi($di);

    $api->setService($serviceMock);
    $result = $api->get_form($data);
    expect($result)->toBeArray();
});

test('gets form fields', function (): void {
    $api = new Admin();
    $data['form_id'] = 1;

    $serviceMock = Mockery::mock(Service::class);
    /** @var Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('getFormFields');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    /** @var Mockery\Expectation $validatorExpectation */
    $validatorExpectation = $validatorMock->shouldReceive('checkRequiredParamsForArray');
    $validatorExpectation->atLeast()->once();

    $di = container();
    $di['validator'] = $validatorMock;
    $api->setDi($di);

    $api->setService($serviceMock);
    $result = $api->get_form_fields($data);
    expect($result)->toBeArray();
});

test('gets a field', function (): void {
    $api = new Admin();
    $data['id'] = 3;

    $serviceMock = Mockery::mock(Service::class);
    /** @var Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('getField');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    /** @var Mockery\Expectation $validatorExpectation */
    $validatorExpectation = $validatorMock->shouldReceive('checkRequiredParamsForArray');
    $validatorExpectation->atLeast()->once();

    $di = container();
    $di['validator'] = $validatorMock;
    $api->setDi($di);

    $api->setService($serviceMock);

    $result = $api->get_field($data);
    expect($result)->toBeArray();
});

test('gets all forms', function (): void {
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    /** @var Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('getForms');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $api->setService($serviceMock);

    $result = $api->get_forms();
    expect($result)->toBeArray();
});

test('deletes a form', function (): void {
    $api = new Admin();
    $data['id'] = 1;

    $serviceMock = Mockery::mock(Service::class);
    /** @var Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('removeForm');
    $expectation->atLeast()->once();
    $expectation->andReturn(true);

    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    /** @var Mockery\Expectation $validatorExpectation */
    $validatorExpectation = $validatorMock->shouldReceive('checkRequiredParamsForArray');
    $validatorExpectation->atLeast()->once();

    $di = container();
    $di['validator'] = $validatorMock;
    $api->setDi($di);

    $api->setService($serviceMock);

    $result = $api->delete_form($data);
    expect($result)->toBeTrue();
});

test('deletes a field', function (): void {
    $api = new Admin();
    $data['id'] = 1;

    $serviceMock = Mockery::mock(Service::class);
    /** @var Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('removeField');
    $expectation->atLeast()->once();
    $expectation->andReturn(true);

    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    /** @var Mockery\Expectation $validatorExpectation */
    $validatorExpectation = $validatorMock->shouldReceive('checkRequiredParamsForArray');
    $validatorExpectation->atLeast()->once();

    $di = container();
    $di['validator'] = $validatorMock;
    $api->setDi($di);

    $api->setService($serviceMock);

    $result = $api->delete_field($data);
    expect($result)->toBeTrue();
});

test('updates a field', function (): void {
    $api = new Admin();
    $updatedFieldId = 1;
    $data = [
        'id' => $updatedFieldId,
        'options' => ['sameValue'],
    ];

    $serviceMock = Mockery::mock(Service::class);
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $serviceMock->shouldReceive('updateField');
    $expectation1->atLeast()->once();
    $expectation1->andReturn($updatedFieldId);
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $serviceMock->shouldReceive('isArrayUnique');
    $expectation2->atLeast()->once();
    $expectation2->andReturn(true);

    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    /** @var Mockery\Expectation $validatorExpectation */
    $validatorExpectation = $validatorMock->shouldReceive('checkRequiredParamsForArray');
    $validatorExpectation->atLeast()->once();

    $di = container();
    $di['validator'] = $validatorMock;
    $api->setDi($di);

    $api->setService($serviceMock);

    $result = $api->update_field($data);
    expect($result)->toBeInt()->toBe($updatedFieldId);
});

test('gets form pairs', function (): void {
    $api = new Admin();
    $data = [];
    $serviceMock = Mockery::mock(Service::class);
    /** @var Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('getFormPairs');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $api->setService($serviceMock);

    $result = $api->get_pairs($data);
    expect($result)->toBeArray();
});

test('copies a form', function (): void {
    $api = new Admin();
    $newFormId = 2;
    $data = [
        'form_id' => 1,
        'name' => 'testForm',
    ];

    $serviceMock = Mockery::mock(Service::class);
    /** @var Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('duplicateForm');
    $expectation->atLeast()->once();
    $expectation->andReturn($newFormId);

    $api->setService($serviceMock);
    $result = $api->copy_form($data);
    expect($result)->toBeInt()->toBe($newFormId);
});

test('throws exception when copying form without id', function (): void {
    $api = new Admin();
    $data = [];

    expect(fn () => $api->copy_form($data))
        ->toThrow(FOSSBilling\InformationException::class, 'Form id was not passed');
});

test('throws exception when copying form without name', function (): void {
    $api = new Admin();
    $data = ['form_id' => 1];

    expect(fn () => $api->copy_form($data))
        ->toThrow(FOSSBilling\InformationException::class, 'Form name was not passed');
});

test('updates form settings', function (): void {
    $api = new Admin();
    $data = [
        'form_id' => 1,
        'form_name' => 'testForm',
        'type' => 'default',
    ];

    $serviceMock = Mockery::mock(Service::class);
    /** @var Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('updateFormSettings');
    $expectation->atLeast()->once();
    $expectation->andReturn(true);

    $api->setService($serviceMock);
    $result = $api->update_form_settings($data);
    expect($result)->toBeTrue();
});

test('throws exception when updating form settings with missing fields', function ($missingField, $exceptionMessage): void {
    $api = new Admin();
    $data = [
        'form_id' => 1,
        'form_name' => 'testForm',
        'type' => 'customType',
    ];
    unset($data[$missingField]);

    expect(fn () => $api->update_form_settings($data))
        ->toThrow(FOSSBilling\Exception::class, $exceptionMessage);
})->with([
    ['form_id', 'Form id was not passed'],
    ['form_name', 'Form name was not passed'],
    ['type', 'Form type was not passed'],
    ['', 'Field type not supported'],
]);
