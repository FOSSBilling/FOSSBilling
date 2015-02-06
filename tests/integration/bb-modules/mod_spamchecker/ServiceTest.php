<?php
/**
 * @group Core
 */
class Box_Mod_Spamchecker_ServiceTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'mod_spamchecker.xml';
    
    public function testOnAfterClientSignUp()
    {
        $parameters = array(
            'ip'    =>  '127.0.0.1',
        );
        $event = new Box_Event(null, 'any', $parameters, $this->api_admin);
        $event->setDi($this->di);

        $object = new \Box\Mod\Spamchecker\Service();
        $object->onBeforeClientSignUp($event);
    }

    public function testonBeforeClientCreateForumTopic()
    {
        $parameters = array(
            'ip'    =>  '127.0.0.1',
            'message' => 'TestUnit',
            'client_id' => 1,
        );
        $event = new Box_Event(null, 'any', $parameters, $this->api_admin);
        $event->setDi($this->di);

        $object = new \Box\Mod\Spamchecker\Service();
        $object->onBeforeClientCreateForumTopic($event);
    }

}