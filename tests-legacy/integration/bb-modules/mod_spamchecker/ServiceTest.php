<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Box_Mod_Antispam_ServiceTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'mod_antispam.xml';

    public function testOnAfterClientSignUp(): void
    {
        $parameters = [
            'ip' => '127.0.0.1',
        ];
        $event = new Box_Event(null, 'any', $parameters, $this->api_admin);
        $event->setDi($this->di);

        $object = new Box\Mod\Antispam\Service();
        $object->onBeforeClientSignUp($event);
    }
}
