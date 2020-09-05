<?php
abstract class BBDbApiTestCase extends BBDatabaseTestCase
{
    protected $di = NULL;
    protected $session = NULL;
    protected $api_guest = NULL;
    protected $api_client = NULL;
    protected $api_admin = NULL;
    protected $api_system = NULL;

    public function setBoxUpdateMock()
    {
        $updatedMock = $this->getMockBuilder('Box_Update')->getMock();
        $updatedMock->expects($this->any())
            ->method('getCanUpdate')
            ->will($this->returnValue(true));
        $updatedMock->expects($this->any())
            ->method('getLatestVersion')
            ->will($this->returnValue('10.20.30'));
        $updatedMock->expects($this->any())
            ->method('performUpdate')
            ->will($this->returnValue(null));
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
        //$this->api_admin->hook_batch_connect();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        $refl = new ReflectionObject($this);
        foreach ($refl->getProperties() as $prop) {
            if (!$prop->isStatic() && 0 !== strpos($prop->getDeclaringClass()->getName(), 'PHPUnit_')) {
                $prop->setAccessible(true);
                $prop->setValue($this, null);
            }
        }
    }
}