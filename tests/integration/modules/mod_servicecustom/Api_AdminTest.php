<?php
/**
 * @group Core
 */
class Api_Admin_ServiceCustomTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'services.xml';

    public function testServiceCustom()
    {
        $data = array(
            'order_id'    =>  10,
        );
        $result = $this->api_admin->servicecustom_return_params($data);
        $this->assertEquals($data, $result);
        
        $result = $this->api_admin->servicecustom_get_config($data);
        $this->assertEquals(array('param'=>'value'), $result);
        
        try {
            $this->api_admin->servicecustom_non_existing($data);
            $this->fail('Method should not exist on plugin');
        } catch(Exception $e) {
            $this->assertEquals(3125, $e->getCode());
        }
        
        try {
            $this->api_admin->servicecustom_delete($data);
            $this->fail('Renew method should be forbidden');
        } catch(Exception $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $orderModel = $this->di['db']->load('ClientOrder', $data['order_id']);
        $serviceCustomModel = $this->di['db']->load('ServiceCustom', $orderModel->service_id);
        $this->di['db']->trash($serviceCustomModel);
    }
}