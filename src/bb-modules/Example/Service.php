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

/**
 * This file is a delegate for module. Class does not extend any other class
 *
 * All methods provided in this example are optional, but function names are
 * still reserved.
 *
 */

namespace Box\Mod\Example;

class Service
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
     * Method to install module. In most cases you will provide your own
     * database table or tables to store extension related data.
     *
     * If your extension is not very complicated then extension_meta
     * database table might be enough.
     *
     * @return bool
     * @throws \Box_Exception
     */
    public function install()
    {
        // execute sql script if needed
        $db = $this->di['db'];
        $db->exec("SELECT NOW()");

        //throw new \Box_Exception("Throw exception to terminate module installation process with a message", array(), 123);
        return true;
    }

    /**
     * Method to uninstall module.
     *
     * @return bool
     * @throws \Box_Exception
     */
    public function uninstall()
    {
        //throw new \Box_Exception("Throw exception to terminate module uninstallation process with a message", array(), 124);
        return true;
    }

    /**
     * Method to update module. When you release new version to
     * extensions.boxbilling.com then this method will be called
     * after new files are placed.
     *
     * @param array $manifest - information about new module version
     * @return bool
     * @throws \Box_Exception
     */
    public function update($manifest)
    {
        //throw new \Box_Exception("Throw exception to terminate module update process with a message", array(), 125);
        return true;
    }

    /**
     * Method is used to create search query for paginated list.
     * Usually there is one paginated list per module
     *
     * @param array $data
     * @return array() = list of 2 parameters: array($sql, $params)
     */
    public function getSearchQuery($data)
    {
        $params = array();
        $sql="SELECT meta_key, meta_value
            FROM extension_meta
            WHERE extension = 'example' ";

        $client_id = $this->di['array_get']($data, 'client_id', NULL);

        if(NULL !== $client_id) {
            $sql .= ' AND client_id = :client_id';
            $params[':client_id'] = $client_id;
        }

        $sql .= ' ORDER BY created_at DESC';
        return array($sql, $params);
    }

    /**
     * Methods is a delegate for one database row.
     *
     * @param array $row - array representing one database row
     * @param string $role - guest|client|admin who is calling this method
     * @param bool $deep - true|false deep or light version of result to return to API
     *
     * @return array
     */
    public function toApiArray($row, $role = 'guest', $deep = true)
    {
        return $row;
    }

    /**
     * Example event hook. Any module can hook to any BoxBilling event and perform actions
     *
     * Make sure extension is enabled before testing this event.
     *
     * NOTE: IF you have BB_DEBUG mode set to TRUE then all events with params
     * are logged to bb-data/log/hook_*.log file. Check this file to see what
     * kind of parameters are passed to event.
     *
     * In this example we are going to count how many times client failed
     * to enter correct login details
     *
     * @param \Box_Event $event
     * @return void
     *
     * @throws  \Box_Exception
     */
    public static function onEventClientLoginFailed(\Box_Event $event)
    {
        //getting Dependency Injector
        $di = $event->getDi();

        //@note almost in all cases you will need Admin API
        $api = $di['api_admin'];

        //sometimes you may need guest API
        //$api_guest = $di['api_guest'];

        $params = $event->getParameters();

        //@note To debug parameters by throwing an exception
        //throw new Exception(print_r($params, 1));

        // Use RedBean ORM in any place of BoxBilling where API call is not enough
        // First we need to find if we already have a counter for this IP
        // We will use extension_meta table to store this data.
        $values = array(
            'ext'        =>  'example',
            'rel_type'   =>  'ip',
            'rel_id'     =>  $params['ip'],
            'meta_key'   =>  'counter',
        );
        $meta = $di['db']->findOne('extension_meta', 'extension = :ext AND rel_type = :rel_type AND rel_id = :rel_id AND meta_key = :meta_key', $values);
        if(!$meta) {
            $meta = $di['db']->dispense('extension_meta');
            //$count->client_id = null; // client id is not known in this situation
            $meta->extension  = 'mod_example';
            $meta->rel_type   = 'ip';
            $meta->rel_id     = $params['ip'];
            $meta->meta_key   = 'counter';
            $meta->created_at = date('Y-m-d H:i:s');
        }
        $meta->meta_value = $meta->meta_value + 1;
        $meta->updated_at = date('Y-m-d H:i:s');
        $di['db']->store($meta);

        // Now we can perform task depending on how many times wrong details were entered

        // We can log event if it repeats for 2 time
        if($meta->meta_value > 2) {
            $api->activity_log(array('m' => 'Client failed to enter correct login details ' . $meta->meta_value . ' time(s)'));
        }

        // if client gets funky, we block him
        if($meta->meta_value > 30) {
            throw new \Box_Exception('You have failed to login too many times. Contact support.');
        }
    }

    /**
     * This event hook is registered in example module client API call
     * @param \Box_Event $event
     */
    public static function onAfterClientCalledExampleModule(\Box_Event $event)
    {
        //error_log('Called event from example module');

        $di = $event->getDi();
        $params = $event->getParameters();

        $meta             = $di['db']->dispense('extension_meta');
        $meta->extension  = 'mod_example';
        $meta->meta_key   = 'event_params';
        $meta->meta_value = json_encode($params);
        $meta->created_at = date('Y-m-d H:i:s');
        $meta->updated_at = date('Y-m-d H:i:s');
        $di['db']->store($meta);
    }

    /**
     * Example event hook for public ticket and set event return value
     * @param \Box_Event $event
     */
    public static function onBeforeGuestPublicTicketOpen(\Box_Event $event)
    {
       /* Uncomment lines below in order to see this function in action */

        /*
        $data            = $event->getParameters();
        $data['status']  = 'closed';
        $data['subject'] = 'Altered subject';
        $data['message'] = 'Altered text';
        $event->setReturnValue($data);
        */
    }

    /**
     * Example email sending
     * @param \Box_Event $event
     */
    public static function onAfterClientOrderCreate(\Box_Event $event)
    {
        /* Uncomment lines below in order to see this function in action */

        /*
         $di = $event->getDi();
         $api    = $di['api_admin'];
         $params = $event->getParameters();

         $email = array();
         $email['to_client'] = $params['client_id'];
         $email['code']      = 'mod_example_email'; //@see bb-modules/Example/html_email/mod_example_email.phtml

         // these parameters are available in email template
         $email['order']     = $api->order_get(array('id'=>$params['id']));
         $email['other']     = 'any other value';

         $api->email_template_send($email);
        */
    }
}