<?php
/**
 * @group Core
 */
class Box_Mod_Invoice_ServiceTest extends BBDbApiTestCase
{
    protected $_mod = 'invoice';
    protected $_initialSeedFile = 'mod_invoice.xml';
    
    public function testEvents()
    {
        $service = new \Box\Mod\Invoice\Service();
        $service->setDi($this->di);
        $params = array(
            'id' => 1,
        );
        $event = new Box_Event(null, 'name', $params, $this->api_admin, $this->api_guest);
        $event->setDi($this->di);
        $bool = $service->onAfterAdminInvoicePaymentReceived($event);
        $this->assertTrue($bool);
        
        $params = array(
            'id' => 1,
        );
        $event = new Box_Event(null, 'name', $params, $this->api_admin, $this->api_guest);
        $event->setDi($this->di);
        $bool = $service->onAfterAdminInvoiceApprove($event);
        $this->assertTrue($bool);
    }

}