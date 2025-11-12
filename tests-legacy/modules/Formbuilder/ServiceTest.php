<?php

namespace Box\Mod\Formbuilder;

class ServiceTest extends \BBTestCase
{
    /**
     * @var Service
     */
    protected $service;

    public function setup(): void
    {
        $this->service = new Service();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetFormFieldsTypes(): void
    {
        $expected = [
            'text' => 'Text input',
            'select' => 'Dropdown',
            'radio' => 'Radio select',
            'checkbox' => 'Checkbox',
            'textarea' => 'Text area',
        ];

        $result = $this->service->getFormFieldsTypes();
        $this->assertEquals($expected, $result);
    }

    public static function typeValidationData(): array
    {
        return [
            ['select', true],
            ['custom', false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('typeValidationData')]
    public function testtypeValidation(string $type, bool $expected): void
    {
        $result = $this->service->typeValidation($type);
        $this->assertEquals($expected, $result);
    }

    public static function isArrayUniqueData(): array
    {
        return [
            [['sameValue', 'sameValue'], false],
            [['sameValue', 'DiffValue'], true],
            [[], true],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('isArrayUniqueData')]
    public function testisArrayUnique(array $data, bool $expected): void
    {
        $result = $this->service->isArrayUnique($data);
        $this->assertEquals($expected, $result);
    }

    public function testaddNewForm(): void
    {
        $newFormId = 1;
        $data = [
            'name' => 'testName',
            'style' => [],
        ];

        $model = new \Model_Form();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($model);

        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($newFormId);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->addNewForm($data);

        $this->assertIsInt($result);
        $this->assertEquals($newFormId, $result);
    }

    public function testaddNewField(): void
    {
        $newFieldId = 1;
        $data = [
            'form_id' => 1,
            'type' => 'select',
        ];

        $model = new \Model_FormField();
        $model->loadBean(new \DummyBean());
        $model->id = 2;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($model);

        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($newFieldId);

        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn(0);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->addNewField($data);

        $this->assertIsInt($result);
        $this->assertEquals($newFieldId, $result);
    }

    public static function updateFieldTypeData(): array
    {
        return [
            ['select'],
            ['textarea'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('updateFieldTypeData')]
    public function testupdateField(string $fieldType): void
    {
        $updateFIeldId = 2;
        $data = [
            'id' => $updateFIeldId,
            'form_id' => 1,
            'type' => $fieldType,
            'default_value' => 'defaultTestValue',
            'values' => ['test'],
            'labels' => ['labels'],
            'name' => 'testFIeld',
            'textarea_size' => [64], // @TODO WHY ARRAY??
            'textarea_option' => [''], // textarea_size & textarea_option should have an equal number of elements
        ];

        $model = new \Model_FormField();
        $model->loadBean(new \DummyBean());

        $modelArray = [
            'id' => $updateFIeldId,
            'form_id' => 1,
            'options' => '{"hidden":"hidden"}',
        ];

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($model);

        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($updateFIeldId);

        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->willReturn($modelArray);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $this->service->setDi($di);

        $result = $this->service->updateField($data);
        $this->assertIsInt($result);
        $this->assertEquals($updateFIeldId, $result);
    }

    public function testupdateFieldExists(): void
    {
        $data = [
            'id' => 2,
            'form_id' => 1,
            'name' => 'testFIeld',
            'type' => 'select',
        ];

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['formFieldNameExists', 'getField'])
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('formFieldNameExists')
            ->willReturn(true);

        $serviceMock->expects($this->atLeastOnce())
            ->method('getField');

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(7628);
        $this->expectExceptionMessage('Unfortunately field with this name exists in this form already. Form must have different field names.');
        $serviceMock->updateField($data);
    }

    public function testupdateFieldValuesNotUnique(): void
    {
        $data = [
            'id' => 2,
            'form_id' => 1,
            'type' => 'select',
            'default_value' => 'defaultTestValue',
            'values' => ['test', 'test'],
            'labels' => ['labels'],
            'name' => 'testFIeld',
        ];

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['formFieldNameExists', 'getField'])
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('formFieldNameExists')
            ->willReturn(false);

        $serviceMock->expects($this->atLeastOnce())
            ->method('getField');

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(1597);
        $this->expectExceptionMessage(ucfirst($data['type']) . ' values must be unique');
        $serviceMock->updateField($data);
    }

    public function testupdateFieldLabelsNotUnique(): void
    {
        $data = [
            'id' => 2,
            'form_id' => 1,
            'type' => 'select',
            'default_value' => 'defaultTestValue',
            'values' => ['test'],
            'labels' => ['labels', 'labels'],
            'name' => 'testFIeld',
        ];

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['formFieldNameExists', 'getField'])
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('formFieldNameExists')
            ->willReturn(false);

        $serviceMock->expects($this->atLeastOnce())
            ->method('getField');

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(1598);
        $this->expectExceptionMessage(ucfirst($data['type']) . ' labels must be unique');
        $serviceMock->updateField($data);
    }

    public function testupdateFieldTextAreaSizeException(): void
    {
        $data = [
            'id' => 2,
            'form_id' => 1,
            'type' => 'textarea',
            'default_value' => 'defaultTestValue',
            'values' => ['test'],
            'labels' => ['labels'],
            'name' => 'testFIeld',
            'textarea_size' => [''], // @TODO WHY ARRAY??
            'textarea_option' => [''], // textarea_size & textarea_option should have an equal number of elements
        ];

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['formFieldNameExists', 'getField'])
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('formFieldNameExists')
            ->willReturn(false);

        $serviceMock->expects($this->atLeastOnce())
            ->method('getField');

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(3510);
        $this->expectExceptionMessage('Textarea size options must be integer values');
        $serviceMock->updateField($data);
    }

    public function testgetForm(): void
    {
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
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->willReturn($modelArray);
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn($getAllResult);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $formId = 1;

        $this->service->setDi($di);
        $result = $this->service->getForm($formId);
        $this->assertIsArray($result);
    }

    public function testgetFormFields(): void
    {
        $formId = 1;
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->getFormFields($formId);
        $this->assertIsArray($result);
    }

    public function testgetFormFieldsCount(): void
    {
        $formId = 1;
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->getFormFieldsCount($formId);
        $this->assertIsArray($result);
    }

    public function testgetFormPairs(): void
    {
        $formId = 1;
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->getFormPairs();
        $this->assertIsArray($result);
    }

    public function testgetField(): void
    {
        $fieldId = 2;
        $modelArray = [
            'id' => 2,
            'options' => '{"options":["hidden"]}',
        ];

        $expectedArray = $modelArray;
        $expectedArray['options'] = json_decode($expectedArray['options'] ?? '');

        $model = new \Model_FormField();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->willReturn($modelArray);

        $di = new \Pimple\Container();
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->getField($fieldId);
        $this->assertIsArray($result);
        $this->assertEquals($expectedArray, $result);
    }

    public function testremoveForm(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->exactly(4))
            ->method('exec');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);
        $formId = 1;
        $result = $this->service->removeForm($formId);
        $this->assertTrue($result);
    }

