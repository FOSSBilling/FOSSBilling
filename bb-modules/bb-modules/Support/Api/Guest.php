<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * Public tickets management
 */
namespace Box\Mod\Support\Api;
class Guest extends \Api_Abstract
{
    /**
     * Submit new public ticket
     *
     * @param string $name - Ticket author name
     * @param string $email - Ticket author email
     * @param string $subject - Ticket subject
     * @param string $message - Ticket message
     *
     * @return string - ticket hash
     */
    public function ticket_create($data)
    {
        $required = array(
            'name'    => 'Please enter your name',
            'email'   => 'Please enter your email',
            'subject' => 'Please enter your subject',
            'message' => 'Please enter your message',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        if (strlen($data['message']) < 4) {
            throw new \Box_Exception('Please enter your message');
        }
        
        return $this->getService()->ticketCreateForGuest($data);
    }

    /**
     * Get public ticket
     *
     * @param string $hash - public ticket hash
     *
     * @return array - ticket details
     */
    public function ticket_get($data)
    {
        $required = array(
            'hash' => 'Public ticket hash required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $publicTicket = $this->getService()->publicFindOneByHash($data['hash']);

        return $this->getService()->publicToApiArray($publicTicket);
    }

    /**
     * Close public ticket
     *
     * @param string $hash - public ticket hash
     *
     * @return bool
     */
    public function ticket_close($data)
    {
        $required = array(
            'hash' => 'Public ticket hash required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $publicTicket = $this->getService()->publicFindOneByHash($data['hash']);

        return $this->getService()->publicCloseTicket($publicTicket, $this->getIdentity());
    }

    /**
     * Reply to public ticket
     *
     * @param string $hash - public ticket hash
     * @param string $message - public ticket reply message
     *
     * @return string - ticket hash
     *
     */
    public function ticket_reply($data)
    {
        $required = array(
            'hash'    => 'Public ticket hash required',
            'message' => 'Message is required and can not be blank',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $publicTicket = $this->getService()->publicFindOneByHash($data['hash']);

        return $this->getService()->publicTicketReplyForGuest($publicTicket, $data['message']);
    }
}