<?php
namespace Box\Tests\Mod\Servicecustom;

use RedBeanPHP\SimpleModel;

class ServiceTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Servicecustom\Service
     */
    protected $service = null;

    public function setup(): void
    {
        $this->service = new \Box\Mod\Servicecustom\Service();
    }

    public function testDi()
    {
        $di = new \Box_Di();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testValidateCustomForm()
    {

        $form = array(
            'fields' => array(
                0 => array(
                    'required'      => 1,
                    'readonly'      => 1,
                    'name'          => 'field_name',
                    'default_value' => 'FieldName',
                    'label'         => 'label',
                ),
            ),
        );

        $service = $this->getMockBuilder('\Box\Mod\Formbuilder\Service')->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getForm')
            ->will($this->returnValue($form));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($service) {
            return $service;
        });

        $this->service->setDi($di);

        $product = array(
            'form_id' => rand(1, 100)
        );
        $data    = array(
            'label'      => 'label',
            'field_name' => 'FieldName'
        );
        $result  = $this->service->validateCustomForm($data, $product);
        $this->assertNull($result);
    }

    public function testValidateCustomFormFieldNameNotSetException()
    {
        $form = array(
            'fields' => array(
                0 => array(
                    'required'      => 1,
                    'readonly'      => 1,
                    'name'          => 'field_name',
                    'default_value' => 'default',
                    'label'         => 'label'
                )
            )
        );

        $service = $this->getMockBuilder('\Box\Mod\Formbuilder\Service')->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getForm')
            ->will($this->returnValue($form));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($service) {
            return $service;
        });

        $this->service->setDi($di);

        $product = array(
            'form_id' => rand(1, 100)
        );
        $data    = array();
        $this->expectException(\Exception::class);
        $result  = $this->service->validateCustomForm($data, $product);
        $this->assertNull($result);
    }

    public function testValidateCustomFormReadonlyFieldChangeException()
    {
        $form = array(
            'fields' => array(
                0 => array(
                    'required'      => 1,
                    'readonly'      => 1,
                    'name'          => 'field_name',
                    'default_value' => 'default',
                    'label'         => 'label'
                )
            )
        );

        $service = $this->getMockBuilder('\Box\Mod\Formbuilder\Service')->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getForm')
            ->will($this->returnValue($form));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($service) {
            return $service;
        });

        $this->service->setDi($di);

        $product = array(
            'form_id' => rand(1, 100)
        );
        $data    = array(
            'field_name' => 'field_name'
        );
        
        $this->expectException(\Exception::class);
        $result  = $this->service->validateCustomForm($data, $product);
        $this->assertNull($result);
    }

    public function testActionCreate()
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $order->product_id = rand(1, 100);
        $order->client_id  = rand(1, 100);
        $order->config     = 'config';

        $product = new \Model_Product();
        $product->loadBean(new \RedBeanPHP\OODBBean());
        $product->plugin        = 'plugin';
        $product->plugin_config = 'plugin_config';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($product));
        $serviceCustomModel = new \Model_ServiceCustom();
        $serviceCustomModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($serviceCustomModel));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->action_create($order);
        $this->assertInstanceOf('Model_ServiceCustom', $result);
    }

    public function testActionActivate()
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $order->client_id = rand(1, 100);
        $order->config    = 'config';

        $serviceCustomModel = new \Model_ServiceCustom();
        $serviceCustomModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceCustomModel->plugin = '';

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderService'))->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue($serviceCustomModel));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($serviceMock) {
            return $serviceMock;
        });
        $this->service->setDi($di);

        $result = $this->service->action_activate($order);
        $this->assertTrue($result);
    }

    public function testActionActivateOrderServiceNotCreatedException()
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $order->client_id = rand(1, 100);
        $order->config    = 'config';

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderService'))->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue(null));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($serviceMock) {
            return $serviceMock;
        });
        $this->service->setDi($di);
        $this->expectException(\Exception::class);
        $this->service->action_activate($order);
    }

    public function testActionRenew()
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $order->client_id = rand(1, 100);
        $order->config    = 'config';

        $serviceCustomModel = new \Model_ServiceCustom();
        $serviceCustomModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceCustomModel->plugin = '';

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderService'))->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue($serviceCustomModel));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($serviceMock) {
            return $serviceMock;
        });
        $this->service->setDi($di);

        $result = $this->service->action_renew($order);
        $this->assertTrue($result);
    }

    public function testActiveServiceNotFoundException()
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $order->id        = rand(1, 100);
        $order->client_id = rand(1, 100);
        $order->config    = 'config';

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderService'))->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue(null));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($serviceMock) {
            return $serviceMock;
        });
        $this->service->setDi($di);
        $this->expectException(\Exception::class);
        $result = $this->service->action_renew($order);
        $this->assertTrue($result);
    }

    public function testActionSuspend()
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $order->client_id = rand(1, 100);
        $order->config    = 'config';

        $serviceCustomModel = new \Model_ServiceCustom();
        $serviceCustomModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceCustomModel->plugin = '';

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderService'))->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue($serviceCustomModel));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($serviceMock) {
            return $serviceMock;
        });
        $this->service->setDi($di);

        $result = $this->service->action_suspend($order);
        $this->assertTrue($result);
    }

    public function testActionUnsuspend()
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $order->client_id = rand(1, 100);
        $order->config    = 'config';

        $serviceCustomModel = new \Model_ServiceCustom();
        $serviceCustomModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceCustomModel->plugin = '';

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderService'))->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue($serviceCustomModel));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($serviceMock) {
            return $serviceMock;
        });
        $this->service->setDi($di);

        $result = $this->service->action_unsuspend($order);
        $this->assertTrue($result);
    }

    public function testActionCancel()
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $order->client_id = rand(1, 100);
        $order->config    = 'config';

        $serviceCustomModel = new \Model_ServiceCustom();
        $serviceCustomModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceCustomModel->plugin = '';

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderService'))->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue($serviceCustomModel));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($serviceMock) {
            return $serviceMock;
        });
        $this->service->setDi($di);

        $result = $this->service->action_cancel($order);
        $this->assertTrue($result);
    }

    public function testActionUncancel()
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $order->client_id = rand(1, 100);
        $order->config    = 'config';

        $serviceCustomModel = new \Model_ServiceCustom();
        $serviceCustomModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceCustomModel->plugin = '';

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderService'))->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue($serviceCustomModel));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($serviceMock) {
            return $serviceMock;
        });
        $this->service->setDi($di);

        $result = $this->service->action_uncancel($order);
        $this->assertTrue($result);
    }

    public function testActionDelete()
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $order->client_id = rand(1, 100);
        $order->config    = 'config';

        $serviceCustomModel = new \Model_ServiceCustom();
        $serviceCustomModel->loadBean(new \RedBeanPHP\OODBBean());;
        $serviceCustomModel->plugin = '';

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderService'))->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue($serviceCustomModel));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->will($this->returnValue(null));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($serviceMock) {
            return $serviceMock;
        });
        $this->service->setDi($di);

        $result = $this->service->action_delete($order);
        $this->assertTrue($result);
    }

    public function testGetConfig()
    {
        $decoded   = array(
            'J' => 5,
            0   => 'N'
        );
        $toolsMock = $this->getMockBuilder('Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('decodeJ')
            ->will($this->returnValue($decoded));

        $di          = new \Box_Di();
        $di['tools'] = $toolsMock;
        $this->service->setDi($di);

        $model = new \Model_ServiceCustom();
        $model->loadBean(new \RedBeanPHP\OODBBean());;
        $model->config = json_encode($decoded);

        $result = $this->service->getConfig($model);


        $this->assertEquals($result, $decoded);
    }

    public function testToApiArray()
    {
        $toolsMock = $this->getMockBuilder('Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('decodeJ')
            ->will($this->returnValue(array('config_param' => 'config_value')));

        $di          = new \Box_Di();
        $di['tools'] = $toolsMock;
        $this->service->setDi($di);


        $model = new \Model_ServiceCustom();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id         = rand(1, 100);
        $model->client_id  = rand(1, 100);
        $model->plugin     = 'plugin';
        $model->config     = 'config_json';
        $model->updated_at = date('Y-m-d H:i:s');
        $model->created_at = date('Y-m-d H:i:s');

        $result = $this->service->toApiArray($model);

        $this->assertEquals($result['client_id'], $model->client_id);
        $this->assertEquals($result['plugin'], $model->plugin);
        $this->assertEquals($result['config_param'], 'config_value');
        $this->assertEquals($result['updated_at'], $model->updated_at);
        $this->assertEquals($result['created_at'], $model->created_at);
    }

    public function testCustomCall()
    {
        $model = new \Model_ServiceCustom();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->plugin = '';
        $this->service->customCall($model, 'custom_call');
    }

    public function testCustomCallForbiddenMethodException()
    {
        $this->expectException(\Exception::class);
        $this->service->customCall(new \Model_ServiceCustom(), 'delete');
    }

    public function testGetServiceCustomByOrderId()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_ClientOrder()));

        $orderService = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderService'))->getMock();
        $orderService->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue(new \Model_ServiceCustom()));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($orderService) {
            return $orderService;
        });
        $this->service->setDi($di);

        $result = $this->service->getServiceCustomByOrderId(rand(1, 100));

        $this->assertInstanceOf('Model_ServiceCustom', $result);
    }

    public function testGetServiceCustomByOrderIdOrderServiceNotFoundException()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_ClientOrder()));

        $orderService = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderService'))->getMock();
        $orderService->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue(null));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($orderService) {
            return $orderService;
        });
        $this->service->setDi($di);
        $this->expectException(\Exception::class);
        $this->service->getServiceCustomByOrderId(rand(1, 100));
    }

    public function testUpdateConfig()
    {
        $model = new \Model_ServiceCustom();
        $model->loadBean(new \RedBeanPHP\OODBBean());;
        $model->id = rand(1, 100);

        $serviceMock = $this->getMockBuilder('Box\Mod\Servicecustom\Service')->setMethods(array('getServiceCustomByOrderId'))->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getServiceCustomByOrderId')
            ->will($this->returnValue($model));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $serviceMock->setDi($di);


        $config = array('param1' => 'value1');
        $result = $serviceMock->updateConfig(rand(1, 100), $config);
        $this->assertNull($result);
    }

    public function testUpdateConfigNotArrayException()
    {
        $model = new \Model_ServiceCustom();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id = rand(1, 100);

        $serviceMock = $this->getMockBuilder('Box\Mod\Servicecustom\Service')->setMethods(array('getServiceCustomByOrderId'))->getMock();
        $serviceMock->expects($this->never())
            ->method('getServiceCustomByOrderId')
            ->will($this->returnValue($model));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->never())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $serviceMock->setDi($di);


        $config = '';
        $this->expectException(\Exception::class);
        $serviceMock->updateConfig(rand(1, 100), $config);
    }
}
 