    public function testremoveField(): void
    {
        $data = ['id' => 1];

        $model = new \Model_FormField();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);
        $result = $this->service->removeField($data);
        $this->assertTrue($result);
    }

    public function testformFieldNameExists(): void
    {
        $data = [
            'field_name' => 'testingName',
            'form_id' => 2,
            'field_id' => 10,
        ];

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(new \Model_FormField());

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->formFieldNameExists($data);
        $this->assertTrue($result);
    }

    public function testgetForms(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->getForms();
        $this->assertIsArray($result);
    }

    public function testduplicateForm(): void
    {
        $data = [
            'form_id' => 1,
            'name' => 'testForm',
        ];

        $newFormId = 3;
        $newFieldId = 4;
        $fields = [
            'options' => [],
        ];

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['getFormFields', 'addNewForm', 'addNewField'])
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getFormFields')
            ->willReturn($fields);

        $serviceMock->expects($this->atLeastOnce())
            ->method('addNewForm')
            ->willReturn($newFormId);

        $serviceMock->expects($this->atLeastOnce())
            ->method('addNewField')
            ->willReturn($newFieldId);

        $di = new \Pimple\Container();
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);
        $result = $serviceMock->duplicateForm($data);
        $this->assertIsInt($result);
        $this->assertEquals($newFormId, $result);
    }

    public function testupdateFormSettings(): void
    {
        $data = [
            'type' => 'default',
            'form_name' => 'testForm',
            'form_id' => 1,
        ];

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->exactly(2))
            ->method('exec');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);
        $result = $this->service->updateFormSettings($data);
        $this->assertTrue($result);
    }
}
