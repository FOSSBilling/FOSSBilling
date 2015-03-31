<?php


namespace Box\Mod\Formbuilder;


class ServiceTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var \Box\Mod\Formbuilder\Service
     */
    protected $service = null;


    public function setup()
    {
        $this->service = new \Box\Mod\Formbuilder\Service();
    }


    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetFormFieldsTypes()
    {
        $expected = array(
            "text" => 'Text input',
            "select" => 'Dropdown',
            "radio" => 'Radio select',
            "checkbox" => 'Checkbox',
            "textarea" => 'Text area'
        );

        $result = $this->service->getFormFieldsTypes();
        $this->assertEquals($expected, $result);
    }

    public function typeValidationData()
    {
        return array(
            array('select', true),
            array('custom', false),
        );
    }

    /**
     * @dataProvider typeValidationData
     */
    public function testtypeValidation($type, $expected)
    {
        $result = $this->service->typeValidation($type);
        $this->assertEquals($expected, $result);
    }

    public function isArrayUniqueData()
    {
        return array(
            array(array('sameValue', 'sameValue'), false),
            array(array('sameValue', 'DiffValue'), true),
            array(array(), true),
        );
    }

    /**
     * @dataProvider isArrayUniqueData
     */
    public function testisArrayUnique($data, $expected)
    {
        $result = $this->service->isArrayUnique($data);
        $this->assertEquals($expected, $result);
    }

    public function testaddNewForm()
    {
        $newFormId = 1;
        $data = array(
            'name' => 'testName',
            'style' => array(),
        );

        $model = new \Model_Form();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($model));

        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($newFormId));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->addNewForm($data);

        $this->assertInternalType('int', $result);
        $this->assertEquals($newFormId, $result);
    }

    public function testaddNewField()
    {
        $newFieldId = 1;
        $data = array(
            'form_id' => 1,
            'type' => 'select'
        );

        $model = new \Model_FormField();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id = 2;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($model));

        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($newFieldId));

        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue(0));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $result = $this->service->addNewField($data);

        $this->assertInternalType('int', $result);
        $this->assertEquals($newFieldId, $result);
    }

    public function updateFieldTypeData()
    {
        return array(
            array('select'),
            array('textarea'),
        );
    }

    /**
     * @dataProvider updateFieldTypeData
     */
    public function testupdateField($fieldType)
    {
        $updateFIeldId = 2;
        $data = array(
            'id' => $updateFIeldId,
            'form_id' => 1,
            'type' => $fieldType,
            'default_value' => 'defaultTestValue',
            'values' => array('test'),
            'labels' => array('labels'),
            'name' => 'testFIeld',
            'textarea_size' => array(64), // @TODO WHY ARRAY??
            'textarea_option' => array(''), //textarea_size & textarea_option should have an equal number of elements
        );

        $model = new \Model_FormField();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $modelArray = array(
            'id' => $updateFIeldId,
            'form_id' => 1,
            'options' => '{"hidden":"hidden"}',
        );

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($model));

        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($updateFIeldId));

        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($model));

        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->will($this->returnValue($modelArray));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $this->service->setDi($di);

        $result = $this->service->updateField($data);
        $this->assertInternalType('int', $result);
        $this->assertEquals($updateFIeldId, $result);
    }

    public function testupdateFieldExists()
    {
        $data = array(
            'id' => 2,
            'name' => 'testFIeld',
            'type' => 'select',
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Formbuilder\Service')
            ->setMethods(array('formFieldNameExists', 'getField'))
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('formFieldNameExists')
            ->will($this->returnValue(true));

        $serviceMock->expects($this->atLeastOnce())
            ->method('getField');

        $this->setExpectedException('\Box_Exception', 'Unfortunately field with this name exists in this form already. Form must have different field names.', 7628);
        $serviceMock->updateField($data);
    }

    public function testupdateFieldValuesNotUnique()
    {
        $data = array(
            'id' => 2,
            'form_id' => 1,
            'type' => 'select',
            'default_value' => 'defaultTestValue',
            'values' => array('test', 'test'),
            'labels' => array('labels'),
            'name' => 'testFIeld',
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Formbuilder\Service')
            ->setMethods(array('formFieldNameExists', 'getField'))
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('formFieldNameExists')
            ->will($this->returnValue(false));

        $serviceMock->expects($this->atLeastOnce())
            ->method('getField');

        $this->setExpectedException('\Box_Exception', ucfirst($data['type']).' values must be unique', 1597);
        $serviceMock->updateField($data);
    }

    public function testupdateFieldLabelsNotUnique()
    {
        $data = array(
            'id' => 2,
            'form_id' => 1,
            'type' => 'select',
            'default_value' => 'defaultTestValue',
            'values' => array('test'),
            'labels' => array('labels', 'labels'),
            'name' => 'testFIeld',
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Formbuilder\Service')
            ->setMethods(array('formFieldNameExists', 'getField'))
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('formFieldNameExists')
            ->will($this->returnValue(false));

        $serviceMock->expects($this->atLeastOnce())
            ->method('getField');

        $this->setExpectedException('\Box_Exception', ucfirst($data['type']).' labels must be unique', 1598);
        $serviceMock->updateField($data);
    }

    public function testupdateFieldTextAreaSizeException()
    {
        $data = array(
            'id' => 2,
            'form_id' => 1,
            'type' => 'textarea',
            'default_value' => 'defaultTestValue',
            'values' => array('test'),
            'labels' => array('labels'),
            'name' => 'testFIeld',
            'textarea_size' => array(''), // @TODO WHY ARRAY??
            'textarea_option' => array(''), //textarea_size & textarea_option should have an equal number of elements
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Formbuilder\Service')
            ->setMethods(array('formFieldNameExists', 'getField'))
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('formFieldNameExists')
            ->will($this->returnValue(false));

        $serviceMock->expects($this->atLeastOnce())
            ->method('getField');

        $this->setExpectedException('\Box_Exception', 'Textarea size options must be integer values', 3510);
        $serviceMock->updateField($data);
    }

    public function testgetForm()
    {
        $modelArray = array(
            'style' => '',
            'id' => 1,
        );
        $getAllResult = array(
            array(
                'options' => '{"options":["hidden"]}',
                'default_value' => 'TestingValue'
            ),
        );

        $model = new \Model_Form();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($model));

        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->will($this->returnValue($modelArray));
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->will($this->returnValue($getAllResult));

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $formId = 1;

        $this->service->setDi($di);
        $result = $this->service->getForm($formId);
        $this->assertInternalType('array', $result);
    }

    public function testgetFormNotFoundException()
    {
        $modelArray = null;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $dbMock->expects($this->atLeastOnce())
            ->method('load');

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $formId = 1;

        $this->service->setDi($di);
        $this->setExpectedException('\Box_Exception', 'Form not found', 9632);
        $this->service->getForm($formId);
    }

    public function testgetFormFields()
    {
        $formId = 1;
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['db'] = $dbMock;


        $this->service->setDi($di);
        $result = $this->service->getFormFields($formId);
        $this->assertInternalType('array', $result);
    }

    public function testgetFormFieldsCount()
    {
        $formId = 1;
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->getFormFieldsCount($formId);
        $this->assertInternalType('array', $result);
    }

    public function testgetFormPairs()
    {
        $formId = 1;
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->getFormPairs($formId);
        $this->assertInternalType('array', $result);
    }

    public function testgetField()
    {
        $fieldId = 2;
        $modelArray = array(
            'id' => 2,
            'options' => '{"options":["hidden"]}',
        );

        $expectedArray = $modelArray;
        $expectedArray['options'] = json_decode($expectedArray['options']);

        $model = new \Model_FormField();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($model));
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->will($this->returnValue($modelArray));

        $di = new \Box_Di();
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->getField($fieldId);
        $this->assertInternalType('array', $result);
        $this->assertEquals($expectedArray, $result);
    }

    public function testgetFieldFieldNotFound()
    {
        $fieldId = 2;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load');

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $this->setExpectedException('\Box_Exception', 'Field was not found', 2575);
        $this->service->getField($fieldId);
    }

    public function testremoveForm()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->exactly(4))
            ->method('exec');

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);
        $formId = 1;
        $result = $this->service->removeForm($formId);
        $this->assertTrue($result);
    }

    public function testremoveField()
    {
        $data = array('id' => 1);

        $model = new \Model_FormField();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($model));

        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);
        $result = $this->service->removeField($data);
        $this->assertTrue($result);
    }

    public function testremoveFieldNotFoundException()
    {
        $data = array('id' => 1);
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue(null));

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $this->setExpectedException('\Box_Exception', 'Field was not found', 1641);
        $this->service->removeField($data);
    }

    public function testformFieldNameExists()
    {
        $data = array(
            'field_name' => 'testingName',
            'form_id' => 2,
            'field_id' => 10,
        );

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(new \Model_FormField()));

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->formFieldNameExists($data);
        $this->assertTrue($result);
    }

    public function testgetForms()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->getForms(array());
        $this->assertInternalType('array', $result);
    }

    public function testdublicateForm()
    {
        $data = array(
            'form_id' => 1,
            'name' => 'testForm',
        );

        $newFormId = 3;
        $newFieldId = 4;
        $fields = array(
            'options' => '',
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Formbuilder\Service')
            ->setMethods(array('getFormFields', 'addNewForm', 'addNewField'))
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getFormFields')
            ->will($this->returnValue($fields));

        $serviceMock->expects($this->atLeastOnce())
            ->method('addNewForm')
            ->will($this->returnValue($newFormId));

        $serviceMock->expects($this->atLeastOnce())
            ->method('addNewField')
            ->will($this->returnValue($newFieldId));

        $di = new \Box_Di();
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);
        $result = $serviceMock->dublicateForm($data);
        $this->assertInternalType('int', $result);
        $this->assertEquals($newFormId, $result);
    }

    public function testupdateFormSettings()
    {
        $data = array(
            'type' => 'default',
            'form_name' => 'testForm',
            'form_id' => 1,
        );

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->exactly(2))
            ->method('exec');

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);
        $result = $this->service->updateFormSettings($data);
        $this->assertTrue($result);
    }


}
 