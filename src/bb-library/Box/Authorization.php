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


class Box_Authorization
{
    private $di = null;
    private $session = null;

    public function __construct(Box_Di $di)
    {
        $this->di = $di;
        $this->session = $di['session'];
    }

    public function isClientLoggedIn()
    {
        return (bool)($this->session->get('client_id'));
    }

    public function isAdminLoggedIn()
    {
        return (bool)($this->session->get('admin'));
    }

}
