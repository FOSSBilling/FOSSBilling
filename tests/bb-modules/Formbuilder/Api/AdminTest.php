<?php


namespace Box\Mod\Formbuilder\Api;


class AdminTest extends \BBTestCase {

    /**
     * @var \Box\Mod\Formbuilder\Service
     */
    protected $service = null;

    /**
     * @var \Box\Mod\Formbuilder\Api\Admin
     */
    protected $api = null;

    public function getServiceMock()
    {
        return $this->getMockBuilder('\Box\Mod\Formbuilder\Service')->getMock();
    }

    public function setup(): void
    {
        $this->service = new \Box\Mod\Formbuilder\Service();
        $this->api = new \Box\Mod\Formbuilder\Api\Admin();
    }


    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testcreate_form()
    {
        $data = array('name' => 'testForm');
        $createdFormId = 1;

        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('addNewForm')
            ->will($this->returnValue($createdFormId));

        $this->api->setService($serviceMock);

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $result = $this->api->create_form($data);
        $this->assertIsInt($result);
        $this->assertEquals($createdFormId, $result);
    }

    public function testcreate_formTypeIsNotInList()
    {
        $data = array(
            'name' => 'testName',
            'type' => 'custom',
        );
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Form style was not found in predefined list', 3657);
        $this->api->create_form($data);
    }

    public function testadd_field()
    {
        $data = array(
            'type' => 'text',
            'options' => array('sameValue'),
            'form_id' => 1,
        );
        $newFieldId = 2;

        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('typeValidation')
            ->will($this->returnValue(true));

        $serviceMock->expects($this->atLeastOnce())
            ->method('isArrayUnique')
            ->will($this->returnValue(true));

        $serviceMock->expects($this->atLeastOnce())
            ->method('addNewField')
            ->will($this->returnValue($newFieldId));

        $this->api->setService($serviceMock);

        $result = $this->api->add_field($data);
        $this->assertIsInt($result);
        $this->assertEquals($newFieldId, $result);
    }

    public function testadd_fieldMissingType()
    {
        $data = array();
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(2684);
        $this->expectExceptionMessage('Form field type is not valid');
        $this->api->add_field($data);
    }

    public function testadd_fieldOptionsNotUnique()
    {
        $data = array(
            'type' => 'text',
            'options' => array('sameValue', 'sameValue'),
        );

        $this->api->setService($this->service);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(3658);
        $this->expectExceptionMessage('This input type must have unique values');
        $this->api->add_field($data);
    }

    public function testadd_fieldMissingFormId()
    {
        $data = array(
            'type' => 'text',
            'options' => array('sameValue'),
        );

        $this->api->setService($this->service);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(9846);
        $this->expectExceptionMessage('Form id was not passed');
        $this->api->add_field($data);
    }

    public function testget_form()
    {
        $data['id'] = 1;

        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getForm')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);
        $result = $this->api->get_form($data);
        $this->assertIsArray($result);
    }

    public function testget_form_fields()
    {
        $data['form_id'] = 1;

        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getFormFields')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);
        $result = $this->api->get_form_fields($data);
        $this->assertIsArray($result);
    }

    public function testget_field()
    {
        $data['id'] = 3;

        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getField')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->get_field($data);
        $this->assertIsArray($result);
    }

    public function testget_forms()
    {
        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getForms')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);

        $result = $this->api->get_forms();
        $this->assertIsArray($result);
    }

    public function testdelete_form()
    {
        $data['id'] = 1;

        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('removeForm')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->delete_form($data);
        $this->assertTrue($result);
    }

    public function testdelete_field()
    {
        $data['id'] = 1;

        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('removeField')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->delete_field($data);
        $this->assertTrue($result);
    }

    public function testupdate_field()
    {
        $updatedFieldId = 1;
        $data = array (
            'id' => $updatedFieldId,
            'options' => array('sameValue'),
        );

        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateField')
            ->will($this->returnValue($updatedFieldId));
        $serviceMock->expects($this->atLeastOnce())
            ->method('isArrayUnique')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->update_field($data);
        $this->assertIsInt($result);
        $this->assertEquals($updatedFieldId, $result);
    }

    public function testget_pairs()
    {
        $data = array();
        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getFormPairs')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);

        $result = $this->api->get_pairs($data);
        $this->assertIsArray($result);
    }

    public function testcopy_form()
    {
        $newFormId = 2;
        $data = array(
            'form_id' => 1,
            'name' => 'testForm',
        );

        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('dublicateForm')
            ->will($this->returnValue($newFormId));

        $this->api->setService($serviceMock);
        $result = $this->api->copy_form($data);
        $this->assertIsInt($result);
        $this->assertEquals($newFormId, $result);
    }

    public function testcopy_formMisssingId()
    {
        $data = array();

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(9958);
        $this->expectExceptionMessage('Form id was not passed');
        $this->api->copy_form($data);
    }

    public function testcopy_formMisssingName()
    {
        $data = array('form_id' => 1);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(9842);
        $this->expectExceptionMessage('Form name was not passed');
        $this->api->copy_form($data);
    }

    public function testupdate_form_settings()
    {
        $data = array(
            'form_id' => 1,
            'form_name' => 'testForm',
            'type' => 'default',

        );

        $serviceMock = $this->getServiceMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateFormSettings')
            ->will($this->returnValue(true));

        $this->api->setService($serviceMock);
        $result = $this->api->update_form_settings($data);
        $this->assertTrue($result);
    }


    public function form_settings_data()
    {
        return array(
            array('form_id', 'Form id was not passed', 1654),
            array('form_name', 'Form name was not passed', 9241),
            array('type', 'Form type was not passed', 3794),
            array('', 'Field type not supported', 3207),
        );
    }

    /**
     * @dataProvider form_settings_data
     */
    public function testupdate_form_settingsExceptions($missingField, $exceptionMessage, $exceptionCode)
    {
        $data = array(
            'form_id' => 1,
            'form_name' => 'testForm',
            'type' => 'customType',

        );
        unset($data[ $missingField ]);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage($exceptionMessage, $exceptionCode);
        $this->api->update_form_settings($data);
    }









}
 