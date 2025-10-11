<?php

namespace Box\Mod\Formbuilder\Api;

class AdminTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Formbuilder\Service
     */
    protected $service;

    /**
     * @var Admin
     */
    protected $api;

    public function getServiceMock(): \PHPUnit\Framework\MockObject\MockObject
    {
        return $this->getMockBuilder('\\' . \Box\Mod\Formbuilder\Service::class)->getMock();
    }

    public function setup(): void
    {
        $this->service = new \Box\Mod\Formbuilder\Service();
        $this->api = new Admin();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testcreateForm(): void
    {
        $data = ['name' => 'testForm'];
        $createdFormId = 1;

        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('addNewForm')
            ->willReturn($createdFormId);

        $this->api->setService($serviceMock);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $result = $this->api->create_form($data);
        $this->assertIsInt($result);
        $this->assertEquals($createdFormId, $result);
    }

    public function testcreateFormTypeIsNotInList(): void
    {
        $data = [
            'name' => 'testName',
            'type' => 'custom',
        ];
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Form style was not found in predefined list');
        $this->api->create_form($data);
    }

    public function testaddField(): void
    {
        $data = [
            'type' => 'text',
            'options' => ['sameValue'],
            'form_id' => 1,
        ];
        $newFieldId = 2;

        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('typeValidation')
            ->willReturn(true);

        $serviceMock->expects($this->atLeastOnce())
            ->method('isArrayUnique')
            ->willReturn(true);

        $serviceMock->expects($this->atLeastOnce())
            ->method('addNewField')
            ->willReturn($newFieldId);

        $this->api->setService($serviceMock);

        $result = $this->api->add_field($data);
        $this->assertIsInt($result);
        $this->assertEquals($newFieldId, $result);
    }

    public function testaddFieldMissingType(): void
    {
        $data = [];
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(2684);
        $this->expectExceptionMessage('Form field type is invalid');
        $this->api->add_field($data);
    }

    public function testaddFieldOptionsNotUnique(): void
    {
        $data = [
            'type' => 'text',
            'options' => ['sameValue', 'sameValue'],
        ];

        $this->api->setService($this->service);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(3658);
        $this->expectExceptionMessage('This input type must have unique values');
        $this->api->add_field($data);
    }

    public function testaddFieldMissingFormId(): void
    {
        $data = [
            'type' => 'text',
            'options' => ['sameValue'],
        ];

        $this->api->setService($this->service);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(9846);
        $this->expectExceptionMessage('Form id was not passed');
        $this->api->add_field($data);
    }

    public function testgetForm(): void
    {
        $data['id'] = 1;

        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getForm')
            ->willReturn([]);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);
        $result = $this->api->get_form($data);
        $this->assertIsArray($result);
    }

    public function testgetFormFields(): void
    {
        $data['form_id'] = 1;

        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getFormFields')
            ->willReturn([]);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);
        $result = $this->api->get_form_fields($data);
        $this->assertIsArray($result);
    }

    public function testgetField(): void
    {
        $data['id'] = 3;

        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getField')
            ->willReturn([]);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->get_field($data);
        $this->assertIsArray($result);
    }

    public function testgetForms(): void
    {
        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getForms')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $result = $this->api->get_forms();
        $this->assertIsArray($result);
    }

    public function testdeleteForm(): void
    {
        $data['id'] = 1;

        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('removeForm')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->delete_form($data);
        $this->assertTrue($result);
    }

    public function testdeleteField(): void
    {
        $data['id'] = 1;

        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('removeField')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->delete_field($data);
        $this->assertTrue($result);
    }

    public function testupdateField(): void
    {
        $updatedFieldId = 1;
        $data = [
            'id' => $updatedFieldId,
            'options' => ['sameValue'],
        ];

        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateField')
            ->willReturn($updatedFieldId);
        $serviceMock->expects($this->atLeastOnce())
            ->method('isArrayUnique')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->update_field($data);
        $this->assertIsInt($result);
        $this->assertEquals($updatedFieldId, $result);
    }

    public function testgetPairs(): void
    {
        $data = [];
        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getFormPairs')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $result = $this->api->get_pairs($data);
        $this->assertIsArray($result);
    }

    public function testcopyForm(): void
    {
        $newFormId = 2;
        $data = [
            'form_id' => 1,
            'name' => 'testForm',
        ];

        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('duplicateForm')
            ->willReturn($newFormId);

        $this->api->setService($serviceMock);
        $result = $this->api->copy_form($data);
        $this->assertIsInt($result);
        $this->assertEquals($newFormId, $result);
    }

    public function testcopyFormMissingId(): void
    {
        $data = [];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(9958);
        $this->expectExceptionMessage('Form id was not passed');
        $this->api->copy_form($data);
    }

    public function testcopyFormMissingName(): void
    {
        $data = ['form_id' => 1];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(9842);
        $this->expectExceptionMessage('Form name was not passed');
        $this->api->copy_form($data);
    }

    public function testupdateFormSettings(): void
    {
        $data = [
            'form_id' => 1,
            'form_name' => 'testForm',
            'type' => 'default',
        ];

        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateFormSettings')
            ->willReturn(true);

        $this->api->setService($serviceMock);
        $result = $this->api->update_form_settings($data);
        $this->assertTrue($result);
    }

    public static function form_settings_data(): array
    {
        return [
            ['form_id', 'Form id was not passed', 1654],
            ['form_name', 'Form name was not passed', 9241],
            ['type', 'Form type was not passed', 3794],
            ['', 'Field type not supported', 3207],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('form_settings_data')]
    public function testupdateFormSettingsExceptions(string $missingField, string $exceptionMessage, int $exceptionCode): void
    {
        $data = [
            'form_id' => 1,
            'form_name' => 'testForm',
            'type' => 'customType',
        ];
        unset($data[$missingField]);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage($exceptionMessage);
        $this->api->update_form_settings($data);
    }
}
