<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */


namespace Box\Mod\Support\Controller;

class Client implements \Box\InjectionAwareInterface
{
    protected $di;

    /**
     * @param mixed $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return mixed
     */
    public function getDi()
    {
        return $this->di;
    }

    public function register(\Box_App &$app)
    {
        $app->get('/support', 'get_tickets', array(), get_class($this));
        $app->get('/support/ticket/:id', 'get_ticket', array(), get_class($this));
        $app->get('/support/contact-us', 'get_contact_us', array(), get_class($this));
        $app->get('/support/contact-us/conversation/:hash', 'get_contact_us_conversation', array('hash'=>'[a-z0-9]+'), get_class($this));
    }
    
    public function get_tickets(\Box_App $app)
    {
        $this->di['is_client_logged'];
        return $app->render('mod_support_tickets');
    }

    public function get_ticket(\Box_App $app, $id)
    {
        $api = $this->di['api_client'];
        $ticket = $api->support_ticket_get(array('id'=>$id));
        return $app->render('mod_support_ticket', array('ticket'=>$ticket));
    }
    
    public function get_contact_us(\Box_App $app)
    {
        return $app->render('mod_support_contact_us');
    }

    public function get_contact_us_conversation(\Box_App $app, $hash)
    {
        $api = $this->di['api_guest'];
        $data = array(
            'hash'    => $hash,
        );
        $array = $api->support_ticket_get($data);
        return $app->render('mod_support_contact_us_conversation', array('ticket'=>$array));
    }

}