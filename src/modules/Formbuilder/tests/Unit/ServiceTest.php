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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;

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
        'text' => 'Text Input',
        'url' => 'URL Input',
        'select' => 'Dropdown',
        'radio' => 'Radio Select',
        'checkbox' => 'Checkbox',
        'textarea' => 'Text Area',
    ];

    $result = $service->getFormFieldsTypes();
    expect($result)->toBe($expected);
});

test('validates field types', function (string $type, bool $expected): void {
    $service = new Service();
    $result = $service->isValidFieldType($type);
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

    $dbal = Mockery::mock(Connection::class);
    $dbal->shouldReceive('insert')->once()->with('form', Mockery::on(fn (array $row): bool => $row['name'] === 'testName'));
    $dbal->shouldReceive('lastInsertId')->once()->andReturn($newFormId);

    $di = container();
    $di['dbal'] = $dbal;
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

    $countResult = Mockery::mock(Result::class);
    $countResult->shouldReceive('fetchOne')->once()->andReturn(0);

    $dbal = Mockery::mock(Connection::class);
    $dbal->shouldReceive('executeQuery')->once()->andReturn($countResult);
    $dbal->shouldReceive('insert')->once()->with('form_field', Mockery::on(fn (array $row): bool => $row['form_id'] === 1 && $row['type'] === 'select'));
    $dbal->shouldReceive('lastInsertId')->once()->andReturn($newFieldId);

    $di = container();
    $di['dbal'] = $dbal;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    $result = $service->addNewField($data);
    expect($result)->toBeInt()->toBe($newFieldId);
});

test('updates a field', function (string $fieldType, int|string $fieldId): void {
    $service = new Service();
    $updateFieldId = 2;
    $data = [
        'id' => $fieldId,
        'form_id' => 1,
        'type' => $fieldType,
        'default_value' => 'defaultTestValue',
        'values' => ['test'],
        'labels' => ['labels'],
        'name' => 'testField',
        'textarea_size' => [64],
        'textarea_option' => [''],
    ];

    $modelArray = [
        'id' => $updateFieldId,
        'form_id' => 1,
        'options' => '{"hidden":"hidden"}',
    ];

    $fieldResult = Mockery::mock(Result::class);
    $fieldResult->shouldReceive('fetchAssociative')->once()->andReturn($modelArray);
    $existsResult = Mockery::mock(Result::class);
    $existsResult->shouldReceive('fetchOne')->once()->andReturn(0);

    $dbal = Mockery::mock(Connection::class);
    $dbal->shouldReceive('executeQuery')->twice()->andReturn($fieldResult, $existsResult);
    $dbal->shouldReceive('update')->once()->with('form_field', Mockery::type('array'), ['id' => $updateFieldId]);

    $di = container();
    $di['dbal'] = $dbal;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray');
    $di['validator'] = $validatorMock;

    $service->setDi($di);

    $result = $service->updateField($data);
    expect($result)->toBeInt()->toBe($updateFieldId);
})->with([
    ['select', 2],
    ['radio', '2'],
    ['textarea', 2],
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

    $formResult = Mockery::mock(Result::class);
    $formResult->shouldReceive('fetchAssociative')->once()->andReturn($modelArray);
    $fieldsResult = Mockery::mock(Result::class);
    $fieldsResult->shouldReceive('fetchAllAssociative')->once()->andReturn($getAllResult);

    $dbal = Mockery::mock(Connection::class);
    $dbal->shouldReceive('executeQuery')->twice()->andReturn($formResult, $fieldsResult);

    $di = container();
    $di['dbal'] = $dbal;

    $formId = 1;

    $service->setDi($di);
    $result = $service->getForm($formId);
    expect($result)->toBeArray();
});

test('gets form fields', function (): void {
    $service = new Service();
    $formId = 1;
    $result = Mockery::mock(Result::class);
    $result->shouldReceive('fetchAllAssociative')->once()->andReturn([]);

    $dbal = Mockery::mock(Connection::class);
    $dbal->shouldReceive('executeQuery')->once()->andReturn($result);

    $di = container();
    $di['dbal'] = $dbal;

    $service->setDi($di);
    $result = $service->getFormFields($formId);
    expect($result)->toBeArray();
});

test('gets form fields count', function (): void {
    $service = new Service();
    $formId = 1;
    $dbalResult = Mockery::mock(Result::class);
    $dbalResult->shouldReceive('fetchOne')->once()->andReturn(0);

    $dbal = Mockery::mock(Connection::class);
    $dbal->shouldReceive('executeQuery')->once()->andReturn($dbalResult);

    $di = container();
    $di['dbal'] = $dbal;

    $service->setDi($di);
    $result = $service->getFormFieldsCount($formId);
    expect($result)->toBeInt()->toBe(0);
});

test('gets form pairs', function (): void {
    $service = new Service();
    $dbalResult = Mockery::mock(Result::class);
    $dbalResult->shouldReceive('fetchAllAssociative')->once()->andReturn([
        ['id' => 1, 'name' => 'Default'],
    ]);

    $dbal = Mockery::mock(Connection::class);
    $dbal->shouldReceive('executeQuery')->once()->andReturn($dbalResult);

    $di = container();
    $di['dbal'] = $dbal;

    $service->setDi($di);
    $result = $service->getFormPairs();
    expect($result)->toBe([1 => 'Default']);
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

    $dbalResult = Mockery::mock(Result::class);
    $dbalResult->shouldReceive('fetchAssociative')->once()->andReturn($modelArray);

    $dbal = Mockery::mock(Connection::class);
    $dbal->shouldReceive('executeQuery')->once()->andReturn($dbalResult);

    $di = container();
    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray');
    $di['validator'] = $validatorMock;
    $di['dbal'] = $dbal;

    $service->setDi($di);
    $result = $service->getField($fieldId);
    expect($result)->toBeArray();
    expect($result['id'])->toBe($fieldId);
});

test('removes a form', function (): void {
    $service = new Service();
    $dbal = Mockery::mock(Connection::class);
    $dbal->shouldReceive('executeStatement')->times(4)->andReturn(1);

    $di = container();
    $di['dbal'] = $dbal;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);
    $formId = 1;
    $result = $service->removeForm($formId);
    expect($result)->toBeTrue();
});

test('removes a field', function (): void {
    $service = new Service();
    $data = ['id' => 1];

    $dbal = Mockery::mock(Connection::class);
    $dbal->shouldReceive('executeStatement')->once()->andReturn(1);

    $di = container();
    $di['dbal'] = $dbal;
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

    $dbalResult = Mockery::mock(Result::class);
    $dbalResult->shouldReceive('fetchOne')->once()->andReturn(1);

    $dbal = Mockery::mock(Connection::class);
    $dbal->shouldReceive('executeQuery')->once()->andReturn($dbalResult);

    $di = container();
    $di['dbal'] = $dbal;

    $service->setDi($di);
    $result = $service->formFieldNameExists($data);
    expect($result)->toBeTrue();
});

test('gets all forms', function (): void {
    $service = new Service();
    $dbalResult = Mockery::mock(Result::class);
    $dbalResult->shouldReceive('fetchAllAssociative')->once()->andReturn([]);

    $dbal = Mockery::mock(Connection::class);
    $dbal->shouldReceive('executeQuery')->once()->andReturn($dbalResult);

    $di = container();
    $di['dbal'] = $dbal;

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

    $dbal = Mockery::mock(Connection::class);
    $dbal->shouldReceive('update')->once()->andReturn(1);

    $di = container();
    $di['dbal'] = $dbal;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);
    $result = $service->updateFormSettings($data);
    expect($result)->toBeTrue();
});
