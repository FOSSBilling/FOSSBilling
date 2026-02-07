<?php

declare(strict_types=1);

namespace Box\Mod\Formbuilder\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class AdminTest extends \BBTestCase
{
    protected ?\Box\Mod\Formbuilder\Service $service;
    protected ?Admin $api;

    public function getServiceMock(): \PHPUnit\Framework\MockObject\MockObject
    {
        return $this->createMock(\Box\Mod\Formbuilder\Service::class);
    }

    public function setUp(): void
    {
        $this->service = new \Box\Mod\Formbuilder\Service();
        $this->api = new Admin();
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testCreateForm(): void
    {
        $data = ['name' => 'testForm'];
        $createdFormId = 1;

        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('addNewForm')
            ->willReturn($createdFormId);

        $this->api->setService($serviceMock);

        $di = $this->getDi();
        $this->api->setDi($di);

        $result = $this->api->create_form($data);
        $this->assertIsInt($result);
        $this->assertEquals($createdFormId, $result);
    }

    public function testCreateFormTypeIsNotInList(): void
    {
        $data = [
            'name' => 'testName',
            'type' => 'custom',
        ];

        $di = $this->getDi();
        $this->api->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Form style was not found in predefined list');
        $this->api->create_form($data);
    }

    public function testAddField(): void
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

    public function testAddFieldMissingType(): void
    {
        $data = [];
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(2684);
        $this->expectExceptionMessage('Form field type is invalid');
        $this->api->add_field($data);
    }

    public function testAddFieldOptionsNotUnique(): void
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

    public function testAddFieldMissingFormId(): void
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

    public function testGetForm(): void
    {
        $data['id'] = 1;

        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getForm')
            ->willReturn([]);

        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);
        $result = $this->api->get_form($data);
        $this->assertIsArray($result);
    }

    public function testGetFormFields(): void
    {
        $data['form_id'] = 1;

        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getFormFields')
            ->willReturn([]);

        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);
        $result = $this->api->get_form_fields($data);
        $this->assertIsArray($result);
    }

    public function testGetField(): void
    {
        $data['id'] = 3;

        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getField')
            ->willReturn([]);

        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->get_field($data);
        $this->assertIsArray($result);
    }

    public function testGetForms(): void
    {
        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getForms')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $result = $this->api->get_forms();
        $this->assertIsArray($result);
    }

    public function testDeleteForm(): void
    {
        $data['id'] = 1;

        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('removeForm')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->delete_form($data);
        $this->assertTrue($result);
    }

    public function testDeleteField(): void
    {
        $data['id'] = 1;

        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('removeField')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->delete_field($data);
        $this->assertTrue($result);
    }

    public function testUpdateField(): void
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

        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->update_field($data);
        $this->assertIsInt($result);
        $this->assertEquals($updatedFieldId, $result);
    }

    public function testGetPairs(): void
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

    public function testCopyForm(): void
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

    public function testCopyFormMissingId(): void
    {
        $data = [];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(9958);
        $this->expectExceptionMessage('Form id was not passed');
        $this->api->copy_form($data);
    }

    public function testCopyFormMissingName(): void
    {
        $data = ['form_id' => 1];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(9842);
        $this->expectExceptionMessage('Form name was not passed');
        $this->api->copy_form($data);
    }

    public function testUpdateFormSettings(): void
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

    #[DataProvider('form_settings_data')]
    public function testUpdateFormSettingsExceptions(string $missingField, string $exceptionMessage, int $exceptionCode): void
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
