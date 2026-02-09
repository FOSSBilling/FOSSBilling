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
use Box\Mod\Formbuilder\Service;

beforeEach(function () {
    $this->service = new Service();
});

test('gets dependency injection container', function () {
    $di = container();
    $this->service->setDi($di);
    $getDi = $this->service->getDi();
    expect($getDi)->toBe($di);
});

test('gets form field types', function () {
    $expected = [
        'text' => 'Text input',
        'select' => 'Dropdown',
        'radio' => 'Radio select',
        'checkbox' => 'Checkbox',
        'textarea' => 'Text area',
    ];

    $result = $this->service->getFormFieldsTypes();
    expect($result)->toBe($expected);
});

test('validates field types', function (string $type, bool $expected) {
    $result = $this->service->typeValidation($type);
    expect($result)->toBe($expected);
})->with([
    ['select', true],
    ['custom', false],
]);

test('checks if array is unique', function (array $data, bool $expected) {
    $result = $this->service->isArrayUnique($data);
    expect($result)->toBe($expected);
})->with([
    [['sameValue', 'sameValue'], false],
    [['sameValue', 'DiffValue'], true],
    [[], true],
]);

test('adds a new form', function () {
    $newFormId = 1;
    $data = [
        'name' => 'testName',
        'style' => [],
    ];

    $model = new \Model_Form();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($model);
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($newFormId);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->service->setDi($di);

    $result = $this->service->addNewForm($data);
    expect($result)->toBeInt()->toBe($newFormId);
});

test('adds a new field', function () {
    $newFieldId = 1;
    $data = [
        'form_id' => 1,
        'type' => 'select',
    ];

    $model = new \Model_FormField();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = 2;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($model);
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($newFieldId);
    $dbMock->shouldReceive('getCell')
        ->atLeast()->once()
        ->andReturn(0);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->service->setDi($di);

    $result = $this->service->addNewField($data);
    expect($result)->toBeInt()->toBe($newFieldId);
});

test('updates a field', function (string $fieldType) {
    $updateFieldId = 2;
    $data = [
        'id' => $updateFieldId,
        'form_id' => 1,
        'type' => $fieldType,
        'default_value' => 'defaultTestValue',
        'values' => ['test'],
        'labels' => ['labels'],
        'name' => 'testField',
        'textarea_size' => [64],
        'textarea_option' => [''],
    ];

    $model = new \Model_FormField();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $modelArray = [
        'id' => $updateFieldId,
        'form_id' => 1,
        'options' => '{"hidden":"hidden"}',
    ];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($model);
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($updateFieldId);
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);
    $dbMock->shouldReceive('toArray')
        ->atLeast()->once()
        ->andReturn($modelArray);
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray');
    $di['validator'] = $validatorMock;

    $this->service->setDi($di);

    $result = $this->service->updateField($data);
    expect($result)->toBeInt()->toBe($updateFieldId);
})->with([
    ['select'],
    ['textarea'],
]);

