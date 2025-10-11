<?php

abstract class BBDbApiTestCase extends BBDatabaseTestCase
{
    protected ?Pimple\Container $di;
    protected $session;
    protected $api_guest;
    protected $api_client;
    protected $api_admin;
    protected $api_system;

    public function setBoxUpdateMock(): void
    {
        $updatedMock = $this->getMockBuilder('Box_Update')
            ->onlyMethods(['isUpdateAvailable', 'getLatestVersion', 'performUpdate'])
            ->getMock();
        $updatedMock->expects($this->any())
            ->method('isUpdateAvailable')
            ->willReturn(true);
        $updatedMock->expects($this->any())
            ->method('getLatestVersion')
            ->willReturn('10.20.30');
        $updatedMock->expects($this->any())
            ->method('performUpdate')
            ->willReturn(null);
        $this->di['updater'] = $updatedMock;
    }

    protected function setUp(): void
    {
        parent::setUp();

        global $di;
        $this->di = $di;
        $this->setBoxUpdateMock();
        $this->di['loggedin_client'] = $this->di['db']->load('Client', 1);
        $this->di['loggedin_admin'] = $this->di['db']->load('Admin', 1);
        $this->session = $this->di['session'];
        $this->api_guest = $this->di['api_guest'];
        $this->api_client = $this->di['api_client'];
        $this->api_admin = $this->di['api_admin'];
        $this->api_system = $this->di['api_system'];
        // $this->api_admin->hook_batch_connect();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $refl = new ReflectionObject($this);
        foreach ($refl->getProperties() as $prop) {
            if (!$prop->isStatic() && !str_starts_with($prop->getDeclaringClass()->getName(), 'PHPUnit_')) {
                $prop->setValue($this, null);
            }
        }
    }
}
