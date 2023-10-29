<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * Public tickets management.
 */

namespace Box\Mod\Support\Api;

class Guest extends \Api_Abstract
{
    /**
     * Submit new public ticket.
     *
     * @return string - ticket hash
     */
    public function ticket_create($data)
    {
        $required = [
            'name' => 'Please enter your name',
            'email' => 'Please enter your email',
            'subject' => 'Please enter your subject',
            'message' => 'Please enter your message',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        if (strlen($data['message']) < 4) {
            throw new \FOSSBilling\InformationException('Please enter your message');
        }

        return $this->getService()->ticketCreateForGuest($data);
    }

    /**
     * Get public ticket.
     *
     * @return array - ticket details
     */
    public function ticket_get($data)
    {
        $required = [
            'hash' => 'Public ticket hash required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $publicTicket = $this->getService()->publicFindOneByHash($data['hash']);

        return $this->getService()->publicToApiArray($publicTicket);
    }

    /**
     * Close public ticket.
     *
     * @return bool
     */
    public function ticket_close($data)
    {
        $required = [
            'hash' => 'Public ticket hash required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $publicTicket = $this->getService()->publicFindOneByHash($data['hash']);

        return $this->getService()->publicCloseTicket($publicTicket, $this->getIdentity());
    }

    /**
     * Reply to public ticket.
     *
     * @return string - ticket hash
     */
    public function ticket_reply($data)
    {
        $required = [
            'hash' => 'Public ticket hash required',
            'message' => 'Message is required and can not be blank',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $publicTicket = $this->getService()->publicFindOneByHash($data['hash']);

        return $this->getService()->publicTicketReplyForGuest($publicTicket, $data['message']);
    }
}
