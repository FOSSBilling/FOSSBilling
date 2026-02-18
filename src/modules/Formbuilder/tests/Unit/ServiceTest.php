<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Formbuilder\Service;

use function Tests\Helpers\container;

test('gets dependency injection container', function (): void {
    $service = new Service();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toBe($di);
});

test('gets form field types', function (): void {
    $service = new Service();
    $expected = [
        'text' => 'Text input',
        'select' => 'Dropdown',
        'radio' => 'Radio select',
        'checkbox' => 'Checkbox',
        'textarea' => 'Text area',
    ];

    $result = $service->getFormFieldsTypes();
    expect($result)->toBe($expected);
});

test('validates field types', function (string $type, bool $expected): void {
    $service = new Service();
    $result = $service->typeValidation($type);
    expect($result)->toBe($expected);
})->with([
    ['select', true],
    ['custom', false],
]);

test('checks if array is unique', function (array $data, bool $expected): void {
    $service = new Service();
    $result = $service->isArrayUnique($data);
    expect($result)->toBe($expected);
})->with([
    [['sameValue', 'sameValue'], false],
    [['sameValue', 'DiffValue'], true],
    [[], true],
]);

test('adds a new form', function (): void {
    $service = new Service();
    $newFormId = 1;
    $data = [
        'name' => 'testName',
        'style' => [],
    ];

    $model = new Model_Form();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $dbMock->shouldReceive('dispense');
    $expectation1->atLeast()->once();
    $expectation1->andReturn($model);
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $dbMock->shouldReceive('store');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($newFormId);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    $result = $service->addNewForm($data);
    expect($result)->toBeInt()->toBe($newFormId);
});

test('adds a new field', function (): void {
    $service = new Service();
    $newFieldId = 1;
    $data = [
        'form_id' => 1,
        'type' => 'select',
    ];

    $model = new Model_FormField();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $model->id = 2;

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $dbMock->shouldReceive('dispense');
    $expectation1->atLeast()->once();
    $expectation1->andReturn($model);
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $dbMock->shouldReceive('store');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($newFieldId);
    /** @var Mockery\Expectation $expectation3 */
    $expectation3 = $dbMock->shouldReceive('getCell');
    $expectation3->atLeast()->once();
    $expectation3->andReturn(0);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    $result = $service->addNewField($data);
    expect($result)->toBeInt()->toBe($newFieldId);
});

test('updates a field', function (string $fieldType): void {
    $service = new Service();
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

    $model = new Model_FormField();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $modelArray = [
        'id' => $updateFieldId,
        'form_id' => 1,
        'options' => '{"hidden":"hidden"}',
    ];

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $dbMock->shouldReceive('dispense');
    $expectation1->atLeast()->once();
    $expectation1->andReturn($model);
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $dbMock->shouldReceive('store');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($updateFieldId);
    /** @var Mockery\Expectation $expectation3 */
    $expectation3 = $dbMock->shouldReceive('getExistingModelById');
    $expectation3->atLeast()->once();
    $expectation3->andReturn($model);
    /** @var Mockery\Expectation $expectation4 */
    $expectation4 = $dbMock->shouldReceive('toArray');
    $expectation4->atLeast()->once();
    $expectation4->andReturn($modelArray);
    /** @var Mockery\Expectation $expectation5 */
    $expectation5 = $dbMock->shouldReceive('findOne');
    $expectation5->atLeast()->once();
    $expectation5->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray');
    $di['validator'] = $validatorMock;

    $service->setDi($di);

    $result = $service->updateField($data);
    expect($result)->toBeInt()->toBe($updateFieldId);
})->with([
    ['select'],
    ['textarea'],
]);

