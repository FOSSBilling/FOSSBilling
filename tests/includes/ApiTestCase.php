<?php
class ApiTestCase extends PHPUnit\Framework\TestCase
{
    protected $di = NULL;
    protected $session = NULL;
    protected $api_guest = NULL;
    protected $api_client = NULL;
    protected $api_admin = NULL;

    public function setUp(): void
    {
        global $di;
        $this->di = $di;
        $this->di['loggedin_client'] = $this->di['db']->load('Client', 1);
        $this->di['loggedin_admin'] = $this->di['db']->load('Admin', 1);
        $this->session = $this->di['session'];
        $this->api_guest = $this->di['api_guest'];
        $this->api_client = $this->di['api_client'];
        $this->api_admin = $this->di['api_admin'];
    }
}