test('throws exception when updating field with existing name', function () {
    $data = [
        'id' => 2,
        'form_id' => 1,
        'name' => 'testField',
        'type' => 'select',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('formFieldNameExists')
        ->atLeast()->once()
        ->andReturn(true);
    $serviceMock->shouldReceive('getField');

    expect(fn () => $serviceMock->updateField($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Unfortunately field with this name exists in this form already. Form must have different field names.');
});

test('throws exception when field values are not unique', function () {
    $data = [
        'id' => 2,
        'form_id' => 1,
        'type' => 'select',
        'default_value' => 'defaultTestValue',
        'values' => ['test', 'test'],
        'labels' => ['labels'],
        'name' => 'testField',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('formFieldNameExists')
        ->atLeast()->once()
        ->andReturn(false);
    $serviceMock->shouldReceive('getField');

    expect(fn () => $serviceMock->updateField($data))
        ->toThrow(\FOSSBilling\Exception::class, ucfirst($data['type']) . ' values must be unique');
});

test('throws exception when field labels are not unique', function () {
    $data = [
        'id' => 2,
        'form_id' => 1,
        'type' => 'select',
        'default_value' => 'defaultTestValue',
        'values' => ['test'],
        'labels' => ['labels', 'labels'],
        'name' => 'testField',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('formFieldNameExists')
        ->atLeast()->once()
        ->andReturn(false);
    $serviceMock->shouldReceive('getField');

    expect(fn () => $serviceMock->updateField($data))
        ->toThrow(\FOSSBilling\Exception::class, ucfirst($data['type']) . ' labels must be unique');
});

test('throws exception when textarea size is invalid', function () {
    $data = [
        'id' => 2,
        'form_id' => 1,
        'type' => 'textarea',
        'default_value' => 'defaultTestValue',
        'values' => ['test'],
        'labels' => ['labels'],
        'name' => 'testField',
        'textarea_size' => [''],
        'textarea_option' => [''],
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('formFieldNameExists')
        ->atLeast()->once()
        ->andReturn(false);
    $serviceMock->shouldReceive('getField');

    expect(fn () => $serviceMock->updateField($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Textarea size options must be integer values');
});

test('gets a form', function () {
    $modelArray = [
        'style' => '',
        'id' => 1,
    ];
    $getAllResult = [
        [
            'options' => '{"options":["hidden"]}',
            'default_value' => 'TestingValue',
        ],
    ];

    $model = new \Model_Form();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);
    $dbMock->shouldReceive('toArray')
        ->atLeast()->once()
        ->andReturn($modelArray);
    $dbMock->shouldReceive('getAll')
        ->atLeast()->once()
        ->andReturn($getAllResult);

    $di = container();
    $di['db'] = $dbMock;

    $formId = 1;

    $this->service->setDi($di);
    $result = $this->service->getForm($formId);
    expect($result)->toBeArray();
});

test('gets form fields', function () {
    $formId = 1;
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;

    $this->service->setDi($di);
    $result = $this->service->getFormFields($formId);
    expect($result)->toBeArray();
});

test('gets form fields count', function () {
    $formId = 1;
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getCell')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;

    $this->service->setDi($di);
    $result = $this->service->getFormFieldsCount($formId);
    expect($result)->toBeArray();
});

test('gets form pairs', function () {
    $formId = 1;
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAssoc')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;

    $this->service->setDi($di);
    $result = $this->service->getFormPairs();
    expect($result)->toBeArray();
});

test('gets a field', function () {
    $fieldId = 2;
    $modelArray = [
        'id' => 2,
        'options' => '{"options":["hidden"]}',
    ];

    $expectedArray = $modelArray;
    $expectedArray['options'] = json_decode($expectedArray['options'] ?? '');

    $model = new \Model_FormField();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);
    $dbMock->shouldReceive('toArray')
        ->atLeast()->once()
        ->andReturn($modelArray);

    $di = container();
    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray');
    $di['validator'] = $validatorMock;
    $di['db'] = $dbMock;

    $this->service->setDi($di);
    $result = $this->service->getField($fieldId);
    expect($result)->toBeArray();
    expect($result['id'])->toBe($fieldId);
});

test('removes a form', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('exec')
        ->times(4);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->service->setDi($di);
    $formId = 1;
    $result = $this->service->removeForm($formId);
    expect($result)->toBeTrue();
});

test('removes a field', function () {
    $data = ['id' => 1];

    $model = new \Model_FormField();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);
    $dbMock->shouldReceive('trash')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->service->setDi($di);
    $result = $this->service->removeField($data);
    expect($result)->toBeTrue();
});

test('checks if form field name exists', function () {
    $data = [
        'field_name' => 'testingName',
        'form_id' => 2,
        'field_id' => 10,
    ];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(new \Model_FormField());

    $di = container();
    $di['db'] = $dbMock;

    $this->service->setDi($di);
    $result = $this->service->formFieldNameExists($data);
    expect($result)->toBeTrue();
});

test('gets all forms', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;

    $this->service->setDi($di);
    $result = $this->service->getForms();
    expect($result)->toBeArray();
});

test('duplicates a form', function () {
    $data = [
        'form_id' => 1,
        'name' => 'testForm',
    ];

    $newFormId = 3;
    $newFieldId = 4;
    $fields = [
        'options' => [],
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getFormFields')
        ->atLeast()->once()
        ->andReturn($fields);
    $serviceMock->shouldReceive('addNewForm')
        ->atLeast()->once()
        ->andReturn($newFormId);
    $serviceMock->shouldReceive('addNewField')
        ->atLeast()->once()
        ->andReturn($newFieldId);

    $di = container();
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $result = $serviceMock->duplicateForm($data);
    expect($result)->toBeInt()->toBe($newFormId);
});

test('updates form settings', function () {
    $data = [
        'type' => 'default',
        'form_name' => 'testForm',
        'form_id' => 1,
    ];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('exec')
        ->times(2);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->service->setDi($di);
    $result = $this->service->updateFormSettings($data);
    expect($result)->toBeTrue();
});