test('throws exception when updating field with existing name', function (): void {
    $data = [
        'id' => 2,
        'form_id' => 1,
        'name' => 'testField',
        'type' => 'select',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $serviceMock->shouldReceive('formFieldNameExists');
    $expectation1->atLeast()->once();
    $expectation1->andReturn(true);
    $serviceMock->shouldReceive('getField');

    expect(fn () => $serviceMock->updateField($data))
        ->toThrow(FOSSBilling\Exception::class, 'Unfortunately field with this name exists in this form already. Form must have different field names.');
});

test('throws exception when field values are not unique', function (): void {
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
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $serviceMock->shouldReceive('formFieldNameExists');
    $expectation1->atLeast()->once();
    $expectation1->andReturn(false);
    $serviceMock->shouldReceive('getField');

    expect(fn () => $serviceMock->updateField($data))
        ->toThrow(FOSSBilling\Exception::class, ucfirst($data['type']) . ' values must be unique');
});

test('throws exception when field labels are not unique', function (): void {
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
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $serviceMock->shouldReceive('formFieldNameExists');
    $expectation1->atLeast()->once();
    $expectation1->andReturn(false);
    $serviceMock->shouldReceive('getField');

    expect(fn () => $serviceMock->updateField($data))
        ->toThrow(FOSSBilling\Exception::class, ucfirst($data['type']) . ' labels must be unique');
});

test('throws exception when textarea size is invalid', function (): void {
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
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $serviceMock->shouldReceive('formFieldNameExists');
    $expectation1->atLeast()->once();
    $expectation1->andReturn(false);
    $serviceMock->shouldReceive('getField');

    expect(fn () => $serviceMock->updateField($data))
        ->toThrow(FOSSBilling\Exception::class, 'Textarea size options must be integer values');
});

test('gets a form', function (): void {
    $service = new Service();
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

    $model = new Model_Form();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $dbMock->shouldReceive('getExistingModelById');
    $expectation1->atLeast()->once();
    $expectation1->andReturn($model);
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $dbMock->shouldReceive('toArray');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($modelArray);
    /** @var Mockery\Expectation $expectation3 */
    $expectation3 = $dbMock->shouldReceive('getAll');
    $expectation3->atLeast()->once();
    $expectation3->andReturn($getAllResult);

    $di = container();
    $di['db'] = $dbMock;

    $formId = 1;

    $service->setDi($di);
    $result = $service->getForm($formId);
    expect($result)->toBeArray();
});

test('gets form fields', function (): void {
    $service = new Service();
    $formId = 1;
    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation */
    $expectation = $dbMock->shouldReceive('getAll');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);
    $result = $service->getFormFields($formId);
    expect($result)->toBeArray();
});

test('gets form fields count', function (): void {
    $service = new Service();
    $formId = 1;
    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation */
    $expectation = $dbMock->shouldReceive('getCell');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);
    $result = $service->getFormFieldsCount($formId);
    expect($result)->toBeArray();
});

test('gets form pairs', function (): void {
    $service = new Service();
    $formId = 1;
    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation */
    $expectation = $dbMock->shouldReceive('getAssoc');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);
    $result = $service->getFormPairs();
    expect($result)->toBeArray();
});

test('gets a field', function (): void {
    $service = new Service();
    $fieldId = 2;
    $modelArray = [
        'id' => 2,
        'options' => '{"options":["hidden"]}',
    ];

    $expectedArray = $modelArray;
    $expectedArray['options'] = json_decode($expectedArray['options']);

    $model = new Model_FormField();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $dbMock->shouldReceive('getExistingModelById');
    $expectation1->atLeast()->once();
    $expectation1->andReturn($model);
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $dbMock->shouldReceive('toArray');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($modelArray);

    $di = container();
    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray');
    $di['validator'] = $validatorMock;
    $di['db'] = $dbMock;

    $service->setDi($di);
    $result = $service->getField($fieldId);
    expect($result)->toBeArray();
    expect($result['id'])->toBe($fieldId);
});

test('removes a form', function (): void {
    $service = new Service();
    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation */
    $expectation = $dbMock->shouldReceive('exec');
    $expectation->times(4);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);
    $formId = 1;
    $result = $service->removeForm($formId);
    expect($result)->toBeTrue();
});

test('removes a field', function (): void {
    $service = new Service();
    $data = ['id' => 1];

    $model = new Model_FormField();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $dbMock->shouldReceive('getExistingModelById');
    $expectation1->atLeast()->once();
    $expectation1->andReturn($model);
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $dbMock->shouldReceive('trash');
    $expectation2->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);
    $result = $service->removeField($data);
    expect($result)->toBeTrue();
});

test('checks if form field name exists', function (): void {
    $service = new Service();
    $data = [
        'field_name' => 'testingName',
        'form_id' => 2,
        'field_id' => 10,
    ];

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation */
    $expectation = $dbMock->shouldReceive('findOne');
    $expectation->atLeast()->once();
    $expectation->andReturn(new Model_FormField());

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);
    $result = $service->formFieldNameExists($data);
    expect($result)->toBeTrue();
});

test('gets all forms', function (): void {
    $service = new Service();
    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation */
    $expectation = $dbMock->shouldReceive('getAll');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);
    $result = $service->getForms();
    expect($result)->toBeArray();
});

test('duplicates a form', function (): void {
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
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $serviceMock->shouldReceive('getFormFields');
    $expectation1->atLeast()->once();
    $expectation1->andReturn($fields);
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $serviceMock->shouldReceive('addNewForm');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($newFormId);
    /** @var Mockery\Expectation $expectation3 */
    $expectation3 = $serviceMock->shouldReceive('addNewField');
    $expectation3->atLeast()->once();
    $expectation3->andReturn($newFieldId);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $result = $serviceMock->duplicateForm($data);
    expect($result)->toBeInt()->toBe($newFormId);
});

test('updates form settings', function (): void {
    $service = new Service();
    $data = [
        'type' => 'default',
        'form_name' => 'testForm',
        'form_id' => 1,
    ];

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation */
    $expectation = $dbMock->shouldReceive('exec');
    $expectation->times(2);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);
    $result = $service->updateFormSettings($data);
    expect($result)->toBeTrue();
});
